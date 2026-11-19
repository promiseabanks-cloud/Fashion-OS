<?php
/**
 * Plugin bootstrap and service registration.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Core;

use FashionOS\API\Controllers\AuditController;
use FashionOS\API\Controllers\AuthController;
use FashionOS\API\Controllers\ClientController;
use FashionOS\API\Controllers\MeasurementController;
use FashionOS\API\Controllers\PlatformController;
use FashionOS\API\Controllers\SystemController;
use FashionOS\API\Controllers\TeamController;
use FashionOS\API\Controllers\TenantController;
use FashionOS\API\Responses\ErrorEnvelope;
use FashionOS\Clients\ClientService;
use FashionOS\Database\Migrations\Migrations;
use FashionOS\Database\Migrator;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\MeasurementRepository;
use FashionOS\Database\Repositories\MeasurementTemplateRepository;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Database\Transaction;
use FashionOS\Measurements\MeasurementService;
use FashionOS\Tenancy\TenantResolver;
use FashionOS\Tenancy\TenantService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin's services and WordPress hooks.
 *
 * The class stays limited to bootstrap concerns: repositories, services and
 * controllers are constructed once here and handed to WordPress hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether boot() has run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * REST controllers, registered on rest_api_init.
	 *
	 * @var array<int, object>
	 */
	private array $controllers = array();

	/**
	 * Private constructor; use instance().
	 */
	private function __construct() {}

	/**
	 * Retrieve the plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register services and hooks. Safe to call repeatedly.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		load_plugin_textdomain(
			'fashionos',
			false,
			dirname( plugin_basename( FASHIONOS_FILE ) ) . '/languages'
		);

		$this->maybe_upgrade();
		$this->register_services();

		( new AppShell( new Assets() ) )->register();

		ErrorEnvelope::register();

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Build the repositories, services and controllers for this request.
	 */
	private function register_services(): void {
		$tenants      = new TenantRepository();
		$roles        = new RoleRepository();
		$memberships  = new MembershipRepository();
		$audit        = new AuditLogRepository();
		$clients      = new ClientRepository();
		$templates    = new MeasurementTemplateRepository();
		$measurements = new MeasurementRepository();
		$transactions = new Transaction();

		$resolver            = new TenantResolver( $tenants, $memberships );
		$service             = new TenantService( $tenants, $roles, $memberships, $audit, $transactions );
		$client_service      = new ClientService( $clients, $audit, $transactions );
		$measurement_service = new MeasurementService( $clients, $templates, $measurements, $audit, $transactions );

		$this->controllers = array(
			new SystemController( new Migrator(), new Assets() ),
			new AuthController( $tenants, $memberships, $resolver ),
			new TenantController( $tenants, $roles, $memberships, $resolver, $service ),
			new TeamController( $memberships, $roles, $resolver, $service ),
			new ClientController( $client_service, $resolver ),
			new MeasurementController( $measurement_service, $resolver ),
			new AuditController( $audit, $resolver ),
			new PlatformController( $tenants, $audit ),
		);
	}

	/**
	 * Register REST routes for the fashionos/v1 namespace.
	 */
	public function register_rest_routes(): void {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Run pending migrations after an upgrade.
	 *
	 * Deployment uploads files without reactivating the plugin, so the stored
	 * database version is compared on every request; the check costs one
	 * autoloaded option read when nothing is pending.
	 */
	private function maybe_upgrade(): void {
		$migrator = new Migrator();

		if ( (string) get_option( 'fashionos_db_version', '' ) === $migrator->latest_version() ) {
			return;
		}

		$migrator->migrate();

		update_option( 'fashionos_version', FASHIONOS_VERSION );
	}

	/**
	 * Activation hook: install the schema and record the plugin version.
	 */
	public static function activate(): void {
		$migrator = new Migrator();
		$migrator->migrate();

		update_option( 'fashionos_version', FASHIONOS_VERSION );
		update_option( 'fashionos_db_version', Migrations::latest_version() );
		update_option( 'fashionos_activated_at', gmdate( 'c' ) );
	}

	/**
	 * Deactivation hook.
	 *
	 * Deactivation must never destroy tenant data, so this intentionally only
	 * clears scheduled work added in later phases.
	 */
	public static function deactivate(): void {
		// No scheduled events exist yet (Phase 7 introduces the queue).
	}
}
