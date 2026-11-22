<?php
/**
 * Client portal tests.
 *
 * @package FashionOS
 *
 * These tests require the WordPress test library and a test database:
 *
 *   WP_TESTS_DIR=/path/to/wordpress-tests-lib vendor/bin/phpunit
 *
 * They have not been executed in the development environment (no PHP runtime
 * or database); see docs/PHASE_6_PLAN.md.
 */

declare( strict_types=1 );

namespace FashionOS\Tests;

use FashionOS\Clients\ClientService;
use FashionOS\Database\Migrations\Migrations;
use FashionOS\Database\Migrator;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\DesignRepository;
use FashionOS\Database\Repositories\InvoiceRepository;
use FashionOS\Database\Repositories\MeasurementRepository;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\PaymentRepository;
use FashionOS\Database\Repositories\PortalAccessRepository;
use FashionOS\Database\Repositories\QuotationRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Database\Tables;
use FashionOS\Database\Transaction;
use FashionOS\Finance\InvoiceService;
use FashionOS\Finance\PaymentService;
use FashionOS\Orders\OrderService;
use FashionOS\Portal\PortalAccessService;
use FashionOS\Portal\PortalContext;
use FashionOS\Portal\PortalResolver;
use FashionOS\Portal\PortalService;
use FashionOS\Tenancy\TenantContext;
use FashionOS\Tenancy\TenantService;
use WP_Error;
use WP_UnitTestCase;

/**
 * Proves the Phase 6 exit criteria: a customer sees only their own permitted
 * information, and internal business data stays private.
 */
final class PortalTest extends WP_UnitTestCase {

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
	 * Order workflows, used to create customer orders.
	 *
	 * @var OrderService
	 */
	private OrderService $orders;

	/**
	 * Invoice workflows.
	 *
	 * @var InvoiceService
	 */
	private InvoiceService $invoices;

	/**
	 * Payment workflows.
	 *
	 * @var PaymentService
	 */
	private PaymentService $payments;

	/**
	 * Portal access workflows under test.
	 *
	 * @var PortalAccessService
	 */
	private PortalAccessService $access;

	/**
	 * Portal reads under test.
	 *
	 * @var PortalService
	 */
	private PortalService $portal;

	/**
	 * Portal token resolver under test.
	 *
	 * @var PortalResolver
	 */
	private PortalResolver $resolver;

	/**
	 * Audit reads.
	 *
	 * @var AuditLogRepository
	 */
	private AuditLogRepository $audit;

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

		$transactions  = new Transaction();
		$audit         = new AuditLogRepository();
		$client_repo   = new ClientRepository();
		$order_repo    = new OrderRepository();
		$invoices      = new InvoiceRepository();
		$payments      = new PaymentRepository();
		$portal_access = new PortalAccessRepository();
		$memberships   = new MembershipRepository();

		$this->audit = $audit;

		$this->tenancy = new TenantService(
			new TenantRepository(),
			new RoleRepository(),
			$memberships,
			$audit,
			$transactions
		);

		$this->clients  = new ClientService( $client_repo, $audit, $transactions );
		$this->orders   = new OrderService(
			$order_repo,
			new QuotationRepository(),
			new DesignRepository(),
			$client_repo,
			new MeasurementRepository(),
			$audit,
			$transactions
		);
		$this->invoices = new InvoiceService( $invoices, $client_repo, $order_repo, $payments, $audit, $transactions );
		$this->payments = new PaymentService( $payments, $invoices, $audit, $transactions );

