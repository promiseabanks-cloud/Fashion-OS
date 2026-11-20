<?php
/**
 * Production and inventory schema.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database\Migrations;

use FashionOS\Database\Migration;
use FashionOS\Database\Tables;
use FashionOS\Production\Stages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the Phase 4 tables: production stages, jobs and tasks, the material
 * catalogue with allocations and stock movements, fittings, alterations and
 * quality checks.
 *
 * Production work hangs off `fos_order_items`, so each garment moves through
 * its own workflow without touching the rest of the order. Material
 * allocations record their supply source, which is what keeps customer-supplied
 * fabric out of designer-owned stock.
 */
final class Migration0004ProductionInventory implements Migration {

	/**
	 * Migration version.
	 */
	public function version(): string {
		return '0004';
	}

	/**
	 * Migration description.
	 */
	public function description(): string {
		return 'Create production stages, jobs, tasks, materials, allocations, stock movements, fittings, alterations and quality checks.';
	}

	/**
	 * Apply the schema.
	 *
	 * @param \wpdb $db Database handle.
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $db->get_charset_collate();

		$stages      = Tables::production_stages();
		$jobs        = Tables::production_jobs();
		$tasks       = Tables::production_tasks();
		$materials   = Tables::materials();
		$allocations = Tables::material_allocations();
		$movements   = Tables::material_movements();
		$fittings    = Tables::fittings();
		$alterations = Tables::alterations();
		$checks      = Tables::quality_checks();

		$queries = array();

		$queries[] = "CREATE TABLE {$stages} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(80) NOT NULL,
			slug VARCHAR(80) NOT NULL,
			sequence INT UNSIGNED NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_slug (tenant_id,slug),
			KEY tenant_order (tenant_id,is_active,sequence)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$jobs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			assigned_to BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			start_date CHAR(10) NOT NULL DEFAULT '',
			due_date CHAR(10) NOT NULL DEFAULT '',
			notes TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_item (tenant_id,order_item_id),
			KEY tenant_status (tenant_id,status),
			KEY tenant_assignee (tenant_id,assigned_to),
			KEY tenant_due (tenant_id,due_date)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$tasks} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			job_id BIGINT UNSIGNED NOT NULL,
			stage_id BIGINT UNSIGNED NOT NULL,
			assigned_to BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			due_date CHAR(10) NOT NULL DEFAULT '',
			completed_at CHAR(19) NOT NULL DEFAULT '',
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_job (tenant_id,job_id),
			KEY tenant_stage (tenant_id,stage_id),
			KEY tenant_assignee (tenant_id,assigned_to),
			KEY tenant_status (tenant_id,status)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$materials} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(120) NOT NULL,
			material_type VARCHAR(30) NOT NULL DEFAULT 'fabric',
			unit VARCHAR(20) NOT NULL DEFAULT 'metre',
			quantity_on_hand DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			notes TEXT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_type (tenant_id,material_type),
			KEY tenant_active (tenant_id,is_active)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$allocations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			material_id BIGINT UNSIGNED NOT NULL,
			quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			source VARCHAR(20) NOT NULL DEFAULT 'designer_supplied',
			notes TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_item (tenant_id,order_item_id),
			KEY tenant_material (tenant_id,material_id)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$movements} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			material_id BIGINT UNSIGNED NOT NULL,
			movement_type VARCHAR(20) NOT NULL,
			quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			reference_type VARCHAR(20) NOT NULL DEFAULT '',
			reference_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			note VARCHAR(191) NOT NULL DEFAULT '',
			actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_material (tenant_id,material_id,id)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$fittings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			assigned_to BIGINT UNSIGNED NOT NULL DEFAULT 0,
			scheduled_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
			location VARCHAR(191) NOT NULL DEFAULT '',
			notes TEXT NULL,
			outcome TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_item (tenant_id,order_item_id),
			KEY tenant_scheduled (tenant_id,scheduled_at),
			KEY tenant_status (tenant_id,status)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$alterations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			fitting_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			description TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			due_date CHAR(10) NOT NULL DEFAULT '',
			completed_at CHAR(19) NOT NULL DEFAULT '',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_item (tenant_id,order_item_id),
			KEY tenant_fitting (tenant_id,fitting_id),
			KEY tenant_status (tenant_id,status)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$checks} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			checked_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			result VARCHAR(20) NOT NULL DEFAULT 'passed',
			notes TEXT NULL,
			checked_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_item (tenant_id,order_item_id,id),
			KEY tenant_result (tenant_id,result)
		) {$charset_collate};";

		dbDelta( $queries );

		$this->seed_stages( $db );
		RoleSync::reseed( $db );
	}

	/**
	 * Give every existing workspace the starter workflow.
	 *
	 * Workspaces created later are seeded the first time they ask for their
	 * stages, so both paths end with the same catalogue.
	 *
	 * @param \wpdb $db Database handle.
	 */
	private function seed_stages( \wpdb $db ): void {
		$stages  = Tables::production_stages();
		$tenants = $db->get_col( "SELECT DISTINCT tenant_id FROM " . Tables::tenants() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name comes from $wpdb->prefix.
		$now     = gmdate( 'Y-m-d H:i:s' );

		if ( ! is_array( $tenants ) ) {
			return;
		}

		foreach ( $tenants as $tenant_id ) {
			$existing = (int) $db->get_var(
				$db->prepare( "SELECT COUNT(*) FROM {$stages} WHERE tenant_id = %d", (int) $tenant_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
			);

			if ( $existing > 0 ) {
				continue;
			}

			foreach ( Stages::defaults() as $stage ) {
				$db->insert(
					$stages,
					array(
						'tenant_id'  => (int) $tenant_id,
						'name'       => (string) $stage['name'],
						'slug'       => (string) $stage['slug'],
						'sequence'   => (int) $stage['sequence'],
						'is_active'  => 1,
						'created_at' => $now,
						'updated_at' => $now,
					),
					array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
				);
			}
		}
	}
}
