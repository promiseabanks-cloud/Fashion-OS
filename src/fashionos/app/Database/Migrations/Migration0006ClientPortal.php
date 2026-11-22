<?php
/**
 * Client portal schema.
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
 * Creates the Phase 6 table: portal access links.
 *
 * A customer is not a WordPress user, so the portal authenticates with a link
 * the studio issues. Only the SHA-256 hash of the token is stored, the hash is
 * unique so a request carrying just its token can find its workspace, and
 * revocation is a timestamp rather than a delete so the audit trail still has a
 * row to point at.
 */
final class Migration0006ClientPortal implements Migration {

	/**
	 * Migration version.
	 */
	public function version(): string {
		return '0006';
	}

	/**
	 * Migration description.
	 */
	public function description(): string {
		return 'Create customer portal access links.';
	}

	/**
	 * Apply the schema.
	 *
	 * @param \wpdb $db Database handle.
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $db->get_charset_collate();
		$portal_access   = Tables::portal_access();

		$queries = array();

		$queries[] = "CREATE TABLE {$portal_access} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id BIGINT UNSIGNED NOT NULL,
			uuid VARCHAR(36) NOT NULL,
			client_id BIGINT UNSIGNED NOT NULL,
			token_hash CHAR(64) NOT NULL,
			label VARCHAR(100) NOT NULL DEFAULT '',
			expires_at CHAR(19) NOT NULL DEFAULT '',
			revoked_at CHAR(19) NOT NULL DEFAULT '',
			last_used_at CHAR(19) NOT NULL DEFAULT '',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY token_hash (token_hash),
			KEY tenant_client (tenant_id,client_id)
		) {$charset_collate};";

		dbDelta( $queries );
	}
}
