<?php
/**
 * Customer portal reads.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Portal;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\InvoiceRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\PaymentRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a customer may read about their own orders and money.
 *
 * Every query starts from the customer id in the portal context, so a token
 * cannot be pointed at another customer. Draft orders, draft and void invoices
 * and unverified payments are not "hidden with a flag": they are excluded by
 * the query and by the status checks here, and a record outside that set
 * answers 404 rather than 403 so its existence is not confirmed.
 */
final class PortalService {

	/**
	 * Invoice statuses a customer may see.
	 */
	public const VISIBLE_INVOICE_STATUSES = array( 'issued', 'partially_paid', 'paid' );

	/**
	 * Order reads.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $orders;

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
	 * Constructor.
	 *
	 * @param OrderRepository   $orders   Order reads.
	 * @param InvoiceRepository $invoices Invoice reads.
	 * @param PaymentRepository $payments Payment reads.
	 */
	public function __construct(
		OrderRepository $orders,
		InvoiceRepository $invoices,
		PaymentRepository $payments
	) {
		$this->orders   = $orders;
		$this->invoices = $invoices;
		$this->payments = $payments;
	}

	/**
	 * The portal session for the resolved customer.
	 *
	 * @param PortalContext $context Portal context.
	 * @return array<string, mixed>
	 */
	public function session( PortalContext $context ): array {
		$invoice_summary = $this->invoices->client_summary( $context->tenant_id(), $context->client_id() );

		return PortalPresenter::session(
			$context->tenant(),
			$context->client(),
			$context->access(),
			array(
				'openOrders'   => $this->orders->active_count( $context->tenant_id(), $context->client_id() ),
				'openInvoices' => $invoice_summary['open'],
				'outstanding'  => $invoice_summary['outstanding'],
			)
		);
	}

	/**
	 * The customer's visible orders.
	 *
	 * @param PortalContext        $context Portal context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function orders( PortalContext $context, array $query ): array {
		list( $page, $per_page ) = $this->pagination( $query );

		$result = $this->orders->for_client(
			$context->tenant_id(),
			$context->client_id(),
			$page,
			$per_page
		);

		return array(
			'orders'     => array_map( array( PortalPresenter::class, 'order' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One of the customer's orders, with its garments.
	 *
	 * @param PortalContext $context Portal context.
	 * @param int           $id      Order id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function order( PortalContext $context, int $id ) {
		$row = $this->orders->find( $context->tenant_id(), $id );

		if ( null === $row || (int) $row['client_id'] !== $context->client_id() || 'draft' === (string) $row['status'] ) {
			return Rest::not_found( __( 'That order could not be found.', 'fashionos' ), 'portal_order_not_found' );
		}

		return PortalPresenter::order(
			$row,
			$this->orders->items( $context->tenant_id(), $id )
		);
	}

	/**
	 * The customer's issued invoices.
	 *
	 * @param PortalContext        $context Portal context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function invoices( PortalContext $context, array $query ): array {
		list( $page, $per_page ) = $this->pagination( $query );

		$result = $this->invoices->for_client(
			$context->tenant_id(),
			$context->client_id(),
			$page,
			$per_page
		);

		$invoices = array_map(
			static fn ( array $row ): array => PortalPresenter::invoice( $row ),
			$result['rows']
		);

		return array(
			'invoices'   => $invoices,
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One of the customer's invoices, with lines and verified payments.
	 *
	 * @param PortalContext $context Portal context.
	 * @param int           $id      Invoice id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function invoice( PortalContext $context, int $id ) {
		$row = $this->invoices->find( $context->tenant_id(), $id );

		if (
			null === $row
			|| (int) $row['client_id'] !== $context->client_id()
			|| ! in_array( (string) $row['status'], self::VISIBLE_INVOICE_STATUSES, true )
		) {
			return Rest::not_found( __( 'That invoice could not be found.', 'fashionos' ), 'portal_invoice_not_found' );
		}

		// Read the paid amount from the ledger, so the customer view matches the studio's.
		$row['amount_paid'] = number_format(
			$this->invoices->verified_total( $context->tenant_id(), $id ),
			2,
			'.',
			''
		);

		$verified = array_values(
			array_filter(
				$this->payments->for_invoice( $context->tenant_id(), $id ),
				static fn ( array $payment ): bool => 'verified' === (string) $payment['status']
			)
		);

		return PortalPresenter::invoice(
			$row,
			$this->invoices->items( $context->tenant_id(), $id ),
			$verified
		);
	}

	/**
	 * Read the page size from a query, within the list limits.
	 *
	 * @param array<string, mixed> $query Raw query parameters.
	 * @return array{0: int, 1: int}
	 */
	private function pagination( array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 50, (int) ( $query['perPage'] ?? 20 ) ) );

		return array( $page, $per_page );
	}
}
