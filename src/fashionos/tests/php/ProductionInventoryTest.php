<?php
/**
 * Production and inventory tests.
 *
 * @package FashionOS
 *
 * These tests require the WordPress test library and a test database:
 *
 *   WP_TESTS_DIR=/path/to/wordpress-tests-lib vendor/bin/phpunit
 *
 * They have not been executed in the development environment (no PHP runtime
 * or database); see docs/PHASE_4_PLAN.md.
 */

declare( strict_types=1 );

namespace FashionOS\Tests;

use FashionOS\Clients\ClientService;
use FashionOS\Database\Migrations\Migrations;
use FashionOS\Database\Migrator;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\FittingRepository;
use FashionOS\Database\Repositories\MaterialRepository;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\ProductionRepository;
use FashionOS\Database\Repositories\QualityRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Database\Transaction;
use FashionOS\Fittings\FittingService;
use FashionOS\Inventory\MaterialService;
use FashionOS\Orders\OrderService;
use FashionOS\Production\ProductionService;
use FashionOS\Production\Staff;
use FashionOS\Quality\QualityService;
use FashionOS\Tenancy\TenantContext;
use FashionOS\Tenancy\TenantService;
use WP_Error;
use WP_UnitTestCase;

/**
 * Proves the Phase 4 exit criteria: each garment moves through its own
 * production workflow, material ownership is explicit, and assignment and
 * tenant scoping are enforced by the services behind the routes.
 */
final class ProductionInventoryTest extends WP_UnitTestCase {

	/**
	 * Tenancy workflows, used to create workspaces.
	 *
	 * @var TenantService
	 */
	private TenantService $tenancy;

	/**
	 * Customer workflows.
	 *
	 * @var ClientService
	 */
	private ClientService $clients;

	/**
	 * Order workflows, used to create garments.
	 *
	 * @var OrderService
	 */
	private OrderService $orders;

	/**
	 * Production workflows under test.
	 *
	 * @var ProductionService
	 */
	private ProductionService $production;

	/**
	 * Material workflows under test.
	 *
	 * @var MaterialService
	 */
	private MaterialService $materials;

	/**
	 * Fitting workflows under test.
	 *
	 * @var FittingService
	 */
	private FittingService $fittings;

	/**
	 * Quality workflows under test.
	 *
	 * @var QualityService
	 */
	private QualityService $quality;

	/**
	 * Material reads, to check stock directly.
	 *
	 * @var MaterialRepository
	 */
	private MaterialRepository $material_repo;

	/**
	 * Order id created by the last garments() call.
	 *
	 * @var int
	 */
	private int $last_order_id = 0;

	/**
	 * Install the plugin schema once for the suite.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		( new Migrator() )->migrate();
	}

	/**
	 * Build the services against the test database.
	 */
	public function set_up(): void {
		parent::set_up();

		$transactions = new Transaction();
		$audit        = new AuditLogRepository();
		$client_repo  = new ClientRepository();
		$order_repo   = new OrderRepository();
		$fittings     = new FittingRepository();
		$quality      = new QualityRepository();
		$memberships  = new MembershipRepository();

		$this->material_repo = new MaterialRepository();

		$this->tenancy = new TenantService(
			new TenantRepository(),
			new RoleRepository(),
			$memberships,
			$audit,
			$transactions
		);

		$this->clients = new ClientService( $client_repo, $audit, $transactions );
		$this->orders  = new OrderService(
			$order_repo,
			new \FashionOS\Database\Repositories\QuotationRepository(),
			new \FashionOS\Database\Repositories\DesignRepository(),
			$client_repo,
			new \FashionOS\Database\Repositories\MeasurementRepository(),
			$audit,
			$transactions
		);

		$staff = new Staff( $memberships );

		$this->production = new ProductionService(
			new ProductionRepository(),
			$order_repo,
			$fittings,
			$quality,
			$this->material_repo,
			$audit,
			$transactions,
			$staff
		);
		$this->materials = new MaterialService( $this->material_repo, $audit, $transactions );
		$this->fittings  = new FittingService( $fittings, $order_repo, $staff, $audit, $transactions );
		$this->quality   = new QualityService( $quality, $order_repo, $audit, $transactions );
	}

	/**
	 * Migration 0004 is the latest registered schema change.
	 */
	public function test_latest_migration_is_0004(): void {
		$this->assertSame( '0004', Migrations::latest_version() );
	}

