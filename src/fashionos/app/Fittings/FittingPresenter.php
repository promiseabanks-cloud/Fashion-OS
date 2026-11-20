<?php
/**
 * Fitting and alteration response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Fittings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps fitting appointments and alterations to public field names.
 */
final class FittingPresenter {

	/**
	 * Public fitting fields.
	 *
	 * @param array<string, mixed> $row Fitting row with joined names.
	 * @return array<string, mixed>
	 */
	public static function fitting( array $row ): array {
		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'orderItemId'    => (int) ( $row['order_item_id'] ?? 0 ),
			'orderId'        => (int) ( $row['order_id'] ?? 0 ),
			'orderReference' => (string) ( $row['order_reference'] ?? '' ),
			'clientName'     => (string) ( $row['client_name'] ?? '' ),
			'garmentName'    => (string) ( $row['garment_name'] ?? '' ),
			'assignedTo'     => (int) ( $row['assigned_to'] ?? 0 ),
			'assigneeName'   => (string) ( $row['assignee_name'] ?? '' ),
			'scheduledAt'    => (string) ( $row['scheduled_at'] ?? '' ),
			'status'         => (string) ( $row['status'] ?? 'scheduled' ),
			'location'       => (string) ( $row['location'] ?? '' ),
			'notes'          => (string) ( $row['notes'] ?? '' ),
			'outcome'        => (string) ( $row['outcome'] ?? '' ),
			'createdAt'      => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'      => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public alteration fields.
	 *
	 * @param array<string, mixed> $row Alteration row with joined names.
	 * @return array<string, mixed>
	 */
	public static function alteration( array $row ): array {
		return array(
			'id'                => (int) ( $row['id'] ?? 0 ),
			'orderItemId'       => (int) ( $row['order_item_id'] ?? 0 ),
			'orderId'           => (int) ( $row['order_id'] ?? 0 ),
			'orderReference'    => (string) ( $row['order_reference'] ?? '' ),
			'clientName'        => (string) ( $row['client_name'] ?? '' ),
			'garmentName'       => (string) ( $row['garment_name'] ?? '' ),
			'fittingId'         => (int) ( $row['fitting_id'] ?? 0 ),
			'fittingScheduledAt' => (string) ( $row['fitting_scheduled_at'] ?? '' ),
			'description'       => (string) ( $row['description'] ?? '' ),
			'status'            => (string) ( $row['status'] ?? 'open' ),
			'dueDate'           => (string) ( $row['due_date'] ?? '' ),
			'completedAt'       => (string) ( $row['completed_at'] ?? '' ),
			'createdAt'         => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'         => (string) ( $row['updated_at'] ?? '' ),
		);
	}
}
