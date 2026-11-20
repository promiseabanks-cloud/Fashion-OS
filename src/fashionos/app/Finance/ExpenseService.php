<?php
/**
 * Expense workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Finance;

use FashionOS\API\Rest;
use FashionOS\Commerce\Money;
use FashionOS\Database\References;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ExpenseRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Studio expenses: money leaving the business.
 *
 * Categories are a fixed list rather than free text so the summary can group
 * spending without guessing. Voiding keeps the row and its audit trail but
 * removes it from totals.
 */
final class ExpenseService {

	/**
	 * Expense categories.
	 */
	public const CATEGORIES = array(
		'materials',
		'rent',
		'salaries',
		'utilities',
		'transport',
		'marketing',
		'equipment',
		'other',
	);

	/**
	 * Expense statuses. A void expense stays visible but is excluded from totals.
	 */
	public const STATUSES = array( 'recorded', 'void' );

	/**
	 * Expense reads and writes.
	 *
	 * @var ExpenseRepository
	 */
	private ExpenseRepository $expenses;

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
	 * @param ExpenseRepository  $expenses     Expense reads and writes.
	 * @param AuditLogRepository $audit        Audit writes.
	 * @param Transaction        $transactions Transaction runner.
	 */
	public function __construct(
		ExpenseRepository $expenses,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->expenses     = $expenses;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Search and paginate expenses.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function search( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $query['perPage'] ?? 25 ) ) );
		$category = sanitize_key( (string) ( $query['category'] ?? 'all' ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? 'all' ) );

		if ( ! in_array( $category, array_merge( self::CATEGORIES, array( 'all' ) ), true ) ) {
			$category = 'all';
		}

		if ( ! in_array( $status, array_merge( self::STATUSES, array( 'all' ) ), true ) ) {
			$status = 'all';
		}

		$result = $this->expenses->search(
			$context->id(),
			array(
				'search'   => mb_substr( trim( sanitize_text_field( (string) ( $query['search'] ?? '' ) ) ), 0, 100 ),
				'category' => $category,
				'status'   => $status,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		return array(
			'expenses'   => array_map( array( FinancePresenter::class, 'expense' ), $result['rows'] ),
			'categories' => self::CATEGORIES,
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One expense.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Expense id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function find( TenantContext $context, int $id ) {
		$row = $this->expenses->find( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That expense could not be found.', 'fashionos' ), 'expense_not_found' );
		}

		return FinancePresenter::expense( $row );
	}

	/**
	 * Record an expense.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$category = sanitize_key( (string) ( $input['category'] ?? '' ) );

		if ( ! in_array( $category, self::CATEGORIES, true ) ) {
			return Rest::invalid( 'expense_category_invalid', __( 'Choose a category for the expense.', 'fashionos' ) );
		}

		$description = trim( sanitize_text_field( (string) ( $input['description'] ?? '' ) ) );

		if ( '' === $description ) {
			return Rest::invalid( 'expense_description_required', __( 'Describe what the expense was for.', 'fashionos' ) );
		}

		if ( mb_strlen( $description ) > 191 ) {
			return Rest::invalid( 'expense_description_too_long', __( 'Expense descriptions may be at most 191 characters.', 'fashionos' ) );
		}

		$amount = Money::amount( $input['amount'] ?? 0 );

		if ( null === $amount || (float) $amount <= 0 ) {
			return Rest::invalid( 'expense_amount_invalid', __( 'Enter the amount spent, greater than zero.', 'fashionos' ) );
		}

		$spent_at = $this->normalise_date( $input['spentAt'] ?? '' );

		if ( $spent_at instanceof WP_Error ) {
			return $spent_at;
		}

		$notes = $this->normalise_notes( $input['notes'] ?? '' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$currency = (string) ( $context->tenant()['currency'] ?? '' );

		$created = $this->transactions->run(
			function () use ( $context, $category, $description, $amount, $spent_at, $notes, $currency ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				$expense_id = $this->expenses->create(
					array(
						'tenant_id'   => $context->id(),
						'uuid'        => wp_generate_uuid4(),
						'reference'   => References::placeholder(),
						'category'    => $category,
						'description' => $description,
						'amount'      => $amount,
						'currency'    => $currency,
						'spent_at'    => $spent_at,
						'status'      => 'recorded',
						'notes'       => $notes,
						'created_by'  => $context->user_id(),
						'created_at'  => $now,
						'updated_at'  => $now,
					)
				);

				if ( 0 === $expense_id ) {
					return Rest::invalid( 'expense_create_failed', __( 'The expense could not be recorded. Please try again.', 'fashionos' ), 500 );
				}

				$reference = References::format( 'E', $expense_id );

				if ( ! $this->expenses->update( $context->id(), $expense_id, array( 'reference' => $reference ) ) ) {
					return Rest::invalid( 'expense_create_failed', __( 'The expense could not be recorded. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'expense.recorded',
					'expense',
					$expense_id,
					array(
						'reference' => $reference,
						'category'  => $category,
						'amount'    => (float) $amount,
					)
				);

				return $expense_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return $this->find( $context, (int) $created );
	}

	/**
	 * Update or void an expense.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Expense id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( TenantContext $context, int $id, array $input ) {
		$existing = $this->expenses->find( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That expense could not be found.', 'fashionos' ), 'expense_not_found' );
		}

		if ( 'void' === (string) $existing['status'] ) {
			return Rest::invalid( 'expense_void', __( 'A void expense cannot be changed.', 'fashionos' ), 409 );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'category', $input ) ) {
			$category = sanitize_key( (string) $input['category'] );

			if ( ! in_array( $category, self::CATEGORIES, true ) ) {
				return Rest::invalid( 'expense_category_invalid', __( 'Choose a category for the expense.', 'fashionos' ) );
			}

			$data['category'] = $category;
		}

		if ( array_key_exists( 'description', $input ) ) {
			$description = trim( sanitize_text_field( (string) $input['description'] ) );

			if ( '' === $description ) {
				return Rest::invalid( 'expense_description_required', __( 'Describe what the expense was for.', 'fashionos' ) );
			}

			$data['description'] = mb_substr( $description, 0, 191 );
		}

		if ( array_key_exists( 'amount', $input ) ) {
			$amount = Money::amount( $input['amount'] );

			if ( null === $amount || (float) $amount <= 0 ) {
				return Rest::invalid( 'expense_amount_invalid', __( 'Enter the amount spent, greater than zero.', 'fashionos' ) );
			}

			$data['amount'] = $amount;
		}

		if ( array_key_exists( 'spentAt', $input ) ) {
			$spent_at = $this->normalise_date( $input['spentAt'] );

			if ( $spent_at instanceof WP_Error ) {
				return $spent_at;
			}

			$data['spent_at'] = $spent_at;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_notes( $input['notes'] );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes'] = $notes;
		}

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( 'void' !== $status ) {
				return Rest::invalid( 'expense_status_invalid', __( 'An expense can only be voided.', 'fashionos' ) );
			}

			$data['status'] = 'void';
		}

		foreach ( array( 'category', 'description', 'amount', 'spent_at', 'notes', 'status' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return FinancePresenter::expense( $existing );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		$updated = $this->transactions->run(
			function () use ( $context, $id, $data, $changed ) {
				if ( ! $this->expenses->update( $context->id(), $id, $data ) ) {
					return Rest::invalid( 'expense_update_failed', __( 'The expense could not be updated. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'expense.updated',
					'expense',
					$id,
					array( 'changed' => $changed )
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
	 * Validate an optional expense date.
	 *
	 * @param mixed $value Candidate date; blank means today.
	 * @return string|WP_Error
	 */
	private function normalise_date( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return gmdate( 'Y-m-d' );
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return Rest::invalid( 'expense_date_invalid', __( 'Enter a valid date for the expense.', 'fashionos' ) );
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Validate the expense notes.
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
			return Rest::invalid( 'expense_notes_too_long', __( 'Expense notes may be at most 2000 characters.', 'fashionos' ) );
		}

		return $notes;
	}
}
