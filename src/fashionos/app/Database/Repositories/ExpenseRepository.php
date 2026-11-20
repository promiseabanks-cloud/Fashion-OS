<?php
/**
 * Expense data access.
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
 * Reads and writes studio expenses.
 *
 * Expenses are money leaving the business: rent, materials, wages. They are
 * kept apart from invoices and payments so the finance summary can compare
 * what was collected with what was spent without either side rewriting the
 * other. Voiding keeps the row for the audit trail but removes it from totals.
 */
final class ExpenseRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array( 'tenant_id', 'created_by' );

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Expenses table.
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
		$this->table = Tables::expenses();
	}

	/**
	 * Find one expense inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Expense id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT e.*, u.display_name AS created_by_name
			FROM {$this->table} e
			LEFT JOIN {$this->db->users} u ON u.ID = e.created_by
			WHERE e.tenant_id = %d AND e.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Search expenses with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$where  = 'e.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['category'] ) {
			$where   .= ' AND e.category = %s';
			$params[] = (string) $query['category'];
		}

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND e.status = %s';
			$params[] = (string) $query['status'];
		}

		if ( '' !== (string) $query['search'] ) {
			$like     = '%' . $this->db->esc_like( (string) $query['search'] ) . '%';
			$where   .= ' AND ( e.description LIKE %s OR e.reference LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$from = "FROM {$this->table} e WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$sql = "SELECT e.*, u.display_name AS created_by_name
			{$from}
			ORDER BY e.spent_at DESC, e.id DESC
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
	 * Sum of recorded expenses in a date range.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $from      First day in `Y-m-d` form.
	 * @param string $to        Last day in `Y-m-d` form.
	 */
	public function total_between( int $tenant_id, string $from, string $to ): float {
		$total = $this->db->get_var(
			$this->db->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$this->table} WHERE tenant_id = %d AND status = 'recorded' AND spent_at >= %s AND spent_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$from,
				$to
			)
		);

		return round( (float) $total, 2 );
	}

	/**
	 * Insert an expense.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New expense id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update an expense inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Expense id.
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
}
