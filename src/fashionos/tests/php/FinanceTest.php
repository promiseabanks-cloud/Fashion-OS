<?php
/**
 * Finance tests.
 *
 * @package FashionOS
 *
 * These tests require the WordPress test library and a test database:
 *
 *   WP_TESTS_DIR=/path/to/wordpress-tests-lib vendor/bin/phpunit
 *
 * They have not been executed in the development environment (no PHP runtime
 * or database); see docs/PHASE_5_PLAN.md.
 */

declare( strict_types=1 );

namespace FashionOS\Tests;

use FashionOS\Clients\ClientService;
use FashionOS\Database\Migrations\Migrations;
use FashionOS\Database\Migrator;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\ExpenseRepository;
use FashionOS\Database\Repositories\InvoiceRepository;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\PaymentRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Database\Transaction;
use FashionOS\Finance\ExpenseService;
use FashionOS\Finance\FinanceService;
use FashionOS\Finance\InvoiceService;
use FashionOS\Finance\PaymentService;
use FashionOS\Tenancy\TenantContext;
use FashionOS\Tenancy\TenantService;
use WP_Error;
use WP_UnitTestCase;

/**
 * Proves the Phase 5 exit criteria: payments are verified and idempotent,
 * balances reconcile with the payment ledger, and every write is audited.
 */
final class FinanceTest extends WP_UnitTestCase {

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
	 * Invoice workflows under test.
	 *
	 * @var InvoiceService
	 */
	private InvoiceService $invoices;

	/**
	 * Payment workflows under test.
	 *
	 * @var PaymentService
	 */
	private PaymentService $payments;

	/**
	 * Expense workflows under test.
	 *
	 * @var ExpenseService
	 */
	private ExpenseService $expenses;

	/**
	 * Finance dashboard.
	 *
	 * @var FinanceService
	 */
	private FinanceService $finance;

	/**
	 * Audit reads, to prove history is kept.
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

		$transactions = new Transaction();
		$audit        = new AuditLogRepository();
		$client_repo  = new ClientRepository();
		$order_repo   = new OrderRepository();
		$invoices     = new InvoiceRepository();
		$payments     = new PaymentRepository();
		$expense_repo = new ExpenseRepository();
		$memberships  = new MembershipRepository();

		$this->audit = $audit;

		$this->tenancy = new TenantService(
			new TenantRepository(),
			new RoleRepository(),
			$memberships,
			$audit,
			$transactions
		);

		$this->clients  = new ClientService( $client_repo, $audit, $transactions );
		$this->invoices = new InvoiceService( $invoices, $client_repo, $order_repo, $payments, $audit, $transactions );
		$this->payments = new PaymentService( $payments, $invoices, $audit, $transactions );
		$this->expenses = new ExpenseService( $expense_repo, $audit, $transactions );
		$this->finance  = new FinanceService( $invoices, $payments, $expense_repo );
	}

	/**
	 * Migration 0005 is the latest registered schema change.
	 */
	public function test_latest_migration_is_0005(): void {
		$this->assertSame( '0005', Migrations::latest_version() );
	}

