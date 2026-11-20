<?php
/**
 * Design, quotation and order tests.
 *
 * @package FashionOS
 *
 * These tests require the WordPress test library and a test database:
 *
 *   WP_TESTS_DIR=/path/to/wordpress-tests-lib vendor/bin/phpunit
 *
 * They have not been executed in the development environment (no PHP runtime
 * or database); see docs/PHASE_3_PLAN.md.
 */

declare( strict_types=1 );

namespace FashionOS\Tests;

use FashionOS\Clients\ClientService;
use FashionOS\Database\Migrations\Migrations;
use FashionOS\Database\Migrator;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\DesignRepository;
use FashionOS\Database\Repositories\MeasurementRepository;
use FashionOS\Database\Repositories\MeasurementTemplateRepository;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\QuotationRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Database\Transaction;
use FashionOS\Designs\DesignService;
use FashionOS\Measurements\MeasurementService;
use FashionOS\Orders\OrderService;
use FashionOS\Quotations\QuotationService;
use FashionOS\Tenancy\TenantContext;
use FashionOS\Tenancy\TenantService;
use WP_Error;
use WP_UnitTestCase;

/**
 * Proves the Phase 3 exit criteria: one order holds several independently
 * tracked garments, quotations and orders stay separate, and order history is
 * auditable. Every record remains tenant-scoped.
 */
final class QuotationOrderTest extends WP_UnitTestCase {

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
	 * Measurement workflows.
	 *
	 * @var MeasurementService
	 */
	private MeasurementService $measurements;

	/**
	 * Design workflows under test.
	 *
	 * @var DesignService
	 */
	private DesignService $designs;

	/**
	 * Quotation workflows under test.
	 *
	 * @var QuotationService
	 */
	private QuotationService $quotations;

	/**
	 * Order workflows under test.
	 *
	 * @var OrderService
	 */
	private OrderService $orders;

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
		$measurements = new MeasurementRepository();
		$design_repo  = new DesignRepository();
		$quote_repo   = new QuotationRepository();
		$order_repo   = new OrderRepository();

		$this->tenancy = new TenantService(
			new TenantRepository(),
			new RoleRepository(),
			new MembershipRepository(),
			$audit,
			$transactions
		);

