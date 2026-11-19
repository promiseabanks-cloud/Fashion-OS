<?php
/**
 * Tenant response shape.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Tenancy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps a tenant row to the API's public field names.
 *
 * Internal columns (created_by, logo storage details) never reach the client.
 */
final class TenantPresenter {

	/**
	 * Public tenant fields.
	 *
	 * @param array<string, mixed> $tenant Tenant row.
	 * @return array<string, mixed>
	 */
	public static function to_array( array $tenant ): array {
		return array(
			'id'        => (int) ( $tenant['id'] ?? 0 ),
			'uuid'      => (string) ( $tenant['uuid'] ?? '' ),
			'name'      => (string) ( $tenant['name'] ?? '' ),
			'slug'      => (string) ( $tenant['slug'] ?? '' ),
			'status'    => (string) ( $tenant['status'] ?? '' ),
			'timezone'  => (string) ( $tenant['timezone'] ?? '' ),
			'currency'  => (string) ( $tenant['currency'] ?? '' ),
			'createdAt' => (string) ( $tenant['created_at'] ?? '' ),
		);
	}
}
