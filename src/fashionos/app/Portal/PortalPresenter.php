<?php
/**
 * Portal response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Portal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps portal rows to the fields a customer is allowed to see.
 *
 * The customer view is deliberately narrower than the studio view: no client
 * ids, no internal notes, no order timeline, no pending payments, no staff
 * names. A field that is absent here cannot leak through a later query change.
 */
final class PortalPresenter {

	/**
	 * Portal session: the studio's branding, the customer's own details and a
	 * small summary of what is open.
	 *
	 * @param array<string, mixed> $tenant  Tenant row.
	 * @param array<string, mixed> $client  Customer row.
	 * @param array<string, mixed> $access  Access-link row.
	 * @param array<string, mixed> $summary Counts and outstanding balance.
	 * @return array<string, mixed>
	 */
	public static function session( array $tenant, array $client, array $access, array $summary ): array {
		return array(
			'studio'   => array(
				'name'     => (string) ( $tenant['name'] ?? '' ),
				'currency' => (string) ( $tenant['currency'] ?? '' ),
			),
			'customer' => array(
				'reference' => (string) ( $client['reference'] ?? '' ),
				'name'      => (string) ( $client['name'] ?? '' ),
				'email'     => (string) ( $client['email'] ?? '' ),
				'phone'     => (string) ( $client['phone'] ?? '' ),
			),
			'access'   => array(
				'expiresAt' => (string) ( $access['expires_at'] ?? '' ),
			),
			'summary'  => array(
				'openOrders'   => (int) ( $summary['openOrders'] ?? 0 ),
				'openInvoices' => (int) ( $summary['openInvoices'] ?? 0 ),
				'outstanding'  => round( (float) ( $summary['outstanding'] ?? 0 ), 2 ),
			),
		);
	}

	/**
	 * One order with its garments.
	 *
	 * @param array<string, mixed>             $row   Order row.
	 * @param array<int, array<string, mixed>> $items Garment rows.
	 * @return array<string, mixed>
	 */
	public static function order( array $row, array $items = array() ): array {
		return array(
			'id'           => (int) ( $row['id'] ?? 0 ),
			'reference'    => (string) ( $row['reference'] ?? '' ),
			'status'       => (string) ( $row['status'] ?? 'draft' ),
			'dueDate'      => (string) ( $row['due_date'] ?? '' ),
			'garmentCount' => (int) ( $row['garment_count'] ?? count( $items ) ),
			'items'        => array_map( array( self::class, 'garment' ), $items ),
			'updatedAt'    => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * One invoice with its lines and its verified payments.
	 *
	 * @param array<string, mixed>             $row      Invoice row.
	 * @param array<int, array<string, mixed>> $items    Invoice lines.
	 * @param array<int, array<string, mixed>> $payments Verified payments.
	 * @return array<string, mixed>
	 */
	public static function invoice( array $row, array $items = array(), array $payments = array() ): array {
		$total       = round( (float) ( $row['total'] ?? 0 ), 2 );
		$amount_paid = round( (float) ( $row['amount_paid'] ?? 0 ), 2 );
		$balance     = round( $total - $amount_paid, 2 );
		$due_date    = (string) ( $row['due_date'] ?? '' );
		$status      = (string) ( $row['status'] ?? '' );

		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'reference'      => (string) ( $row['reference'] ?? '' ),
			'orderReference' => (string) ( $row['order_reference'] ?? '' ),
			'currency'       => (string) ( $row['currency'] ?? '' ),
			'status'         => $status,
			'issueDate'      => (string) ( $row['issue_date'] ?? '' ),
			'dueDate'        => $due_date,
			'overdue'        => in_array( $status, array( 'issued', 'partially_paid' ), true )
				&& '' !== $due_date
				&& $balance > 0
				&& $due_date < gmdate( 'Y-m-d' ),
			'notes'          => (string) ( $row['notes'] ?? '' ),
			'total'          => $total,
			'amountPaid'     => $amount_paid,
			'balance'        => $balance,
			'items'          => array_map( array( self::class, 'line' ), $items ),
			'payments'       => array_map( array( self::class, 'payment' ), $payments ),
			'issuedAt'       => (string) ( $row['created_at'] ?? '' ),
		);
	}

	/**
	 * One staff-issued access link.
	 *
	 * The token and its hash never appear here: the link is shown once when it
	 * is created and cannot be recovered afterwards.
	 *
	 * @param array<string, mixed> $row Access-link row.
	 * @return array<string, mixed>
	 */
	public static function access( array $row ): array {
		$expires_at = (string) ( $row['expires_at'] ?? '' );
		$revoked_at = (string) ( $row['revoked_at'] ?? '' );

		return array(
			'id'         => (int) ( $row['id'] ?? 0 ),
			'label'      => (string) ( $row['label'] ?? '' ),
			'expiresAt'  => $expires_at,
			'revokedAt'  => $revoked_at,
			'lastUsedAt' => (string) ( $row['last_used_at'] ?? '' ),
			'isActive'   => '' === $revoked_at
				&& ( '' === $expires_at || $expires_at >= gmdate( 'Y-m-d H:i:s' ) ),
			'createdAt'  => (string) ( $row['created_at'] ?? '' ),
		);
	}

	/**
	 * One garment inside a portal order.
	 *
	 * @param array<string, mixed> $row Garment row.
	 * @return array<string, mixed>
	 */
	private static function garment( array $row ): array {
		return array(
			'id'              => (int) ( $row['id'] ?? 0 ),
			'garmentName'     => (string) ( $row['garment_name'] ?? '' ),
			'garmentCategory' => (string) ( $row['garment_category'] ?? '' ),
			'quantity'        => (int) ( $row['quantity'] ?? 1 ),
			'status'          => (string) ( $row['status'] ?? 'pending' ),
		);
	}

	/**
	 * One invoice line.
	 *
	 * @param array<string, mixed> $row Line row.
	 * @return array<string, mixed>
	 */
	private static function line( array $row ): array {
		return array(
			'description' => (string) ( $row['description'] ?? '' ),
			'quantity'    => (int) ( $row['quantity'] ?? 1 ),
			'unitPrice'   => round( (float) ( $row['unit_price'] ?? 0 ), 2 ),
			'lineTotal'   => round( (float) ( $row['line_total'] ?? 0 ), 2 ),
		);
	}

	/**
	 * One verified payment.
	 *
	 * @param array<string, mixed> $row Payment row.
	 * @return array<string, mixed>
	 */
	private static function payment( array $row ): array {
		return array(
			'reference'         => (string) ( $row['reference'] ?? '' ),
			'amount'            => round( (float) ( $row['amount'] ?? 0 ), 2 ),
			'method'            => (string) ( $row['method'] ?? '' ),
			'providerReference' => (string) ( $row['provider_reference'] ?? '' ),
			'paidAt'            => (string) ( $row['paid_at'] ?? '' ),
		);
	}
}
