<?php
/**
 * Finance response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Finance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps invoice, payment and expense rows to public field names.
 *
 * Amounts leave the API as floats rounded to two decimals, the same shape the
 * quotation and order endpoints use, so the client never has to parse money.
 */
final class FinancePresenter {

	/**
	 * Public invoice fields.
	 *
	 * @param array<string, mixed>             $row    Invoice row with joined names.
	 * @param array<int, array<string, mixed>> $items  Invoice lines.
	 * @param array<int, array<string, mixed>> $payments Payments against the invoice.
	 * @return array<string, mixed>
	 */
	public static function invoice( array $row, array $items = array(), array $payments = array() ): array {
		$total       = round( (float) ( $row['total'] ?? 0 ), 2 );
		$amount_paid = round( (float) ( $row['amount_paid'] ?? 0 ), 2 );

		return array(
			'id'               => (int) ( $row['id'] ?? 0 ),
			'uuid'             => (string) ( $row['uuid'] ?? '' ),
			'reference'        => (string) ( $row['reference'] ?? '' ),
			'clientId'         => (int) ( $row['client_id'] ?? 0 ),
			'clientName'       => (string) ( $row['client_name'] ?? '' ),
			'clientReference'  => (string) ( $row['client_reference'] ?? '' ),
			'orderId'          => (int) ( $row['order_id'] ?? 0 ),
			'orderReference'   => (string) ( $row['order_reference'] ?? '' ),
			'currency'         => (string) ( $row['currency'] ?? '' ),
			'status'           => (string) ( $row['status'] ?? 'draft' ),
			'issueDate'        => (string) ( $row['issue_date'] ?? '' ),
			'dueDate'          => (string) ( $row['due_date'] ?? '' ),
			'overdue'          => self::is_overdue( $row ),
			'notes'            => (string) ( $row['notes'] ?? '' ),
			'subtotal'         => round( (float) ( $row['subtotal'] ?? 0 ), 2 ),
			'total'            => $total,
			'amountPaid'       => $amount_paid,
			'balance'          => round( $total - $amount_paid, 2 ),
			'itemCount'        => (int) ( $row['item_count'] ?? count( $items ) ),
			'items'            => array_map( array( self::class, 'item' ), $items ),
			'payments'         => array_map( array( self::class, 'payment' ), $payments ),
			'createdAt'        => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'        => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public invoice line fields.
	 *
	 * @param array<string, mixed> $row Line row.
	 * @return array<string, mixed>
	 */
	public static function item( array $row ): array {
		return array(
			'id'          => (int) ( $row['id'] ?? 0 ),
			'description' => (string) ( $row['description'] ?? '' ),
			'quantity'    => (int) ( $row['quantity'] ?? 1 ),
			'unitPrice'   => round( (float) ( $row['unit_price'] ?? 0 ), 2 ),
			'lineTotal'   => round( (float) ( $row['line_total'] ?? 0 ), 2 ),
		);
	}

	/**
	 * Public payment fields.
	 *
	 * @param array<string, mixed> $row Payment row with joined names.
	 * @return array<string, mixed>
	 */
	public static function payment( array $row ): array {
		return array(
			'id'                => (int) ( $row['id'] ?? 0 ),
			'uuid'              => (string) ( $row['uuid'] ?? '' ),
			'reference'         => (string) ( $row['reference'] ?? '' ),
			'invoiceId'         => (int) ( $row['invoice_id'] ?? 0 ),
			'invoiceReference'  => (string) ( $row['invoice_reference'] ?? '' ),
			'invoiceStatus'     => (string) ( $row['invoice_status'] ?? '' ),
			'clientId'          => (int) ( $row['client_id'] ?? 0 ),
			'clientName'        => (string) ( $row['client_name'] ?? '' ),
			'amount'            => round( (float) ( $row['amount'] ?? 0 ), 2 ),
			'method'            => (string) ( $row['method'] ?? '' ),
			'provider'          => (string) ( $row['provider'] ?? '' ),
			'providerReference' => (string) ( $row['provider_reference'] ?? '' ),
			'status'            => (string) ( $row['status'] ?? 'pending' ),
			'paidAt'            => (string) ( $row['paid_at'] ?? '' ),
			'verifiedBy'        => (int) ( $row['verified_by'] ?? 0 ),
			'verifiedByName'    => (string) ( $row['verified_by_name'] ?? '' ),
			'verifiedAt'        => (string) ( $row['verified_at'] ?? '' ),
			'notes'             => (string) ( $row['notes'] ?? '' ),
			'createdAt'         => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'         => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public expense fields.
	 *
	 * @param array<string, mixed> $row Expense row with the actor name.
	 * @return array<string, mixed>
	 */
	public static function expense( array $row ): array {
		return array(
			'id'            => (int) ( $row['id'] ?? 0 ),
			'uuid'          => (string) ( $row['uuid'] ?? '' ),
			'reference'     => (string) ( $row['reference'] ?? '' ),
			'category'      => (string) ( $row['category'] ?? 'other' ),
			'description'   => (string) ( $row['description'] ?? '' ),
			'amount'        => round( (float) ( $row['amount'] ?? 0 ), 2 ),
			'currency'      => (string) ( $row['currency'] ?? '' ),
			'spentAt'       => (string) ( $row['spent_at'] ?? '' ),
			'status'        => (string) ( $row['status'] ?? 'recorded' ),
			'notes'         => (string) ( $row['notes'] ?? '' ),
			'createdBy'     => (int) ( $row['created_by'] ?? 0 ),
			'createdByName' => (string) ( $row['created_by_name'] ?? '' ),
			'createdAt'     => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'     => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Whether an open invoice is past its due date.
	 *
	 * @param array<string, mixed> $row Invoice row.
	 */
	private static function is_overdue( array $row ): bool {
		$status = (string) ( $row['status'] ?? '' );

		if ( ! in_array( $status, array( 'issued', 'partially_paid' ), true ) ) {
			return false;
		}

		$due_date    = (string) ( $row['due_date'] ?? '' );
		$outstanding = round( (float) ( $row['total'] ?? 0 ) - (float) ( $row['amount_paid'] ?? 0 ), 2 );

		if ( '' === $due_date || $outstanding <= 0 ) {
			return false;
		}

		return $due_date < gmdate( 'Y-m-d' );
	}
}
