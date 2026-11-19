<?php
/**
 * Permission catalogue.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Tenancy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every permission the platform understands today.
 *
 * Permissions are namespaced by module and checked on the server only. Later
 * modules add their own keys here; roles store the keys they grant, and the
 * wildcard ["*"] means "all current and future permissions".
 */
final class Permissions {

	/**
	 * See the workspace and its basic profile.
	 */
	public const WORKSPACE_VIEW = 'workspace.view';

	/**
	 * Change workspace name, timezone and currency.
	 */
	public const WORKSPACE_MANAGE = 'workspace.manage';

	/**
	 * See the team list.
	 */
	public const TEAM_VIEW = 'team.view';

	/**
	 * Add, re-role and remove team members.
	 */
	public const TEAM_MANAGE = 'team.manage';

	/**
	 * Read the workspace audit trail.
	 */
	public const AUDIT_VIEW = 'audit.view';

	/**
	 * See customer profiles, contact details and preferences.
	 */
	public const CLIENTS_VIEW = 'clients.view';

	/**
	 * Create, edit and archive customers.
	 */
	public const CLIENTS_MANAGE = 'clients.manage';

	/**
	 * See measurement templates, profiles and recorded versions.
	 */
	public const MEASUREMENTS_VIEW = 'measurements.view';

	/**
	 * Manage templates, profiles and recorded measurement versions.
	 */
	public const MEASUREMENTS_MANAGE = 'measurements.manage';

	/**
	 * All permission keys.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::WORKSPACE_VIEW,
			self::WORKSPACE_MANAGE,
			self::TEAM_VIEW,
			self::TEAM_MANAGE,
			self::AUDIT_VIEW,
			self::CLIENTS_VIEW,
			self::CLIENTS_MANAGE,
			self::MEASUREMENTS_VIEW,
			self::MEASUREMENTS_MANAGE,
		);
	}
}
