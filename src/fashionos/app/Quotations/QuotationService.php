<?php
/**
 * Quotation workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Quotations;

use FashionOS\API\Rest;
use FashionOS\Commerce\Money;
use FashionOS\Database\References;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\DesignRepository;
use FashionOS\Database\Repositories\QuotationRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quotations: a priced offer with its own lifecycle.
 *
 * Totals are never accepted from the browser. Every line total and the
 * quotation subtotal are computed here from quantity and unit price, and the
 * lines may only be replaced while the quotation is still a draft.
 */
final class QuotationService {

	/**
	 * Allowed quotation statuses.
	 */
	public const STATUSES = array( 'draft', 'sent', 'accepted', 'declined', 'expired' );

	/**
	 * Maximum lines in one quotation.
	 */
	public const MAX_ITEMS = 50;

	/**
	 * Quotation reads and writes.
	 *
	 * @var QuotationRepository
	 */
	private QuotationRepository $quotations;

	/**
	 * Design reads, to validate an optional reference.
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
	 * @param QuotationRepository $quotations Quotation reads and writes.
	 * @param DesignRepository    $designs    Design reads.
	 * @param ClientRepository    $clients    Customer reads.
	 * @param AuditLogRepository  $audit      Audit writes.
	 * @param Transaction         $transactions Transaction runner.
	 */
	public function __construct(
		QuotationRepository $quotations,
		DesignRepository $designs,
		ClientRepository $clients,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->quotations   = $quotations;
		$this->designs      = $designs;
		$this->clients      = $clients;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Search and paginate quotations.
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

		$result = $this->quotations->search(
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
			'quotations' => array_map( array( QuotationPresenter::class, 'to_array' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One quotation with its line items.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Quotation id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function find( TenantContext $context, int $id ) {
		$row = $this->quotations->find( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That quotation could not be found.', 'fashionos' ), 'quotation_not_found' );
		}

		return QuotationPresenter::to_array( $row, $this->quotations->items( $context->id(), $id ) );
	}

	/**
	 * Create a quotation.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$client_id = (int) ( $input['clientId'] ?? 0 );

		if ( $client_id <= 0 ) {
			return Rest::invalid( 'quotation_client_required', __( 'Choose the customer this quotation is for.', 'fashionos' ) );
		}

		if ( null === $this->clients->find( $context->id(), $client_id ) ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		$design_id = (int) ( $input['designId'] ?? 0 );

		if ( $design_id > 0 ) {
			$design = $this->designs->find( $context->id(), $design_id );

			if ( null === $design ) {
				return Rest::not_found( __( 'That design could not be found.', 'fashionos' ), 'design_not_found' );
			}

			if ( (int) $design['client_id'] !== $client_id ) {
				return Rest::invalid( 'quotation_design_mismatch', __( 'That design belongs to a different customer.', 'fashionos' ) );
			}
		}

		$priced = $this->normalise_items( $input['items'] ?? null );

		if ( $priced instanceof WP_Error ) {
			return $priced;
		}

		$valid_until = $this->normalise_date( $input['validUntil'] ?? '' );

		if ( $valid_until instanceof WP_Error ) {
			return $valid_until;
		}

		$notes = $this->normalise_notes( $input['notes'] ?? '' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$currency = (string) ( $context->tenant()['currency'] ?? '' );

		$created = $this->transactions->run(
			function () use ( $context, $client_id, $design_id, $priced, $valid_until, $notes, $currency ) {
				$now        = gmdate( 'Y-m-d H:i:s' );
				$subtotal   = number_format( $priced['subtotal'], 2, '.', '' );

				$quotation_id = $this->quotations->create(
					array(
						'tenant_id'   => $context->id(),
						'uuid'        => wp_generate_uuid4(),
						'reference'   => References::placeholder(),
						'client_id'   => $client_id,
						'design_id'   => $design_id,
						'currency'    => $currency,
						'status'      => 'draft',
						'valid_until' => $valid_until,
						'notes'       => $notes,
						'subtotal'    => $subtotal,
						'total'       => $subtotal,
						'created_by'  => $context->user_id(),
						'created_at'  => $now,
						'updated_at'  => $now,
					)
				);

				if ( 0 === $quotation_id ) {
					return Rest::invalid( 'quotation_create_failed', __( 'The quotation could not be created. Please try again.', 'fashionos' ), 500 );
				}

				if ( ! $this->quotations->replace_items( $context->id(), $quotation_id, $priced['items'] ) ) {
					return Rest::invalid( 'quotation_create_failed', __( 'The quotation could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$reference = References::format( 'Q', $quotation_id );

				if ( ! $this->quotations->update( $context->id(), $quotation_id, array( 'reference' => $reference ) ) ) {
					return Rest::invalid( 'quotation_create_failed', __( 'The quotation could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'quotation.created',
					'quotation',
					$quotation_id,
					array(
						'reference' => $reference,
						'clientId'  => $client_id,
						'items'     => count( $priced['items'] ),
						'total'     => (float) $subtotal,
					)
				);

				return $quotation_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return $this->find( $context, (int) $created );
	}

	/**
	 * Update a quotation, replacing its lines only while it is a draft.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Quotation id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( TenantContext $context, int $id, array $input ) {
		$existing = $this->quotations->find( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That quotation could not be found.', 'fashionos' ), 'quotation_not_found' );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return Rest::invalid( 'quotation_status_invalid', __( 'Choose a valid quotation status.', 'fashionos' ) );
			}

			$data['status'] = $status;
		}

		if ( array_key_exists( 'validUntil', $input ) ) {
			$valid_until = $this->normalise_date( $input['validUntil'] );

			if ( $valid_until instanceof WP_Error ) {
				return $valid_until;
			}

			$data['valid_until'] = $valid_until;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_notes( $input['notes'] );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes'] = $notes;
		}

		if ( array_key_exists( 'designId', $input ) ) {
			$design_id = (int) $input['designId'];

			if ( $design_id > 0 ) {
				$design = $this->designs->find( $context->id(), $design_id );

				if ( null === $design ) {
					return Rest::not_found( __( 'That design could not be found.', 'fashionos' ), 'design_not_found' );
				}

				if ( (int) $design['client_id'] !== (int) $existing['client_id'] ) {
					return Rest::invalid( 'quotation_design_mismatch', __( 'That design belongs to a different customer.', 'fashionos' ) );
				}
			}

			$data['design_id'] = max( 0, $design_id );
		}

		$priced = null;

		if ( array_key_exists( 'items', $input ) ) {
			if ( 'draft' !== (string) $existing['status'] ) {
				return Rest::invalid(
					'quotation_locked',
					__( 'Only a draft quotation can change its line items. Create a new quotation for a new offer.', 'fashionos' ),
					409
				);
			}

			$priced = $this->normalise_items( $input['items'] );

			if ( $priced instanceof WP_Error ) {
				return $priced;
			}

			$subtotal            = number_format( $priced['subtotal'], 2, '.', '' );
			$data['subtotal']    = $subtotal;
			$data['total']       = $subtotal;
			$changed[]           = 'items';
		}

		foreach ( array( 'status', 'valid_until', 'notes', 'design_id' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return QuotationPresenter::to_array( $existing, $this->quotations->items( $context->id(), $id ) );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		$updated = $this->transactions->run(
			function () use ( $context, $id, $data, $priced, $changed ) {
				if ( ! $this->quotations->update( $context->id(), $id, $data ) ) {
					return Rest::invalid( 'quotation_update_failed', __( 'The quotation could not be updated. Please try again.', 'fashionos' ), 500 );
				}

				if ( null !== $priced && ! $this->quotations->replace_items( $context->id(), $id, $priced['items'] ) ) {
					return Rest::invalid( 'quotation_update_failed', __( 'The quotation could not be updated. Please try again.', 'fashionos' ), 500 );
				}

				if ( array() !== $changed ) {
					$metadata = array( 'changed' => $changed );

					if ( isset( $data['status'] ) ) {
						$metadata['status'] = (string) $data['status'];
					}

					$this->audit->record( $context->id(), $context->user_id(), 'quotation.updated', 'quotation', $id, $metadata );
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
	 * Validate and price quotation lines.
	 *
	 * @param mixed $items Candidate items.
	 * @return array{items: array<int, array<string, mixed>>, subtotal: float}|WP_Error
	 */
	private function normalise_items( $items ) {
		if ( ! is_array( $items ) || array() === $items ) {
			return Rest::invalid( 'quotation_items_required', __( 'Add at least one line to the quotation.', 'fashionos' ) );
		}

		$items = array_values( $items );

		if ( count( $items ) > self::MAX_ITEMS ) {
			return Rest::invalid(
				'quotation_too_many_items',
				sprintf(
					/* translators: %d: maximum number of lines. */
					__( 'A quotation may have at most %d lines.', 'fashionos' ),
					self::MAX_ITEMS
				)
			);
		}

		$normalised = array();
		$subtotal   = 0.0;

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				return Rest::invalid( 'quotation_item_invalid', __( 'Every quotation line needs a description and a price.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$description = trim( sanitize_text_field( (string) ( $item['description'] ?? '' ) ) );

			if ( '' === $description ) {
				return Rest::invalid( 'quotation_item_description_required', __( 'Give each quotation line a description.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			if ( mb_strlen( $description ) > 191 ) {
				return Rest::invalid( 'quotation_item_description_too_long', __( 'Line descriptions may be at most 191 characters.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$quantity = Money::quantity( $item['quantity'] ?? 1 );

			if ( null === $quantity ) {
				return Rest::invalid( 'quotation_item_quantity_invalid', __( 'Quantities must be whole numbers between 1 and 9999.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$unit_price = Money::amount( $item['unitPrice'] ?? 0 );

			if ( null === $unit_price ) {
				return Rest::invalid( 'quotation_item_price_invalid', __( 'Prices must be amounts between 0 and 9,999,999.99.', 'fashionos' ), 422, array( 'index' => $index ) );
			}

			$line_total = Money::line_total( $quantity, $unit_price );
			$subtotal  += (float) $line_total;

			$normalised[] = array(
				'description' => $description,
				'quantity'    => $quantity,
				'unit_price'  => $unit_price,
				'line_total'  => $line_total,
				'sort_order'  => $index,
			);
		}

		return array(
			'items'    => $normalised,
			'subtotal' => round( $subtotal, 2 ),
		);
	}

	/**
	 * Validate an optional date.
	 *
	 * @param mixed $value Candidate date; blank clears it.
	 * @return string|WP_Error
	 */
	private function normalise_date( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return Rest::invalid( 'quotation_valid_until_invalid', __( 'Enter a valid date for the quotation.', 'fashionos' ) );
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Validate the quotation notes.
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
			return Rest::invalid( 'quotation_notes_too_long', __( 'Quotation notes may be at most 2000 characters.', 'fashionos' ) );
		}

		return $notes;
	}
}
