<?php
/**
 * Order workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Orders;

use FashionOS\API\Rest;
use FashionOS\Commerce\Money;
use FashionOS\Database\References;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\DesignRepository;
use FashionOS\Database\Repositories\MeasurementRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\QuotationRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orders and the garments inside them.
 *
 * One order can hold several garments, each with its own design reference, its
 * own measurement version and its own production status. Creating an order from
 * a quotation copies the lines and leaves the quotation untouched: the two
 * records keep separate lifecycles.
 */
final class OrderService {

	/**
	 * Allowed order statuses.
	 */
	public const STATUSES = array( 'draft', 'confirmed', 'in_progress', 'ready', 'delivered', 'cancelled' );

	/**
	 * Allowed garment statuses.
	 */
	public const ITEM_STATUSES = array( 'pending', 'cutting', 'sewing', 'fitting', 'ready', 'delivered', 'cancelled' );

	/**
	 * Maximum garments in one order.
	 */
	public const MAX_ITEMS = 50;

	/**
	 * Order reads and writes.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $orders;

	/**
	 * Quotation reads, for orders created from a quotation.
	 *
	 * @var QuotationRepository
	 */
	private QuotationRepository $quotations;

	/**
	 * Design reads, to validate garment references.
	 *
	 * @var DesignRepository
	 */
	private DesignRepository $designs;

	/**
	 * Customer reads.
	 *
	 * @var ClientRepository
	 */
	private ClientRepository $clients;

	/**
	 * Measurement reads, to validate garment versions.
	 *
	 * @var MeasurementRepository
	 */
	private MeasurementRepository $measurements;

	/**
	 * Audit writes.
	 *
	 * @var AuditLogRepository
	 */
	private AuditLogRepository $audit;

	/**
	 * Transaction runner.
	 *
	 * @var Transaction
	 */
	private Transaction $transactions;

