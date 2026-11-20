<?php
/**
 * System role permission re-seed.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database\Migrations;

use FashionOS\Database\Tables;
use FashionOS\Tenancy\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Re-seeds the system roles of every existing workspace from `Roles::defaults()`.
 *
 * Each phase introduces permission keys, and every workspace owns copies of the
 * system roles created by migration 0001 with that phase's fixed lists. A later
 * migration therefore has to write the current catalogue into those copies.
 * There is no role-editing UI, so nothing user-authored is overwritten, and the
 * owner's `*` wildcard is written back unchanged.
 */
final class RoleSync {

	/**
	 * Write the current permission catalogue into every system role.
	 *
	 * @param \wpdb $db Database handle.
	 */
	public static function reseed( \wpdb $db ): void {
		$roles   = Tables::roles();
		$tenants = $db->get_col( "SELECT DISTINCT tenant_id FROM {$roles}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.

		if ( ! is_array( $tenants ) ) {
			return;
		}

		foreach ( $tenants as $tenant_id ) {
			foreach ( Roles::defaults() as $slug => $definition ) {
				$db->query(
					$db->prepare(
						"UPDATE {$roles} SET permissions_json = %s WHERE tenant_id = %d AND slug = %s AND is_system = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
						wp_json_encode( array_values( $definition['permissions'] ) ),
						(int) $tenant_id,
						(string) $slug
					)
				);
			}
		}
	}
}
