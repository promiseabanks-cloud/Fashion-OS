<?php
/**
 * Quality-control workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Quality;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\QualityRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quality checks per garment.
 *
 * Checks are append-only. A failed garment is re-inspected after rework and the
 * new result is another row, so "passed" can never erase the earlier failure
 * from the record.
 */
final class QualityService {

	/**
	 * Allowed results.
	 */
	public const RESULTS = array( 'passed', 'failed', 'needs_rework' );

	/**
	 * Quality reads and writes.
	 *
	 * @var QualityRepository
	 */
	private QualityRepository $quality;

	/**
	 * Garment reads and timeline writes.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $orders;

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
	 * @param QualityRepository  $quality      Quality reads and writes.
	 * @param OrderRepository    $orders       Garment reads and timeline writes.
	 * @param AuditLogRepository $audit        Audit writes.
	 * @param Transaction        $transactions Transaction runner.
	 */
	public function __construct(
		QualityRepository $quality,
		OrderRepository $orders,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->quality      = $quality;
		$this->orders       = $orders;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Paginated checks.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function search( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $query['perPage'] ?? 25 ) ) );
		$result   = sanitize_key( (string) ( $query['result'] ?? 'all' ) );

		if ( ! in_array( $result, array_merge( self::RESULTS, array( 'all' ) ), true ) ) {
			$result = 'all';
		}

		$rows = $this->quality->search(
			$context->id(),
			array(
				'result'        => $result,
				'order_item_id' => max( 0, (int) ( $query['orderItemId'] ?? 0 ) ),
				'page'          => $page,
				'per_page'      => $per_page,
			)
		);

		return array(
			'checks'     => array_map( array( QualityPresenter::class, 'check' ), $rows['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $rows['total'],
			'totalPages' => $rows['total'] > 0 ? (int) ceil( $rows['total'] / $per_page ) : 0,
		);
	}

	/**
	 * Record a quality check.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$item_id = (int) ( $input['orderItemId'] ?? 0 );

		if ( $item_id <= 0 ) {
			return Rest::invalid( 'quality_garment_required', __( 'Choose the garment that was checked.', 'fashionos' ) );
		}

		$garment = $this->orders->find_item( $context->id(), $item_id );

		if ( null === $garment ) {
			return Rest::not_found( __( 'That garment could not be found.', 'fashionos' ), 'order_item_not_found' );
		}

		$result = sanitize_key( (string) ( $input['result'] ?? '' ) );

		if ( ! in_array( $result, self::RESULTS, true ) ) {
			return Rest::invalid( 'quality_result_invalid', __( 'Choose passed, failed or needs rework.', 'fashionos' ) );
		}

		$notes = '';

		if ( isset( $input['notes'] ) && is_string( $input['notes'] ) ) {
			$notes = sanitize_textarea_field( $input['notes'] );

			if ( mb_strlen( $notes ) > 2000 ) {
				return Rest::invalid( 'quality_notes_too_long', __( 'Inspection notes may be at most 2000 characters.', 'fashionos' ) );
			}
		}

		$checked_at = gmdate( 'Y-m-d H:i:s' );

		if ( isset( $input['checkedAt'] ) && is_string( $input['checkedAt'] ) && '' !== trim( $input['checkedAt'] ) ) {
			$timestamp = strtotime( $input['checkedAt'] );

			if ( false === $timestamp ) {
				return Rest::invalid( 'quality_checked_at_invalid', __( 'Enter a valid inspection date.', 'fashionos' ) );
			}

			$checked_at = gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		$order_id     = (int) $garment['order_id'];
		$garment_name = (string) $garment['garment_name'];

		$created = $this->transactions->run(
			function () use ( $context, $item_id, $order_id, $garment_name, $result, $notes, $checked_at ) {
				$check_id = $this->quality->create(
					array(
						'tenant_id'     => $context->id(),
						'order_item_id' => $item_id,
						'checked_by'    => $context->user_id(),
						'result'        => $result,
						'notes'         => $notes,
						'checked_at'    => $checked_at,
						'created_at'    => gmdate( 'Y-m-d H:i:s' ),
					)
				);

				if ( 0 === $check_id ) {
					return Rest::invalid( 'quality_create_failed', __( 'The quality check could not be saved. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'quality.check.recorded',
					'quality_check',
					$check_id,
					array(
						'orderItemId' => $item_id,
						'result'      => $result,
					)
				);

				if ( 'passed' !== $result ) {
					$this->orders->create_event(
						array(
							'tenant_id'     => $context->id(),
							'order_id'      => $order_id,
							'order_item_id' => $item_id,
							'event_type'    => 'quality',
							'message'       => sprintf(
								/* translators: 1: garment name, 2: quality result. */
								__( 'Quality check for %1$s: %2$s.', 'fashionos' ),
								$garment_name,
								$this->result_label( $result )
							),
							'actor_id'      => $context->user_id(),
							'created_at'    => gmdate( 'Y-m-d H:i:s' ),
						)
					);
				}

				return $check_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		$row = $this->quality->find( $context->id(), (int) $created );

		return null === $row
			? Rest::not_found( __( 'That quality check could not be found.', 'fashionos' ), 'quality_check_not_found' )
			: QualityPresenter::check( $row );
	}

	/**
	 * Human-readable result name for timeline messages.
	 *
	 * @param string $result Stored result key.
	 */
	private function result_label( string $result ): string {
		$labels = array(
			'passed'       => __( 'passed', 'fashionos' ),
			'failed'       => __( 'failed', 'fashionos' ),
			'needs_rework' => __( 'needs rework', 'fashionos' ),
		);

		return $labels[ $result ] ?? $result;
	}
}
