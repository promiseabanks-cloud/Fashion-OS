<?php
/**
 * Audit trail data access.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database\Repositories;

use FashionOS\Database\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records and reads security-relevant actions.
 *
 * Tenant actions are stored with their tenant id. Deliberate platform-level
 * access is recorded with tenant id 0 so it never appears inside a tenant's
 * own audit view.
 */
final class AuditLogRepository {

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Table name.
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
		$this->table = Tables::audit_logs();
	}

	/**
	 * Append an audit entry.
	 *
	 * @param int                  $tenant_id   Tenant id, or 0 for platform actions.
	 * @param int                  $actor_id    WordPress user id performing the action.
	 * @param string               $action      Stable action name, e.g. "tenant.created".
	 * @param string               $entity_type Affected entity type.
	 * @param int                  $entity_id   Affected entity id.
	 * @param array<string, mixed> $metadata    Additional non-sensitive context.
	 */
	public function record( int $tenant_id, int $actor_id, string $action, string $entity_type = '', int $entity_id = 0, array $metadata = array() ): void {
		$this->db->insert(
			$this->table,
			array(
				'tenant_id'     => max( 0, $tenant_id ),
				'actor_id'      => max( 0, $actor_id ),
				'action'        => $action,
				'entity_type'   => $entity_type,
				'entity_id'     => max( 0, $entity_id ),
				'metadata_json' => array() === $metadata ? null : wp_json_encode( $metadata ),
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Recent entries for one tenant.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $tenant_id, int $limit = 50 ): array {
		$limit = max( 1, min( 200, $limit ) );
		$users = $this->db->users;

		$sql = "SELECT a.id, a.tenant_id, a.actor_id, a.action, a.entity_type, a.entity_id,
				a.metadata_json, a.created_at, u.display_name AS actor_name
			FROM {$this->table} a
			LEFT JOIN {$users} u ON u.ID = a.actor_id
			WHERE a.tenant_id = %d
			ORDER BY a.id DESC
			LIMIT %d";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Decode stored metadata into an array.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$metadata = json_decode( (string) ( $row['metadata_json'] ?? '{}' ), true );

		$row['metadata'] = is_array( $metadata ) ? $metadata : array();

		unset( $row['metadata_json'] );

		return $row;
	}
}
