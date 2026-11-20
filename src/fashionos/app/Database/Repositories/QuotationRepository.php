<?php
/**
 * Quotation data access.
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
 * Reads and writes quotations and their line items.
 *
 * A quotation is a priced offer, not an order: it has its own lifecycle and
 * status, and creating an order from it copies the lines rather than linking
 * the two records' fates. Amounts are stored as `DECIMAL(12,2)` and always
 * recomputed from the lines on the server.
 */
final class QuotationRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array( 'tenant_id', 'client_id', 'design_id', 'created_by', 'quotation_id', 'quantity', 'sort_order' );

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Quotations table.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Quotation items table.
	 *
	 * @var string
	 */
	private string $items;

	/**
	 * Clients table.
	 *
	 * @var string
	 */
	private string $clients;

	/**
	 * Designs table.
	 *
	 * @var string
	 */
	private string $designs;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional database handle (tests).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->db      = $db ?? $wpdb;
		$this->table   = Tables::quotations();
		$this->items   = Tables::quotation_items();
		$this->clients = Tables::clients();
		$this->designs = Tables::designs();
	}

	/**
	 * Find one quotation inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Quotation id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT q.*, c.name AS client_name, d.title AS design_title,
				( SELECT COUNT(*) FROM {$this->items} qi WHERE qi.tenant_id = q.tenant_id AND qi.quotation_id = q.id ) AS item_count
			{$this->joins()}
			WHERE q.tenant_id = %d AND q.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Search quotations with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$where  = 'q.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND q.status = %s';
			$params[] = (string) $query['status'];
		}

		if ( (int) $query['client_id'] > 0 ) {
			$where   .= ' AND q.client_id = %d';
			$params[] = (int) $query['client_id'];
		}

		if ( '' !== (string) $query['search'] ) {
			$like     = '%' . $this->db->esc_like( (string) $query['search'] ) . '%';
			$where   .= ' AND ( q.reference LIKE %s OR c.name LIKE %s OR d.title LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$from = "{$this->joins()} WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$sql = "SELECT q.*, c.name AS client_name, d.title AS design_title,
				( SELECT COUNT(*) FROM {$this->items} qi WHERE qi.tenant_id = q.tenant_id AND qi.quotation_id = q.id ) AS item_count
			{$from}
			ORDER BY q.updated_at DESC, q.id DESC
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
	 * Line items of one quotation, in display order.
	 *
	 * @param int $tenant_id    Tenant id.
	 * @param int $quotation_id Quotation id.
	 * @return array<int, array<string, mixed>>
	 */
	public function items( int $tenant_id, int $quotation_id ): array {
		if ( $tenant_id <= 0 || $quotation_id <= 0 ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, description, quantity, unit_price, line_total, sort_order FROM {$this->items} WHERE tenant_id = %d AND quotation_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$quotation_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert a quotation.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New quotation id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a quotation inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Quotation id.
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
	 * Replace every line item of a quotation.
	 *
	 * Only used while a quotation is a draft; the service enforces that.
	 *
	 * @param int                              $tenant_id    Tenant id.
	 * @param int                              $quotation_id Quotation id.
	 * @param array<int, array<string, mixed>> $items        Normalised items.
	 */
	public function replace_items( int $tenant_id, int $quotation_id, array $items ): bool {
		$this->db->delete(
			$this->items,
			array(
				'tenant_id'    => $tenant_id,
				'quotation_id' => $quotation_id,
			),
			array( '%d', '%d' )
		);

		foreach ( $items as $item ) {
			$item['tenant_id']    = $tenant_id;
			$item['quotation_id'] = $quotation_id;

			if ( ! $this->create_item( $item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Find one line item inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $item_id   Line item id.
	 * @return array<string, mixed>|null
	 */
	public function item( int $tenant_id, int $item_id ): ?array {
		if ( $tenant_id <= 0 || $item_id <= 0 ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->items} WHERE tenant_id = %d AND id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$item_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert one quotation line item.
	 *
	 * @param array<string, mixed> $data Column values.
	 */
	public function create_item( array $data ): bool {
		$inserted = $this->db->insert( $this->items, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false !== $inserted;
	}

	/**
	 * Shared joins for list and single-record reads.
	 */
	private function joins(): string {
		return "FROM {$this->table} q
			INNER JOIN {$this->clients} c ON c.id = q.client_id AND c.tenant_id = q.tenant_id
			LEFT JOIN {$this->designs} d ON d.id = q.design_id AND d.tenant_id = q.tenant_id";
	}
}
