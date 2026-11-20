<?php
/**
 * Payment workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Finance;

use FashionOS\API\Rest;
use FashionOS\Commerce\Money;
use FashionOS\Database\References;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\InvoiceRepository;
use FashionOS\Database\Repositories\PaymentRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payments recorded against an invoice.
 *
 * A payment starts as `pending` and only moves the invoice balance once staff
 * verify it. Every payment carries an idempotency key, unique per workspace,
 * so a repeated callback or a double-submitted form returns the payment that
 * already exists instead of recording the same money twice.
 */
final class PaymentService {

	/**
	 * Payment statuses. `pending` is the only non-terminal one.
	 */
	public const STATUSES = array( 'pending', 'verified', 'rejected' );

	/**
	 * Accepted payment methods.
	 */
	public const METHODS = array( 'cash', 'transfer', 'card', 'pos' );

	/**
	 * Payment reads and writes.
	 *
	 * @var PaymentRepository
	 */
	private PaymentRepository $payments;

	/**
	 * Invoice reads and writes, for the balance.
	 *
	 * @var InvoiceRepository
	 */
	private InvoiceRepository $invoices;

	/**
	 * Audit writes.
	 *
	 * @var AuditLogRepository
	 */
	private AuditLogRepository $audit;

	/**
	 * Transaction runner.
	 *
	 * @var Transaction
	 */
	private Transaction $transactions;

