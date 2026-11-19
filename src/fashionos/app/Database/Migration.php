<?php
/**
 * Migration contract.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A single, ordered, forward-only database migration.
 *
 * Migrations must be idempotent-safe through the version table rather than
 * through "IF NOT EXISTS" guessing. Destructive changes need their own
 * documented, reviewed migration.
 */
interface Migration {

	/**
	 * Zero-padded version string, for example "0001".
	 */
	public function version(): string;

	/**
	 * Short human-readable description recorded in the migrations table.
	 */
	public function description(): string;

	/**
	 * Apply the schema change.
	 *
	 * @param \wpdb $db WordPress database handle.
	 */
	public function up( \wpdb $db ): void;
}
