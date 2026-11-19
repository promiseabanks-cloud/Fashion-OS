<?php
/**
 * Authorization policies.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Security;

use FashionOS\Tenancy\TenantContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side authorization decisions.
 *
 * Controllers never decide access themselves: they call these helpers, and the
 * same helpers are used by REST permission callbacks.
 */
final class Policy {

	/**
	 * Whether the resolved membership grants a permission.
	 *
	 * @param TenantContext $context    Resolved workspace context.
	 * @param string        $permission Permission key from the catalogue.
	 */
	public static function allows( TenantContext $context, string $permission ): bool {
		return $context->can( $permission );
	}

	/**
	 * Whether the current WordPress user is a platform administrator.
	 *
	 * Platform administration is separate from tenant membership and is audited
	 * wherever it is used.
	 */
	public static function is_platform_admin(): bool {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}
}
