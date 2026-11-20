<?php
/**
 * Material and stock data access.
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
 * Reads and writes the material catalogue, garment allocations and the stock
 * movement history.
 *
 * Stock is only ever changed by an explicit movement or a designer-supplied
 * allocation, and both write a movement row. Customer-supplied and shared
 * allocations never touch `quantity_on_hand`: that fabric is not the studio's
 * stock, and counting it would overstate what is available.
 */
final class MaterialRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array(
		'tenant_id',
		'order_item_id',
		'material_id',
		'created_by',
		'actor_id',
		'reference_id',
		'is_active',
	);

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Materials table.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Allocations table.
	 *
	 * @var string
	 */
	private string $allocations;

	/**
	 * Movements table.
	 *
	 * @var string
	 */
	private string $movements;

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

		$this->db          = $db ?? $wpdb;
		$this->table       = Tables::materials();
		$this->allocations = Tables::material_allocations();
		$this->movements   = Tables::material_movements();
		$this->items       = Tables::order_items();
		$this->orders      = Tables::orders();
		$this->clients     = Tables::clients();
		$this->users       = $this->db->users;
	}

	/**
	 * Search the catalogue with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$where  = 'tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['type'] ) {
			$where   .= ' AND material_type = %s';
			$params[] = (string) $query['type'];
		}

		if ( $query['low_stock'] ) {
			$where .= ' AND reorder_level > 0 AND quantity_on_hand <= reorder_level';
		}

		if ( ! $query['include_inactive'] ) {
			$where .= ' AND is_active = %d';
			$params[] = 1;
		}

		if ( '' !== (string) $query['search'] ) {
			$like     = '%' . $this->db->esc_like( (string) $query['search'] ) . '%';
			$where   .= ' AND ( name LIKE %s OR notes LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$total = (int) $this->db->get_var(
			$this->db->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE {$where}", $params ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
		);

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE {$where} ORDER BY name ASC, id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) )
			),
			ARRAY_A
		);

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Find one material inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Material id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE tenant_id = %d AND id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a material.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New material id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a material inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Material id.
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
	 * Move stock by a signed amount.
	 *
	 * The update is conditional on the resulting quantity staying non-negative,
	 * so two simultaneous allocations cannot drive stock below zero. The check
	 * and the write are one statement, not a read-then-write race.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param int    $id        Material id.
	 * @param string $delta     Signed decimal amount.
	 */
	public function adjust_stock( int $tenant_id, int $id, string $delta ): bool {
		if ( $tenant_id <= 0 || $id <= 0 || 0.0 === (float) $delta ) {
			return false;
		}

		$value = number_format( (float) $delta, 2, '.', '' );

		$affected = $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table}
				SET quantity_on_hand = quantity_on_hand + %f, updated_at = %s
				WHERE tenant_id = %d AND id = %d AND quantity_on_hand + %f >= 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				(float) $value,
				gmdate( 'Y-m-d H:i:s' ),
				$tenant_id,
				$id,
				(float) $value
			)
		);

		return is_int( $affected ) && $affected > 0;
	}

	/**
	 * Stock movements of one material, newest first.
	 *
	 * @param int $tenant_id   Tenant id.
	 * @param int $material_id Material id.
	 * @param int $limit       Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function movements( int $tenant_id, int $material_id, int $limit = 50 ): array {
		if ( $tenant_id <= 0 || $material_id <= 0 ) {
			return array();
		}

		$sql = "SELECT m.*, u.display_name AS actor_name
			FROM {$this->movements} m
			LEFT JOIN {$this->users} u ON u.ID = m.actor_id
			WHERE m.tenant_id = %d AND m.material_id = %d
			ORDER BY m.id DESC
			LIMIT %d";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $material_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Append a stock movement.
	 *
	 * @param array<string, mixed> $data Column values.
	 */
	public function create_movement( array $data ): bool {
		$inserted = $this->db->insert( $this->movements, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false !== $inserted;
	}

	/**
	 * Allocations of one garment.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $item_id   Garment id.
	 * @return array<int, array<string, mixed>>
	 */
	public function allocations( int $tenant_id, int $item_id ): array {
		if ( $tenant_id <= 0 || $item_id <= 0 ) {
			return array();
		}

		$sql = "SELECT a.*, m.name AS material_name, m.unit AS unit,
				u.display_name AS created_by_name
			FROM {$this->allocations} a
			INNER JOIN {$this->table} m ON m.id = a.material_id AND m.tenant_id = a.tenant_id
			LEFT JOIN {$this->users} u ON u.ID = a.created_by
			WHERE a.tenant_id = %d AND a.order_item_id = %d
			ORDER BY a.id ASC";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $item_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find one allocation inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Allocation id.
	 * @return array<string, mixed>|null
	 */
	public function allocation( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->allocations} WHERE tenant_id = %d AND id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert an allocation.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New allocation id, or 0 on failure.
	 */
	public function create_allocation( array $data ): int {
		$inserted = $this->db->insert( $this->allocations, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Delete an allocation inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Allocation id.
	 */
	public function delete_allocation( int $tenant_id, int $id ): bool {
		$deleted = $this->db->delete(
			$this->allocations,
			array(
				'tenant_id' => $tenant_id,
				'id'        => $id,
			),
			array( '%d', '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Materials at or below their reorder level.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function low_stock( int $tenant_id, int $limit = 10 ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE tenant_id = %d AND is_active = 1 AND reorder_level > 0 AND quantity_on_hand <= reorder_level ORDER BY quantity_on_hand ASC, name ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Garment context used when allocating material.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $item_id   Garment id.
	 * @return array<string, mixed>|null
	 */
	public function garment( int $tenant_id, int $item_id ): ?array {
		if ( $tenant_id <= 0 || $item_id <= 0 ) {
			return null;
		}

		$sql = "SELECT oi.*, o.reference AS order_reference, o.client_id AS client_id, c.name AS client_name
			FROM {$this->items} oi
			INNER JOIN {$this->orders} o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
			INNER JOIN {$this->clients} c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
			WHERE oi.tenant_id = %d AND oi.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $item_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}
}
