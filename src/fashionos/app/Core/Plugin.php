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
use FashionOS\API\Controllers\DesignController;
use FashionOS\API\Controllers\ExpenseController;
use FashionOS\API\Controllers\FinanceController;
use FashionOS\API\Controllers\FittingController;
use FashionOS\API\Controllers\InvoiceController;
use FashionOS\API\Controllers\MaterialController;
use FashionOS\API\Controllers\MeasurementController;
use FashionOS\API\Controllers\OrderController;
use FashionOS\API\Controllers\PaymentController;
use FashionOS\API\Controllers\ProductionController;
use FashionOS\API\Controllers\QualityController;
use FashionOS\API\Controllers\QuotationController;
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
use FashionOS\Database\Repositories\DesignRepository;
use FashionOS\Database\Repositories\ExpenseRepository;
use FashionOS\Database\Repositories\FittingRepository;
use FashionOS\Database\Repositories\InvoiceRepository;
use FashionOS\Database\Repositories\MaterialRepository;
use FashionOS\Database\Repositories\MeasurementRepository;
use FashionOS\Database\Repositories\MeasurementTemplateRepository;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\PaymentRepository;
use FashionOS\Database\Repositories\ProductionRepository;
use FashionOS\Database\Repositories\QualityRepository;
use FashionOS\Database\Repositories\QuotationRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Database\Transaction;
use FashionOS\Designs\DesignService;
use FashionOS\Finance\ExpenseService;
use FashionOS\Finance\FinanceService;
use FashionOS\Finance\InvoiceService;
use FashionOS\Finance\PaymentService;
use FashionOS\Fittings\FittingService;
use FashionOS\Inventory\MaterialService;
use FashionOS\Measurements\MeasurementService;
use FashionOS\Orders\OrderService;
use FashionOS\Production\ProductionService;
use FashionOS\Production\Staff;
use FashionOS\Quality\QualityService;
use FashionOS\Quotations\QuotationService;
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
		$designs      = new DesignRepository();
		$quotations   = new QuotationRepository();
		$orders       = new OrderRepository();
		$production   = new ProductionRepository();
		$materials    = new MaterialRepository();
		$fittings     = new FittingRepository();
		$quality      = new QualityRepository();
		$invoices     = new InvoiceRepository();
		$payments     = new PaymentRepository();
		$expenses     = new ExpenseRepository();
		$transactions = new Transaction();
		$staff        = new Staff( $memberships );

		$resolver            = new TenantResolver( $tenants, $memberships );
		$service             = new TenantService( $tenants, $roles, $memberships, $audit, $transactions );
		$client_service      = new ClientService( $clients, $audit, $transactions );
		$measurement_service = new MeasurementService( $clients, $templates, $measurements, $audit, $transactions );
		$design_service      = new DesignService( $designs, $clients, $measurements, $audit, $transactions );
		$quotation_service   = new QuotationService( $quotations, $designs, $clients, $audit, $transactions );
		$order_service       = new OrderService( $orders, $quotations, $designs, $clients, $measurements, $audit, $transactions );
		$production_service  = new ProductionService( $production, $orders, $fittings, $quality, $materials, $audit, $transactions, $staff );
		$material_service    = new MaterialService( $materials, $audit, $transactions );
		$fitting_service     = new FittingService( $fittings, $orders, $staff, $audit, $transactions );
		$quality_service     = new QualityService( $quality, $orders, $audit, $transactions );
		$invoice_service     = new InvoiceService( $invoices, $clients, $orders, $payments, $audit, $transactions );
		$payment_service     = new PaymentService( $payments, $invoices, $audit, $transactions );
		$expense_service     = new ExpenseService( $expenses, $audit, $transactions );
		$finance_service     = new FinanceService( $invoices, $payments, $expenses );

		$this->controllers = array(
			new SystemController( new Migrator(), new Assets() ),
			new AuthController( $tenants, $memberships, $resolver ),
			new TenantController( $tenants, $roles, $memberships, $resolver, $service ),
			new TeamController( $memberships, $roles, $resolver, $service ),
			new ClientController( $client_service, $resolver ),
			new MeasurementController( $measurement_service, $resolver ),
			new DesignController( $design_service, $resolver ),
			new QuotationController( $quotation_service, $resolver ),
			new OrderController( $order_service, $resolver ),
			new ProductionController( $production_service, $resolver ),
			new MaterialController( $material_service, $resolver ),
			new FittingController( $fitting_service, $resolver ),
			new QualityController( $quality_service, $resolver ),
			new InvoiceController( $invoice_service, $resolver ),
			new PaymentController( $payment_service, $resolver ),
			new ExpenseController( $expense_service, $resolver ),
			new FinanceController( $finance_service, $resolver ),
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