	/**
	 * Constructor.
	 *
	 * @param OrderRepository       $orders       Order reads and writes.
	 * @param QuotationRepository   $quotations   Quotation reads.
	 * @param DesignRepository      $designs      Design reads.
	 * @param ClientRepository      $clients      Customer reads.
	 * @param MeasurementRepository $measurements Measurement reads.
	 * @param AuditLogRepository    $audit        Audit writes.
	 * @param Transaction           $transactions Transaction runner.
	 */
	public function __construct(
		OrderRepository $orders,
		QuotationRepository $quotations,
		DesignRepository $designs,
		ClientRepository $clients,
		MeasurementRepository $measurements,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->orders       = $orders;
		$this->quotations   = $quotations;
		$this->designs      = $designs;
		$this->clients      = $clients;
		$this->measurements = $measurements;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Search and paginate orders.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function search( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $query['perPage'] ?? 25 ) ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? 'all' ) );

		if ( ! in_array( $status, array_merge( self::STATUSES, array( 'all' ) ), true ) ) {
			$status = 'all';
		}

		$result = $this->orders->search(
			$context->id(),
			array(
				'search'    => mb_substr( trim( sanitize_text_field( (string) ( $query['search'] ?? '' ) ) ), 0, 100 ),
				'status'    => $status,
				'client_id' => max( 0, (int) ( $query['clientId'] ?? 0 ) ),
				'page'      => $page,
				'per_page'  => $per_page,
			)
		);

		return array(
			'orders'     => array_map( array( OrderPresenter::class, 'to_array' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One order with its garments and timeline.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Order id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function find( TenantContext $context, int $id ) {
		$row = $this->orders->find( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That order could not be found.', 'fashionos' ), 'order_not_found' );
		}

		return OrderPresenter::to_array(
			$row,
			$this->orders->items( $context->id(), $id ),
			$this->orders->events( $context->id(), $id )
		);
	}

	/**
	 * Create an order, optionally copying a quotation's lines.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$quotation    = null;
		$quotation_id = (int) ( $input['quotationId'] ?? 0 );
		$client_id    = (int) ( $input['clientId'] ?? 0 );

		if ( $quotation_id > 0 ) {
			$quotation = $this->quotations->find( $context->id(), $quotation_id );

			if ( null === $quotation ) {
				return Rest::not_found( __( 'That quotation could not be found.', 'fashionos' ), 'quotation_not_found' );
			}

			if ( $client_id > 0 && $client_id !== (int) $quotation['client_id'] ) {
				return Rest::invalid( 'order_client_mismatch', __( 'That quotation belongs to a different customer.', 'fashionos' ) );
			}

			$client_id = (int) $quotation['client_id'];
		}

		if ( $client_id <= 0 ) {
			return Rest::invalid( 'order_client_required', __( 'Choose the customer this order is for.', 'fashionos' ) );
		}

		if ( null === $this->clients->find( $context->id(), $client_id ) ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		$raw_items = $input['items'] ?? null;

		if ( ! is_array( $raw_items ) || array() === $raw_items ) {
			if ( null === $quotation ) {
				return Rest::invalid( 'order_items_required', __( 'Add at least one garment to the order.', 'fashionos' ) );
			}

			$raw_items = $this->items_from_quotation( $context, $quotation );

			if ( $raw_items instanceof WP_Error ) {
				return $raw_items;
			}
		}

		$items = $this->normalise_items( $raw_items, $context->id(), $client_id, $quotation_id );

		if ( $items instanceof WP_Error ) {
			return $items;
		}

		$due_date = $this->normalise_due_date( $input['dueDate'] ?? '' );

		if ( $due_date instanceof WP_Error ) {
			return $due_date;
		}

		$notes = $this->normalise_notes( $input['notes'] ?? '' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$currency = null !== $quotation ? (string) $quotation['currency'] : (string) ( $context->tenant()['currency'] ?? '' );

		$created = $this->transactions->run(
			function () use ( $context, $client_id, $quotation_id, $currency, $due_date, $notes, $items ) {
				$now   = gmdate( 'Y-m-d H:i:s' );
				$total = 0.0;

				foreach ( $items as $item ) {
					$total += (float) $item['line_total'];
				}

				$order_id = $this->orders->create(
					array(
						'tenant_id'    => $context->id(),
						'uuid'         => wp_generate_uuid4(),
						'reference'    => References::placeholder(),
						'client_id'    => $client_id,
						'quotation_id' => max( 0, $quotation_id ),
						'currency'     => $currency,
						'status'       => 'draft',
						'due_date'     => $due_date,
						'notes'        => $notes,
						'total'        => number_format( $total, 2, '.', '' ),
						'created_by'   => $context->user_id(),
						'created_at'   => $now,
						'updated_at'   => $now,
					)
				);

				if ( 0 === $order_id ) {
					return Rest::invalid( 'order_create_failed', __( 'The order could not be created. Please try again.', 'fashionos' ), 500 );
				}

				foreach ( $items as $item ) {
					$item_id = $this->orders->create_item(
						array(
							'tenant_id'               => $context->id(),
							'order_id'                => $order_id,
							'quotation_item_id'       => $item['quotation_item_id'],
							'design_id'               => $item['design_id'],
							'measurement_profile_id'  => $item['measurement_profile_id'],
							'measurement_version_id'  => $item['measurement_version_id'],
							'garment_name'            => $item['garment_name'],
							'garment_category'        => $item['garment_category'],
							'quantity'                => $item['quantity'],
							'unit_price'              => $item['unit_price'],
							'status'                  => 'pending',
							'notes'                   => $item['notes'],
							'sort_order'              => $item['sort_order'],
							'created_at'              => $now,
							'updated_at'              => $now,
						)
					);

					if ( 0 === $item_id ) {
						return Rest::invalid( 'order_create_failed', __( 'The order could not be created. Please try again.', 'fashionos' ), 500 );
					}

					$added = $item['measurement_version_number'] > 0
						? sprintf(
							/* translators: 1: garment name, 2: measurement version number. */
							__( '%1$s added with measurements v%2$d.', 'fashionos' ),
							$item['garment_name'],
							$item['measurement_version_number']
						)
						: sprintf(
							/* translators: %s: garment name. */
							__( '%s added without a measurement reference.', 'fashionos' ),
							$item['garment_name']
						);

					if ( ! $this->record_event( $context, $order_id, $item_id, 'item_added', $added ) ) {
						return Rest::invalid( 'order_create_failed', __( 'The order could not be created. Please try again.', 'fashionos' ), 500 );
					}
				}

				$reference = References::format( 'O', $order_id );

				if ( ! $this->orders->update( $context->id(), $order_id, array( 'reference' => $reference ) ) ) {
					return Rest::invalid( 'order_create_failed', __( 'The order could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$created_event = $this->record_event(
					$context,
					$order_id,
					0,
					'created',
					sprintf(
						/* translators: %d: number of garments. */
						__( 'Order created with %d garments.', 'fashionos' ),
						count( $items )
					)
				);

				if ( ! $created_event ) {
					return Rest::invalid( 'order_create_failed', __( 'The order could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'order.created',
					'order',
					$order_id,
					array(
						'reference'    => $reference,
						'clientId'     => $client_id,
						'quotationId'  => max( 0, $quotation_id ),
						'garments'     => count( $items ),
						'total'        => (float) number_format( $total, 2, '.', '' ),
					)
				);

				return $order_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return $this->find( $context, (int) $created );
	}

	/**
	 * Update an order's status, due date or notes.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Order id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( TenantContext $context, int $id, array $input ) {
		$existing = $this->orders->find( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That order could not be found.', 'fashionos' ), 'order_not_found' );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return Rest::invalid( 'order_status_invalid', __( 'Choose a valid order status.', 'fashionos' ) );
			}

			$data['status'] = $status;
		}

		if ( array_key_exists( 'dueDate', $input ) ) {
			$due_date = $this->normalise_due_date( $input['dueDate'] );

			if ( $due_date instanceof WP_Error ) {
				return $due_date;
			}

			$data['due_date'] = $due_date;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_notes( $input['notes'] );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes'] = $notes;
		}

		foreach ( array( 'status', 'due_date', 'notes' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return $this->find( $context, $id );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$previous_status    = (string) $existing['status'];

		$updated = $this->transactions->run(
			function () use ( $context, $id, $data, $changed, $previous_status ) {
				if ( ! $this->orders->update( $context->id(), $id, $data ) ) {
					return Rest::invalid( 'order_update_failed', __( 'The order could not be updated. Please try again.', 'fashionos' ), 500 );
				}

				if ( array() !== $changed ) {
					$this->audit->record(
						$context->id(),
						$context->user_id(),
						'order.updated',
						'order',
						$id,
						array( 'changed' => $changed )
					);
				}

				if ( isset( $data['status'] ) && $previous_status !== (string) $data['status'] ) {
					$this->record_event(
						$context,
						$id,
						0,
						'status',
						sprintf(
							/* translators: 1: previous status, 2: new status. */
							__( 'Order moved from %1$s to %2$s.', 'fashionos' ),
							$this->status_label( $previous_status ),
							$this->status_label( (string) $data['status'] )
						)
					);

					$this->audit->record(
						$context->id(),
						$context->user_id(),
						'order.status_changed',
						'order',
						$id,
						array(
							'from' => $previous_status,
							'to'   => (string) $data['status'],
						)
					);
				}

				return true;
			}
		);

		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		return $this->find( $context, $id );
	}

	/**
	 * Update one garment's production status or notes.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $item_id Garment id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_item( TenantContext $context, int $item_id, array $input ) {
		$item = $this->orders->find_item( $context->id(), $item_id );

		if ( null === $item ) {
			return Rest::not_found( __( 'That garment could not be found.', 'fashionos' ), 'order_item_not_found' );
		}

		$data           = array();
		$status_changed = false;
		$notes_changed  = false;
		$previous       = (string) $item['status'];

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::ITEM_STATUSES, true ) ) {
				return Rest::invalid( 'order_item_status_invalid', __( 'Choose a valid garment status.', 'fashionos' ) );
			}

			$data['status'] = $status;
			$status_changed = $previous !== $status;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_item_notes( $input['notes'] );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes']  = $notes;
			$notes_changed  = (string) $item['notes'] !== $notes;
		}

		if ( array() === $data ) {
			return $this->find( $context, (int) $item['order_id'] );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$order_id           = (int) $item['order_id'];
		$garment_name       = (string) $item['garment_name'];

		$updated = $this->transactions->run(
			function () use ( $context, $item_id, $order_id, $data, $status_changed, $notes_changed, $previous, $garment_name ) {
				if ( ! $this->orders->update_item( $context->id(), $item_id, $data ) ) {
					return Rest::invalid( 'order_item_update_failed', __( 'The garment could not be updated. Please try again.', 'fashionos' ), 500 );
				}

				$this->orders->update( $context->id(), $order_id, array( 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) );

				if ( $status_changed ) {
					$this->record_event(
						$context,
						$order_id,
						$item_id,
						'item_status',
						sprintf(
							/* translators: 1: garment name, 2: previous status, 3: new status. */
							__( '%1$s moved from %2$s to %3$s.', 'fashionos' ),
							$garment_name,
							$this->status_label( $previous ),
							$this->status_label( (string) $data['status'] )
						)
					);

					$this->audit->record(
						$context->id(),
						$context->user_id(),
						'order.item.status_changed',
						'order_item',
						$item_id,
						array(
							'orderId' => $order_id,
							'from'    => $previous,
							'to'      => (string) $data['status'],
						)
					);
				} elseif ( $notes_changed ) {
					$this->record_event(
						$context,
						$order_id,
						$item_id,
						'item_updated',
						sprintf(
							/* translators: %s: garment name. */
							__( 'Notes updated for %s.', 'fashionos' ),
							$garment_name
						)
					);
				}

				return true;
			}
		);

		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		return $this->find( $context, $order_id );
	}

	/**
	 * Add a free-text note to an order's timeline.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Order id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function add_note( TenantContext $context, int $id, array $input ) {
		if ( null === $this->orders->find( $context->id(), $id ) ) {
			return Rest::not_found( __( 'That order could not be found.', 'fashionos' ), 'order_not_found' );
		}

		$message = $this->normalise_note( $input['message'] ?? '' );

		if ( $message instanceof WP_Error ) {
			return $message;
		}

		if ( ! $this->record_event( $context, $id, 0, 'note', $message ) ) {
			return Rest::invalid( 'order_event_failed', __( 'The note could not be saved. Please try again.', 'fashionos' ), 500 );
		}

		$this->orders->update( $context->id(), $id, array( 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) );

		return $this->find( $context, $id );
	}

	/**
	 * Copy a quotation's lines into order garment payloads.
	 *
	 * @param TenantContext        $context   Verified context.
	 * @param array<string, mixed> $quotation Quotation row.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function items_from_quotation( TenantContext $context, array $quotation ) {
		$lines = $this->quotations->items( $context->id(), (int) $quotation['id'] );

		if ( array() === $lines ) {
			return Rest::invalid( 'order_items_required', __( 'That quotation has no lines to copy. Add garments instead.', 'fashionos' ) );
		}

		return array_map(
			static fn ( array $line ): array => array(
				'quotationItemId'      => (int) $line['id'],
				'garmentName'          => (string) $line['description'],
				'quantity'             => (int) $line['quantity'],
				'unitPrice'            => (string) $line['unit_price'],
				'designId'             => (int) $quotation['design_id'],
			),
			$lines
		);
	}

	/**
	 * Validate garments and resolve their design and measurement references.
	 *
	 * @param array<int, mixed> $items        Candidate garments.
	 * @param int               $tenant_id    Tenant id.
	 * @param int               $client_id    Order customer.
	 * @param int               $quotation_id Quotation the order is being created from, or 0.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function normalise_items( array $items, int $tenant_id, int $client_id, int $quotation_id = 0 ) {
		$items = array_values( $items );

		if ( count( $items ) > self::MAX_ITEMS ) {
			return Rest::invalid(
				'order_too_many_items',
				sprintf(
					/* translators: %d: maximum number of garments. */
					__( 'An order may have at most %d garments.', 'fashionos' ),
					self::MAX_ITEMS
				)
			);
		}

		$normalised = array();

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				return Rest::invalid( 'order_item_invalid', __( 'Every garment needs a name.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$garment_name = trim( sanitize_text_field( (string) ( $item['garmentName'] ?? '' ) ) );

			if ( '' === $garment_name ) {
				return Rest::invalid( 'order_item_name_required', __( 'Give each garment a name.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			if ( mb_strlen( $garment_name ) > 120 ) {
				return Rest::invalid( 'order_item_name_too_long', __( 'Garment names may be at most 120 characters.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$quantity = Money::quantity( $item['quantity'] ?? 1 );

			if ( null === $quantity ) {
				return Rest::invalid( 'order_item_quantity_invalid', __( 'Quantities must be whole numbers between 1 and 9999.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$unit_price = Money::amount( $item['unitPrice'] ?? 0 );

			if ( null === $unit_price ) {
				return Rest::invalid( 'order_item_price_invalid', __( 'Prices must be amounts between 0 and 9,999,999.99.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$notes = $this->normalise_item_notes( $item['notes'] ?? '' );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$quotation_item_id = max( 0, (int) ( $item['quotationItemId'] ?? 0 ) );

			if ( $quotation_item_id > 0 ) {
				$line = $this->quotations->item( $tenant_id, $quotation_item_id );

				if ( null === $line ) {
					return Rest::invalid( 'order_quotation_item_invalid', __( 'That quotation line could not be found.', 'fashionos' ), 422, array( 'index' => $index ) );
				}

				if ( $quotation_id > 0 && (int) $line['quotation_id'] !== $quotation_id ) {
					return Rest::invalid( 'order_quotation_item_invalid', __( 'That quotation line belongs to a different quotation.', 'fashionos' ), 422, array( 'index' => $index ) );
				}
			}

			$design_id  = (int) ( $item['designId'] ?? 0 );
			$version_id = (int) ( $item['measurementVersionId'] ?? 0 );
			$profile_id = 0;
			$category   = '';

			if ( $design_id > 0 ) {
				$design = $this->designs->find( $tenant_id, $design_id );

				if ( null === $design ) {
					return Rest::not_found( __( 'That design could not be found.', 'fashionos' ), 'design_not_found' );
				}

				if ( (int) $design['client_id'] !== $client_id ) {
					return Rest::invalid( 'order_design_mismatch', __( 'That design belongs to a different customer.', 'fashionos' ) );
				}

				$category   = (string) $design['garment_category'];
				$profile_id = (int) $design['measurement_profile_id'];

				if ( $version_id <= 0 ) {
					$version_id = (int) $design['measurement_version_id'];
				}
			}

			$version_number = 0;

			if ( $version_id > 0 ) {
				$version = $this->measurements->version( $tenant_id, $version_id );

				if ( null === $version ) {
					return Rest::not_found( __( 'That measurement version could not be found.', 'fashionos' ), 'measurement_version_not_found' );
				}

				if ( (int) $version['client_id'] !== $client_id ) {
					return Rest::invalid( 'order_measurement_mismatch', __( 'That measurement version belongs to a different customer.', 'fashionos' ) );
				}

				$profile_id     = (int) $version['profile_id'];
				$version_number = (int) $version['version_number'];
			}

			$normalised[] = array(
				'quotation_item_id'        => $quotation_item_id,
				'design_id'                => max( 0, $design_id ),
				'measurement_profile_id'   => max( 0, $profile_id ),
				'measurement_version_id'   => max( 0, $version_id ),
				'measurement_version_number' => $version_number,
				'garment_name'             => $garment_name,
				'garment_category'         => $category,
				'quantity'                 => $quantity,
				'unit_price'               => $unit_price,
				'line_total'               => Money::line_total( $quantity, $unit_price ),
				'notes'                    => $notes,
				'sort_order'               => $index,
			);
		}

		return $normalised;
	}

	/**
	 * Append a timeline entry.
	 *
	 * @param TenantContext $context     Verified context.
	 * @param int           $order_id    Order id.
	 * @param int           $item_id     Garment id, or 0 for the order itself.
	 * @param string        $event_type  Stable event type.
	 * @param string        $message     Human-readable message.
	 */
	private function record_event( TenantContext $context, int $order_id, int $item_id, string $event_type, string $message ): bool {
		return $this->orders->create_event(
			array(
				'tenant_id'     => $context->id(),
				'order_id'      => $order_id,
				'order_item_id' => max( 0, $item_id ),
				'event_type'    => $event_type,
				'message'       => $message,
				'actor_id'      => $context->user_id(),
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Human-readable status name for timeline messages.
	 *
	 * @param string $status Stored status key.
	 */
	private function status_label( string $status ): string {
		$labels = array(
			'draft'       => __( 'Draft', 'fashionos' ),
			'confirmed'   => __( 'Confirmed', 'fashionos' ),
			'in_progress' => __( 'In progress', 'fashionos' ),
			'ready'       => __( 'Ready', 'fashionos' ),
			'delivered'   => __( 'Delivered', 'fashionos' ),
			'cancelled'   => __( 'Cancelled', 'fashionos' ),
			'pending'     => __( 'Pending', 'fashionos' ),
			'cutting'     => __( 'Cutting', 'fashionos' ),
			'sewing'      => __( 'Sewing', 'fashionos' ),
			'fitting'     => __( 'Fitting', 'fashionos' ),
		);

		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Validate the order due date.
	 *
	 * @param mixed $value Candidate date; blank clears it.
	 * @return string|WP_Error
	 */
	private function normalise_due_date( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return Rest::invalid( 'order_due_date_invalid', __( 'Enter a valid due date for the order.', 'fashionos' ) );
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Validate order notes.
	 *
	 * @param mixed $value Candidate notes.
	 * @return string|WP_Error
	 */
	private function normalise_notes( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$notes = sanitize_textarea_field( $value );

		if ( mb_strlen( $notes ) > 2000 ) {
			return Rest::invalid( 'order_notes_too_long', __( 'Order notes may be at most 2000 characters.', 'fashionos' ) );
		}

		return $notes;
	}

	/**
	 * Validate notes attached to one garment.
	 *
	 * @param mixed $value Candidate notes.
	 * @return string|WP_Error
	 */
	private function normalise_item_notes( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$notes = sanitize_textarea_field( $value );

		if ( mb_strlen( $notes ) > 2000 ) {
			return Rest::invalid( 'order_item_notes_too_long', __( 'Garment notes may be at most 2000 characters.', 'fashionos' ) );
		}

		return $notes;
	}

	/**
	 * Validate a free-text timeline note.
	 *
	 * @param mixed $value Candidate note.
	 * @return string|WP_Error
	 */
	private function normalise_note( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return Rest::invalid( 'order_note_required', __( 'Write the note before saving it.', 'fashionos' ) );
		}

		$note = sanitize_textarea_field( $value );

		if ( mb_strlen( $note ) > 2000 ) {
			return Rest::invalid( 'order_note_too_long', __( 'Notes may be at most 2000 characters.', 'fashionos' ) );
		}

		return $note;
	}
}
