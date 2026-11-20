<?php
/**
 * Fitting and alteration data access.
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
 * Reads and writes fitting appointments and the alterations raised from them.
 *
 * Both hang off a garment, so a fitting for the jacket never hides the
 * trousers' own appointment. Alterations may cite the fitting that produced
 * them, which keeps the reason for the work attached to the record.
 */
final class FittingRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array(
		'tenant_id',
		'order_item_id',
		'fitting_id',
		'assigned_to',
		'created_by',
	);

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Fittings table.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Alterations table.
	 *
	 * @var string
	 */
	private string $alterations;

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

		$this->db         = $db ?? $wpdb;
		$this->table      = Tables::fittings();
		$this->alterations = Tables::alterations();
		$this->items      = Tables::order_items();
		$this->orders     = Tables::orders();
		$this->clients    = Tables::clients();
		$this->users      = $this->db->users;
	}

	/**
	 * Search fittings with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$where  = 'f.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND f.status = %s';
			$params[] = (string) $query['status'];
		}

		if ( (int) $query['order_item_id'] > 0 ) {
			$where   .= ' AND f.order_item_id = %d';
			$params[] = (int) $query['order_item_id'];
		}

		if ( $query['upcoming'] ) {
			$where   .= ' AND f.status = %s AND f.scheduled_at >= %s';
			$params[] = 'scheduled';
			$params[] = gmdate( 'Y-m-d H:i:s' );
		}

		$from = "{$this->joins()} WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];
		$order    = $query['upcoming'] ? 'ASC' : 'DESC';

		$sql = "SELECT {$this->select()} {$from}
			ORDER BY f.scheduled_at {$order}, f.id DESC
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
	 * Find one fitting inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Fitting id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT {$this->select()} {$this->joins()} WHERE f.tenant_id = %d AND f.id = %d LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a fitting.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New fitting id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a fitting inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Fitting id.
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
	 * Scheduled fittings inside a date window.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $from      Inclusive `Y-m-d H:i:s`.
	 * @param string $to        Exclusive `Y-m-d H:i:s`.
	 * @param int    $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function upcoming( int $tenant_id, string $from, string $to, int $limit = 10 ): array {
		$sql = "SELECT {$this->select()} {$this->joins()}
			WHERE f.tenant_id = %d AND f.status = 'scheduled' AND f.scheduled_at >= %s AND f.scheduled_at < %s
			ORDER BY f.scheduled_at ASC
			LIMIT %d";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $from, $to, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Search alterations with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function alterations( int $tenant_id, array $query ): array {
		$where  = 'a.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND a.status = %s';
			$params[] = (string) $query['status'];
		}

		if ( (int) $query['order_item_id'] > 0 ) {
			$where   .= ' AND a.order_item_id = %d';
			$params[] = (int) $query['order_item_id'];
		}

		$from = "{$this->alteration_joins()} WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$sql = "SELECT a.*, oi.garment_name AS garment_name, o.reference AS order_reference,
				o.id AS order_id, c.name AS client_name, f.scheduled_at AS fitting_scheduled_at,
				u.display_name AS created_by_name
			{$from}
			ORDER BY a.id DESC
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
	 * Find one alteration inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Alteration id.
	 * @return array<string, mixed>|null
	 */
	public function alteration( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT a.*, oi.garment_name AS garment_name, o.reference AS order_reference,
				o.id AS order_id, c.name AS client_name
			{$this->alteration_joins()}
			WHERE a.tenant_id = %d AND a.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert an alteration.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New alteration id, or 0 on failure.
	 */
	public function create_alteration( array $data ): int {
		$inserted = $this->db->insert( $this->alterations, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update an alteration inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Alteration id.
	 * @param array<string, mixed> $data      Changed columns.
	 */
	public function update_alteration( int $tenant_id, int $id, array $data ): bool {
		if ( $tenant_id <= 0 || $id <= 0 || array() === $data ) {
			return false;
		}

		$updated = $this->db->update(
			$this->alterations,
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
	 * Shared fitting joins.
	 */
	private function joins(): string {
		return "FROM {$this->table} f
			INNER JOIN {$this->items} oi ON oi.id = f.order_item_id AND oi.tenant_id = f.tenant_id
			INNER JOIN {$this->orders} o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
			INNER JOIN {$this->clients} c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
			LEFT JOIN {$this->users} u ON u.ID = f.assigned_to";
	}

	/**
	 * Fitting columns with joined names.
	 */
	private function select(): string {
		return "f.*, oi.garment_name AS garment_name, oi.status AS garment_status,
			o.id AS order_id, o.reference AS order_reference, c.name AS client_name,
			u.display_name AS assignee_name";
	}

	/**
	 * Shared alteration joins.
	 */
	private function alteration_joins(): string {
		return "FROM {$this->alterations} a
			INNER JOIN {$this->items} oi ON oi.id = a.order_item_id AND oi.tenant_id = a.tenant_id
			INNER JOIN {$this->orders} o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
			INNER JOIN {$this->clients} c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
			LEFT JOIN {$this->table} f ON f.id = a.fitting_id AND f.tenant_id = a.tenant_id
			LEFT JOIN {$this->users} u ON u.ID = a.created_by";
	}
}
