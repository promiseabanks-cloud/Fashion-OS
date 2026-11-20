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
	 * See designs and their measurement references.
	 */
	public const DESIGNS_VIEW = 'designs.view';

	/**
	 * Create, edit and archive designs.
	 */
	public const DESIGNS_MANAGE = 'designs.manage';

	/**
	 * See quotations and their line items.
	 */
	public const QUOTATIONS_VIEW = 'quotations.view';

	/**
	 * Create and edit quotations.
	 */
	public const QUOTATIONS_MANAGE = 'quotations.manage';

	/**
	 * See orders, garments and their activity history.
	 */
	public const ORDERS_VIEW = 'orders.view';

	/**
	 * Create orders and move garments through production.
	 */
	public const ORDERS_MANAGE = 'orders.manage';

	/**
	 * See production stages, jobs and the production dashboard.
	 */
	public const PRODUCTION_VIEW = 'production.view';

	/**
	 * Configure stages and manage jobs and tasks.
	 */
	public const PRODUCTION_MANAGE = 'production.manage';

	/**
	 * See the material catalogue, stock and allocations.
	 */
	public const MATERIALS_VIEW = 'materials.view';

	/**
	 * Manage materials, stock movements and garment allocations.
	 */
	public const MATERIALS_MANAGE = 'materials.manage';

	/**
	 * See fittings and alterations.
	 */
	public const FITTINGS_VIEW = 'fittings.view';

	/**
	 * Schedule fittings and manage alterations.
	 */
	public const FITTINGS_MANAGE = 'fittings.manage';

	/**
	 * See quality-control records.
	 */
	public const QUALITY_VIEW = 'quality.view';

	/**
	 * Record quality-control results.
	 */
	public const QUALITY_MANAGE = 'quality.manage';

	/**
	 * See invoices, balances and the finance summary.
	 */
	public const INVOICES_VIEW = 'invoices.view';

	/**
	 * Create, issue and void invoices.
	 */
	public const INVOICES_MANAGE = 'invoices.manage';

	/**
	 * See payments recorded against invoices.
	 */
	public const PAYMENTS_VIEW = 'payments.view';

	/**
	 * Record and verify payments.
	 */
	public const PAYMENTS_MANAGE = 'payments.manage';

	/**
	 * See studio expenses.
	 */
	public const EXPENSES_VIEW = 'expenses.view';

	/**
	 * Record and void expenses.
	 */
	public const EXPENSES_MANAGE = 'expenses.manage';

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
			self::DESIGNS_VIEW,
			self::DESIGNS_MANAGE,
			self::QUOTATIONS_VIEW,
			self::QUOTATIONS_MANAGE,
			self::ORDERS_VIEW,
			self::ORDERS_MANAGE,
			self::PRODUCTION_VIEW,
			self::PRODUCTION_MANAGE,
			self::MATERIALS_VIEW,
			self::MATERIALS_MANAGE,
			self::FITTINGS_VIEW,
			self::FITTINGS_MANAGE,
			self::QUALITY_VIEW,
			self::QUALITY_MANAGE,
			self::INVOICES_VIEW,
			self::INVOICES_MANAGE,
			self::PAYMENTS_VIEW,
			self::PAYMENTS_MANAGE,
			self::EXPENSES_VIEW,
			self::EXPENSES_MANAGE,
		);
	}
}
