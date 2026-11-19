<?php
/**
 * Database value formats.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derives `$wpdb` format specifiers from the data being written.
 *
 * `$wpdb->insert()` and `$wpdb->update()` take formats positionally, so a
 * hand-written list silently breaks as soon as a column is added or reordered.
 * Deriving them from the array keys keeps every repository honest.
 */
trait ColumnFormats {

	/**
	 * Format specifiers matching the order of `$data`.
	 *
	 * @param array<string, mixed> $data            Column values.
	 * @param string[]             $integer_columns Columns stored as integers.
	 * @return string[]
	 */
	private function formats( array $data, array $integer_columns ): array {
		return array_map(
			static fn ( string $column ): string => in_array( $column, $integer_columns, true ) ? '%d' : '%s',
			array_keys( $data )
		);
	}
}
