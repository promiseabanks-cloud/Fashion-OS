<?php
/**
 * Membership data access.
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
 * Reads and writes user-to-tenant memberships.
 *
 * A membership is the only proof that a user may act inside a workspace: the
 * tenant resolver consults it before any tenant-scoped work happens.
 */
final class MembershipRepository {

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
		$this->table = Tables::memberships();
	}

	/**
	 * Find a user's membership and role inside one tenant.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $user_id   WordPress user id.
	 * @return array<string, mixed>|null
	 */
	public function find_for_user( int $tenant_id, int $user_id ): ?array {
		if ( $tenant_id <= 0 || $user_id <= 0 ) {
			return null;
		}

		$roles = Tables::roles();
		$users = $this->db->users;

		$sql = "SELECT m.id, m.tenant_id, m.user_id, m.role_id, m.status, m.created_at,
				r.slug AS role_slug, r.name AS role_name, r.permissions_json,
				u.display_name, u.user_email
			FROM {$this->table} m
			INNER JOIN {$roles} r ON r.id = m.role_id AND r.tenant_id = m.tenant_id
			INNER JOIN {$users} u ON u.ID = m.user_id
			WHERE m.tenant_id = %d AND m.user_id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Every membership of a tenant, with the member's WordPress identity.
	 *
	 * @param int $tenant_id Tenant id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_tenant( int $tenant_id ): array {
		if ( $tenant_id <= 0 ) {
			return array();
		}

		$roles = Tables::roles();
		$users = $this->db->users;

		$sql = "SELECT m.id, m.tenant_id, m.user_id, m.role_id, m.status, m.created_at,
				r.slug AS role_slug, r.name AS role_name, r.permissions_json,
				u.display_name, u.user_email
			FROM {$this->table} m
			INNER JOIN {$roles} r ON r.id = m.role_id AND r.tenant_id = m.tenant_id
			INNER JOIN {$users} u ON u.ID = m.user_id
			WHERE m.tenant_id = %d
			ORDER BY m.created_at ASC";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Count active members of a tenant.
	 *
	 * @param int $tenant_id Tenant id.
	 */
	public function count_for_tenant( int $tenant_id ): int {
		if ( $tenant_id <= 0 ) {
			return 0;
		}

		return (int) $this->db->get_var(
			$this->db->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = %d AND status = 'active'", $tenant_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
		);
	}

	/**
	 * Insert a membership.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $user_id   WordPress user id.
	 * @param int $role_id   Role id inside the tenant.
	 * @return int New membership id, or 0 on failure.
	 */
	public function create( int $tenant_id, int $user_id, int $role_id ): int {
		$now = gmdate( 'Y-m-d H:i:s' );

		$inserted = $this->db->insert(
			$this->table,
			array(
				'tenant_id'  => $tenant_id,
				'user_id'    => $user_id,
				'role_id'    => $role_id,
				'status'     => 'active',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Change the role of a membership.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $user_id   WordPress user id.
	 * @param int $role_id   New role id inside the tenant.
	 */
	public function update_role( int $tenant_id, int $user_id, int $role_id ): bool {
		$updated = $this->db->update(
			$this->table,
			array(
				'role_id'    => $role_id,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array(
				'tenant_id' => $tenant_id,
				'user_id'   => $user_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Remove a membership.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $user_id   WordPress user id.
	 */
	public function delete( int $tenant_id, int $user_id ): bool {
		$deleted = $this->db->delete(
			$this->table,
			array(
				'tenant_id' => $tenant_id,
				'user_id'   => $user_id,
			),
			array( '%d', '%d' )
		);

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Decode role permissions into an array.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$permissions = json_decode( (string) ( $row['permissions_json'] ?? '[]' ), true );

		$row['role_permissions'] = is_array( $permissions ) ? array_values( array_filter( $permissions, 'is_string' ) ) : array();

		unset( $row['permissions_json'] );

		return $row;
	}
}
