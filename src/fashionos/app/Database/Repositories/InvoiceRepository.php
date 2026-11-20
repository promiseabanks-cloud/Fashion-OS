<?php
/**
 * Invoice data access.
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
 * Reads and writes invoices and their line items.
 *
 * An invoice states what a customer owes for one order or one ad-hoc set of
 * lines. Amounts are stored as `DECIMAL(14,2)`; `amount_paid` only ever
 * reflects verified payments, so the balance can always be recomputed from the
 * payment ledger.
 */
final class InvoiceRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array(
		'tenant_id',
		'client_id',
		'order_id',
		'created_by',
		'invoice_id',
		'quantity',
		'sort_order',
	);

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Invoices table.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Invoice items table.
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
	 * Orders table.
	 *
	 * @var string
	 */
	private string $orders;

	/**
	 * Payments table.
	 *
	 * @var string
	 */
	private string $payments;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional database handle (tests).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->db       = $db ?? $wpdb;
		$this->table    = Tables::invoices();
		$this->items    = Tables::invoice_items();
		$this->clients  = Tables::clients();
		$this->orders   = Tables::orders();
		$this->payments = Tables::payments();
	}

	/**
	 * Find one invoice inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Invoice id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT i.*, c.name AS client_name, c.reference AS client_reference,
				o.reference AS order_reference,
				( SELECT COUNT(*) FROM {$this->items} ii WHERE ii.tenant_id = i.tenant_id AND ii.invoice_id = i.id ) AS item_count
			{$this->joins()}
			WHERE i.tenant_id = %d AND i.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Search invoices with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$where  = 'i.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND i.status = %s';
			$params[] = (string) $query['status'];
		}

		if ( (int) $query['client_id'] > 0 ) {
			$where   .= ' AND i.client_id = %d';
			$params[] = (int) $query['client_id'];
		}

		if ( ! empty( $query['overdue'] ) ) {
			$where   .= " AND i.balance > 0 AND i.due_date <> '' AND i.due_date < %s AND i.status <> 'void'";
			$params[] = (string) $query['today'];
		}

		if ( '' !== (string) $query['search'] ) {
			$like     = '%' . $this->db->esc_like( (string) $query['search'] ) . '%';
			$where   .= ' AND ( i.reference LIKE %s OR c.name LIKE %s OR o.reference LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$from = "{$this->joins()} WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$sql = "SELECT i.*, c.name AS client_name, c.reference AS client_reference,
				o.reference AS order_reference,
				( SELECT COUNT(*) FROM {$this->items} ii WHERE ii.tenant_id = i.tenant_id AND ii.invoice_id = i.id ) AS item_count
			{$from}
			ORDER BY i.updated_at DESC, i.id DESC
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
	 * Line items of one invoice, in display order.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $invoice_id Invoice id.
	 * @return array<int, array<string, mixed>>
	 */
	public function items( int $tenant_id, int $invoice_id ): array {
		if ( $tenant_id <= 0 || $invoice_id <= 0 ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, description, quantity, unit_price, line_total, sort_order FROM {$this->items} WHERE tenant_id = %d AND invoice_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$invoice_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert an invoice.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New invoice id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update an invoice inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Invoice id.
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
	 * Replace every line of an invoice.
	 *
	 * Only used while an invoice is a draft; the service enforces that.
	 *
	 * @param int                              $tenant_id  Tenant id.
	 * @param int                              $invoice_id Invoice id.
	 * @param array<int, array<string, mixed>> $items      Normalised items.
	 */
	public function replace_items( int $tenant_id, int $invoice_id, array $items ): bool {
		$deleted = $this->db->delete(
			$this->items,
			array(
				'tenant_id'  => $tenant_id,
				'invoice_id' => $invoice_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			return false;
		}

		foreach ( $items as $item ) {
			$item['tenant_id']  = $tenant_id;
			$item['invoice_id'] = $invoice_id;

			if ( ! $this->create_item( $item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Insert one invoice line.
	 *
	 * @param array<string, mixed> $data Column values.
	 */
	private function create_item( array $data ): bool {
		$inserted = $this->db->insert( $this->items, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false !== $inserted;
	}

	/**
	 * Total verified payments for one invoice.
	 *
	 * @param int $tenant_id  Tenant id.
	 * @param int $invoice_id Invoice id.
	 */
	public function verified_total( int $tenant_id, int $invoice_id ): float {
		if ( $tenant_id <= 0 || $invoice_id <= 0 ) {
			return 0.0;
		}

		$total = $this->db->get_var(
			$this->db->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$this->payments} WHERE tenant_id = %d AND invoice_id = %d AND status = 'verified'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$invoice_id
			)
		);

		return round( (float) $total, 2 );
	}

	/**
	 * Workspace-wide financial totals for the dashboard.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $today     Today in `Y-m-d` form.
	 * @return array<string, mixed>
	 */
	public function totals( int $tenant_id, string $today ): array {
		$sql = "SELECT
				COALESCE( SUM( CASE WHEN status IN ( 'issued', 'partially_paid' ) THEN total ELSE 0 END ), 0 ) AS invoiced_open,
				COALESCE( SUM( CASE WHEN status IN ( 'issued', 'partially_paid' ) THEN balance ELSE 0 END ), 0 ) AS outstanding,
				COALESCE( SUM( CASE WHEN status = 'draft' THEN 1 ELSE 0 END ), 0 ) AS drafts,
				COALESCE( SUM( CASE WHEN status = 'paid' THEN 1 ELSE 0 END ), 0 ) AS paid_count,
				COALESCE( SUM( CASE WHEN status IN ( 'issued', 'partially_paid' ) AND due_date <> '' AND due_date < %s THEN 1 ELSE 0 END ), 0 ) AS overdue
			FROM {$this->table}
			WHERE tenant_id = %d";

		$row = $this->db->get_row( $this->db->prepare( $sql, $today, $tenant_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		if ( ! is_array( $row ) ) {
			$row = array();
		}

		return array(
			'invoicedOpen' => round( (float) ( $row['invoiced_open'] ?? 0 ), 2 ),
			'outstanding'  => round( (float) ( $row['outstanding'] ?? 0 ), 2 ),
			'drafts'       => (int) ( $row['drafts'] ?? 0 ),
			'paidCount'    => (int) ( $row['paid_count'] ?? 0 ),
			'overdue'      => (int) ( $row['overdue'] ?? 0 ),
		);
	}

	/**
	 * Invoices that are open and past their due date.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $today     Today in `Y-m-d` form.
	 * @param int    $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function overdue( int $tenant_id, string $today, int $limit = 5 ): array {
		$sql = "SELECT i.*, c.name AS client_name, c.reference AS client_reference,
				o.reference AS order_reference, 0 AS item_count
			{$this->joins()}
			WHERE i.tenant_id = %d AND i.balance > 0 AND i.due_date <> '' AND i.due_date < %s
				AND i.status IN ( 'issued', 'partially_paid' )
			ORDER BY i.due_date ASC, i.id ASC
			LIMIT %d";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $today, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Shared joins for list and single-record reads.
	 */
	private function joins(): string {
		return "FROM {$this->table} i
			INNER JOIN {$this->clients} c ON c.id = i.client_id AND c.tenant_id = i.tenant_id
			LEFT JOIN {$this->orders} o ON o.id = i.order_id AND o.tenant_id = i.tenant_id";
	}
}
