<?php
/**
 * Measurement profile, version and value data access.
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
 * Reads and writes measurement profiles, versions and values.
 *
 * There is deliberately no update or delete method for a version or a value:
 * history is append-only by construction, not by convention. A correction is a
 * new version.
 */
final class MeasurementRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array(
		'tenant_id',
		'client_id',
		'template_id',
		'template_version',
		'profile_id',
		'version_id',
		'version_number',
		'taken_by',
		'created_by',
		'sort_order',
	);

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Profiles table.
	 *
	 * @var string
	 */
	private string $profiles;

	/**
	 * Versions table.
	 *
	 * @var string
	 */
	private string $versions;

	/**
	 * Values table.
	 *
	 * @var string
	 */
	private string $values;

	/**
	 * Templates table.
	 *
	 * @var string
	 */
	private string $templates;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional database handle (tests).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->db        = $db ?? $wpdb;
		$this->profiles  = Tables::measurement_profiles();
		$this->versions  = Tables::measurement_versions();
		$this->values    = Tables::measurement_values();
		$this->templates = Tables::measurement_templates();
	}

	/**
	 * Profiles of one customer, each with its version count and latest version.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $client_id Client id.
	 * @return array<int, array<string, mixed>>
	 */
	public function profiles_for_client( int $tenant_id, int $client_id ): array {
		if ( $tenant_id <= 0 || $client_id <= 0 ) {
			return array();
		}

		$sql = "SELECT p.*, t.name AS template_name
			FROM {$this->profiles} p
			LEFT JOIN {$this->templates} t ON t.id = p.template_id AND t.tenant_id = p.tenant_id
			WHERE p.tenant_id = %d AND p.client_id = %d
			ORDER BY p.name ASC, p.id ASC";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $client_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return $this->attach_latest_versions( $tenant_id, $rows );
	}

	/**
	 * Find one profile inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $profile_id Profile id.
	 * @return array<string, mixed>|null
	 */
	public function profile( int $tenant_id, int $profile_id ): ?array {
		if ( $tenant_id <= 0 || $profile_id <= 0 ) {
			return null;
		}

		$sql = "SELECT p.*, t.name AS template_name
			FROM {$this->profiles} p
			LEFT JOIN {$this->templates} t ON t.id = p.template_id AND t.tenant_id = p.tenant_id
			WHERE p.tenant_id = %d AND p.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a profile.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New profile id, or 0 on failure.
	 */
	public function create_profile( array $data ): int {
		$inserted = $this->db->insert( $this->profiles, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a profile inside a workspace.
	 *
	 * @param int                  $tenant_id  Tenant id.
	 * @param int                  $profile_id Profile id.
	 * @param array<string, mixed> $data       Changed columns.
	 */
	public function update_profile( int $tenant_id, int $profile_id, array $data ): bool {
		if ( $tenant_id <= 0 || $profile_id <= 0 || array() === $data ) {
			return false;
		}

		$updated = $this->db->update(
			$this->profiles,
			$data,
			array(
				'tenant_id' => $tenant_id,
				'id'        => $profile_id,
			),
			$this->formats( $data, self::INT_COLUMNS ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Every version of a profile, newest first.
	 *
	 * @param int $tenant_id  Tenant id.
	 * @param int $profile_id Profile id.
	 * @return array<int, array<string, mixed>>
	 */
	public function versions_for_profile( int $tenant_id, int $profile_id ): array {
		if ( $tenant_id <= 0 || $profile_id <= 0 ) {
			return array();
		}

		$users = $this->db->users;

		$sql = "SELECT v.id, v.profile_id, v.version_number, v.unit, v.taken_at, v.taken_by,
				v.template_id, v.template_version, v.notes, v.fit_notes, v.created_at,
				u.display_name AS taken_by_name,
				( SELECT COUNT(*) FROM {$this->values} mv WHERE mv.tenant_id = v.tenant_id AND mv.version_id = v.id ) AS value_count
			FROM {$this->versions} v
			LEFT JOIN {$users} u ON u.ID = v.taken_by
			WHERE v.tenant_id = %d AND v.profile_id = %d
			ORDER BY v.version_number DESC";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find one version inside a workspace.
	 *
	 * @param int $tenant_id  Tenant id.
	 * @param int $version_id Version id.
	 * @return array<string, mixed>|null
	 */
	public function version( int $tenant_id, int $version_id ): ?array {
		if ( $tenant_id <= 0 || $version_id <= 0 ) {
			return null;
		}

		$users = $this->db->users;

		$sql = "SELECT v.*, u.display_name AS taken_by_name, p.profile_name, p.client_id
			FROM {$this->versions} v
			INNER JOIN {$this->profiles} p ON p.id = v.profile_id AND p.tenant_id = v.tenant_id
			LEFT JOIN {$users} u ON u.ID = v.taken_by
			WHERE v.tenant_id = %d AND v.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $version_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Values of a version, in template order.
	 *
	 * @param int $tenant_id  Tenant id.
	 * @param int $version_id Version id.
	 * @return array<int, array<string, mixed>>
	 */
	public function values_for_version( int $tenant_id, int $version_id ): array {
		if ( $tenant_id <= 0 || $version_id <= 0 ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT measurement_key, label, value, unit, sort_order FROM {$this->values} WHERE tenant_id = %d AND version_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$version_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Highest version number recorded for a profile.
	 *
	 * @param int $tenant_id  Tenant id.
	 * @param int $profile_id Profile id.
	 */
	public function latest_version_number( int $tenant_id, int $profile_id ): int {
		if ( $tenant_id <= 0 || $profile_id <= 0 ) {
			return 0;
		}

		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT MAX(version_number) FROM {$this->versions} WHERE tenant_id = %d AND profile_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$profile_id
			)
		);
	}

	/**
	 * Insert a measurement version.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New version id, or 0 on failure.
	 */
	public function create_version( array $data ): int {
		$inserted = $this->db->insert( $this->versions, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Insert one measurement value.
	 *
	 * @param array<string, mixed> $data Column values.
	 */
	public function create_value( array $data ): bool {
		$inserted = $this->db->insert( $this->values, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false !== $inserted;
	}

	/**
	 * Attach the version count and latest version to profile rows.
	 *
	 * One extra query covers every profile instead of one per row.
	 *
	 * @param int                              $tenant_id Tenant id.
	 * @param array<int, array<string, mixed>> $profiles  Profile rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function attach_latest_versions( int $tenant_id, array $profiles ): array {
		$ids = array_map( static fn ( array $row ): int => (int) $row['id'], $profiles );
		$ids = array_values( array_filter( $ids ) );

		$latest = array();

		if ( array() !== $ids ) {
			$users        = $this->db->users;
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$sql = "SELECT v.profile_id, v.id, v.version_number, v.taken_at, u.display_name AS taken_by_name
				FROM {$this->versions} v
				LEFT JOIN {$users} u ON u.ID = v.taken_by
				WHERE v.tenant_id = %d AND v.profile_id IN ( {$placeholders} )
				ORDER BY v.profile_id ASC, v.version_number DESC";

			$rows = $this->db->get_results(
				$this->db->prepare( $sql, array_merge( array( $tenant_id ), $ids ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.
				ARRAY_A
			);

			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$profile_id = (int) $row['profile_id'];

				if ( ! isset( $latest[ $profile_id ] ) ) {
					$latest[ $profile_id ] = array(
						'id'            => (int) $row['id'],
						'versionNumber' => (int) $row['version_number'],
						'takenAt'       => (string) $row['taken_at'],
						'takenByName'   => (string) ( $row['taken_by_name'] ?? '' ),
						'versionCount'  => 0,
					);
				}

				++$latest[ $profile_id ]['versionCount'];
			}
		}

		foreach ( $profiles as $index => $profile ) {
			$profile_id = (int) $profile['id'];
			$summary    = $latest[ $profile_id ] ?? null;

			$profiles[ $index ]['version_count'] = null === $summary ? 0 : (int) $summary['versionCount'];
			$profiles[ $index ]['latest_version'] = null === $summary ? null : array(
				'id'            => (int) $summary['id'],
				'versionNumber' => (int) $summary['versionNumber'],
				'takenAt'       => (string) $summary['takenAt'],
				'takenByName'   => (string) $summary['takenByName'],
			);
		}

		return $profiles;
	}
}
