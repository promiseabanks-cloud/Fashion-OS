<?php
/**
 * Material and inventory workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Inventory;

use FashionOS\API\Rest;
use FashionOS\Commerce\Money;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\MaterialRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Material catalogue, stock movements and garment allocations.
 *
 * Only `designer_supplied` allocations consume stock. `customer_supplied` and
 * `shared_supply` allocations are recorded for material readiness but never
 * change `quantity_on_hand`, because that fabric is not the studio's property.
 * Every stock change is written as a movement, including allocations and
 * returns, so the quantity on hand can always be explained.
 */
final class MaterialService {

	/**
	 * Allowed material types.
	 */
	public const TYPES = array( 'fabric', 'accessory', 'consumable' );

	/**
	 * Allowed units of measure.
	 */
	public const UNITS = array( 'metre', 'yard', 'piece', 'roll', 'spool', 'kilogram', 'litre' );

	/**
	 * Allowed supply sources.
	 */
	public const SOURCES = array( 'customer_supplied', 'designer_supplied', 'shared_supply' );

	/**
	 * Allowed movement types.
	 */
	public const MOVEMENT_TYPES = array( 'in', 'out', 'adjustment' );

	/**
	 * Material reads and writes.
	 *
	 * @var MaterialRepository
	 */
	private MaterialRepository $materials;

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
	 * @param MaterialRepository $materials    Material reads and writes.
	 * @param AuditLogRepository $audit        Audit writes.
	 * @param Transaction        $transactions Transaction runner.
	 */
	public function __construct( MaterialRepository $materials, AuditLogRepository $audit, Transaction $transactions ) {
		$this->materials    = $materials;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Paginated material catalogue.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function search( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $query['perPage'] ?? 25 ) ) );
		$type     = sanitize_key( (string) ( $query['type'] ?? 'all' ) );

		if ( ! in_array( $type, array_merge( self::TYPES, array( 'all' ) ), true ) ) {
			$type = 'all';
		}

		$low_stock = filter_var( $query['lowStock'] ?? false, FILTER_VALIDATE_BOOLEAN );

		$result = $this->materials->search(
			$context->id(),
			array(
				'search'           => mb_substr( trim( sanitize_text_field( (string) ( $query['search'] ?? '' ) ) ), 0, 100 ),
				'type'             => $type,
				'low_stock'        => $low_stock,
				'include_inactive' => filter_var( $query['includeInactive'] ?? false, FILTER_VALIDATE_BOOLEAN ),
				'page'             => $page,
				'per_page'         => $per_page,
			)
		);

		return array(
			'materials'  => array_map( array( MaterialPresenter::class, 'material' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One material with its recent movements.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Material id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function find( TenantContext $context, int $id ) {
		$row = $this->materials->find( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That material could not be found.', 'fashionos' ), 'material_not_found' );
		}

		return MaterialPresenter::material( $row, $this->materials->movements( $context->id(), $id ) );
	}

	/**
	 * Add a material, optionally with opening stock.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$name = trim( sanitize_text_field( (string) ( $input['name'] ?? '' ) ) );

		if ( '' === $name ) {
			return Rest::invalid( 'material_name_required', __( 'Give the material a name.', 'fashionos' ) );
		}

		if ( mb_strlen( $name ) > 120 ) {
			return Rest::invalid( 'material_name_too_long', __( 'Material names may be at most 120 characters.', 'fashionos' ) );
		}

		$type = sanitize_key( (string) ( $input['materialType'] ?? 'fabric' ) );

		if ( ! in_array( $type, self::TYPES, true ) ) {
			return Rest::invalid( 'material_type_invalid', __( 'Choose fabric, accessory or consumable.', 'fashionos' ) );
		}

		$unit = sanitize_key( (string) ( $input['unit'] ?? 'metre' ) );

		if ( ! in_array( $unit, self::UNITS, true ) ) {
			return Rest::invalid( 'material_unit_invalid', __( 'Choose a valid unit of measure.', 'fashionos' ) );
		}

		$reorder = Money::amount( $input['reorderLevel'] ?? 0 );

		if ( null === $reorder ) {
			return Rest::invalid( 'material_reorder_level_invalid', __( 'Enter a reorder level between 0 and 9,999,999.99.', 'fashionos' ) );
		}

		$unit_cost = Money::amount( $input['unitCost'] ?? 0 );

		if ( null === $unit_cost ) {
			return Rest::invalid( 'material_unit_cost_invalid', __( 'Enter a unit cost between 0 and 9,999,999.99.', 'fashionos' ) );
		}

		$notes = $this->normalise_notes( $input['notes'] ?? '', 2000, 'material_notes_too_long' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$opening = '';

		if ( array_key_exists( 'quantityOnHand', $input ) && '' !== $input['quantityOnHand'] && 0 !== $input['quantityOnHand'] ) {
			$opening = Money::measure( $input['quantityOnHand'] );

			if ( null === $opening ) {
				return Rest::invalid( 'material_quantity_invalid', __( 'Opening stock must be a quantity greater than zero.', 'fashionos' ) );
			}
		}

		$is_active = ! array_key_exists( 'isActive', $input ) || (bool) $input['isActive'];

		$created = $this->transactions->run(
			function () use ( $context, $name, $type, $unit, $reorder, $unit_cost, $notes, $opening, $is_active ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				$material_id = $this->materials->create(
					array(
						'tenant_id'        => $context->id(),
						'name'             => $name,
						'material_type'    => $type,
						'unit'             => $unit,
						'quantity_on_hand' => '' === $opening ? '0.00' : $opening,
						'reorder_level'    => $reorder,
						'unit_cost'        => $unit_cost,
						'notes'            => $notes,
						'is_active'        => $is_active ? 1 : 0,
						'created_by'       => $context->user_id(),
						'created_at'       => $now,
						'updated_at'       => $now,
					)
				);

				if ( 0 === $material_id ) {
					return Rest::invalid( 'material_create_failed', __( 'The material could not be created. Please try again.', 'fashionos' ), 500 );
				}

				if ( '' !== $opening && ! $this->materials->create_movement(
					array(
						'tenant_id'      => $context->id(),
						'material_id'    => $material_id,
						'movement_type'  => 'in',
						'quantity'       => $opening,
						'reference_type' => 'opening',
						'reference_id'   => 0,
						'note'           => __( 'Opening stock', 'fashionos' ),
						'actor_id'       => $context->user_id(),
						'created_at'     => $now,
					)
				) ) {
					return Rest::invalid( 'material_create_failed', __( 'The material could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'material.created',
					'material',
					$material_id,
					array(
						'name'    => $name,
						'type'    => $type,
						'unit'    => $unit,
						'opening' => '' === $opening ? 0.0 : (float) $opening,
					)
				);

				return $material_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return $this->find( $context, (int) $created );
	}

	/**
	 * Edit a material's catalogue fields.
	 *
	 * Quantities are deliberately absent: stock only changes through a movement
	 * so every change keeps an actor and a reason.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Material id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( TenantContext $context, int $id, array $input ) {
		$existing = $this->materials->find( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That material could not be found.', 'fashionos' ), 'material_not_found' );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'name', $input ) ) {
			$name = trim( sanitize_text_field( (string) $input['name'] ) );

			if ( '' === $name ) {
				return Rest::invalid( 'material_name_required', __( 'Give the material a name.', 'fashionos' ) );
			}

			if ( mb_strlen( $name ) > 120 ) {
				return Rest::invalid( 'material_name_too_long', __( 'Material names may be at most 120 characters.', 'fashionos' ) );
			}

			$data['name'] = $name;
		}

		if ( array_key_exists( 'materialType', $input ) ) {
			$type = sanitize_key( (string) $input['materialType'] );

			if ( ! in_array( $type, self::TYPES, true ) ) {
				return Rest::invalid( 'material_type_invalid', __( 'Choose fabric, accessory or consumable.', 'fashionos' ) );
			}

			$data['material_type'] = $type;
		}

		if ( array_key_exists( 'unit', $input ) ) {
			$unit = sanitize_key( (string) $input['unit'] );

			if ( ! in_array( $unit, self::UNITS, true ) ) {
				return Rest::invalid( 'material_unit_invalid', __( 'Choose a valid unit of measure.', 'fashionos' ) );
			}

			$data['unit'] = $unit;
		}

		if ( array_key_exists( 'reorderLevel', $input ) ) {
			$reorder = Money::amount( $input['reorderLevel'] );

			if ( null === $reorder ) {
				return Rest::invalid( 'material_reorder_level_invalid', __( 'Enter a reorder level between 0 and 9,999,999.99.', 'fashionos' ) );
			}

			$data['reorder_level'] = $reorder;
		}

		if ( array_key_exists( 'unitCost', $input ) ) {
			$unit_cost = Money::amount( $input['unitCost'] );

			if ( null === $unit_cost ) {
				return Rest::invalid( 'material_unit_cost_invalid', __( 'Enter a unit cost between 0 and 9,999,999.99.', 'fashionos' ) );
			}

			$data['unit_cost'] = $unit_cost;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_notes( $input['notes'], 2000, 'material_notes_too_long' );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes'] = $notes;
		}

		if ( array_key_exists( 'isActive', $input ) ) {
			$data['is_active'] = (bool) $input['isActive'] ? 1 : 0;
		}

		foreach ( array( 'name', 'material_type', 'unit', 'reorder_level', 'unit_cost', 'notes', 'is_active' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return $this->find( $context, $id );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		if ( ! $this->materials->update( $context->id(), $id, $data ) ) {
			return Rest::invalid( 'material_update_failed', __( 'The material could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		if ( array() !== $changed ) {
			$this->audit->record( $context->id(), $context->user_id(), 'material.updated', 'material', $id, array( 'changed' => $changed ) );
		}

		return $this->find( $context, $id );
	}

	/**
	 * Record stock coming in, going out or being adjusted.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Material id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function movement( TenantContext $context, int $id, array $input ) {
		$material = $this->materials->find( $context->id(), $id );

		if ( null === $material ) {
			return Rest::not_found( __( 'That material could not be found.', 'fashionos' ), 'material_not_found' );
		}

		$type = sanitize_key( (string) ( $input['type'] ?? '' ) );

		if ( ! in_array( $type, self::MOVEMENT_TYPES, true ) ) {
			return Rest::invalid( 'material_movement_type_invalid', __( 'Choose a stock in, stock out or adjustment movement.', 'fashionos' ) );
		}

		$note = $this->normalise_notes( $input['note'] ?? '', 191, 'material_movement_note_too_long' );

		if ( $note instanceof WP_Error ) {
			return $note;
		}

		if ( 'adjustment' === $type ) {
			$delta = Money::delta( $input['quantity'] ?? null );

			if ( null === $delta ) {
				return Rest::invalid( 'material_movement_quantity_invalid', __( 'Enter the correction as a positive or negative quantity.', 'fashionos' ) );
			}
		} else {
			$quantity = Money::measure( $input['quantity'] ?? null );

			if ( null === $quantity ) {
				return Rest::invalid( 'material_movement_quantity_invalid', __( 'Enter a quantity greater than zero.', 'fashionos' ) );
			}

			$delta = 'out' === $type ? '-' . $quantity : $quantity;
		}

		if ( (float) $delta < 0 && (float) $material['quantity_on_hand'] + (float) $delta < 0 ) {
			return Rest::invalid(
				'material_insufficient_stock',
				sprintf(
					/* translators: 1: material name, 2: quantity on hand, 3: unit. */
					__( 'Only %2$s %3$s of %1$s is on hand.', 'fashionos' ),
					(string) $material['name'],
					(string) $material['quantity_on_hand'],
					(string) $material['unit']
				),
				409
			);
		}

		$recorded = $this->transactions->run(
			function () use ( $context, $id, $type, $delta, $note ) {
				if ( ! $this->materials->adjust_stock( $context->id(), $id, $delta ) ) {
					return Rest::invalid( 'material_insufficient_stock', __( 'The stock change would leave a negative quantity. Check the amount and try again.', 'fashionos' ), 409 );
				}

				if ( ! $this->materials->create_movement(
					array(
						'tenant_id'      => $context->id(),
						'material_id'    => $id,
						'movement_type'  => $type,
						'quantity'       => $delta,
						'reference_type' => 'manual',
						'reference_id'   => 0,
						'note'           => $note,
						'actor_id'       => $context->user_id(),
						'created_at'     => gmdate( 'Y-m-d H:i:s' ),
					)
				) ) {
					return Rest::invalid( 'material_movement_failed', __( 'The stock movement could not be saved. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'material.stock_changed',
					'material',
					$id,
					array(
						'type'     => $type,
						'quantity' => (float) $delta,
						'note'     => $note,
					)
				);

				return true;
			}
		);

		if ( $recorded instanceof WP_Error ) {
			return $recorded;
		}

		return $this->find( $context, $id );
	}

	/**
	 * Materials allocated to one garment.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $item_id Garment id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function allocations( TenantContext $context, int $item_id ) {
		$garment = $this->materials->garment( $context->id(), $item_id );

		if ( null === $garment ) {
			return Rest::not_found( __( 'That garment could not be found.', 'fashionos' ), 'order_item_not_found' );
		}

		return array(
			'garment'     => MaterialPresenter::garment( $garment ),
			'allocations' => array_map(
				array( MaterialPresenter::class, 'allocation' ),
				$this->materials->allocations( $context->id(), $item_id )
			),
		);
	}

	/**
	 * Allocate material to a garment, naming who supplied it.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $item_id Garment id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function allocate( TenantContext $context, int $item_id, array $input ) {
		$garment = $this->materials->garment( $context->id(), $item_id );

		if ( null === $garment ) {
			return Rest::not_found( __( 'That garment could not be found.', 'fashionos' ), 'order_item_not_found' );
		}

		$material_id = (int) ( $input['materialId'] ?? 0 );
		$material    = $this->materials->find( $context->id(), $material_id );

		if ( null === $material ) {
			return Rest::not_found( __( 'That material could not be found.', 'fashionos' ), 'material_not_found' );
		}

		if ( 1 !== (int) $material['is_active'] ) {
			return Rest::invalid( 'material_inactive', __( 'That material is switched off. Choose an active material.', 'fashionos' ) );
		}

		$quantity = Money::measure( $input['quantity'] ?? null );

		if ( null === $quantity ) {
			return Rest::invalid( 'material_allocation_quantity_invalid', __( 'Enter a quantity greater than zero.', 'fashionos' ) );
		}

		$source = sanitize_key( (string) ( $input['source'] ?? '' ) );

		if ( ! in_array( $source, self::SOURCES, true ) ) {
			return Rest::invalid(
				'material_allocation_source_invalid',
				__( 'Say whether the studio, the customer or both supplied this material.', 'fashionos' )
			);
		}

		$notes = $this->normalise_notes( $input['notes'] ?? '', 2000, 'material_allocation_notes_too_long' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$consumes_stock = 'designer_supplied' === $source;

		if ( $consumes_stock && (float) $material['quantity_on_hand'] < (float) $quantity ) {
			return Rest::invalid(
				'material_insufficient_stock',
				sprintf(
					/* translators: 1: material name, 2: quantity on hand, 3: unit. */
					__( 'Only %2$s %3$s of %1$s is on hand.', 'fashionos' ),
					(string) $material['name'],
					(string) $material['quantity_on_hand'],
					(string) $material['unit']
				),
				409
			);
		}

		$created = $this->transactions->run(
			function () use ( $context, $item_id, $material_id, $quantity, $source, $notes, $consumes_stock, $material ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				if ( $consumes_stock && ! $this->materials->adjust_stock( $context->id(), $material_id, '-' . $quantity ) ) {
					return Rest::invalid( 'material_insufficient_stock', __( 'There is not enough stock left to allocate that quantity.', 'fashionos' ), 409 );
				}

				$allocation_id = $this->materials->create_allocation(
					array(
						'tenant_id'     => $context->id(),
						'order_item_id' => $item_id,
						'material_id'   => $material_id,
						'quantity'      => $quantity,
						'source'        => $source,
						'notes'         => $notes,
						'created_by'    => $context->user_id(),
						'created_at'    => $now,
					)
				);

				if ( 0 === $allocation_id ) {
					return Rest::invalid( 'material_allocation_failed', __( 'The material could not be allocated. Please try again.', 'fashionos' ), 500 );
				}

				if ( $consumes_stock && ! $this->materials->create_movement(
					array(
						'tenant_id'      => $context->id(),
						'material_id'    => $material_id,
						'movement_type'  => 'allocation',
						'quantity'       => '-' . $quantity,
						'reference_type' => 'order_item',
						'reference_id'   => $item_id,
						'note'           => sprintf(
							/* translators: %s: material name. */
							__( 'Allocated %s', 'fashionos' ),
							(string) $material['name']
						),
						'actor_id'       => $context->user_id(),
						'created_at'     => $now,
					)
				) ) {
					return Rest::invalid( 'material_allocation_failed', __( 'The material could not be allocated. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'material.allocated',
					'order_item',
					$item_id,
					array(
						'materialId' => $material_id,
						'quantity'   => (float) $quantity,
						'source'     => $source,
						'consumesStock' => $consumes_stock,
					)
				);

				return $allocation_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return $this->allocations( $context, $item_id );
	}

	/**
	 * Remove an allocation, returning designer stock when it was consumed.
	 *
	 * @param TenantContext $context       Verified context.
	 * @param int           $allocation_id Allocation id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function release( TenantContext $context, int $allocation_id ) {
		$allocation = $this->materials->allocation( $context->id(), $allocation_id );

		if ( null === $allocation ) {
			return Rest::not_found( __( 'That material allocation could not be found.', 'fashionos' ), 'material_allocation_not_found' );
		}

		$material_id = (int) $allocation['material_id'];
		$item_id     = (int) $allocation['order_item_id'];
		$quantity    = number_format( (float) $allocation['quantity'], 2, '.', '' );
		$source      = (string) $allocation['source'];
		$material    = $this->materials->find( $context->id(), $material_id );

		$released = $this->transactions->run(
			function () use ( $context, $allocation_id, $material_id, $item_id, $quantity, $source, $material ) {
				if ( 'designer_supplied' === $source && ! $this->materials->adjust_stock( $context->id(), $material_id, $quantity ) ) {
					return Rest::invalid( 'material_release_failed', __( 'The allocation could not be released. Please try again.', 'fashionos' ), 500 );
				}

				if ( 'designer_supplied' === $source && ! $this->materials->create_movement(
					array(
						'tenant_id'      => $context->id(),
						'material_id'    => $material_id,
						'movement_type'  => 'return',
						'quantity'       => $quantity,
						'reference_type' => 'order_item',
						'reference_id'   => $item_id,
						'note'           => sprintf(
							/* translators: %s: material name. */
							__( 'Released allocation of %s', 'fashionos' ),
							null === $material ? '' : (string) $material['name']
						),
						'actor_id'       => $context->user_id(),
						'created_at'     => gmdate( 'Y-m-d H:i:s' ),
					)
				) ) {
					return Rest::invalid( 'material_release_failed', __( 'The allocation could not be released. Please try again.', 'fashionos' ), 500 );
				}

				if ( ! $this->materials->delete_allocation( $context->id(), $allocation_id ) ) {
					return Rest::invalid( 'material_release_failed', __( 'The allocation could not be released. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'material.released',
					'order_item',
					$item_id,
					array(
						'materialId' => $material_id,
						'quantity'   => (float) $quantity,
						'source'     => $source,
					)
				);

				return true;
			}
		);

		if ( $released instanceof WP_Error ) {
			return $released;
		}

		return $this->allocations( $context, $item_id );
	}

	/**
	 * Validate and trim a text field.
	 *
	 * @param mixed  $value      Candidate text.
	 * @param int    $max        Maximum characters.
	 * @param string $error_code Error code for overlong text.
	 * @return string|WP_Error
	 */
	private function normalise_notes( $value, int $max, string $error_code ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$text = sanitize_textarea_field( $value );

		if ( mb_strlen( $text ) > $max ) {
			return Rest::invalid(
				$error_code,
				sprintf(
					/* translators: %d: maximum characters. */
					__( 'Notes may be at most %d characters.', 'fashionos' ),
					$max
				)
			);
		}

		return $text;
	}
}
