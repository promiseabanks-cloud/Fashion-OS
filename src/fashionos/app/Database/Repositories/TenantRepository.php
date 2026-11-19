<?php
/**
 * Tenant data access.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database\Repositories;

use FashionOS\Database\ColumnFormats;
use FashionOS\Database\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes workspace rows.
 *
 * Every method that returns tenant data also returns the tenant id, so callers
 * can keep working within an explicit tenant scope.
 */
final class TenantRepository {

	use ColumnFormats;

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
		$this->table = Tables::tenants();
	}

	/**
	 * Find a workspace by primary key.
	 *
	 * @param int $id Tenant id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find a workspace by slug.
	 *
	 * @param string $slug Tenant slug.
	 * @return array<string, mixed>|null
	 */
	public function find_by_slug( string $slug ): ?array {
		if ( '' === $slug ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE slug = %s LIMIT 1", $slug ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Whether a slug is already taken.
	 *
	 * @param string $slug Candidate slug.
	 */
	public function slug_exists( string $slug ): bool {
		if ( '' === $slug ) {
			return false;
		}

		$found = $this->db->get_var(
			$this->db->prepare( "SELECT id FROM {$this->table} WHERE slug = %s LIMIT 1", $slug ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
		);

		return null !== $found;
	}

	/**
	 * Active workspaces a user belongs to, including their role in each.
	 *
	 * @param int $user_id WordPress user id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$memberships = Tables::memberships();
		$roles       = Tables::roles();

		$sql = "SELECT t.*, m.status AS membership_status, m.created_at AS joined_at,
				r.slug AS role_slug, r.name AS role_name
			FROM {$this->table} t
			INNER JOIN {$memberships} m ON m.tenant_id = t.id
			INNER JOIN {$roles} r ON r.id = m.role_id AND r.tenant_id = t.id
			WHERE m.user_id = %d AND m.status = 'active' AND t.status = 'active'
			ORDER BY t.name ASC";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert a workspace.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New tenant id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert(
			$this->table,
			$data,
			$this->formats( $data, array( 'logo_id', 'created_by' ) )
		);

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a workspace.
	 *
	 * @param int                  $id   Tenant id.
	 * @param array<string, mixed> $data Changed columns.
	 */
	public function update( int $id, array $data ): bool {
		if ( $id <= 0 || array() === $data ) {
			return false;
		}

		$formats = array();

		foreach ( array_keys( $data ) as $column ) {
			$formats[] = 'logo_id' === $column ? '%d' : '%s';
		}

		$updated = $this->db->update( $this->table, $data, array( 'id' => $id ), $formats, array( '%d' ) );

		return false !== $updated;
	}

	/**
	 * Every workspace with an active member count, for platform administration.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function all_with_counts( int $limit = 100 ): array {
		$memberships = Tables::memberships();
		$limit       = max( 1, min( 200, $limit ) );

		$sql = "SELECT t.id, t.name, t.slug, t.status, t.created_at,
				( SELECT COUNT(*) FROM {$memberships} m WHERE m.tenant_id = t.id AND m.status = 'active' ) AS member_count
			FROM {$this->table} t
			ORDER BY t.created_at DESC
			LIMIT %d";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}
}