		$this->clients      = new ClientService( $client_repo, $audit, $transactions );
		$this->measurements = new MeasurementService(
			$client_repo,
			new MeasurementTemplateRepository(),
			$measurements,
			$audit,
			$transactions
		);
		$this->designs    = new DesignService( $design_repo, $client_repo, $measurements, $audit, $transactions );
		$this->quotations = new QuotationService( $quote_repo, $design_repo, $client_repo, $audit, $transactions );
		$this->orders     = new OrderService(
			$order_repo,
			$quote_repo,
			$design_repo,
			$client_repo,
			$measurements,
			$audit,
			$transactions
		);
	}

	/**
	 * Migration 0003 is the latest registered schema change.
	 */
	public function test_latest_migration_is_0003(): void {
		$this->assertSame( '0003', Migrations::latest_version() );
	}

	/**
	 * A design pins the exact measurement version it was drafted against.
	 */
	public function test_design_pins_measurement_version(): void {
		$context   = $this->workspace();
		$customer  = $this->customer( $context, 'Amina Yusuf' );
		$version   = $this->version( $context, $customer, 'Wedding suit' );

		$design = $this->designs->create(
			$context,
			array(
				'title'                => 'Ivory wedding suit',
				'clientId'             => (int) $customer['id'],
				'measurementVersionId' => (int) $version['id'],
				'garmentCategory'      => 'Suit',
			)
		);

		$this->assertIsArray( $design );
		$this->assertMatchesRegularExpression( '/^D-\d{4}$/', $design['reference'] );
		$this->assertSame( (int) $version['id'], $design['measurementVersionId'] );
		$this->assertSame( 1, $design['measurementVersionNumber'] );
		$this->assertSame( 'Wedding suit', $design['profileName'] );
		$this->assertSame( 'draft', $design['status'] );

		$wrong_customer = $this->customer( $context, 'Chidi Okonkwo' );
		$wrong_version  = $this->version( $context, $wrong_customer, 'Business suit' );

		$mismatch = $this->designs->create(
			$context,
			array(
				'title'                => 'Stolen measurements',
				'clientId'             => (int) $customer['id'],
				'measurementVersionId' => (int) $wrong_version['id'],
			)
		);

		$this->assertWPError( $mismatch );
		$this->assertSame( 'design_measurement_mismatch', $mismatch->get_error_code() );
	}

	/**
	 * Quotation totals are computed from quantity and unit price.
	 */
	public function test_quotation_totals_are_computed_from_lines(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Amina Yusuf' );

		$quotation = $this->quotations->create(
			$context,
			array(
				'clientId' => (int) $customer['id'],
				'items'    => array(
					array(
						'description' => 'Wedding jacket',
						'quantity'    => 2,
						'unitPrice'   => '150.50',
					),
					array(
						'description' => 'Trousers',
						'quantity'    => 1,
						'unitPrice'   => 80,
					),
				),
			)
		);

		$this->assertIsArray( $quotation );
		$this->assertSame( 381.0, $quotation['total'] );
		$this->assertSame( 381.0, $quotation['subtotal'] );
		$this->assertSame( 301.0, $quotation['items'][0]['lineTotal'] );
		$this->assertSame( 80.0, $quotation['items'][1]['lineTotal'] );
		$this->assertMatchesRegularExpression( '/^Q-\d{4}$/', $quotation['reference'] );

		$locked = $this->quotations->update(
			$context,
			(int) $quotation['id'],
			array( 'status' => 'sent' )
		);

		$this->assertIsArray( $locked );
		$this->assertSame( 'sent', $locked['status'] );

		$replace = $this->quotations->update(
			$context,
			(int) $quotation['id'],
			array(
				'items' => array(
					array(
						'description' => 'Replacement line',
						'quantity'    => 1,
						'unitPrice'   => 10,
					),
				),
			)
		);

		$this->assertWPError( $replace );
		$this->assertSame( 'quotation_locked', $replace->get_error_code() );
	}

	/**
	 * Ordering from a quotation copies its garments without changing it.
	 */
	public function test_order_from_quotation_copies_lines_and_keeps_quotation_separate(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Amina Yusuf' );
		$version  = $this->version( $context, $customer, 'Wedding suit' );

		$design = $this->designs->create(
			$context,
			array(
				'title'                => 'Ivory wedding suit',
				'clientId'             => (int) $customer['id'],
				'measurementVersionId' => (int) $version['id'],
			)
		);

		$this->assertIsArray( $design );

		$quotation = $this->quotations->create(
			$context,
			array(
				'clientId' => (int) $customer['id'],
				'designId' => (int) $design['id'],
				'items'    => array(
					array(
						'description' => 'Wedding jacket',
						'quantity'    => 1,
						'unitPrice'   => 380000,
					),
					array(
						'description' => 'Wedding trousers',
						'quantity'    => 1,
						'unitPrice'   => 100000,
					),
				),
			)
		);

		$this->assertIsArray( $quotation );

		$order = $this->orders->create(
			$context,
			array( 'quotationId' => (int) $quotation['id'] )
		);

		$this->assertIsArray( $order );
		$this->assertSame( 2, $order['garmentCount'] );
		$this->assertSame( 480000.0, $order['total'] );
		$this->assertSame( (int) $quotation['id'], $order['quotationId'] );

		foreach ( $order['items'] as $item ) {
			$this->assertSame( (int) $version['id'], $item['measurementVersionId'] );
			$this->assertSame( 1, $item['measurementVersionNumber'] );
			$this->assertSame( (int) $design['id'], $item['designId'] );
			$this->assertSame( 'pending', $item['status'] );
		}

		$reloaded = $this->quotations->find( $context, (int) $quotation['id'] );

		$this->assertIsArray( $reloaded );
		$this->assertSame( 'draft', $reloaded['status'] );
		$this->assertSame( 2, $reloaded['itemCount'] );
	}

	/**
	 * Garments move independently and every change reaches the timeline.
	 */
	public function test_garments_track_statuses_independently_with_timeline(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Amina Yusuf' );
		$jacket   = $this->version( $context, $customer, 'Wedding suit' );
		$dress    = $this->version( $context, $customer, 'Reception dress' );

		$order = $this->orders->create(
			$context,
			array(
				'clientId' => (int) $customer['id'],
				'items'    => array(
					array(
						'garmentName'          => 'Wedding jacket',
						'quantity'             => 1,
						'unitPrice'            => '250.00',
						'measurementVersionId' => (int) $jacket['id'],
					),
					array(
						'garmentName'          => 'Reception dress',
						'quantity'             => 1,
						'unitPrice'            => '300.00',
						'measurementVersionId' => (int) $dress['id'],
					),
				),
			)
		);

		$this->assertIsArray( $order );
		$this->assertSame( 550.0, $order['total'] );

		$items = $order['items'];

		$this->assertSame( (int) $jacket['id'], $items[0]['measurementVersionId'] );
		$this->assertSame( (int) $dress['id'], $items[1]['measurementVersionId'] );

		$updated = $this->orders->update_item(
			$context,
			(int) $items[0]['id'],
			array( 'status' => 'cutting' )
		);

		$this->assertIsArray( $updated );
		$this->assertSame( 'cutting', $updated['items'][0]['status'] );
		$this->assertSame( 'pending', $updated['items'][1]['status'] );

		$types = array_column( $updated['timeline'], 'type' );

		$this->assertContains( 'created', $types );
		$this->assertContains( 'item_added', $types );
		$this->assertContains( 'item_status', $types );

		$messages = array_column( $updated['timeline'], 'message' );

		$this->assertContains( 'Wedding jacket moved from Pending to Cutting.', $messages );

		$confirmed = $this->orders->update( $context, (int) $order['id'], array( 'status' => 'confirmed' ) );

		$this->assertIsArray( $confirmed );
		$this->assertSame( 'confirmed', $confirmed['status'] );
		$this->assertContains( 'Order moved from Draft to Confirmed.', array_column( $confirmed['timeline'], 'message' ) );

		$entries = ( new AuditLogRepository() )->recent( $context->id(), 200 );
		$actions = array_column( $entries, 'action' );

		$this->assertContains( 'order.created', $actions );
		$this->assertContains( 'order.item.status_changed', $actions );
		$this->assertContains( 'order.status_changed', $actions );
	}

	/**
	 * An order cannot cite another customer's measurement version.
	 */
	public function test_order_rejects_another_customers_measurement_version(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Amina Yusuf' );
		$other    = $this->customer( $context, 'Chidi Okonkwo' );
		$version  = $this->version( $context, $other, 'Business suit' );

		$order = $this->orders->create(
			$context,
			array(
				'clientId' => (int) $customer['id'],
				'items'    => array(
					array(
						'garmentName'          => 'Wedding jacket',
						'measurementVersionId' => (int) $version['id'],
					),
				),
			)
		);

		$this->assertWPError( $order );
		$this->assertSame( 'order_measurement_mismatch', $order->get_error_code() );
	}

	/**
	 * Design, quotation and order records of another workspace are invisible.
	 */
	public function test_designs_quotations_and_orders_are_tenant_scoped(): void {
		$owner    = $this->workspace();
		$other    = $this->workspace( 'Other Studio' );
		$customer = $this->customer( $owner, 'Amina Yusuf' );
		$version  = $this->version( $owner, $customer, 'Wedding suit' );

		$design = $this->designs->create(
			$owner,
			array(
				'title'                => 'Ivory wedding suit',
				'clientId'             => (int) $customer['id'],
				'measurementVersionId' => (int) $version['id'],
			)
		);

		$this->assertIsArray( $design );

		$quotation = $this->quotations->create(
			$owner,
			array(
				'clientId' => (int) $customer['id'],
				'items'    => array(
					array(
						'description' => 'Wedding suit',
						'quantity'    => 1,
						'unitPrice'   => 100,
					),
				),
			)
		);

		$this->assertIsArray( $quotation );

		$order = $this->orders->create(
			$owner,
			array(
				'clientId' => (int) $customer['id'],
				'items'    => array( array( 'garmentName' => 'Wedding jacket' ) ),
			)
		);

		$this->assertIsArray( $order );

		$foreign_design = $this->designs->find( $other, (int) $design['id'] );

		$this->assertWPError( $foreign_design );
		$this->assertSame( 'design_not_found', $foreign_design->get_error_code() );

		$foreign_quotation = $this->quotations->find( $other, (int) $quotation['id'] );

		$this->assertWPError( $foreign_quotation );
		$this->assertSame( 'quotation_not_found', $foreign_quotation->get_error_code() );

		$foreign_order = $this->orders->find( $other, (int) $order['id'] );

		$this->assertWPError( $foreign_order );
		$this->assertSame( 'order_not_found', $foreign_order->get_error_code() );

		$foreign_item = $this->orders->update_item(
			$other,
			(int) $order['items'][0]['id'],
			array( 'status' => 'cutting' )
		);

		$this->assertWPError( $foreign_item );
		$this->assertSame( 'order_item_not_found', $foreign_item->get_error_code() );
	}

	/**
	 * Create a workspace owned by a fresh user.
	 *
	 * @param string $name Workspace name.
	 */
	private function workspace( string $name = 'Order Studio' ): TenantContext {
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
	 * Create a template, profile and first measurement version for a customer.
	 *
	 * @param TenantContext        $context      Tenant context.
	 * @param array<string, mixed> $customer     Customer payload.
	 * @param string               $profile_name Profile name.
	 * @return array<string, mixed>
	 */
	private function version( TenantContext $context, array $customer, string $profile_name ): array {
		$template = $this->measurements->create_template(
			$context,
			array(
				'name'            => 'Two-piece suit',
				'garmentCategory' => 'Suit',
				'unit'            => 'cm',
				'fields'          => array(
					array( 'label' => 'Chest', 'key' => 'chest', 'required' => true ),
					array( 'label' => 'Waist', 'key' => 'waist', 'required' => false ),
				),
			)
		);

		$this->assertIsArray( $template );

		$profile = $this->measurements->create_profile(
			$context,
			(int) $customer['id'],
			array(
				'profileName' => $profile_name,
				'templateId'  => (int) $template['id'],
			)
		);

		$this->assertIsArray( $profile );

		$version = $this->measurements->create_version(
			$context,
			(int) $profile['id'],
			array( 'values' => array( 'chest' => '96' ) )
		);

		$this->assertIsArray( $version );

		return $version;
	}
}
