<?php
/**
 * Database migration runner.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database;

use FashionOS\Database\Migrations\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies pending migrations and records them in a version table.
 *
 * WordPress core tables handle identities; business tables are all prefixed
 * with the configured WordPress prefix plus `fos_`.
 */
final class Migrator {

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Fully qualified migrations table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional database handle (tests).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->db    = $db ?? $wpdb;
		$this->table = $this->db->prefix . 'fos_migrations';
	}

	/**
	 * Migrations table name.
	 */
	public function table_name(): string {
		return $this->table;
	}

	/**
	 * Latest version in the migration registry.
	 */
	public function latest_version(): string {
		return Migrations::latest_version();
	}

	/**
	 * Versions already applied to this database.
	 *
	 * @return string[]
	 */
	public function applied_versions(): array {
		if ( ! $this->table_exists() ) {
			return array();
		}

		$versions = $this->db->get_col( "SELECT version FROM {$this->table} ORDER BY version ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix.

		return is_array( $versions ) ? array_map( 'strval', $versions ) : array();
	}

	/**
	 * Migrations that still need to run, in version order.
	 *
	 * @return Migration[]
	 */
	public function pending(): array {
		$applied = $this->applied_versions();

		return array_values(
			array_filter(
				Migrations::all(),
				static fn ( Migration $migration ): bool => ! in_array( $migration->version(), $applied, true )
			)
		);
	}

	/**
	 * Apply every pending migration.
	 */
	public function migrate(): void {
		$this->create_version_table();

		foreach ( $this->pending() as $migration ) {
			$migration->up( $this->db );

			$this->db->insert(
				$this->table,
				array(
					'version'     => $migration->version(),
					'description' => $migration->description(),
					'applied_at'  => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%s', '%s', '%s' )
			);
		}

		update_option( 'fashionos_db_version', $this->latest_version() );
	}

	/**
	 * Migration status summary for the health endpoint.
	 *
	 * @return array{latestVersion: string, applied: int, pending: int}
	 */
	public function status(): array {
		return array(
			'latestVersion' => $this->latest_version(),
			'applied'       => count( $this->applied_versions() ),
			'pending'       => count( $this->pending() ),
		);
	}

	/**
	 * Whether the version table exists.
	 */
	private function table_exists(): bool {
		$found = $this->db->get_var(
			$this->db->prepare( 'SHOW TABLES LIKE %s', $this->table )
		);

		return $this->table === $found;
	}

	/**
	 * Install or update the migrations table with dbDelta.
	 */
	private function create_version_table(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->db->get_charset_collate();

		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			version VARCHAR(20) NOT NULL,
			description VARCHAR(191) NOT NULL DEFAULT '',
			applied_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY version (version)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
