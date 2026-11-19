<?php
/**
 * Tenancy schema.
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
 * Creates the platform tables Phase 1 needs: workspaces, their roles,
 * memberships and the audit trail.
 *
 * Plans and subscriptions belong to Phase 8 (SaaS administration) and tenant
 * settings arrive with the settings module; neither is created here.
 */
final class Migration0001Tenancy implements Migration {

	/**
	 * Migration version.
	 */
	public function version(): string {
		return '0001';
	}

	/**
	 * Migration description.
	 */
	public function description(): string {
		return 'Create the tenancy, role, membership and audit log tables.';
	}

	/**
	 * Apply the schema.
	 *
	 * @param \wpdb $db Database handle.
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $db->get_charset_collate();

		$tenants    = Tables::tenants();
		$roles      = Tables::roles();
		$memberships = Tables::memberships();
		$audit_logs = Tables::audit_logs();

		$queries = array();

		$queries[] = "CREATE TABLE {$tenants} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(100) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
			currency CHAR(3) NOT NULL DEFAULT 'NGN',
			logo_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$roles} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			slug VARCHAR(50) NOT NULL,
			name VARCHAR(100) NOT NULL,
			permissions_json LONGTEXT NULL,
			is_system TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_slug (tenant_id,slug),
			KEY tenant_id (tenant_id)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$memberships} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			role_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_user (tenant_id,user_id),
			KEY user_id (user_id),
			KEY tenant_status (tenant_id,status)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$audit_logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(100) NOT NULL DEFAULT '',
			entity_type VARCHAR(50) NOT NULL DEFAULT '',
			entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			metadata_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_created (tenant_id,created_at),
			KEY actor_id (actor_id),
			KEY action (action)
		) {$charset_collate};";

		dbDelta( $queries );
	}
}
