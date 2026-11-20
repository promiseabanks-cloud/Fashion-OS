<?php
/**
 * Design data access.
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
 * Reads and writes design records.
 *
 * A design belongs to one customer and may point at the measurement profile and
 * the exact measurement version it was drafted against. The version reference
 * is stored as an id, so later measurements never rewrite an approved design.
 */
final class DesignRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array(
		'tenant_id',
		'client_id',
		'measurement_profile_id',
		'measurement_version_id',
		'created_by',
	);

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Designs table.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Clients table.
	 *
	 * @var string
	 */
	private string $clients;

	/**
	 * Measurement profiles table.
	 *
	 * @var string
	 */
	private string $profiles;

	/**
	 * Measurement versions table.
	 *
	 * @var string
	 */
	private string $versions;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional database handle (tests).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->db       = $db ?? $wpdb;
		$this->table    = Tables::designs();
		$this->clients  = Tables::clients();
		$this->profiles = Tables::measurement_profiles();
		$this->versions = Tables::measurement_versions();
	}

	/**
	 * Find one design inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Design id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT d.*, c.name AS client_name, p.profile_name AS profile_name,
				v.version_number AS measurement_version_number, v.taken_at AS measurement_taken_at
			{$this->joins()}
			WHERE d.tenant_id = %d AND d.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Search designs with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$where  = 'd.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND d.status = %s';
			$params[] = (string) $query['status'];
		}

		if ( (int) $query['client_id'] > 0 ) {
			$where   .= ' AND d.client_id = %d';
			$params[] = (int) $query['client_id'];
		}

		if ( '' !== (string) $query['search'] ) {
			$like     = '%' . $this->db->esc_like( (string) $query['search'] ) . '%';
			$where   .= ' AND ( d.title LIKE %s OR d.reference LIKE %s OR d.garment_category LIKE %s OR c.name LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$from = "{$this->joins()} WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$sql = "SELECT d.*, c.name AS client_name, p.profile_name AS profile_name,
				v.version_number AS measurement_version_number, v.taken_at AS measurement_taken_at
			{$from}
			ORDER BY d.updated_at DESC, d.id DESC
			LIMIT %d OFFSET %d";

		$rows = $this->db->get_results(
			$this->db->prepare( $sql, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.
			ARRAY_A
		);

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Insert a design.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New design id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a design inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Design id.
	 * @param array<string, mixed> $data      Changed columns.
	 */
	public function update( int $tenant_id, int $id, array $data ): bool {
		if ( $tenant_id <= 0 || $id <= 0 || array() === $data ) {
			return false;
		}

		$updated = $this->db->update(
			$this->table,
			$data,
			array(
				'tenant_id' => $tenant_id,
				'id'        => $id,
			),
			$this->formats( $data, self::INT_COLUMNS ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Shared joins for list and single-record reads.
	 *
	 * The client is an inner join because a design always belongs to one; the
	 * profile and version are optional, so their joins cannot drop rows.
	 */
	private function joins(): string {
		return "FROM {$this->table} d
			INNER JOIN {$this->clients} c ON c.id = d.client_id AND c.tenant_id = d.tenant_id
			LEFT JOIN {$this->profiles} p ON p.id = d.measurement_profile_id AND p.tenant_id = d.tenant_id
			LEFT JOIN {$this->versions} v ON v.id = d.measurement_version_id AND v.tenant_id = d.tenant_id";
	}
}