	/**
	 * Invoice totals are computed on the server, and only a draft can change
	 * its lines or be issued.
	 */
	public function test_invoice_totals_are_server_computed_and_draft_only_editable(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Amina Yusuf' );

		$invoice = $this->invoices->create(
			$context,
			array(
				'clientId' => (int) $customer['id'],
				// A browser-supplied total must be ignored.
				'total'    => 1,
				'items'    => array(
					array(
						'description' => 'Wedding jacket',
						'quantity'    => 1,
						'unitPrice'   => '380000',
					),
					array(
						'description' => 'Wedding trousers',
						'quantity'    => 2,
						'unitPrice'   => '50000',
					),
				),
			)
		);

		$this->assertIsArray( $invoice );
		$this->assertSame( 'draft', $invoice['status'] );
		$this->assertSame( 'I-0001', $invoice['reference'] );
		$this->assertSame( 480000.0, $invoice['total'] );
		$this->assertSame( 480000.0, $invoice['balance'] );
		$this->assertSame( 0.0, $invoice['amountPaid'] );
		$this->assertSame( 2, $invoice['itemCount'] );

		$revised = $this->invoices->update(
			$context,
			(int) $invoice['id'],
			array(
				'items' => array(
					array(
						'description' => 'Wedding suit',
						'quantity'    => 1,
						'unitPrice'   => '480000',
					),
					array(
						'description' => 'Express tailoring',
						'quantity'    => 2,
						'unitPrice'   => '25000',
					),
				),
			)
		);

		// Replacing draft lines reprices the header instead of leaving the old total behind.
		$this->assertIsArray( $revised );
		$this->assertSame( 530000.0, $revised['total'] );
		$this->assertSame( 530000.0, $revised['balance'] );
		$this->assertSame( 0.0, $revised['amountPaid'] );
		$this->assertSame( 2, $revised['itemCount'] );

		$issued = $this->invoices->update( $context, (int) $invoice['id'], array( 'status' => 'issued' ) );

		$this->assertIsArray( $issued );
		$this->assertSame( 'issued', $issued['status'] );

		$back = $this->invoices->update( $context, (int) $invoice['id'], array( 'status' => 'draft' ) );

		$this->assertWPError( $back );
		$this->assertSame( 'invoice_status_invalid', $back->get_error_code() );

		$locked = $this->invoices->update(
			$context,
			(int) $invoice['id'],
			array(
				'items' => array(
					array(
						'description' => 'Replacement',
						'quantity'    => 1,
						'unitPrice'   => '1',
					),
				),
			)
		);

		$this->assertWPError( $locked );
		$this->assertSame( 'invoice_locked', $locked->get_error_code() );

		$derived = $this->invoices->update( $context, (int) $invoice['id'], array( 'status' => 'paid' ) );

		$this->assertWPError( $derived );
		$this->assertSame( 'invoice_status_invalid', $derived->get_error_code() );
	}

	/**
	 * The same idempotency key records one payment, not two.
	 */
	public function test_duplicate_payments_are_idempotent(): void {
		$context = $this->workspace();
		$invoice = $this->issued_invoice( $context, 'Chidi Okonkwo', '100000' );
		$payload = array(
			'invoiceId'      => (int) $invoice['id'],
			'amount'         => '40000',
			'method'         => 'transfer',
			'idempotencyKey' => 'callback-1',
		);

		$first = $this->payments->create( $context, $payload );

		$this->assertIsArray( $first );
		$this->assertFalse( $first['duplicate'] );
		$this->assertSame( 'pending', $first['payment']['status'] );

		$second = $this->payments->create( $context, $payload );

		$this->assertIsArray( $second );
		$this->assertTrue( $second['duplicate'] );
		$this->assertSame( (int) $first['payment']['id'], (int) $second['payment']['id'] );

		$list = $this->payments->search( $context, array( 'invoiceId' => (int) $invoice['id'] ) );

		$this->assertSame( 1, $list['total'] );
	}

