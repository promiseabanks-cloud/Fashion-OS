<?php
/**
 * Quality-control response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Quality;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps quality checks to public field names.
 */
final class QualityPresenter {

	/**
	 * Public check fields.
	 *
	 * @param array<string, mixed> $row Check row with joined names.
	 * @return array<string, mixed>
	 */
	public static function check( array $row ): array {
		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'orderItemId'    => (int) ( $row['order_item_id'] ?? 0 ),
			'orderId'        => (int) ( $row['order_id'] ?? 0 ),
			'orderReference' => (string) ( $row['order_reference'] ?? '' ),
			'clientName'     => (string) ( $row['client_name'] ?? '' ),
			'garmentName'    => (string) ( $row['garment_name'] ?? '' ),
			'checkedBy'      => (int) ( $row['checked_by'] ?? 0 ),
			'checkedByName'  => (string) ( $row['checked_by_name'] ?? '' ),
			'result'         => (string) ( $row['result'] ?? 'passed' ),
			'notes'          => (string) ( $row['notes'] ?? '' ),
			'checkedAt'      => (string) ( $row['checked_at'] ?? '' ),
			'createdAt'      => (string) ( $row['created_at'] ?? '' ),
		);
	}
}