		$this->access   = new PortalAccessService( $portal_access, $client_repo, $audit, $transactions );
		$this->portal   = new PortalService( $order_repo, $invoices, $payments );
		$this->resolver = new PortalResolver( $portal_access, new TenantRepository(), $client_repo );
	}

	/**
	 * Migration 0006 is the latest registered schema change.
	 */
	public function test_latest_migration_is_0006(): void {
		$this->assertSame( '0006', Migrations::latest_version() );
	}

	/**
	 * An issued link is returned once, stored only as a hash, and resolves to
	 * the one workspace and customer it was issued for.
	 */
	public function test_issued_link_is_stored_as_a_hash(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Amina Yusuf' );

		$issued = $this->access->issue(
			$context,
			(int) $customer['id'],
			array( 'label' => 'Order updates', 'expiresInDays' => 7 )
		);

		$this->assertIsArray( $issued );
		$this->assertSame( 64, strlen( $issued['token'] ) );
		$this->assertStringContainsString( $issued['token'], $issued['url'] );
		$this->assertArrayNotHasKey( 'token', $issued['access'] );
		$this->assertTrue( $issued['access']['isActive'] );
		$this->assertNotSame( '', $issued['access']['expiresAt'] );

		$stored = $this->stored_hash( (int) $issued['access']['id'] );

		$this->assertSame( hash( 'sha256', $issued['token'] ), $stored );
		$this->assertNotSame( $issued['token'], $stored );

		$resolved = $this->resolver->resolve( $issued['token'] );

		$this->assertInstanceOf( PortalContext::class, $resolved );
		$this->assertSame( $context->id(), $resolved->tenant_id() );
		$this->assertSame( (int) $customer['id'], $resolved->client_id() );
	}

	/**
	 * A portal token reads its own customer's orders, invoices and verified
	 * payments, and nothing from another customer, workspace, draft or void.
	 */
	public function test_portal_reads_only_the_customers_visible_records(): void {
		$context = $this->workspace( 'Portal Studio' );
		$owner   = $this->customer( $context, 'Amina Yusuf' );
		$other   = $this->customer( $context, 'Chidi Okonkwo' );

		$order = $this->order( $context, (int) $owner['id'], 'confirmed' );
		$this->order( $context, (int) $owner['id'], 'draft' );
		$foreign_order = $this->order( $context, (int) $other['id'], 'confirmed' );

		$invoice = $this->issued_invoice( $context, (int) $owner['id'], '300000' );
		$draft   = $this->invoices->create(
			$context,
			array(
				'clientId' => (int) $owner['id'],
				'items'    => array(
					array(
						'description' => 'Not sent yet',
						'quantity'    => 1,
						'unitPrice'   => '50000',
					),
				),
			)
		);

		$this->assertIsArray( $draft );

		$void = $this->issued_invoice( $context, (int) $owner['id'], '90000' );
		$this->invoices->update( $context, (int) $void['id'], array( 'status' => 'void' ) );

		$foreign_invoice = $this->issued_invoice( $context, (int) $other['id'], '120000' );

		$deposit = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $invoice['id'],
				'amount'    => '120000',
				'method'    => 'transfer',
			)
		);

		$this->assertIsArray( $deposit );
		$this->payments->update( $context, (int) $deposit['payment']['id'], array( 'status' => 'verified' ) );

		$pending = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $invoice['id'],
				'amount'    => '30000',
				'method'    => 'cash',
			)
		);

		$this->assertIsArray( $pending );

		$portal_context = $this->portal_context( $context, (int) $owner['id'] );
		$orders         = $this->portal->orders( $portal_context, array() );

		$this->assertSame( 1, $orders['total'] );
		$this->assertSame( (string) $order['reference'], $orders['orders'][0]['reference'] );
		$this->assertArrayNotHasKey( 'notes', $orders['orders'][0] );
		$this->assertArrayNotHasKey( 'timeline', $orders['orders'][0] );

		$detail = $this->portal->order( $portal_context, (int) $order['id'] );

		$this->assertIsArray( $detail );
		$this->assertSame( 1, $detail['garmentCount'] );
		$this->assertSame( 'Wedding jacket', $detail['items'][0]['garmentName'] );
		$this->assertArrayNotHasKey( 'unitPrice', $detail['items'][0] );

		$hidden_order = $this->portal->order( $portal_context, (int) $foreign_order['id'] );

		$this->assertWPError( $hidden_order );
		$this->assertSame( 'portal_order_not_found', $hidden_order->get_error_code() );

		$invoices = $this->portal->invoices( $portal_context, array() );

		$this->assertSame( 1, $invoices['total'] );
		$this->assertSame( (string) $invoice['reference'], $invoices['invoices'][0]['reference'] );

		$shown = $this->portal->invoice( $portal_context, (int) $invoice['id'] );

		$this->assertIsArray( $shown );
		$this->assertSame( 300000.0, $shown['total'] );
		$this->assertSame( 120000.0, $shown['amountPaid'] );
		$this->assertSame( 180000.0, $shown['balance'] );
		$this->assertCount( 1, $shown['payments'] );
		$this->assertSame( 'P-0001', $shown['payments'][0]['reference'] );

		$draft_hidden = $this->portal->invoice( $portal_context, (int) $draft['id'] );

		$this->assertWPError( $draft_hidden );
		$this->assertSame( 'portal_invoice_not_found', $draft_hidden->get_error_code() );

		$void_hidden = $this->portal->invoice( $portal_context, (int) $void['id'] );

		$this->assertWPError( $void_hidden );
		$this->assertSame( 'portal_invoice_not_found', $void_hidden->get_error_code() );

		$foreign_hidden = $this->portal->invoice( $portal_context, (int) $foreign_invoice['id'] );

		$this->assertWPError( $foreign_hidden );
		$this->assertSame( 'portal_invoice_not_found', $foreign_hidden->get_error_code() );

		$session = $this->portal->session( $portal_context );

		$this->assertSame( 'Portal Studio', $session['studio']['name'] );
		$this->assertSame( 1, $session['summary']['openOrders'] );
		$this->assertSame( 1, $session['summary']['openInvoices'] );
		$this->assertSame( 180000.0, $session['summary']['outstanding'] );
		$this->assertArrayNotHasKey( 'notes', $session['customer'] );
	}

	/**
	 * Revoked, expired and unknown tokens are refused, and a revoked link
	 * cannot be quietly re-issued by reusing its token.
	 */
	public function test_revoked_expired_and_unknown_links_are_refused(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Ngozi Eze' );

		$issued = $this->access->issue( $context, (int) $customer['id'], array() );

		$this->assertIsArray( $issued );
		$this->assertInstanceOf( PortalContext::class, $this->resolver->resolve( $issued['token'] ) );

		$revoked = $this->access->revoke( $context, (int) $issued['access']['id'] );

		$this->assertIsArray( $revoked );
		$this->assertFalse( $revoked['isActive'] );

		$after = $this->resolver->resolve( $issued['token'] );

		$this->assertWPError( $after );
		$this->assertSame( 'portal_access_invalid', $after->get_error_code() );

		// Revoking twice is harmless and does not rewrite the timestamp.
		$twice = $this->access->revoke( $context, (int) $issued['access']['id'] );

		$this->assertIsArray( $twice );
		$this->assertSame( $revoked['revokedAt'], $twice['revokedAt'] );

		$expired = $this->access->issue( $context, (int) $customer['id'], array( 'expiresInDays' => 1 ) );

		$this->assertIsArray( $expired );

		( new PortalAccessRepository() )->update(
			$context->id(),
			(int) $expired['access']['id'],
			array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) )
		);

		$late = $this->resolver->resolve( $expired['token'] );

		$this->assertWPError( $late );
		$this->assertSame( 'portal_access_invalid', $late->get_error_code() );

		$unknown = $this->resolver->resolve( 'not-a-real-token' );

		$this->assertWPError( $unknown );
		$this->assertSame( 'portal_access_invalid', $unknown->get_error_code() );

		$empty = $this->resolver->resolve( '' );

		$this->assertWPError( $empty );
		$this->assertSame( 'portal_access_invalid', $empty->get_error_code() );
	}

	/**
	 * Staff cannot share access for an archived customer, and every issue and
	 * revocation leaves an audit entry.
	 */
	public function test_sharing_access_respects_customer_state_and_is_audited(): void {
		$context  = $this->workspace( 'Audit Studio' );
		$customer = $this->customer( $context, 'Halima Bello' );

		$issued = $this->access->issue( $context, (int) $customer['id'], array( 'label' => 'Fitting' ) );

		$this->assertIsArray( $issued );
		$this->assertSame( 'Fitting', $issued['access']['label'] );

		$this->access->revoke( $context, (int) $issued['access']['id'] );

		$this->clients->update( $context, (int) $customer['id'], array( 'status' => 'archived' ) );

		$blocked = $this->access->issue( $context, (int) $customer['id'], array() );

		$this->assertWPError( $blocked );
		$this->assertSame( 'portal_client_archived', $blocked->get_error_code() );

		$actions = array_column( $this->audit->recent( $context->id(), 50 ), 'action' );

		$this->assertContains( 'portal.access_issued', $actions );
		$this->assertContains( 'portal.access_revoked', $actions );
	}

	/**
	 * Access links never cross workspace boundaries.
	 */
	public function test_access_links_are_tenant_scoped(): void {
		$owner  = $this->workspace( 'Owner Studio' );
		$other  = $this->workspace( 'Other Studio' );
		$record = $this->customer( $owner, 'Bola Adeyemi' );

		$issued = $this->access->issue( $owner, (int) $record['id'], array() );

		$this->assertIsArray( $issued );

		$foreign_list = $this->access->list( $other, (int) $record['id'] );

		$this->assertWPError( $foreign_list );
		$this->assertSame( 'client_not_found', $foreign_list->get_error_code() );

		$foreign_revoke = $this->access->revoke( $other, (int) $issued['access']['id'] );

		$this->assertWPError( $foreign_revoke );
		$this->assertSame( 'portal_access_not_found', $foreign_revoke->get_error_code() );
	}

	/**
	 * Read the stored hash for one access row.
	 *
	 * @param int $access_id Access id.
	 */
	private function stored_hash( int $access_id ): string {
		global $wpdb;

		$table = Tables::portal_access();

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT token_hash FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$access_id
			)
		);
	}

	/**
	 * Resolve a live portal context for a customer.
	 *
	 * @param TenantContext $context   Tenant context.
	 * @param int           $client_id Customer id.
	 */
	private function portal_context( TenantContext $context, int $client_id ): PortalContext {
		$issued = $this->access->issue( $context, $client_id, array() );

		$this->assertIsArray( $issued );

		$resolved = $this->resolver->resolve( $issued['token'] );

		$this->assertInstanceOf( PortalContext::class, $resolved );

		return $resolved;
	}

	/**
	 * Create a workspace owned by a fresh user.
	 *
	 * @param string $name Workspace name.
	 */
	private function workspace( string $name = 'Portal Studio' ): TenantContext {
		$context = $this->tenancy->create( self::factory()->user->create(), array( 'name' => $name ) );

		$this->assertInstanceOf( TenantContext::class, $context );

		return $context;
	}

	/**
	 * Create a customer in a workspace.
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
	 * Create one order with a single garment.
	 *
	 * @param TenantContext $context   Tenant context.
	 * @param int           $client_id Customer id.
	 * @param string        $status    Status to move the order into.
	 * @return array<string, mixed>
	 */
	private function order( TenantContext $context, int $client_id, string $status ): array {
		$order = $this->orders->create(
			$context,
			array(
				'clientId' => $client_id,
				'dueDate'  => gmdate( 'Y-m-d', strtotime( '+21 days' ) ),
				'items'    => array(
					array(
						'garmentName' => 'Wedding jacket',
						'quantity'    => 1,
						'unitPrice'   => '380000',
						// Internal detail that must not reach the portal.
						'notes'       => 'Use the second bolt of ivory wool.',
					),
				),
			)
		);

		$this->assertIsArray( $order );

		if ( 'draft' === $status ) {
			return $order;
		}

		$moved = $this->orders->update( $context, (int) $order['id'], array( 'status' => $status ) );

		$this->assertIsArray( $moved );

		return $moved;
	}

	/**
	 * Create and issue a one-line invoice for a known customer.
	 *
	 * @param TenantContext $context   Tenant context.
	 * @param int           $client_id Customer id.
	 * @param string        $amount    Line price.
	 * @return array<string, mixed>
	 */
	private function issued_invoice( TenantContext $context, int $client_id, string $amount ): array {
		$invoice = $this->invoices->create(
			$context,
			array(
				'clientId' => $client_id,
				'dueDate'  => gmdate( 'Y-m-d', strtotime( '+14 days' ) ),
				'items'    => array(
					array(
						'description' => 'Bespoke garment',
						'quantity'    => 1,
						'unitPrice'   => $amount,
					),
				),
			)
		);

		$this->assertIsArray( $invoice );

		$issued = $this->invoices->update( $context, (int) $invoice['id'], array( 'status' => 'issued' ) );

		$this->assertIsArray( $issued );

		return $issued;
	}
}
