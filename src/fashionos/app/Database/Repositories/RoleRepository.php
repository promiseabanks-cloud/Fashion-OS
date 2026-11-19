<?php
/**
 * Role data access.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database\Repositories;

use FashionOS\Database\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes tenant-scoped roles.
 *
 * Roles are per tenant, so a user can hold different permissions in different
 * workspaces even with the same role name.
 */
final class RoleRepository {

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Table name.
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
		$this->table = Tables::roles();
	}

	/**
	 * Roles belonging to a tenant.
	 *
	 * @param int $tenant_id Tenant id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_tenant( int $tenant_id ): array {
		if ( $tenant_id <= 0 ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE tenant_id = %d ORDER BY id ASC", $tenant_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
			ARRAY_A
		);

		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Find one role by slug inside a tenant.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $slug      Role slug.
	 * @return array<string, mixed>|null
	 */
	public function find_by_slug( int $tenant_id, string $slug ): ?array {
		if ( $tenant_id <= 0 || '' === $slug ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE tenant_id = %d AND slug = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$slug
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Insert a role.
	 *
	 * @param int      $tenant_id   Tenant id.
	 * @param string   $slug        Role slug.
	 * @param string   $name        Display name.
	 * @param string[] $permissions Permission keys, or ["*"] for all.
	 * @param bool     $is_system   Whether the role ships with the plugin.
	 * @return int New role id, or 0 on failure.
	 */
	public function create( int $tenant_id, string $slug, string $name, array $permissions, bool $is_system = true ): int {
		$inserted = $this->db->insert(
			$this->table,
			array(
				'tenant_id'        => $tenant_id,
				'slug'             => $slug,
				'name'             => $name,
				'permissions_json' => wp_json_encode( array_values( $permissions ) ),
				'is_system'        => $is_system ? 1 : 0,
				'created_at'       => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Decode the stored permissions into an array.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$permissions = json_decode( (string) ( $row['permissions_json'] ?? '[]' ), true );

		$row['permissions'] = is_array( $permissions ) ? array_values( array_filter( $permissions, 'is_string' ) ) : array();

		unset( $row['permissions_json'] );

		return $row;
	}
}