	/**
	 * The starter workflow is seeded once per workspace and can be extended.
	 */
	public function test_stages_are_seeded_and_configurable(): void {
		$context = $this->workspace();

		$stages = $this->production->stages( $context );

		$this->assertCount( 9, $stages['stages'] );
		$this->assertSame( 'Design confirmation', $stages['stages'][0]['name'] );

		$custom = $this->production->create_stage(
			$context,
			array(
				'name'     => 'Hand finishing',
				'sequence' => 55,
			)
		);

		$this->assertIsArray( $custom );
		$this->assertSame( 'hand-finishing', $custom['slug'] );
		$this->assertTrue( $custom['isActive'] );

		$duplicate = $this->production->create_stage( $context, array( 'name' => 'Hand finishing' ) );

		$this->assertWPError( $duplicate );
		$this->assertSame( 'production_stage_slug_exists', $duplicate->get_error_code() );

		$paused = $this->production->update_stage( $context, (int) $custom['id'], array( 'isActive' => false ) );

		$this->assertIsArray( $paused );
		$this->assertFalse( $paused['isActive'] );
	}

	/**
	 * Two garments on one order get separate jobs, tasks and statuses.
	 */
	public function test_each_garment_moves_through_its_own_workflow(): void {
		$context   = $this->workspace();
		$customer  = $this->customer( $context, 'Amina Yusuf' );
		$garments  = $this->garments( $context, $customer, 2 );
		$stages    = $this->production->stages( $context )['stages'];
		$stage_ids = array_column( $stages, 'id', 'slug' );

		$jacket = $this->production->create_job(
			$context,
			array(
				'orderItemId' => (int) $garments[0]['id'],
				'assignedTo'  => $context->user_id(),
			)
		);

		$this->assertIsArray( $jacket );
		$this->assertSame( 'queued', $jacket['status'] );

		$trousers = $this->production->create_job(
			$context,
			array( 'orderItemId' => (int) $garments[1]['id'] )
		);

		$this->assertIsArray( $trousers );
		$this->assertSame( 0, $trousers['assignedTo'] );

		$duplicate = $this->production->create_job(
			$context,
			array( 'orderItemId' => (int) $garments[0]['id'] )
		);

		$this->assertWPError( $duplicate );
		$this->assertSame( 'production_job_exists', $duplicate->get_error_code() );

		$with_task = $this->production->add_task(
			$context,
			(int) $jacket['id'],
			array(
				'stageId'     => (int) $stage_ids['cutting'],
				'assignedTo'  => $context->user_id(),
				'dueDate'     => '2026-12-05',
			)
		);

		$this->assertIsArray( $with_task );
		$this->assertCount( 1, $with_task['tasks'] );
		$this->assertSame( 'Cutting', $with_task['tasks'][0]['stageName'] );

		$task_id = (int) $with_task['tasks'][0]['id'];
		$done    = $this->production->update_task( $context, $task_id, array( 'status' => 'done' ) );

		$this->assertIsArray( $done );
		$this->assertSame( 1, $done['taskDone'] );
		$this->assertNotSame( '', $done['tasks'][0]['completedAt'] );

		$moved = $this->production->update_job( $context, (int) $jacket['id'], array( 'status' => 'in_progress' ) );

		$this->assertIsArray( $moved );
		$this->assertSame( 'in_progress', $moved['status'] );

		$trousers_now = $this->production->job( $context, (int) $trousers['id'] );

		$this->assertIsArray( $trousers_now );
		$this->assertSame( 'queued', $trousers_now['status'] );
		$this->assertCount( 0, $trousers_now['tasks'] );

		$invalid = $this->production->update_job( $context, (int) $jacket['id'], array( 'status' => 'queued' ) );

		$this->assertWPError( $invalid );
		$this->assertSame( 'production_job_transition_invalid', $invalid->get_error_code() );
	}

	/**
	 * Garments without a job are reported, and only workspace members can be assigned.
	 */
	public function test_dashboard_reports_unplanned_garments_and_rejects_foreign_staff(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Chidi Okonkwo' );
		$garments = $this->garments( $context, $customer, 1 );

		$dashboard = $this->production->dashboard( $context );

		$this->assertSame( 1, $dashboard['counts']['unplannedGarments'] );
		$this->assertSame( (int) $garments[0]['id'], $dashboard['unplannedGarments'][0]['id'] );

		$outsider = self::factory()->user->create();
		$rejected = $this->production->create_job(
			$context,
			array(
				'orderItemId' => (int) $garments[0]['id'],
				'assignedTo'  => $outsider,
			)
		);

		$this->assertWPError( $rejected );
		$this->assertSame( 'production_assignee_invalid', $rejected->get_error_code() );
	}

