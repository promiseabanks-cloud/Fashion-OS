<?php
/**
 * Finance dashboard.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Finance;

use FashionOS\Database\Repositories\ExpenseRepository;
use FashionOS\Database\Repositories\InvoiceRepository;
use FashionOS\Database\Repositories\PaymentRepository;
use FashionOS\Tenancy\TenantContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One read that answers "where does the studio stand financially?".
 *
 * Every number is computed from the invoice and payment rows rather than from
 * a stored total, so the dashboard cannot drift from the ledger. Totals cover
 * the current month where the label says so; balances are always all-time.
 */
final class FinanceService {

	/**
	 * Invoice reads.
	 *
	 * @var InvoiceRepository
	 */
	private InvoiceRepository $invoices;

	/**
	 * Payment reads.
	 *
	 * @var PaymentRepository
	 */
	private PaymentRepository $payments;

	/**
	 * Expense reads.
	 *
	 * @var ExpenseRepository
	 */
	private ExpenseRepository $expenses;

	/**
	 * Constructor.
	 *
	 * @param InvoiceRepository $invoices Invoice reads.
	 * @param PaymentRepository $payments Payment reads.
	 * @param ExpenseRepository $expenses Expense reads.
	 */
	public function __construct(
		InvoiceRepository $invoices,
		PaymentRepository $payments,
		ExpenseRepository $expenses
	) {
		$this->invoices = $invoices;
		$this->payments = $payments;
		$this->expenses = $expenses;
	}

	/**
	 * Financial position for the active workspace.
	 *
	 * @param TenantContext $context Verified context.
	 * @return array<string, mixed>
	 */
	public function summary( TenantContext $context ): array {
		$tenant_id   = $context->id();
		$today       = gmdate( 'Y-m-d' );
		$month_start = gmdate( 'Y-m-01' );
		$month_end   = gmdate( 'Y-m-t' );

		$totals  = $this->invoices->totals( $tenant_id, $today );
		$pending = $this->payments->pending_totals( $tenant_id );

		return array(
			'currency'           => (string) ( $context->tenant()['currency'] ?? '' ),
			'today'              => $today,
			'outstanding'        => $totals['outstanding'],
			'invoicedOpen'       => $totals['invoicedOpen'],
			'overdue'            => $totals['overdue'],
			'drafts'             => $totals['drafts'],
			'paidInvoices'       => $totals['paidCount'],
			'collectedThisMonth' => $this->payments->collected_between( $tenant_id, $month_start, $month_end ),
			'expensesThisMonth'  => $this->expenses->total_between( $tenant_id, $month_start, $month_end ),
			'pendingPayments'    => $pending,
			'recentPayments'     => array_map(
				array( FinancePresenter::class, 'payment' ),
				$this->payments->recent( $tenant_id, 5 )
			),
			'overdueInvoices'    => array_map(
				array( FinancePresenter::class, 'invoice' ),
				$this->invoices->overdue( $tenant_id, $today, 5 )
			),
		);
	}
}