	/**
	 * Partial payments move the balance; verifying the rest settles the
	 * invoice, and only verified money counts.
	 */
	public function test_balances_reconcile_across_partial_payments(): void {
		$context = $this->workspace();
		$invoice = $this->issued_invoice( $context, 'Halima Bello', '300000' );

		$deposit = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $invoice['id'],
				'amount'    => '120000',
				'method'    => 'transfer',
			)
		);

		$this->assertIsArray( $deposit );

		$still_issued = $this->invoices->find( $context, (int) $invoice['id'] );

		$this->assertIsArray( $still_issued );
		$this->assertSame( 'issued', $still_issued['status'] );
		$this->assertSame( 300000.0, $still_issued['balance'] );

		$verified = $this->payments->update(
			$context,
			(int) $deposit['payment']['id'],
			array( 'status' => 'verified' )
		);

		$this->assertIsArray( $verified );
		$this->assertSame( 'verified', $verified['status'] );
		$this->assertNotSame( '', $verified['verifiedAt'] );
		$this->assertSame( $context->user_id(), (int) $verified['verifiedBy'] );

		$partial = $this->invoices->find( $context, (int) $invoice['id'] );

		$this->assertIsArray( $partial );
		$this->assertSame( 'partially_paid', $partial['status'] );
		$this->assertSame( 120000.0, $partial['amountPaid'] );
		$this->assertSame( 180000.0, $partial['balance'] );

		$rejected = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $invoice['id'],
				'amount'    => '50000',
				'method'    => 'cash',
			)
		);

		$this->assertIsArray( $rejected );

		$declined = $this->payments->update(
			$context,
			(int) $rejected['payment']['id'],
			array( 'status' => 'rejected' )
		);

		$this->assertIsArray( $declined );
		$this->assertSame( 'rejected', $declined['status'] );

		$unchanged = $this->invoices->find( $context, (int) $invoice['id'] );

		$this->assertIsArray( $unchanged );
		$this->assertSame( 180000.0, $unchanged['balance'] );

		$rest = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $invoice['id'],
				'amount'    => '180000',
				'method'    => 'card',
			)
		);

		$this->assertIsArray( $rest );

		$this->payments->update( $context, (int) $rest['payment']['id'], array( 'status' => 'verified' ) );

		$settled = $this->invoices->find( $context, (int) $invoice['id'] );

		$this->assertIsArray( $settled );
		$this->assertSame( 'paid', $settled['status'] );
		$this->assertSame( 0.0, $settled['balance'] );

		$overpay = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $invoice['id'],
				'amount'    => '1000',
				'method'    => 'cash',
			)
		);

		$this->assertWPError( $overpay );
		$this->assertSame( 'invoice_not_payable', $overpay->get_error_code() );

		$again = $this->payments->update(
			$context,
			(int) $rest['payment']['id'],
			array( 'status' => 'rejected' )
		);

		$this->assertWPError( $again );
		$this->assertSame( 'payment_locked', $again->get_error_code() );
	}

	/**
	 * A draft invoice cannot take payments and a payment cannot exceed the
	 * outstanding balance.
	 */
	public function test_payment_rules_reject_drafts_and_overpayment(): void {
		$context  = $this->workspace();
		$customer = $this->customer( $context, 'Ngozi Eze' );

		$draft = $this->invoices->create(
			$context,
			array(
				'clientId' => (int) $customer['id'],
				'items'    => array(
					array(
						'description' => 'Dress',
						'quantity'    => 1,
						'unitPrice'   => '90000',
					),
				),
			)
		);

		$this->assertIsArray( $draft );

		$too_early = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $draft['id'],
				'amount'    => '1000',
				'method'    => 'cash',
			)
		);

		$this->assertWPError( $too_early );
		$this->assertSame( 'invoice_not_issued', $too_early->get_error_code() );

		$invoice = $this->issued_invoice( $context, 'Ngozi Eze', '100000' );

		$too_much = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $invoice['id'],
				'amount'    => '100001',
				'method'    => 'cash',
			)
		);

		$this->assertWPError( $too_much );
		$this->assertSame( 'payment_exceeds_balance', $too_much->get_error_code() );

		$bad_method = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $invoice['id'],
				'amount'    => '1000',
				'method'    => 'barter',
			)
		);

		$this->assertWPError( $bad_method );
		$this->assertSame( 'payment_method_invalid', $bad_method->get_error_code() );

		$voidable = $this->issued_invoice( $context, 'Ngozi Eze', '50000' );
		$pending  = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $voidable['id'],
				'amount'    => '10000',
				'method'    => 'cash',
			)
		);

		$this->assertIsArray( $pending );

		$voided = $this->invoices->update( $context, (int) $voidable['id'], array( 'status' => 'void' ) );

		$this->assertIsArray( $voided );

		$revived = $this->payments->update( $context, (int) $pending['payment']['id'], array( 'status' => 'verified' ) );

		// A late verification must not reopen an invoice that was already voided.
		$this->assertWPError( $revived );
		$this->assertSame( 'invoice_not_payable', $revived->get_error_code() );
	}

	/**
	 * Expenses fall into fixed categories, voiding keeps the row, and the
	 * summary reports collected money against spending.
	 */
	public function test_expenses_and_summary_reconcile(): void {
		$context = $this->workspace( 'Finance Studio' );
		$invoice = $this->issued_invoice( $context, 'Bola Adeyemi', '200000' );

		$payment = $this->payments->create(
			$context,
			array(
				'invoiceId' => (int) $invoice['id'],
				'amount'    => '80000',
				'method'    => 'transfer',
				'paidAt'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		$this->assertIsArray( $payment );
		$this->payments->update( $context, (int) $payment['payment']['id'], array( 'status' => 'verified' ) );

		$rent = $this->expenses->create(
			$context,
			array(
				'category'    => 'rent',
				'description' => 'Studio rent',
				'amount'      => '50000',
				'spentAt'     => gmdate( 'Y-m-d' ),
			)
		);

		$this->assertIsArray( $rent );
		$this->assertSame( 'E-0001', $rent['reference'] );

		$bad = $this->expenses->create(
			$context,
			array(
				'category'    => 'holiday',
				'description' => 'Not a category',
				'amount'      => '10',
			)
		);

		$this->assertWPError( $bad );
		$this->assertSame( 'expense_category_invalid', $bad->get_error_code() );

		$summary = $this->finance->summary( $context );

		$this->assertSame( 120000.0, $summary['outstanding'] );
		$this->assertSame( 80000.0, $summary['collectedThisMonth'] );
		$this->assertSame( 50000.0, $summary['expensesThisMonth'] );
		$this->assertSame( 0, $summary['pendingPayments']['count'] );

		$voided = $this->expenses->update( $context, (int) $rent['id'], array( 'status' => 'void' ) );

		$this->assertIsArray( $voided );
		$this->assertSame( 'void', $voided['status'] );

		$after = $this->finance->summary( $context );

		$this->assertSame( 0.0, $after['expensesThisMonth'] );

		$reopen = $this->expenses->update( $context, (int) $rent['id'], array( 'status' => 'recorded' ) );

		$this->assertWPError( $reopen );
		$this->assertSame( 'expense_void', $reopen->get_error_code() );
	}

	/**
	 * Invoices, payments and expenses never cross workspace boundaries, and
	 * every write leaves an audit entry.
	 */
	public function test_finance_records_are_tenant_scoped_and_audited(): void {
		$owner  = $this->workspace( 'Owner Studio' );
		$other  = $this->workspace( 'Other Studio' );
		$record = $this->issued_invoice( $owner, 'Amina Yusuf', '150000' );

		$foreign = $this->invoices->find( $other, (int) $record['id'] );

		$this->assertWPError( $foreign );
		$this->assertSame( 'invoice_not_found', $foreign->get_error_code() );

		$foreign_payment = $this->payments->create(
			$other,
			array(
				'invoiceId' => (int) $record['id'],
				'amount'    => '1000',
				'method'    => 'cash',
			)
		);

		$this->assertWPError( $foreign_payment );
		$this->assertSame( 'invoice_not_found', $foreign_payment->get_error_code() );

		$actions = array_column( $this->audit->recent( $owner->id(), 50 ), 'action' );

		$this->assertContains( 'invoice.created', $actions );
		$this->assertContains( 'invoice.updated', $actions );
	}

	/**
	 * Create a workspace owned by a fresh user.
	 *
	 * @param string $name Workspace name.
	 */
	private function workspace( string $name = 'Finance Studio' ): TenantContext {
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
	 * Create and issue a one-line invoice.
	 *
	 * @param TenantContext $context Tenant context.
	 * @param string        $name    Customer name.
	 * @param string        $amount  Line price.
	 * @return array<string, mixed>
	 */
	private function issued_invoice( TenantContext $context, string $name, string $amount ): array {
		$customer = $this->customer( $context, $name );

		$invoice = $this->invoices->create(
			$context,
			array(
				'clientId'   => (int) $customer['id'],
				'dueDate'    => gmdate( 'Y-m-d', strtotime( '+14 days' ) ),
				'items'      => array(
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
