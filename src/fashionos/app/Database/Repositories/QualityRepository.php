<?php
/**
 * Quality-control data access.
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
 * Reads and writes quality-control checks.
 *
 * Checks are append-only: a failed garment gets a new check after rework rather
 * than an edited result, so the inspection history stays intact.
 */
final class QualityRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array( 'tenant_id', 'order_item_id', 'checked_by', 'created_by' );

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Checks table.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Garments table.
	 *
	 * @var string
	 */
	private string $items;

	/**
	 * Orders table.
	 *
	 * @var string
	 */
	private string $orders;

	/**
	 * Clients table.
	 *
	 * @var string
	 */
	private string $clients;

	/**
	 * Users table.
	 *
	 * @var string
	 */
	private string $users;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional database handle (tests).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->db      = $db ?? $wpdb;
		$this->table   = Tables::quality_checks();
		$this->items   = Tables::order_items();
		$this->orders  = Tables::orders();
		$this->clients = Tables::clients();
		$this->users   = $this->db->users;
	}

	/**
	 * Search checks with pagination, newest first.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$where  = 'q.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['result'] ) {
			$where   .= ' AND q.result = %s';
			$params[] = (string) $query['result'];
		}

		if ( (int) $query['order_item_id'] > 0 ) {
			$where   .= ' AND q.order_item_id = %d';
			$params[] = (int) $query['order_item_id'];
		}

		$from = "{$this->joins()} WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$sql = "SELECT {$this->select()} {$from}
			ORDER BY q.checked_at DESC, q.id DESC
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
	 * Find one check inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Check id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT {$this->select()} {$this->joins()} WHERE q.tenant_id = %d AND q.id = %d LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a check.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New check id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Checks that failed or need rework, newest first.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent_failures( int $tenant_id, int $limit = 10 ): array {
		$sql = "SELECT {$this->select()} {$this->joins()}
			WHERE q.tenant_id = %d AND q.result IN ('failed','needs_rework')
			ORDER BY q.checked_at DESC, q.id DESC
			LIMIT %d";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count checks with a given result.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $result    Stored result key.
	 */
	public function count_result( int $tenant_id, string $result ): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = %d AND result = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$result
			)
		);
	}

	/**
	 * Shared joins for check reads.
	 */
	private function joins(): string {
		return "FROM {$this->table} q
			INNER JOIN {$this->items} oi ON oi.id = q.order_item_id AND oi.tenant_id = q.tenant_id
			INNER JOIN {$this->orders} o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
			INNER JOIN {$this->clients} c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
			LEFT JOIN {$this->users} u ON u.ID = q.checked_by";
	}

	/**
	 * Check columns with joined names.
	 */
	private function select(): string {
		return "q.*, oi.garment_name AS garment_name, o.id AS order_id,
			o.reference AS order_reference, c.name AS client_name, u.display_name AS checked_by_name";
	}
}
