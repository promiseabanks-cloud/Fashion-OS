<?php
/**
 * Portal access data access.
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
 * Reads and writes the customer portal's access links.
 *
 * Only a SHA-256 hash of each token is stored, so a database dump cannot be
 * replayed as a working portal link. The hash is globally unique, which lets a
 * portal request that carries nothing but its token identify both the workspace
 * and the customer before any other query runs.
 */
final class PortalAccessRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array(
		'tenant_id',
		'client_id',
		'created_by',
	);

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Access table.
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
		$this->table = Tables::portal_access();
	}

	/**
	 * Find one access link by the hash of the token presented.
	 *
	 * @param string $hash SHA-256 of the token.
	 * @return array<string, mixed>|null
	 */
	public function find_by_hash( string $hash ): ?array {
		if ( '' === $hash ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE token_hash = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$hash
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find one access link inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Access id.
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
	 * Every access link issued to one customer, newest first.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $client_id Customer id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_client( int $tenant_id, int $client_id ): array {
		if ( $tenant_id <= 0 || $client_id <= 0 ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE tenant_id = %d AND client_id = %d ORDER BY created_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$client_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert an access link.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New access id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update an access link inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Access id.
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
	 * Record that a link was used, at most once per hour.
	 *
	 * Portal reads would otherwise write on every request; the timestamp is
	 * informational, so a coarse update is enough.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param int    $id        Access id.
	 * @param string $now       Current UTC timestamp.
	 * @param string $cutoff    Only update when last use is older than this.
	 */
	public function touch( int $tenant_id, int $id, string $now, string $cutoff ): void {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return;
		}

		$this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET last_used_at = %s WHERE tenant_id = %d AND id = %d AND ( last_used_at = '' OR last_used_at < %s )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$now,
				$tenant_id,
				$id,
				$cutoff
			)
		);
	}
}