	/**
	 * Designer-supplied fabric consumes stock; customer fabric never does.
	 */
	public function test_material_source_decides_whether_stock_is_consumed(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Ngozi Eze' );
		$garments = $this->garments( $context, $customer, 1 );
		$item_id  = (int) $garments[0]['id'];

		$wool = $this->material( $context, 'Italian wool', '10' );

		$this->assertIsArray( $wool );

		$designer = $this->materials->allocate(
			$context,
			$item_id,
			array(
				'materialId' => (int) $wool['id'],
				'quantity'   => '4',
				'source'     => 'designer_supplied',
			)
		);

		$this->assertIsArray( $designer );
		$this->assertCount( 1, $designer['allocations'] );

		$after_designer = $this->materials->find( $context, (int) $wool['id'] );

		$this->assertIsArray( $after_designer );
		$this->assertSame( 6.0, $after_designer['quantityOnHand'] );
		$this->assertSame( 'allocation', $after_designer['movements'][0]['type'] );

		$customer_fabric = $this->materials->allocate(
			$context,
			$item_id,
			array(
				'materialId' => (int) $wool['id'],
				'quantity'   => '2',
				'source'     => 'customer_supplied',
			)
		);

		$this->assertIsArray( $customer_fabric );
		$this->assertCount( 2, $customer_fabric['allocations'] );

		$after_customer = $this->materials->find( $context, (int) $wool['id'] );

		$this->assertIsArray( $after_customer );
		$this->assertSame( 6.0, $after_customer['quantityOnHand'] );

		$designer_allocation = (int) $customer_fabric['allocations'][0]['id'];
		$released            = $this->materials->release( $context, $designer_allocation );

		$this->assertIsArray( $released );
		$this->assertCount( 1, $released['allocations'] );

		$after_release = $this->materials->find( $context, (int) $wool['id'] );

		$this->assertIsArray( $after_release );
		$this->assertSame( 10.0, $after_release['quantityOnHand'] );
	}

	/**
	 * Stock can never be allocated or adjusted below zero.
	 */
	public function test_stock_cannot_go_negative(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Bola Adeyemi' );
		$garments = $this->garments( $context, $customer, 1 );
		$silk     = $this->material( $context, 'Silk lining', '3' );

		$this->assertIsArray( $silk );

		$too_much = $this->materials->allocate(
			$context,
			(int) $garments[0]['id'],
			array(
				'materialId' => (int) $silk['id'],
				'quantity'   => '5',
				'source'     => 'designer_supplied',
			)
		);

		$this->assertWPError( $too_much );
		$this->assertSame( 'material_insufficient_stock', $too_much->get_error_code() );

		$adjustment = $this->materials->movement(
			$context,
			(int) $silk['id'],
			array(
				'type'     => 'adjustment',
				'quantity' => '-4',
			)
		);

		$this->assertWPError( $adjustment );
		$this->assertSame( 'material_insufficient_stock', $adjustment->get_error_code() );

		$still = $this->materials->find( $context, (int) $silk['id'] );

		$this->assertIsArray( $still );
		$this->assertSame( 3.0, $still['quantityOnHand'] );
	}

	/**
	 * Fittings, alterations and quality checks belong to one garment.
	 */
	public function test_fittings_alterations_and_checks_are_garment_level(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Amina Yusuf' );
		$garments = $this->garments( $context, $customer, 2 );

		$fitting = $this->fittings->create(
			$context,
			array(
				'orderItemId' => (int) $garments[0]['id'],
				'scheduledAt' => '2026-12-01 10:00:00',
				'location'    => 'Atelier',
			)
		);

		$this->assertIsArray( $fitting );
		$this->assertSame( 'scheduled', $fitting['status'] );

		$completed = $this->fittings->update(
			$context,
			(int) $fitting['id'],
			array(
				'status'  => 'completed',
				'outcome' => 'Sleeves approved.',
			)
		);

		$this->assertIsArray( $completed );
		$this->assertSame( 'completed', $completed['status'] );

		$alteration = $this->fittings->create_alteration(
			$context,
			array(
				'fittingId'   => (int) $fitting['id'],
				'description' => 'Shorten the sleeves by 1 cm',
			)
		);

		$this->assertIsArray( $alteration );
		$this->assertSame( (int) $garments[0]['id'], $alteration['orderItemId'] );
		$this->assertSame( 'open', $alteration['status'] );

		$finished = $this->fittings->update_alteration(
			$context,
			(int) $alteration['id'],
			array( 'status' => 'completed' )
		);

		$this->assertIsArray( $finished );
		$this->assertSame( 'completed', $finished['status'] );
		$this->assertNotSame( '', $finished['completedAt'] );

		$check = $this->quality->create(
			$context,
			array(
				'orderItemId' => (int) $garments[0]['id'],
				'result'      => 'failed',
				'notes'       => 'Loose thread on the cuff.',
			)
		);

		$this->assertIsArray( $check );
		$this->assertSame( 'failed', $check['result'] );

		$order = $this->orders->find( $context, $this->last_order_id );

		$this->assertIsArray( $order );
		$this->assertSame( 'quality', $order['timeline'][ count( $order['timeline'] ) - 1 ]['type'] );

		$dashboard = $this->production->dashboard( $context );

		$this->assertSame( 1, $dashboard['counts']['failedChecks'] );
		$this->assertSame( (int) $garments[0]['id'], $dashboard['recentFailures'][0]['orderItemId'] );
	}

