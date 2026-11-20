<?php
/**
 * Finance schema.
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
 * Creates the Phase 5 tables: invoices and their lines, payments recorded
 * against an invoice, and studio expenses.
 *
 * An invoice states what a customer owes; payments are separate rows so a
 * partially paid invoice has a history rather than a rewritten total. Only
 * verified payments count towards the balance, which is why the idempotency
 * key is unique per workspace: a repeated callback cannot record the same
 * money twice.
 */
final class Migration0005Finance implements Migration {

	/**
	 * Migration version.
	 */
	public function version(): string {
		return '0005';
	}

	/**
	 * Migration description.
	 */
	public function description(): string {
		return 'Create invoices, invoice lines, payments and expenses.';
	}

	/**
	 * Apply the schema.
	 *
	 * @param \wpdb $db Database handle.
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $db->get_charset_collate();

		$invoices      = Tables::invoices();
		$invoice_items = Tables::invoice_items();
		$payments      = Tables::payments();
		$expenses      = Tables::expenses();

		$queries = array();

		$queries[] = "CREATE TABLE {$invoices} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			uuid VARCHAR(36) NOT NULL,
			reference VARCHAR(20) NOT NULL,
			client_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			currency VARCHAR(3) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			issue_date CHAR(10) NOT NULL DEFAULT '',
			due_date CHAR(10) NOT NULL DEFAULT '',
			subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			notes TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY tenant_reference (tenant_id,reference),
			KEY tenant_client (tenant_id,client_id),
			KEY tenant_status (tenant_id,status),
			KEY tenant_due (tenant_id,due_date),
			KEY tenant_order (tenant_id,order_id)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$invoice_items} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			invoice_id BIGINT UNSIGNED NOT NULL,
			description VARCHAR(191) NOT NULL,
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY tenant_invoice (tenant_id,invoice_id)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$payments} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			uuid VARCHAR(36) NOT NULL,
			reference VARCHAR(20) NOT NULL,
			invoice_id BIGINT UNSIGNED NOT NULL,
			client_id BIGINT UNSIGNED NOT NULL,
			amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			method VARCHAR(20) NOT NULL DEFAULT 'transfer',
			provider VARCHAR(40) NOT NULL DEFAULT '',
			provider_reference VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			idempotency_key VARCHAR(191) NOT NULL,
			paid_at DATETIME NOT NULL,
			verified_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			verified_at CHAR(19) NOT NULL DEFAULT '',
			notes TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY tenant_reference (tenant_id,reference),
			UNIQUE KEY tenant_idempotency (tenant_id,idempotency_key),
			KEY tenant_invoice (tenant_id,invoice_id,id),
			KEY tenant_status (tenant_id,status)
		) {$charset_collate};";

		$queries[] = "CREATE TABLE {$expenses} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			uuid VARCHAR(36) NOT NULL,
			reference VARCHAR(20) NOT NULL,
			category VARCHAR(30) NOT NULL DEFAULT 'other',
			description VARCHAR(191) NOT NULL,
			amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			currency VARCHAR(3) NOT NULL DEFAULT '',
			spent_at CHAR(10) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'recorded',
			notes TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY tenant_reference (tenant_id,reference),
			KEY tenant_category (tenant_id,category),
			KEY tenant_spent (tenant_id,spent_at)
		) {$charset_collate};";

		dbDelta( $queries );

		RoleSync::reseed( $db );
	}
}
