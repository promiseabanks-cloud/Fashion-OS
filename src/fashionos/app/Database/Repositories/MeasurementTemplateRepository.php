<?php
/**
 * Measurement template data access.
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
 * Reads and writes measurement templates.
 *
 * A template is the definition of which fields a garment category needs. It is
 * editable, which is exactly why every recorded measurement version keeps its
 * own copy of the template schema.
 */
final class MeasurementTemplateRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array( 'tenant_id', 'created_by', 'schema_version' );

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
		$this->table = Tables::measurement_templates();
	}

	/**
	 * Templates of a workspace, newest first.
	 *
	 * @param int $tenant_id Tenant id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_tenant( int $tenant_id ): array {
		if ( $tenant_id <= 0 ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE tenant_id = %d ORDER BY status ASC, name ASC", $tenant_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
			ARRAY_A
		);

		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Find one template inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Template id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE tenant_id = %d AND id = %d LIMIT 1", $tenant_id, $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Insert a template.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New template id, or 0 on failure.
	 */
	public function create( array $data ): int {
		$inserted = $this->db->insert( $this->table, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a template inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Template id.
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
	 * Decode the stored field schema.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$schema = json_decode( (string) ( $row['schema_json'] ?? '{}' ), true );
		$fields = is_array( $schema ) && isset( $schema['fields'] ) && is_array( $schema['fields'] ) ? $schema['fields'] : array();

		$row['fields'] = array_values( array_filter( $fields, 'is_array' ) );

		unset( $row['schema_json'] );

		return $row;
	}
}
