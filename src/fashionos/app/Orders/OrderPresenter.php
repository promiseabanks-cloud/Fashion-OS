<?php
/**
 * Order response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Orders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps order rows, garments and timeline entries to public field names.
 */
final class OrderPresenter {

	/**
	 * Public order fields.
	 *
	 * @param array<string, mixed>             $row    Order row with joined names.
	 * @param array<int, array<string, mixed>> $items  Garments.
	 * @param array<int, array<string, mixed>> $events Timeline entries.
	 * @return array<string, mixed>
	 */
	public static function to_array( array $row, array $items = array(), array $events = array() ): array {
		return array(
			'id'                  => (int) ( $row['id'] ?? 0 ),
			'uuid'                => (string) ( $row['uuid'] ?? '' ),
			'reference'           => (string) ( $row['reference'] ?? '' ),
			'clientId'            => (int) ( $row['client_id'] ?? 0 ),
			'clientName'          => (string) ( $row['client_name'] ?? '' ),
			'quotationId'         => (int) ( $row['quotation_id'] ?? 0 ),
			'quotationReference'  => (string) ( $row['quotation_reference'] ?? '' ),
			'currency'            => (string) ( $row['currency'] ?? '' ),
			'status'              => (string) ( $row['status'] ?? 'draft' ),
			'dueDate'             => (string) ( $row['due_date'] ?? '' ),
			'notes'               => (string) ( $row['notes'] ?? '' ),
			'total'               => round( (float) ( $row['total'] ?? 0 ), 2 ),
			'garmentCount'        => (int) ( $row['garment_count'] ?? count( $items ) ),
			'items'               => array_map( array( self::class, 'item' ), $items ),
			'timeline'            => array_map( array( self::class, 'event' ), $events ),
			'createdAt'           => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'           => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public garment fields.
	 *
	 * @param array<string, mixed> $row Garment row with joined names.
	 * @return array<string, mixed>
	 */
	public static function item( array $row ): array {
		$quantity  = (int) ( $row['quantity'] ?? 1 );
		$unit_price = round( (float) ( $row['unit_price'] ?? 0 ), 2 );

		return array(
			'id'                       => (int) ( $row['id'] ?? 0 ),
			'garmentName'              => (string) ( $row['garment_name'] ?? '' ),
			'garmentCategory'          => (string) ( $row['garment_category'] ?? '' ),
			'quantity'                 => $quantity,
			'unitPrice'                => $unit_price,
			'lineTotal'                => round( $quantity * $unit_price, 2 ),
			'status'                   => (string) ( $row['status'] ?? 'pending' ),
			'notes'                    => (string) ( $row['notes'] ?? '' ),
			'designId'                 => (int) ( $row['design_id'] ?? 0 ),
			'designTitle'              => (string) ( $row['design_title'] ?? '' ),
			'designReference'          => (string) ( $row['design_reference'] ?? '' ),
			'measurementProfileId'     => (int) ( $row['measurement_profile_id'] ?? 0 ),
			'profileName'              => (string) ( $row['profile_name'] ?? '' ),
			'measurementVersionId'     => (int) ( $row['measurement_version_id'] ?? 0 ),
			'measurementVersionNumber' => (int) ( $row['measurement_version_number'] ?? 0 ),
			'measurementTakenAt'       => (string) ( $row['measurement_taken_at'] ?? '' ),
			'createdAt'                => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'                => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public timeline entry.
	 *
	 * @param array<string, mixed> $row Event row.
	 * @return array<string, mixed>
	 */
	public static function event( array $row ): array {
		return array(
			'id'          => (int) ( $row['id'] ?? 0 ),
			'type'        => (string) ( $row['event_type'] ?? '' ),
			'message'     => (string) ( $row['message'] ?? '' ),
			'orderItemId' => (int) ( $row['order_item_id'] ?? 0 ),
			'actorId'     => (int) ( $row['actor_id'] ?? 0 ),
			'actorName'   => (string) ( $row['actor_name'] ?? '' ),
			'createdAt'   => (string) ( $row['created_at'] ?? '' ),
		);
	}
}
