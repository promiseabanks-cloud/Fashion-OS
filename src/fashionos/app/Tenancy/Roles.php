<?php
/**
 * Default tenant roles.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Tenancy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Roles created for every new workspace.
 *
 * Role names follow the product's target users. Each workspace owns its copies,
 * so permissions can differ between workspaces for the same person. The owner
 * role holds the wildcard permission and cannot be assigned through the team
 * API; the creator of the workspace becomes its owner.
 */
final class Roles {

	/**
	 * Workspace owner.
	 */
	public const OWNER = 'owner';

	/**
	 * Day-to-day manager.
	 */
	public const MANAGER = 'manager';

	/**
	 * Designer handling briefs, measurements and garment specifications.
	 */
	public const DESIGNER = 'designer';

	/**
	 * Tailor or production staff member.
	 */
	public const TAILOR = 'tailor';

	/**
	 * Finance staff member.
	 */
	public const FINANCE = 'finance';

	/**
	 * Default role definitions keyed by slug.
	 *
	 * @return array<string, array{name: string, permissions: string[]}>
	 */
	public static function defaults(): array {
		return array(
			self::OWNER    => array(
				'name'        => __( 'Owner', 'fashionos' ),
				'permissions' => array( '*' ),
			),
			self::MANAGER  => array(
				'name'        => __( 'Manager', 'fashionos' ),
				'permissions' => array(
					Permissions::WORKSPACE_VIEW,
					Permissions::WORKSPACE_MANAGE,
					Permissions::TEAM_VIEW,
					Permissions::TEAM_MANAGE,
					Permissions::AUDIT_VIEW,
					Permissions::CLIENTS_VIEW,
					Permissions::CLIENTS_MANAGE,
					Permissions::MEASUREMENTS_VIEW,
					Permissions::MEASUREMENTS_MANAGE,
					Permissions::DESIGNS_VIEW,
					Permissions::DESIGNS_MANAGE,
					Permissions::QUOTATIONS_VIEW,
					Permissions::QUOTATIONS_MANAGE,
					Permissions::ORDERS_VIEW,
					Permissions::ORDERS_MANAGE,
					Permissions::PRODUCTION_VIEW,
					Permissions::PRODUCTION_MANAGE,
					Permissions::MATERIALS_VIEW,
					Permissions::MATERIALS_MANAGE,
					Permissions::FITTINGS_VIEW,
					Permissions::FITTINGS_MANAGE,
					Permissions::QUALITY_VIEW,
					Permissions::QUALITY_MANAGE,
				),
			),
			self::DESIGNER => array(
				'name'        => __( 'Designer', 'fashionos' ),
				'permissions' => array(
					Permissions::WORKSPACE_VIEW,
					Permissions::TEAM_VIEW,
					Permissions::CLIENTS_VIEW,
					Permissions::CLIENTS_MANAGE,
					Permissions::MEASUREMENTS_VIEW,
					Permissions::MEASUREMENTS_MANAGE,
					Permissions::DESIGNS_VIEW,
					Permissions::DESIGNS_MANAGE,
					Permissions::QUOTATIONS_VIEW,
					Permissions::QUOTATIONS_MANAGE,
					Permissions::ORDERS_VIEW,
					Permissions::ORDERS_MANAGE,
					Permissions::PRODUCTION_VIEW,
					Permissions::PRODUCTION_MANAGE,
					Permissions::MATERIALS_VIEW,
					Permissions::MATERIALS_MANAGE,
					Permissions::FITTINGS_VIEW,
					Permissions::FITTINGS_MANAGE,
					Permissions::QUALITY_VIEW,
					Permissions::QUALITY_MANAGE,
				),
			),
			self::TAILOR   => array(
				'name'        => __( 'Tailor / production', 'fashionos' ),
				'permissions' => array(
					Permissions::WORKSPACE_VIEW,
					Permissions::CLIENTS_VIEW,
					Permissions::MEASUREMENTS_VIEW,
					Permissions::DESIGNS_VIEW,
					Permissions::ORDERS_VIEW,
					Permissions::PRODUCTION_VIEW,
					Permissions::PRODUCTION_MANAGE,
					Permissions::MATERIALS_VIEW,
					Permissions::MATERIALS_MANAGE,
					Permissions::FITTINGS_VIEW,
					Permissions::FITTINGS_MANAGE,
					Permissions::QUALITY_VIEW,
					Permissions::QUALITY_MANAGE,
				),
			),
			self::FINANCE  => array(
				'name'        => __( 'Finance', 'fashionos' ),
				'permissions' => array(
					Permissions::WORKSPACE_VIEW,
					Permissions::CLIENTS_VIEW,
					Permissions::QUOTATIONS_VIEW,
					Permissions::ORDERS_VIEW,
					Permissions::PRODUCTION_VIEW,
				),
			),
		);
	}

	/**
	 * Roles that may be assigned to an additional team member.
	 *
	 * @return array<string, array{name: string, permissions: string[]}>
	 */
	public static function assignable(): array {
		$roles = self::defaults();

		unset( $roles[ self::OWNER ] );

		return $roles;
	}
}
