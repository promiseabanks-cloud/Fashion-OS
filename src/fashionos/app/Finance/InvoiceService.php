<?php
/**
 * Invoice workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Finance;

use FashionOS\API\Rest;
use FashionOS\Commerce\Money;
use FashionOS\Database\References;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\InvoiceRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\PaymentRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoices: what a customer owes, and how much of it has arrived.
 *
 * Totals are always computed here from quantity and unit price. `amount_paid`
 * and `balance` are never accepted from the browser: they are derived from
 * verified payments, so the invoice and the payment ledger cannot disagree.
 */
final class InvoiceService {

	/**
	 * Statuses an invoice can be created or moved into.
	 *
	 * `partially_paid` and `paid` are derived from payments and cannot be set.
	 */
	public const STATUSES = array( 'draft', 'issued', 'void' );

	/**
	 * Maximum lines in one invoice.
	 */
	public const MAX_ITEMS = 100;

	/**
	 * Invoice reads and writes.
	 *
	 * @var InvoiceRepository
	 */
	private InvoiceRepository $invoices;

	/**
	 * Customer reads.
	 *
	 * @var ClientRepository
	 */
	private ClientRepository $clients;

	/**
	 * Order reads, to copy garments into an invoice.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $orders;

	/**
	 * Payment reads, for the invoice detail.
	 *
	 * @var PaymentRepository
	 */
	private PaymentRepository $payments;

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
	 * @param InvoiceRepository  $invoices     Invoice reads and writes.
	 * @param ClientRepository   $clients      Customer reads.
	 * @param OrderRepository    $orders       Order reads.
	 * @param PaymentRepository  $payments     Payment reads.
	 * @param AuditLogRepository $audit        Audit writes.
	 * @param Transaction        $transactions Transaction runner.
	 */
	public function __construct(
		InvoiceRepository $invoices,
		ClientRepository $clients,
		OrderRepository $orders,
		PaymentRepository $payments,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->invoices     = $invoices;
		$this->clients      = $clients;
		$this->orders       = $orders;
		$this->payments     = $payments;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Search and paginate invoices.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function search( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $query['perPage'] ?? 25 ) ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? 'all' ) );
		$allowed  = array_merge( self::STATUSES, array( 'partially_paid', 'paid', 'all' ) );

		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'all';
		}

		$result = $this->invoices->search(
			$context->id(),
			array(
				'search'    => mb_substr( trim( sanitize_text_field( (string) ( $query['search'] ?? '' ) ) ), 0, 100 ),
				'status'    => $status,
				'client_id' => max( 0, (int) ( $query['clientId'] ?? 0 ) ),
				'overdue'   => ! empty( $query['overdue'] ),
				'today'     => gmdate( 'Y-m-d' ),
				'page'      => $page,
				'per_page'  => $per_page,
			)
		);

		return array(
			'invoices'   => array_map( array( FinancePresenter::class, 'invoice' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One invoice with its lines and payments.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Invoice id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function find( TenantContext $context, int $id ) {
		$row = $this->invoices->find( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That invoice could not be found.', 'fashionos' ), 'invoice_not_found' );
		}

		// Read the paid amount from the ledger, so the document cannot drift from its payments.
		$row['amount_paid'] = number_format( $this->invoices->verified_total( $context->id(), $id ), 2, '.', '' );

		return FinancePresenter::invoice(
			$row,
			$this->invoices->items( $context->id(), $id ),
			$this->payments->for_invoice( $context->id(), $id )
		);
	}

	/**
	 * Create an invoice, optionally from an order's garments.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$order_id  = max( 0, (int) ( $input['orderId'] ?? 0 ) );
		$client_id = max( 0, (int) ( $input['clientId'] ?? 0 ) );
		$order     = null;

		if ( $order_id > 0 ) {
			$order = $this->orders->find( $context->id(), $order_id );

			if ( null === $order ) {
				return Rest::not_found( __( 'That order could not be found.', 'fashionos' ), 'order_not_found' );
			}

			if ( 0 === $client_id ) {
				$client_id = (int) $order['client_id'];
			} elseif ( (int) $order['client_id'] !== $client_id ) {
				return Rest::invalid( 'invoice_order_mismatch', __( 'That order belongs to a different customer.', 'fashionos' ) );
			}
		}

		if ( $client_id <= 0 ) {
			return Rest::invalid( 'invoice_client_required', __( 'Choose the customer this invoice is for.', 'fashionos' ) );
		}

		if ( null === $this->clients->find( $context->id(), $client_id ) ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		$candidate = $input['items'] ?? null;

		if ( ( ! is_array( $candidate ) || array() === $candidate ) && null !== $order ) {
			$candidate = $this->lines_from_order( $context, $order_id );
		}

		$priced = $this->normalise_items( $candidate );

		if ( $priced instanceof WP_Error ) {
			return $priced;
		}

		$issue_date = $this->normalise_date( $input['issueDate'] ?? '', gmdate( 'Y-m-d' ) );

		if ( $issue_date instanceof WP_Error ) {
			return $issue_date;
		}

		$due_date = $this->normalise_date( $input['dueDate'] ?? '', '' );

		if ( $due_date instanceof WP_Error ) {
			return $due_date;
		}

		if ( '' !== $due_date && $due_date < $issue_date ) {
			return Rest::invalid( 'invoice_due_date_invalid', __( 'The due date cannot be before the issue date.', 'fashionos' ) );
		}

		$notes = $this->normalise_notes( $input['notes'] ?? '' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$currency = (string) ( $context->tenant()['currency'] ?? '' );

		$created = $this->transactions->run(
			function () use ( $context, $client_id, $order_id, $priced, $issue_date, $due_date, $notes, $currency ) {
				$now      = gmdate( 'Y-m-d H:i:s' );
				$subtotal = number_format( $priced['subtotal'], 2, '.', '' );

				$invoice_id = $this->invoices->create(
					array(
						'tenant_id'   => $context->id(),
						'uuid'        => wp_generate_uuid4(),
						'reference'   => References::placeholder(),
						'client_id'   => $client_id,
						'order_id'    => $order_id,
						'currency'    => $currency,
						'status'      => 'draft',
						'issue_date'  => $issue_date,
						'due_date'    => $due_date,
						'subtotal'    => $subtotal,
						'total'       => $subtotal,
						'amount_paid' => '0.00',
						'balance'     => $subtotal,
						'notes'       => $notes,
						'created_by'  => $context->user_id(),
						'created_at'  => $now,
						'updated_at'  => $now,
					)
				);

				if ( 0 === $invoice_id ) {
					return Rest::invalid( 'invoice_create_failed', __( 'The invoice could not be created. Please try again.', 'fashionos' ), 500 );
				}

				if ( ! $this->invoices->replace_items( $context->id(), $invoice_id, $priced['items'] ) ) {
					return Rest::invalid( 'invoice_create_failed', __( 'The invoice could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$reference = References::format( 'I', $invoice_id );

				if ( ! $this->invoices->update( $context->id(), $invoice_id, array( 'reference' => $reference ) ) ) {
					return Rest::invalid( 'invoice_create_failed', __( 'The invoice could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'invoice.created',
					'invoice',
					$invoice_id,
					array(
						'reference' => $reference,
						'clientId'  => $client_id,
						'orderId'   => $order_id,
						'items'     => count( $priced['items'] ),
						'total'     => (float) $subtotal,
					)
				);

				return $invoice_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return $this->find( $context, (int) $created );
	}

	/**
	 * Update an invoice, replacing its lines only while it is a draft.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Invoice id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( TenantContext $context, int $id, array $input ) {
		$existing = $this->invoices->find( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That invoice could not be found.', 'fashionos' ), 'invoice_not_found' );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return Rest::invalid(
					'invoice_status_invalid',
					__( 'A paid or partially paid invoice is set by its payments, so choose draft, issued or void.', 'fashionos' )
				);
			}

			if ( $status !== (string) $existing['status'] ) {
				$guard = $this->guard_transition( $context, $existing, $status );

				if ( $guard instanceof WP_Error ) {
					return $guard;
				}
			}

			$data['status'] = $status;
		}

		if ( array_key_exists( 'issueDate', $input ) ) {
			$issue_date = $this->normalise_date( $input['issueDate'], '' );

			if ( $issue_date instanceof WP_Error ) {
				return $issue_date;
			}

			$data['issue_date'] = '' === $issue_date ? (string) $existing['issue_date'] : $issue_date;
		}

		if ( array_key_exists( 'dueDate', $input ) ) {
			$due_date = $this->normalise_date( $input['dueDate'], '' );

			if ( $due_date instanceof WP_Error ) {
				return $due_date;
			}

			$data['due_date'] = $due_date;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_notes( $input['notes'] );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes'] = $notes;
		}

		$issue = (string) ( $data['issue_date'] ?? $existing['issue_date'] );
		$due   = (string) ( $data['due_date'] ?? $existing['due_date'] );

		if ( '' !== $due && '' !== $issue && $due < $issue ) {
			return Rest::invalid( 'invoice_due_date_invalid', __( 'The due date cannot be before the issue date.', 'fashionos' ) );
		}

		$priced = null;

		if ( array_key_exists( 'items', $input ) ) {
			if ( 'draft' !== (string) $existing['status'] ) {
				return Rest::invalid(
					'invoice_locked',
					__( 'Only a draft invoice can change its lines. Void this invoice and issue a new one instead.', 'fashionos' ),
					409
				);
			}

			$priced = $this->normalise_items( $input['items'] );

			if ( $priced instanceof WP_Error ) {
				return $priced;
			}

			$subtotal = number_format( $priced['subtotal'], 2, '.', '' );
			$paid     = $this->invoices->verified_total( $context->id(), $id );

			$data['subtotal']    = $subtotal;
			$data['total']       = $subtotal;
			$data['amount_paid'] = number_format( $paid, 2, '.', '' );
			$data['balance']     = number_format( max( 0, $priced['subtotal'] - $paid ), 2, '.', '' );
			$changed[]           = 'items';
		}

		if ( 'issued' === (string) ( $data['status'] ?? '' ) && (float) ( $data['total'] ?? $existing['total'] ) <= 0 ) {
			return Rest::invalid( 'invoice_total_required', __( 'Add at least one priced line before issuing the invoice.', 'fashionos' ) );
		}

		foreach ( array( 'status', 'issue_date', 'due_date', 'notes' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return $this->find( $context, $id );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		$updated = $this->transactions->run(
			function () use ( $context, $id, $data, $priced, $changed ) {
				if ( ! $this->invoices->update( $context->id(), $id, $data ) ) {
					return Rest::invalid( 'invoice_update_failed', __( 'The invoice could not be updated. Please try again.', 'fashionos' ), 500 );
				}

				if ( null !== $priced && ! $this->invoices->replace_items( $context->id(), $id, $priced['items'] ) ) {
					return Rest::invalid( 'invoice_update_failed', __( 'The invoice could not be updated. Please try again.', 'fashionos' ), 500 );
				}

				if ( array() !== $changed ) {
					$metadata = array( 'changed' => $changed );

					if ( isset( $data['status'] ) ) {
						$metadata['status'] = (string) $data['status'];
					}

					$this->audit->record( $context->id(), $context->user_id(), 'invoice.updated', 'invoice', $id, $metadata );
				}

				return true;
			}
		);

		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		return $this->find( $context, $id );
	}

	/**
	 * Check that an invoice may move to a new status.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $invoice Existing invoice row.
	 * @param string               $next    Requested status.
	 * @return true|WP_Error
	 */
	private function guard_transition( TenantContext $context, array $invoice, string $next ) {
		$current = (string) $invoice['status'];

		if ( 'void' === $current ) {
			return Rest::invalid( 'invoice_void', __( 'A void invoice cannot be reopened.', 'fashionos' ), 409 );
		}

		if ( 'draft' === $next && 'draft' !== $current ) {
			return Rest::invalid(
				'invoice_status_invalid',
				__( 'An issued invoice cannot return to draft. Void it and issue a new one instead.', 'fashionos' ),
				409
			);
		}

		if ( in_array( $current, array( 'partially_paid', 'paid' ), true ) ) {
			return Rest::invalid(
				'invoice_has_payments',
				__( 'This invoice has verified payments; record a correction instead of changing its status.', 'fashionos' ),
				409
			);
		}

		if ( 'void' === $next && $this->invoices->verified_total( $context->id(), (int) $invoice['id'] ) > 0 ) {
			return Rest::invalid(
				'invoice_has_payments',
				__( 'This invoice has verified payments and cannot be voided.', 'fashionos' ),
				409
			);
		}

		return true;
	}

	/**
	 * Turn an order's garments into invoice lines.
	 *
	 * @param TenantContext $context  Verified context.
	 * @param int           $order_id Order id.
	 * @return array<int, array<string, mixed>>
	 */
	private function lines_from_order( TenantContext $context, int $order_id ): array {
		$lines = array();

		foreach ( $this->orders->items( $context->id(), $order_id ) as $item ) {
			$description = trim( (string) ( $item['garment_name'] ?? '' ) );

			$lines[] = array(
				'description' => '' === $description ? __( 'Garment', 'fashionos' ) : $description,
				'quantity'    => (int) ( $item['quantity'] ?? 1 ),
				'unitPrice'   => (float) ( $item['unit_price'] ?? 0 ),
			);
		}

		return $lines;
	}

	/**
	 * Validate and price invoice lines.
	 *
	 * @param mixed $items Candidate items.
	 * @return array{items: array<int, array<string, mixed>>, subtotal: float}|WP_Error
	 */
	private function normalise_items( $items ) {
		if ( ! is_array( $items ) || array() === $items ) {
			return Rest::invalid( 'invoice_items_required', __( 'Add at least one line to the invoice.', 'fashionos' ) );
		}

		$items = array_values( $items );

		if ( count( $items ) > self::MAX_ITEMS ) {
			return Rest::invalid(
				'invoice_too_many_items',
				sprintf(
					/* translators: %d: maximum number of lines. */
					__( 'An invoice may have at most %d lines.', 'fashionos' ),
					self::MAX_ITEMS
				)
			);
		}

		$normalised = array();
		$subtotal   = 0.0;

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				return Rest::invalid( 'invoice_item_invalid', __( 'Every invoice line needs a description and a price.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$description = trim( sanitize_text_field( (string) ( $item['description'] ?? '' ) ) );

			if ( '' === $description ) {
				return Rest::invalid( 'invoice_item_description_required', __( 'Give each invoice line a description.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			if ( mb_strlen( $description ) > 191 ) {
				return Rest::invalid( 'invoice_item_description_too_long', __( 'Line descriptions may be at most 191 characters.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$quantity = Money::quantity( $item['quantity'] ?? 1 );

			if ( null === $quantity ) {
				return Rest::invalid( 'invoice_item_quantity_invalid', __( 'Quantities must be whole numbers between 1 and 9999.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$unit_price = Money::amount( $item['unitPrice'] ?? 0 );

			if ( null === $unit_price ) {
				return Rest::invalid( 'invoice_item_price_invalid', __( 'Prices must be amounts between 0 and 9,999,999.99.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$line_total = Money::line_total( $quantity, $unit_price );
			$subtotal  += (float) $line_total;

			$normalised[] = array(
				'description' => $description,
				'quantity'    => $quantity,
				'unit_price'  => $unit_price,
				'line_total'  => $line_total,
				'sort_order'  => $index,
			);
		}

		return array(
			'items'    => $normalised,
			'subtotal' => round( $subtotal, 2 ),
		);
	}

	/**
	 * Validate an optional date.
	 *
	 * @param mixed  $value    Candidate date.
	 * @param string $fallback Value used when the input is blank.
	 * @return string|WP_Error
	 */
	private function normalise_date( $value, string $fallback ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $fallback;
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return Rest::invalid( 'invoice_date_invalid', __( 'Enter a valid date for the invoice.', 'fashionos' ) );
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Validate the invoice notes.
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
			return Rest::invalid( 'invoice_notes_too_long', __( 'Invoice notes may be at most 2000 characters.', 'fashionos' ) );
		}

		return $notes;
	}
}
