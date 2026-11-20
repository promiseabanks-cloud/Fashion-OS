<?php
/**
 * Payment data access.
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
 * Reads and writes payments against invoices.
 *
 * A payment is its own row so a partially paid invoice keeps a history. Only
 * rows with status `verified` count towards the invoice balance, and the
 * `(tenant_id, idempotency_key)` index makes a repeated callback return the
 * existing payment instead of recording the money twice.
 */
final class PaymentRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array(
		'tenant_id',
		'invoice_id',
		'client_id',
		'created_by',
		'verified_by',
	);

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Payments table.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Invoices table.
	 *
	 * @var string
	 */
	private string $invoices;

	/**
	 * Clients table.
	 *
	 * @var string
	 */
	private string $clients;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional database handle (tests).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->db       = $db ?? $wpdb;
		$this->table    = Tables::payments();
		$this->invoices = Tables::invoices();
		$this->clients  = Tables::clients();
	}

	/**
	 * Find one payment inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Payment id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT p.*, i.reference AS invoice_reference, i.status AS invoice_status,
				c.name AS client_name, v.display_name AS verified_by_name
			FROM {$this->table} p
			INNER JOIN {$this->invoices} i ON i.id = p.invoice_id AND i.tenant_id = p.tenant_id
			INNER JOIN {$this->clients} c ON c.id = p.client_id AND c.tenant_id = p.tenant_id
			LEFT JOIN {$this->db->users} v ON v.ID = p.verified_by
			WHERE p.tenant_id = %d AND p.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find one payment by its idempotency key.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $key       Idempotency key.
	 * @return array<string, mixed>|null
	 */
	public function find_by_key( int $tenant_id, string $key ): ?array {
		if ( $tenant_id <= 0 || '' === $key ) {
			return null;
		}

		$sql = "SELECT p.*, i.reference AS invoice_reference, i.status AS invoice_status,
				c.name AS client_name, v.display_name AS verified_by_name
			FROM {$this->table} p
			INNER JOIN {$this->invoices} i ON i.id = p.invoice_id AND i.tenant_id = p.tenant_id
			INNER JOIN {$this->clients} c ON c.id = p.client_id AND c.tenant_id = p.tenant_id
			LEFT JOIN {$this->db->users} v ON v.ID = p.verified_by
			WHERE p.tenant_id = %d AND p.idempotency_key = %s
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Search payments with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$where  = 'p.tenant_id = %d';
		$params = array( $tenant_id );

		if ( (int) $query['invoice_id'] > 0 ) {
			$where   .= ' AND p.invoice_id = %d';
			$params[] = (int) $query['invoice_id'];
		}

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND p.status = %s';
			$params[] = (string) $query['status'];
		}

		$from = "FROM {$this->table} p
			INNER JOIN {$this->invoices} i ON i.id = p.invoice_id AND i.tenant_id = p.tenant_id
			INNER JOIN {$this->clients} c ON c.id = p.client_id AND c.tenant_id = p.tenant_id
			WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$sql = "SELECT p.*, i.reference AS invoice_reference, i.status AS invoice_status,
				c.name AS client_name, '' AS verified_by_name
			{$from}
			ORDER BY p.paid_at DESC, p.id DESC
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
	 * Every payment recorded against one invoice, newest first.
	 *
	 * @param int $tenant_id  Tenant id.
	 * @param int $invoice_id Invoice id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_invoice( int $tenant_id, int $invoice_id ): array {
		if ( $tenant_id <= 0 || $invoice_id <= 0 ) {
			return array();
		}

		$sql = "SELECT p.*, i.reference AS invoice_reference, i.status AS invoice_status,
				c.name AS client_name, v.display_name AS verified_by_name
			FROM {$this->table} p
			INNER JOIN {$this->invoices} i ON i.id = p.invoice_id AND i.tenant_id = p.tenant_id
			INNER JOIN {$this->clients} c ON c.id = p.client_id AND c.tenant_id = p.tenant_id
			LEFT JOIN {$this->db->users} v ON v.ID = p.verified_by
			WHERE p.tenant_id = %d AND p.invoice_id = %d
			ORDER BY p.id ASC";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $invoice_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Most recent payments for the finance dashboard.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $tenant_id, int $limit = 5 ): array {
		$sql = "SELECT p.*, i.reference AS invoice_reference, i.status AS invoice_status,
				c.name AS client_name, '' AS verified_by_name
			FROM {$this->table} p
			INNER JOIN {$this->invoices} i ON i.id = p.invoice_id AND i.tenant_id = p.tenant_id
			INNER JOIN {$this->clients} c ON c.id = p.client_id AND c.tenant_id = p.tenant_id
			WHERE p.tenant_id = %d
			ORDER BY p.created_at DESC, p.id DESC
			LIMIT %d";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Sum of verified payments received in a date range.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $from      First day in `Y-m-d` form.
	 * @param string $to        Last day in `Y-m-d` form.
	 */
	public function collected_between( int $tenant_id, string $from, string $to ): float {
		$total = $this->db->get_var(
			$this->db->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$this->table} WHERE tenant_id = %d AND status = 'verified' AND paid_at >= %s AND paid_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			)
		);

		return round( (float) $total, 2 );
	}

	/**
	 * Count and sum of payments still awaiting verification.
	 *
	 * @param int $tenant_id Tenant id.
	 * @return array{count: int, total: float}
	 */
	public function pending_totals( int $tenant_id ): array {
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT COUNT(*) AS payments, COALESCE(SUM(amount), 0) AS total FROM {$this->table} WHERE tenant_id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id
			),
			ARRAY_A
		);

		return array(
			'count' => is_array( $row ) ? (int) ( $row['payments'] ?? 0 ) : 0,
			'total' => is_array( $row ) ? round( (float) ( $row['total'] ?? 0 ), 2 ) : 0.0,
		);
	}

	/**
	 * Insert a payment.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New payment id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a payment inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Payment id.
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
