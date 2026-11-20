<?php
/**
 * Order, garment and activity data access.
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
 * Reads and writes orders, the garments inside them and their timeline.
 *
 * Garments are rows rather than columns because one order routinely contains a
 * jacket, trousers and a shirt that move through production independently.
 * Each garment stores its own design and measurement version reference.
 */
final class OrderRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array(
		'tenant_id',
		'client_id',
		'quotation_id',
		'created_by',
		'order_id',
		'order_item_id',
		'quotation_item_id',
		'design_id',
		'measurement_profile_id',
		'measurement_version_id',
		'quantity',
		'sort_order',
		'actor_id',
	);

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Orders table.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Order items table.
	 *
	 * @var string
	 */
	private string $items;

	/**
	 * Order events table.
	 *
	 * @var string
	 */
	private string $events;

	/**
	 * Clients table.
	 *
	 * @var string
	 */
	private string $clients;

	/**
	 * Quotations table.
	 *
	 * @var string
	 */
	private string $quotations;

	/**
	 * Designs table.
	 *
	 * @var string
	 */
	private string $designs;

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

		$this->db         = $db ?? $wpdb;
		$this->table      = Tables::orders();
		$this->items      = Tables::order_items();
		$this->events     = Tables::order_events();
		$this->clients    = Tables::clients();
		$this->quotations = Tables::quotations();
		$this->designs    = Tables::designs();
		$this->profiles   = Tables::measurement_profiles();
		$this->versions   = Tables::measurement_versions();
	}

	/**
	 * Find one order inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Order id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT o.*, c.name AS client_name, q.reference AS quotation_reference,
				( SELECT COUNT(*) FROM {$this->items} oi WHERE oi.tenant_id = o.tenant_id AND oi.order_id = o.id ) AS garment_count
			{$this->joins()}
			WHERE o.tenant_id = %d AND o.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Search orders with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$where  = 'o.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND o.status = %s';
			$params[] = (string) $query['status'];
		}

		if ( (int) $query['client_id'] > 0 ) {
			$where   .= ' AND o.client_id = %d';
			$params[] = (int) $query['client_id'];
		}

		if ( '' !== (string) $query['search'] ) {
			$like     = '%' . $this->db->esc_like( (string) $query['search'] ) . '%';
			$where   .= " AND ( o.reference LIKE %s OR c.name LIKE %s
				OR EXISTS ( SELECT 1 FROM {$this->items} search_item
					WHERE search_item.tenant_id = o.tenant_id AND search_item.order_id = o.id
					AND search_item.garment_name LIKE %s ) )";
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$from = "{$this->joins()} WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$sql = "SELECT o.*, c.name AS client_name, q.reference AS quotation_reference,
				( SELECT COUNT(*) FROM {$this->items} oi WHERE oi.tenant_id = o.tenant_id AND oi.order_id = o.id ) AS garment_count
			{$from}
			ORDER BY o.updated_at DESC, o.id DESC
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
	 * Garments of one order.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $order_id  Order id.
	 * @return array<int, array<string, mixed>>
	 */
	public function items( int $tenant_id, int $order_id ): array {
		if ( $tenant_id <= 0 || $order_id <= 0 ) {
			return array();
		}

		$sql = "SELECT oi.*, d.title AS design_title, d.reference AS design_reference,
				p.profile_name AS profile_name, v.version_number AS measurement_version_number,
				v.taken_at AS measurement_taken_at
			FROM {$this->items} oi
			LEFT JOIN {$this->designs} d ON d.id = oi.design_id AND d.tenant_id = oi.tenant_id
			LEFT JOIN {$this->profiles} p ON p.id = oi.measurement_profile_id AND p.tenant_id = oi.tenant_id
			LEFT JOIN {$this->versions} v ON v.id = oi.measurement_version_id AND v.tenant_id = oi.tenant_id
			WHERE oi.tenant_id = %d AND oi.order_id = %d
			ORDER BY oi.sort_order ASC, oi.id ASC";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Timeline of one order, oldest first.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $order_id  Order id.
	 * @return array<int, array<string, mixed>>
	 */
	public function events( int $tenant_id, int $order_id ): array {
		if ( $tenant_id <= 0 || $order_id <= 0 ) {
			return array();
		}

		$users = $this->db->users;

		$sql = "SELECT e.*, u.display_name AS actor_name
			FROM {$this->events} e
			LEFT JOIN {$users} u ON u.ID = e.actor_id
			WHERE e.tenant_id = %d AND e.order_id = %d
			ORDER BY e.id ASC";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find one garment with the order it belongs to.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $item_id   Garment id.
	 * @return array<string, mixed>|null
	 */
	public function find_item( int $tenant_id, int $item_id ): ?array {
		if ( $tenant_id <= 0 || $item_id <= 0 ) {
			return null;
		}

		$sql = "SELECT oi.*, o.client_id AS client_id, o.status AS order_status, o.reference AS order_reference
			FROM {$this->items} oi
			INNER JOIN {$this->table} o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
			WHERE oi.tenant_id = %d AND oi.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $item_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert an order.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New order id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update an order inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Order id.
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
	 * Insert a garment.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New garment id, or 0 on failure.
	 */
	public function create_item( array $data ): int {
		$inserted = $this->db->insert( $this->items, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a garment inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $item_id   Garment id.
	 * @param array<string, mixed> $data      Changed columns.
	 */
	public function update_item( int $tenant_id, int $item_id, array $data ): bool {
		if ( $tenant_id <= 0 || $item_id <= 0 || array() === $data ) {
			return false;
		}

		$updated = $this->db->update(
			$this->items,
			$data,
			array(
				'tenant_id' => $tenant_id,
				'id'        => $item_id,
			),
			$this->formats( $data, self::INT_COLUMNS ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Append a timeline entry.
	 *
	 * @param array<string, mixed> $data Column values.
	 */
	public function create_event( array $data ): bool {
		$inserted = $this->db->insert( $this->events, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false !== $inserted;
	}

	/**
	 * Shared joins for list and single-record reads.
	 */
	private function joins(): string {
		return "FROM {$this->table} o
			INNER JOIN {$this->clients} c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
			LEFT JOIN {$this->quotations} q ON q.id = o.quotation_id AND q.tenant_id = o.tenant_id";
	}
}
