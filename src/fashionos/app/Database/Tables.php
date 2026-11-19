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
	 * Configured WordPress table prefix.
	 */
	private static function prefix(): string {
		global $wpdb;

		return (string) $wpdb->prefix;
	}
}
