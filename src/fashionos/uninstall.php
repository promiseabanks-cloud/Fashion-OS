<?php
/**
 * Uninstall routine for FashionOS.
 *
 * Business data is intentionally retained on uninstall: workspaces may hold
 * financial and measurement records that must stay traceable until the tenant
 * requests deletion through the documented data-retention process. Only
 * plugin-owned options are removed here.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'fashionos_version' );
delete_option( 'fashionos_db_version' );
delete_option( 'fashionos_activated_at' );
