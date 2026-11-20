<?php
/**
 * Customer and measurement schema.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database\Migrations;

use FashionOS\Database\Migration;
use FashionOS\Database\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the Phase 2 tables: customers, their preferences, measurement
 * templates, profiles, immutable versions and the values inside each version.
 *
 * `fos_client_files` is not created here: file handling is deferred until the
 * portal and media rules are designed (see docs/PHASE_2_PLAN.md).
 */
final class Migration0002ClientsMeasurements implements Migration {

	/**
	 * Migration version.
	 */
	public function version(): string {
		return '0002';
	}

	/**
	 * Migration description.
	 */
	public function description(): string {
		return 'Create the customer, measurement template, profile, version and value tables.';
	}

	/**
	 * Apply the schema.
	 *
	 * @param \wpdb $db Database handle.
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $db->get_charset_collate();

		$clients               = Tables::clients();
		$preferences           = Tables::client_preferences();
		$templates             = Tables::measurement_templates();
		$profiles              = Tables::measurement_profiles();
		$versions              = Tables::measurement_versions();
		$values                = Tables::measurement_values();

		$queries = array();

		$queries[] = "CREATE TABLE {$clients} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			uuid CHAR(36) NOT NULL,
			reference VARCHAR(30) NOT NULL DEFAULT '',
			name VARCHAR(191) NOT NULL,
			email VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(40) NOT NULL DEFAULT '',
			notes LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY tenant_reference (tenant_id,reference),
			KEY tenant_status_name (tenant_id,status,name),
			KEY tenant_email (tenant_id,email),
			KEY tenant_phone (tenant_id,phone)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$preferences} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			client_id BIGINT UNSIGNED NOT NULL,
			preferences_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY client (tenant_id,client_id)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$templates} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			uuid CHAR(36) NOT NULL,
			name VARCHAR(120) NOT NULL,
			garment_category VARCHAR(60) NOT NULL,
			unit CHAR(2) NOT NULL DEFAULT 'cm',
			schema_json LONGTEXT NOT NULL,
			schema_version INT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY tenant_status (tenant_id,status),
			KEY tenant_category (tenant_id,garment_category)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$profiles} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			client_id BIGINT UNSIGNED NOT NULL,
			template_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			uuid CHAR(36) NOT NULL,
			profile_name VARCHAR(120) NOT NULL,
			garment_category VARCHAR(60) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY tenant_client (tenant_id,client_id),
			KEY tenant_template (tenant_id,template_id),
			KEY tenant_status (tenant_id,status)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$versions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			profile_id BIGINT UNSIGNED NOT NULL,
			version_number INT UNSIGNED NOT NULL DEFAULT 1,
			template_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			template_version INT UNSIGNED NOT NULL DEFAULT 1,
			template_snapshot_json LONGTEXT NOT NULL,
			unit CHAR(2) NOT NULL DEFAULT 'cm',
			taken_at DATETIME NOT NULL,
			taken_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			notes TEXT NULL,
			fit_notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY profile_version (tenant_id,profile_id,version_number),
			KEY tenant_taken (tenant_id,taken_at),
			KEY taken_by (taken_by)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$values} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			version_id BIGINT UNSIGNED NOT NULL,
			measurement_key VARCHAR(60) NOT NULL,
			label VARCHAR(120) NOT NULL DEFAULT '',
			value DECIMAL(10,2) NOT NULL DEFAULT 0,
			unit CHAR(2) NOT NULL DEFAULT 'cm',
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY version_key (tenant_id,version_id,measurement_key),
			KEY version (tenant_id,version_id)
		) {$charset_collate};";

		dbDelta( $queries );

		RoleSync::reseed( $db );
	}
}
