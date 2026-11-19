<?php
/**
 * Client data access.
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
 * Reads and writes customer profiles and their preferences.
 *
 * Every method takes a tenant id and filters by it, so a customer id from a
 * different workspace simply does not exist here. Contact details are never
 * returned for an archiving decision: archiving is a status change that keeps
 * the record (and its measurement history) intact.
 */
final class ClientRepository {

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
	 * Clients table.
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
		$this->table = Tables::clients();
	}

	/**
	 * Find one customer inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Client id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$profiles = Tables::measurement_profiles();

		$sql = "SELECT c.*,
				( SELECT COUNT(*) FROM {$profiles} p WHERE p.tenant_id = c.tenant_id AND p.client_id = c.id ) AS profile_count
			FROM {$this->table} c
			WHERE c.tenant_id = %d AND c.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find a live customer by email, for duplicate detection.
	 *
	 * @param int    $tenant_id  Tenant id.
	 * @param string $email      Normalised email address.
	 * @param int    $exclude_id Client id to ignore (the record being edited).
	 * @return array<string, mixed>|null
	 */
	public function find_by_email( int $tenant_id, string $email, int $exclude_id = 0 ): ?array {
		if ( $tenant_id <= 0 || '' === $email ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT id, reference, name FROM {$this->table} WHERE tenant_id = %d AND email = %s AND status = 'active' AND id != %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$email,
				max( 0, $exclude_id )
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Search customers with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( int $tenant_id, array $query ): array {
		$profiles = Tables::measurement_profiles();

		$where  = 'c.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND c.status = %s';
			$params[] = (string) $query['status'];
		}

		if ( '' !== (string) $query['search'] ) {
			$like     = '%' . $this->db->esc_like( (string) $query['search'] ) . '%';
			$where   .= ' AND ( c.name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s OR c.reference LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$from = "FROM {$this->table} c WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$sql = "SELECT c.*,
				( SELECT COUNT(*) FROM {$profiles} p WHERE p.tenant_id = c.tenant_id AND p.client_id = c.id ) AS profile_count
			{$from}
			ORDER BY c.updated_at DESC, c.id DESC
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
	 * Insert a customer.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New client id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a customer inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Client id.
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
	 * Preferences stored for a customer.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $client_id Client id.
	 * @return array<string, string>
	 */
	public function preferences( int $tenant_id, int $client_id ): array {
		if ( $tenant_id <= 0 || $client_id <= 0 ) {
			return array();
		}

		$table = Tables::client_preferences();
		$json  = $this->db->get_var(
			$this->db->prepare( "SELECT preferences_json FROM {$table} WHERE tenant_id = %d AND client_id = %d LIMIT 1", $tenant_id, $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
		);

		$decoded = json_decode( is_string( $json ) ? $json : '{}', true );

		return is_array( $decoded ) ? array_filter( $decoded, 'is_string' ) : array();
	}

	/**
	 * Store a customer's preferences, replacing the previous values.
	 *
	 * Preferences are one row per customer; the unique `(tenant_id, client_id)`
	 * key makes the lookup unambiguous.
	 *
	 * @param int                  $tenant_id   Tenant id.
	 * @param int                  $client_id   Client id.
	 * @param array<string, mixed> $preferences Normalised preferences.
	 */
	public function save_preferences( int $tenant_id, int $client_id, array $preferences ): bool {
		if ( $tenant_id <= 0 || $client_id <= 0 ) {
			return false;
		}

		$table = Tables::client_preferences();
		$now   = gmdate( 'Y-m-d H:i:s' );
		$json  = wp_json_encode( $preferences );

		$existing = $this->db->get_var(
			$this->db->prepare( "SELECT id FROM {$table} WHERE tenant_id = %d AND client_id = %d LIMIT 1", $tenant_id, $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
		);

		if ( null === $existing ) {
			$inserted = $this->db->insert(
				$table,
				array(
					'tenant_id'        => $tenant_id,
					'client_id'        => $client_id,
					'preferences_json' => $json,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);

			return false !== $inserted;
		}

		$updated = $this->db->update(
			$table,
			array(
				'preferences_json' => $json,
				'updated_at'       => $now,
			),
			array(
				'tenant_id' => $tenant_id,
				'client_id' => $client_id,
			),
			array( '%s', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}
}
