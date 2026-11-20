<?php
/**
 * Human-readable record references.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the workspace-facing reference numbers (D-0001, Q-0001, O-0001).
 *
 * Numbers are derived from the row id so they are sequential per workspace
 * without a second sequence table. Records are inserted with a temporary
 * unique value because the id does not exist yet, then updated inside the same
 * transaction. `(tenant_id, reference)` is unique, so two records can never
 * share a number.
 */
final class References {

	/**
	 * Temporary reference used for the first insert.
	 *
	 * A UUID-derived token cannot collide with another row or with a formatted
	 * reference, and it never leaves the request.
	 */
	public static function placeholder(): string {
		return 'T' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 28 );
	}

	/**
	 * Formatted reference for a stored record.
	 *
	 * @param string $prefix Single uppercase letter, e.g. "Q".
	 * @param int    $id     Record id.
	 */
	public static function format( string $prefix, int $id ): string {
		$prefix = preg_match( '/^[A-Z]$/', $prefix ) ? $prefix : 'X';

		return sprintf( '%s-%04d', $prefix, max( 1, $id ) );
	}
}
