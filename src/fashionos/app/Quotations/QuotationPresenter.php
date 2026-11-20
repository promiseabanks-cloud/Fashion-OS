<?php
/**
 * Quotation response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Quotations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps quotation rows and their line items to public field names.
 *
 * Amounts are returned as numbers rounded to two decimals; the stored values
 * are `DECIMAL(12,2)` strings recomputed on the server from the lines.
 */
final class QuotationPresenter {

	/**
	 * Public quotation fields.
	 *
	 * @param array<string, mixed>             $row   Quotation row with joined names.
	 * @param array<int, array<string, mixed>> $items Line items.
	 * @return array<string, mixed>
	 */
	public static function to_array( array $row, array $items = array() ): array {
		return array(
			'id'            => (int) ( $row['id'] ?? 0 ),
			'uuid'          => (string) ( $row['uuid'] ?? '' ),
			'reference'     => (string) ( $row['reference'] ?? '' ),
			'clientId'      => (int) ( $row['client_id'] ?? 0 ),
			'clientName'    => (string) ( $row['client_name'] ?? '' ),
			'designId'      => (int) ( $row['design_id'] ?? 0 ),
			'designTitle'   => (string) ( $row['design_title'] ?? '' ),
			'currency'      => (string) ( $row['currency'] ?? '' ),
			'status'        => (string) ( $row['status'] ?? 'draft' ),
			'validUntil'    => (string) ( $row['valid_until'] ?? '' ),
			'notes'         => (string) ( $row['notes'] ?? '' ),
			'subtotal'      => round( (float) ( $row['subtotal'] ?? 0 ), 2 ),
			'total'         => round( (float) ( $row['total'] ?? 0 ), 2 ),
			'itemCount'     => (int) ( $row['item_count'] ?? count( $items ) ),
			'items'         => array_map( array( self::class, 'item' ), $items ),
			'createdAt'     => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'     => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public line item fields.
	 *
	 * @param array<string, mixed> $row Quotation item row.
	 * @return array<string, mixed>
	 */
	public static function item( array $row ): array {
		return array(
			'id'          => (int) ( $row['id'] ?? 0 ),
			'description' => (string) ( $row['description'] ?? '' ),
			'quantity'    => (int) ( $row['quantity'] ?? 1 ),
			'unitPrice'   => round( (float) ( $row['unit_price'] ?? 0 ), 2 ),
			'lineTotal'   => round( (float) ( $row['line_total'] ?? 0 ), 2 ),
		);
	}
}
