<?php
/**
 * Custom table names.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the plugin's custom table names from the WordPress table prefix.
 *
 * Names are always derived from `$wpdb->prefix`; `wp_` is never hardcoded.
 */
final class Tables {

	/**
	 * Business workspaces.
	 */
	public static function tenants(): string {
		return self::prefix() . 'fos_tenants';
	}

	/**
	 * Tenant-specific roles.
	 */
	public static function roles(): string {
		return self::prefix() . 'fos_roles';
	}

	/**
	 * User-to-tenant memberships.
	 */
	public static function memberships(): string {
		return self::prefix() . 'fos_memberships';
	}

	/**
	 * Audit trail.
	 */
	public static function audit_logs(): string {
		return self::prefix() . 'fos_audit_logs';
	}

	/**
	 * Customer profiles.
	 */
	public static function clients(): string {
		return self::prefix() . 'fos_clients';
	}

	/**
	 * Customer style and communication preferences.
	 */
	public static function client_preferences(): string {
		return self::prefix() . 'fos_client_preferences';
	}

	/**
	 * Configurable measurement templates.
	 */
	public static function measurement_templates(): string {
		return self::prefix() . 'fos_measurement_templates';
	}

	/**
	 * Measurement profiles per customer and garment category.
	 */
	public static function measurement_profiles(): string {
		return self::prefix() . 'fos_measurement_profiles';
	}

	/**
	 * Immutable measurement snapshots.
	 */
	public static function measurement_versions(): string {
		return self::prefix() . 'fos_measurement_versions';
	}

	/**
	 * Individual values belonging to a measurement version.
	 */
	public static function measurement_values(): string {
		return self::prefix() . 'fos_measurement_values';
	}

	/**
	 * Design records and their references.
	 */
	public static function designs(): string {
		return self::prefix() . 'fos_designs';
	}

	/**
	 * Quotation headers.
	 */
	public static function quotations(): string {
		return self::prefix() . 'fos_quotations';
	}

	/**
	 * Priced lines inside a quotation.
	 */
	public static function quotation_items(): string {
		return self::prefix() . 'fos_quotation_items';
	}

	/**
	 * Order headers.
	 */
	public static function orders(): string {
		return self::prefix() . 'fos_orders';
	}

	/**
	 * Independently tracked garments inside an order.
	 */
	public static function order_items(): string {
		return self::prefix() . 'fos_order_items';
	}

	/**
	 * Order activity timeline.
	 */
	public static function order_events(): string {
		return self::prefix() . 'fos_order_events';
	}

	/**
	 * Configurable production workflow stages.
	 */
	public static function production_stages(): string {
		return self::prefix() . 'fos_production_stages';
	}

	/**
	 * Production jobs, one per garment.
	 */
	public static function production_jobs(): string {
		return self::prefix() . 'fos_production_jobs';
	}

	/**
	 * Tasks inside a production job.
	 */
	public static function production_tasks(): string {
		return self::prefix() . 'fos_production_tasks';
	}

	/**
	 * Fabric, accessory and consumable catalogue.
	 */
	public static function materials(): string {
		return self::prefix() . 'fos_materials';
	}

	/**
	 * Material allocated to a garment.
	 */
	public static function material_allocations(): string {
		return self::prefix() . 'fos_material_allocations';
	}

	/**
	 * Stock movement history.
	 */
	public static function material_movements(): string {
		return self::prefix() . 'fos_material_movements';
	}

	/**
	 * Fitting appointments, garment level.
	 */
	public static function fittings(): string {
		return self::prefix() . 'fos_fittings';
	}

	/**
	 * Alteration records raised from a fitting or a garment.
	 */
	public static function alterations(): string {
		return self::prefix() . 'fos_alterations';
	}

	/**
	 * Quality-control records per garment.
	 */
	public static function quality_checks(): string {
		return self::prefix() . 'fos_quality_checks';
	}

	/**
	 * Invoices issued to customers.
	 */
	public static function invoices(): string {
		return self::prefix() . 'fos_invoices';
	}

	/**
	 * Priced lines inside an invoice.
	 */
	public static function invoice_items(): string {
		return self::prefix() . 'fos_invoice_items';
	}

	/**
	 * Payments recorded against an invoice.
	 */
	public static function payments(): string {
		return self::prefix() . 'fos_payments';
	}

	/**
	 * Studio expenses and their categories.
	 */
	public static function expenses(): string {
		return self::prefix() . 'fos_expenses';
	}

	/**
	 * Customer portal access links.
	 */
	public static function portal_access(): string {
		return self::prefix() . 'fos_portal_access';
	}

	/**
	 * Configured WordPress table prefix.
	 */
	private static function prefix(): string {
		global $wpdb;

		return (string) $wpdb->prefix;
	}
}
