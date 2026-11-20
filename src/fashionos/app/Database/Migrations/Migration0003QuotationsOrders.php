<?php
/**
 * Design, quotation and order schema.
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
 * Creates the Phase 3 tables: designs, quotations and their line items, orders,
 * the garments inside an order, and the order activity timeline.
 *
 * Each garment row carries its own `measurement_version_id` and `design_id`.
 * Because measurement versions are append-only, the measurements a garment was
 * cut from can never change underneath the reference.
 */
final class Migration0003QuotationsOrders implements Migration {

	/**
	 * Migration version.
	 */
	public function version(): string {
		return '0003';
	}

	/**
	 * Migration description.
	 */
	public function description(): string {
		return 'Create the design, quotation, quotation item, order, garment and order event tables.';
	}

	/**
	 * Apply the schema.
	 *
	 * @param \wpdb $db Database handle.
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $db->get_charset_collate();

		$designs     = Tables::designs();
		$quotations  = Tables::quotations();
		$quote_items = Tables::quotation_items();
		$orders      = Tables::orders();
		$order_items = Tables::order_items();
		$events      = Tables::order_events();

		$queries = array();

		$queries[] = "CREATE TABLE {$designs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			uuid CHAR(36) NOT NULL,
			reference VARCHAR(30) NOT NULL DEFAULT '',
			client_id BIGINT UNSIGNED NOT NULL,
			measurement_profile_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			measurement_version_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(120) NOT NULL,
			garment_category VARCHAR(60) NOT NULL DEFAULT '',
			brief TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY tenant_reference (tenant_id,reference),
			KEY tenant_client (tenant_id,client_id),
			KEY tenant_status (tenant_id,status),
			KEY tenant_version (tenant_id,measurement_version_id)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$quotations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			uuid CHAR(36) NOT NULL,
			reference VARCHAR(30) NOT NULL DEFAULT '',
			client_id BIGINT UNSIGNED NOT NULL,
			design_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			currency CHAR(3) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			valid_until CHAR(10) NOT NULL DEFAULT '',
			notes TEXT NULL,
			subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY tenant_reference (tenant_id,reference),
			KEY tenant_client (tenant_id,client_id),
			KEY tenant_status (tenant_id,status)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$quote_items} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			quotation_id BIGINT UNSIGNED NOT NULL,
			description VARCHAR(191) NOT NULL,
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY quotation (tenant_id,quotation_id)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$orders} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			uuid CHAR(36) NOT NULL,
			reference VARCHAR(30) NOT NULL DEFAULT '',
			client_id BIGINT UNSIGNED NOT NULL,
			quotation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			currency CHAR(3) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			due_date CHAR(10) NOT NULL DEFAULT '',
			notes TEXT NULL,
			total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY tenant_reference (tenant_id,reference),
			KEY tenant_client (tenant_id,client_id),
			KEY tenant_status (tenant_id,status),
			KEY tenant_due (tenant_id,due_date)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$order_items} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL,
			quotation_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			design_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			measurement_profile_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			measurement_version_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			garment_name VARCHAR(120) NOT NULL,
			garment_category VARCHAR(60) NOT NULL DEFAULT '',
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			notes TEXT NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_order (tenant_id,order_id),
			KEY tenant_version (tenant_id,measurement_version_id),
			KEY tenant_status (tenant_id,status)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			event_type VARCHAR(40) NOT NULL,
			message TEXT NULL,
			actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_order (tenant_id,order_id,id)
		) {$charset_collate};";

		dbDelta( $queries );

		RoleSync::reseed( $db );
	}
}