	/**
	 * Every new record stays inside its workspace.
	 */
	public function test_production_and_inventory_are_tenant_scoped(): void {
		$owner  = $this->workspace( 'Owner Studio' );
		$other  = $this->workspace( 'Other Studio' );
		$client = $this->customer( $owner, 'Amina Yusuf' );
		$items  = $this->garments( $owner, $client, 1 );
		$job    = $this->production->create_job( $owner, array( 'orderItemId' => (int) $items[0]['id'] ) );
		$wool   = $this->material( $owner, 'Italian wool', '10' );

		$this->assertIsArray( $job );
		$this->assertIsArray( $wool );

		$foreign_job = $this->production->job( $other, (int) $job['id'] );

		$this->assertWPError( $foreign_job );
		$this->assertSame( 'production_job_not_found', $foreign_job->get_error_code() );

		$foreign_material = $this->materials->find( $other, (int) $wool['id'] );

		$this->assertWPError( $foreign_material );
		$this->assertSame( 'material_not_found', $foreign_material->get_error_code() );

		$foreign_allocate = $this->materials->allocate(
			$other,
			(int) $items[0]['id'],
			array(
				'materialId' => (int) $wool['id'],
				'quantity'   => '1',
				'source'     => 'designer_supplied',
			)
		);

		$this->assertWPError( $foreign_allocate );
		$this->assertSame( 'order_item_not_found', $foreign_allocate->get_error_code() );
	}

	/**
	 * Create a workspace owned by a fresh user.
	 *
	 * @param string $name Workspace name.
	 */
	private function workspace( string $name = 'Production Studio' ): TenantContext {
		$context = $this->tenancy->create( self::factory()->user->create(), array( 'name' => $name ) );

		$this->assertInstanceOf( TenantContext::class, $context );

		return $context;
	}

	/**
	 * Create a customer.
	 *
	 * @param TenantContext $context Tenant context.
	 * @param string        $name    Customer name.
	 * @return array<string, mixed>
	 */
	private function customer( TenantContext $context, string $name ): array {
		$customer = $this->clients->create( $context, array( 'name' => $name ) );

		$this->assertIsArray( $customer );

		return $customer;
	}

	/**
	 * Create an order with the requested number of garments.
	 *
	 * @param TenantContext        $context   Tenant context.
	 * @param array<string, mixed> $customer  Customer payload.
	 * @param int                  $how_many  Number of garments.
	 * @return array<int, array<string, mixed>>
	 */
	private function garments( TenantContext $context, array $customer, int $how_many ): array {
		$items = array();

		for ( $index = 1; $index <= $how_many; $index++ ) {
			$items[] = array(
				'garmentName' => 'Garment ' . $index,
				'quantity'    => 1,
				'unitPrice'   => 100,
			);
		}

		$order = $this->orders->create(
			$context,
			array(
				'clientId' => (int) $customer['id'],
				'items'    => $items,
			)
		);

		$this->assertIsArray( $order );

		$this->last_order_id = (int) $order['id'];

		return $order['items'];
	}

	/**
	 * Create a material with opening stock.
	 *
	 * @param TenantContext $context Tenant context.
	 * @param string        $name    Material name.
	 * @param string        $opening Opening quantity.
	 * @return array<string, mixed>|WP_Error
	 */
	private function material( TenantContext $context, string $name, string $opening ) {
		return $this->materials->create(
			$context,
			array(
				'name'           => $name,
				'materialType'   => 'fabric',
				'unit'           => 'metre',
				'quantityOnHand' => $opening,
				'reorderLevel'   => '2',
				'unitCost'       => '1500',
			)
		);
	}
}