	/**
	 * Constructor.
	 *
	 * @param PaymentRepository  $payments     Payment reads and writes.
	 * @param InvoiceRepository  $invoices     Invoice reads and writes.
	 * @param AuditLogRepository $audit        Audit writes.
	 * @param Transaction        $transactions Transaction runner.
	 */
	public function __construct(
		PaymentRepository $payments,
		InvoiceRepository $invoices,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->payments     = $payments;
		$this->invoices     = $invoices;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Search and paginate payments.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function search( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $query['perPage'] ?? 25 ) ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? 'all' ) );

		if ( ! in_array( $status, array_merge( self::STATUSES, array( 'all' ) ), true ) ) {
			$status = 'all';
		}

		$result = $this->payments->search(
			$context->id(),
			array(
				'invoice_id' => max( 0, (int) ( $query['invoiceId'] ?? 0 ) ),
				'status'     => $status,
				'page'       => $page,
				'per_page'   => $per_page,
			)
		);

		return array(
			'payments'   => array_map( array( FinancePresenter::class, 'payment' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One payment.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Payment id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function find( TenantContext $context, int $id ) {
		$row = $this->payments->find( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That payment could not be found.', 'fashionos' ), 'payment_not_found' );
		}

		return FinancePresenter::payment( $row );
	}

	/**
	 * Record a payment against an invoice.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array{payment: array<string, mixed>, duplicate: bool}|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$key = $this->idempotency_key( $input['idempotencyKey'] ?? '' );

		if ( $key instanceof WP_Error ) {
			return $key;
		}

		$existing = $this->payments->find_by_key( $context->id(), $key );

		if ( null !== $existing ) {
			return array(
				'payment'   => FinancePresenter::payment( $existing ),
				'duplicate' => true,
			);
		}

		$invoice_id = (int) ( $input['invoiceId'] ?? 0 );

		if ( $invoice_id <= 0 ) {
			return Rest::invalid( 'payment_invoice_required', __( 'Choose the invoice this payment belongs to.', 'fashionos' ) );
		}

		$invoice = $this->invoices->find( $context->id(), $invoice_id );

		if ( null === $invoice ) {
			return Rest::not_found( __( 'That invoice could not be found.', 'fashionos' ), 'invoice_not_found' );
		}

		$invoice_status = (string) $invoice['status'];

		if ( 'void' === $invoice_status ) {
			return Rest::invalid( 'invoice_void', __( 'That invoice has been voided and cannot take payments.', 'fashionos' ), 409 );
		}

		if ( 'draft' === $invoice_status ) {
			return Rest::invalid( 'invoice_not_issued', __( 'Issue the invoice before recording a payment against it.', 'fashionos' ), 409 );
		}

		if ( ! in_array( $invoice_status, array( 'issued', 'partially_paid' ), true ) ) {
			return Rest::invalid( 'invoice_not_payable', __( 'This invoice is already settled.', 'fashionos' ), 409 );
		}

		$amount = Money::amount( $input['amount'] ?? 0 );

		if ( null === $amount || (float) $amount <= 0 ) {
			return Rest::invalid( 'payment_amount_invalid', __( 'Enter the amount received, greater than zero.', 'fashionos' ) );
		}

		$balance = round( (float) $invoice['total'] - $this->invoices->verified_total( $context->id(), $invoice_id ), 2 );

		if ( (float) $amount > $balance ) {
			return Rest::invalid(
				'payment_exceeds_balance',
				__( 'That payment is more than the invoice balance. Record the amount actually owed.', 'fashionos' )
			);
		}

		$method = sanitize_key( (string) ( $input['method'] ?? 'transfer' ) );

		if ( ! in_array( $method, self::METHODS, true ) ) {
			return Rest::invalid( 'payment_method_invalid', __( 'Choose cash, transfer, card or POS.', 'fashionos' ) );
		}

		$paid_at = $this->normalise_datetime( $input['paidAt'] ?? '' );

		if ( $paid_at instanceof WP_Error ) {
			return $paid_at;
		}

		$provider = mb_substr( trim( sanitize_text_field( (string) ( $input['provider'] ?? '' ) ) ), 0, 40 );
		$provider_reference = mb_substr( trim( sanitize_text_field( (string) ( $input['providerReference'] ?? '' ) ) ), 0, 191 );
		$notes = $this->normalise_notes( $input['notes'] ?? '' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$created = $this->transactions->run(
			function () use ( $context, $invoice, $amount, $method, $provider, $provider_reference, $paid_at, $notes, $key ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				$payment_id = $this->payments->create(
					array(
						'tenant_id'          => $context->id(),
						'uuid'               => wp_generate_uuid4(),
						'reference'          => References::placeholder(),
						'invoice_id'         => (int) $invoice['id'],
						'client_id'          => (int) $invoice['client_id'],
						'amount'             => $amount,
						'method'             => $method,
						'provider'           => $provider,
						'provider_reference' => $provider_reference,
						'status'             => 'pending',
						'idempotency_key'    => $key,
						'paid_at'            => $paid_at,
						'verified_by'        => 0,
						'verified_at'        => '',
						'notes'              => $notes,
						'created_by'         => $context->user_id(),
						'created_at'         => $now,
						'updated_at'         => $now,
					)
				);

				if ( 0 === $payment_id ) {
					return Rest::invalid( 'payment_create_failed', __( 'The payment could not be recorded. Please try again.', 'fashionos' ), 500 );
				}

				$reference = References::format( 'P', $payment_id );

				if ( ! $this->payments->update( $context->id(), $payment_id, array( 'reference' => $reference ) ) ) {
					return Rest::invalid( 'payment_create_failed', __( 'The payment could not be recorded. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'payment.recorded',
					'payment',
					$payment_id,
					array(
						'reference'  => $reference,
						'invoiceId'  => (int) $invoice['id'],
						'amount'     => (float) $amount,
						'method'     => $method,
					)
				);

				return $payment_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			// A concurrent duplicate loses the unique index; return the payment that won.
			$stored = $this->payments->find_by_key( $context->id(), $key );

			if ( null !== $stored ) {
				return array(
					'payment'   => FinancePresenter::payment( $stored ),
					'duplicate' => true,
				);
			}

			return $created;
		}

		$payment = $this->find( $context, (int) $created );

		if ( $payment instanceof WP_Error ) {
			return $payment;
		}

		return array(
			'payment'   => $payment,
			'duplicate' => false,
		);
	}

	/**
	 * Verify or reject a pending payment.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Payment id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( TenantContext $context, int $id, array $input ) {
		$existing = $this->payments->find( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That payment could not be found.', 'fashionos' ), 'payment_not_found' );
		}

		$status = sanitize_key( (string) ( $input['status'] ?? '' ) );

		if ( ! in_array( $status, array( 'verified', 'rejected' ), true ) ) {
			return Rest::invalid( 'payment_status_invalid', __( 'A payment can only be verified or rejected.', 'fashionos' ) );
		}

		if ( 'pending' !== (string) $existing['status'] ) {
			return Rest::invalid(
				'payment_locked',
				__( 'This payment has already been decided and cannot be changed. Record a new payment instead.', 'fashionos' ),
				409
			);
		}

		$invoice_id = (int) $existing['invoice_id'];
		$invoice    = $this->invoices->find( $context->id(), $invoice_id );

		if ( null === $invoice ) {
			return Rest::not_found( __( 'That invoice could not be found.', 'fashionos' ), 'invoice_not_found' );
		}

		if ( 'verified' === $status ) {
			if ( ! in_array( (string) $invoice['status'], array( 'issued', 'partially_paid' ), true ) ) {
				return Rest::invalid(
					'invoice_not_payable',
					__( 'This invoice is no longer open, so the payment cannot be verified.', 'fashionos' ),
					409
				);
			}

			$balance = round( (float) $invoice['total'] - $this->invoices->verified_total( $context->id(), $invoice_id ), 2 );

			if ( (float) $existing['amount'] > $balance ) {
				return Rest::invalid(
					'payment_exceeds_balance',
					__( 'Verifying this payment would overpay the invoice, because another payment was verified first.', 'fashionos' )
				);
			}
		}

		$updated = $this->transactions->run(
			function () use ( $context, $id, $status, $invoice, $invoice_id ) {
				$now  = gmdate( 'Y-m-d H:i:s' );
				$data = array(
					'status'      => $status,
					'updated_at'  => $now,
					'verified_by' => 'verified' === $status ? $context->user_id() : 0,
					'verified_at' => 'verified' === $status ? $now : '',
				);

				if ( ! $this->payments->update( $context->id(), $id, $data ) ) {
					return Rest::invalid( 'payment_update_failed', __( 'The payment could not be updated. Please try again.', 'fashionos' ), 500 );
				}

				if ( 'verified' === $status ) {
					$reconciled = $this->reconcile_invoice( $context, $invoice, $invoice_id );

					if ( $reconciled instanceof WP_Error ) {
						return $reconciled;
					}
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'verified' === $status ? 'payment.verified' : 'payment.rejected',
					'payment',
					$id,
					array(
						'invoiceId' => $invoice_id,
						'status'    => $status,
					)
				);

				return true;
			}
		);

		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		return $this->find( $context, $id );
	}

	/**
	 * Rewrite an invoice's paid amount, balance and derived status.
	 *
	 * @param TenantContext        $context    Verified context.
	 * @param array<string, mixed> $invoice   Invoice row.
	 * @param int                  $invoice_id Invoice id.
	 * @return true|WP_Error
	 */
	private function reconcile_invoice( TenantContext $context, array $invoice, int $invoice_id ) {
		$paid    = $this->invoices->verified_total( $context->id(), $invoice_id );
		$total   = round( (float) $invoice['total'], 2 );
		$balance = round( $total - $paid, 2 );
		// Verifying a positive payment always leaves money on the invoice.
		$status  = $balance <= 0 ? 'paid' : 'partially_paid';

		$updated = $this->invoices->update(
			$context->id(),
			$invoice_id,
			array(
				'amount_paid' => number_format( $paid, 2, '.', '' ),
				'balance'     => number_format( max( 0, $balance ), 2, '.', '' ),
				'status'      => $status,
				'updated_at'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		if ( ! $updated ) {
			return Rest::invalid( 'payment_update_failed', __( 'The invoice balance could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		return true;
	}

	/**
	 * Validate or generate the idempotency key for a payment.
	 *
	 * @param mixed $value Candidate key.
	 * @return string|WP_Error
	 */
	private function idempotency_key( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			// A generated key keeps the unique index meaningful without requiring callers to send one.
			return 'auto-' . str_replace( '-', '', wp_generate_uuid4() );
		}

		$key = trim( $value );

		if ( mb_strlen( $key ) > 191 ) {
			return Rest::invalid( 'payment_idempotency_key_invalid', __( 'The idempotency key may be at most 191 characters.', 'fashionos' ) );
		}

		return $key;
	}

	/**
	 * Validate an optional payment timestamp.
	 *
	 * @param mixed $value Candidate timestamp; blank means now.
	 * @return string|WP_Error
	 */
	private function normalise_datetime( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return Rest::invalid( 'payment_paid_at_invalid', __( 'Enter a valid date and time for the payment.', 'fashionos' ) );
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Validate the payment notes.
	 *
	 * @param mixed $value Candidate notes.
	 * @return string|WP_Error
	 */
	private function normalise_notes( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$notes = sanitize_textarea_field( $value );

		if ( mb_strlen( $notes ) > 2000 ) {
			return Rest::invalid( 'payment_notes_too_long', __( 'Payment notes may be at most 2000 characters.', 'fashionos' ) );
		}

		return $notes;
	}
}
