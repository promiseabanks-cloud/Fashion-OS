<?php
/**
 * Material response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps materials, stock movements and garment allocations to public fields.
 *
 * Quantities are returned as numbers with two decimals; the stored values are
 * `DECIMAL(12,2)` strings.
 */
final class MaterialPresenter {

	/**
	 * Public material fields.
	 *
	 * @param array<string, mixed>             $row       Material row.
	 * @param array<int, array<string, mixed>> $movements Recent movements, when included.
	 * @return array<string, mixed>
	 */
	public static function material( array $row, array $movements = array() ): array {
		$quantity = round( (float) ( $row['quantity_on_hand'] ?? 0 ), 2 );
		$reorder  = round( (float) ( $row['reorder_level'] ?? 0 ), 2 );

		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'name'           => (string) ( $row['name'] ?? '' ),
			'materialType'   => (string) ( $row['material_type'] ?? 'fabric' ),
			'unit'           => (string) ( $row['unit'] ?? 'metre' ),
			'quantityOnHand' => $quantity,
			'reorderLevel'   => $reorder,
			'unitCost'       => round( (float) ( $row['unit_cost'] ?? 0 ), 2 ),
			'notes'          => (string) ( $row['notes'] ?? '' ),
			'isActive'       => (int) ( $row['is_active'] ?? 1 ) === 1,
			'lowStock'       => $reorder > 0 && $quantity <= $reorder,
			'movements'      => array_map( array( self::class, 'movement' ), $movements ),
			'createdAt'      => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'      => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public movement fields.
	 *
	 * @param array<string, mixed> $row Movement row.
	 * @return array<string, mixed>
	 */
	public static function movement( array $row ): array {
		return array(
			'id'            => (int) ( $row['id'] ?? 0 ),
			'type'          => (string) ( $row['movement_type'] ?? '' ),
			'quantity'      => round( (float) ( $row['quantity'] ?? 0 ), 2 ),
			'referenceType' => (string) ( $row['reference_type'] ?? '' ),
			'referenceId'   => (int) ( $row['reference_id'] ?? 0 ),
			'note'          => (string) ( $row['note'] ?? '' ),
			'actorId'       => (int) ( $row['actor_id'] ?? 0 ),
			'actorName'     => (string) ( $row['actor_name'] ?? '' ),
			'createdAt'     => (string) ( $row['created_at'] ?? '' ),
		);
	}

	/**
	 * Public allocation fields.
	 *
	 * @param array<string, mixed> $row Allocation row with material names.
	 * @return array<string, mixed>
	 */
	public static function allocation( array $row ): array {
		return array(
			'id'           => (int) ( $row['id'] ?? 0 ),
			'orderItemId'  => (int) ( $row['order_item_id'] ?? 0 ),
			'materialId'   => (int) ( $row['material_id'] ?? 0 ),
			'materialName' => (string) ( $row['material_name'] ?? '' ),
			'unit'         => (string) ( $row['unit'] ?? '' ),
			'quantity'     => round( (float) ( $row['quantity'] ?? 0 ), 2 ),
			'source'       => (string) ( $row['source'] ?? '' ),
			'notes'        => (string) ( $row['notes'] ?? '' ),
			'createdBy'    => (int) ( $row['created_by'] ?? 0 ),
			'createdByName' => (string) ( $row['created_by_name'] ?? '' ),
			'createdAt'    => (string) ( $row['created_at'] ?? '' ),
		);
	}

	/**
	 * Public garment summary used with allocations.
	 *
	 * @param array<string, mixed> $row Garment row with order and customer names.
	 * @return array<string, mixed>
	 */
	public static function garment( array $row ): array {
		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'garmentName'    => (string) ( $row['garment_name'] ?? '' ),
			'garmentCategory' => (string) ( $row['garment_category'] ?? '' ),
			'orderId'        => (int) ( $row['order_id'] ?? 0 ),
			'orderReference' => (string) ( $row['order_reference'] ?? '' ),
			'clientName'     => (string) ( $row['client_name'] ?? '' ),
		);
	}
}
