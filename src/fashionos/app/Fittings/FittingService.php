<?php
/**
 * Fitting and alteration workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Fittings;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\FittingRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Transaction;
use FashionOS\Production\Staff;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fitting appointments and the alterations they produce.
 *
 * A fitting belongs to one garment, which keeps the jacket's appointment
 * separate from the trousers'. Alterations can cite the fitting that produced
 * them or be raised directly against a garment.
 */
final class FittingService {

	/**
	 * Allowed fitting statuses.
	 */
	public const STATUSES = array( 'scheduled', 'completed', 'missed', 'cancelled' );

	/**
	 * Allowed alteration statuses.
	 */
	public const ALTERATION_STATUSES = array( 'open', 'in_progress', 'completed', 'cancelled' );

	/**
	 * Allowed fitting status moves.
	 *
	 * A completed fitting is final; a missed or cancelled one can be rebooked,
	 * which returns it to `scheduled` with a new time.
	 *
	 * @var array<string, string[]>
	 */
	private const TRANSITIONS = array(
		'scheduled' => array( 'completed', 'missed', 'cancelled' ),
		'missed'    => array( 'scheduled', 'cancelled' ),
		'cancelled' => array( 'scheduled' ),
		'completed' => array(),
	);

	/**
	 * Fitting reads and writes.
	 *
	 * @var FittingRepository
	 */
	private FittingRepository $fittings;

	/**
	 * Garment reads.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $orders;

	/**
	 * Assignee validation.
	 *
	 * @var Staff
	 */
	private Staff $staff;

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
	 * @param FittingRepository  $fittings     Fitting reads and writes.
	 * @param OrderRepository    $orders       Garment reads.
	 * @param Staff              $staff        Assignee validation.
	 * @param AuditLogRepository $audit        Audit writes.
	 * @param Transaction        $transactions Transaction runner.
	 */
	public function __construct(
		FittingRepository $fittings,
		OrderRepository $orders,
		Staff $staff,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->fittings     = $fittings;
		$this->orders       = $orders;
		$this->staff        = $staff;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Paginated fittings.
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

		$upcoming = filter_var( $query['upcoming'] ?? false, FILTER_VALIDATE_BOOLEAN );

		$result = $this->fittings->search(
			$context->id(),
			array(
				'status'        => $upcoming ? 'scheduled' : $status,
				'order_item_id' => max( 0, (int) ( $query['orderItemId'] ?? 0 ) ),
				'upcoming'      => $upcoming,
				'page'          => $page,
				'per_page'      => $per_page,
			)
		);

		return array(
			'fittings'   => array_map( array( FittingPresenter::class, 'fitting' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One fitting.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Fitting id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function find( TenantContext $context, int $id ) {
		$row = $this->fittings->find( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That fitting could not be found.', 'fashionos' ), 'fitting_not_found' );
		}

		return FittingPresenter::fitting( $row );
	}

	/**
	 * Schedule a fitting.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$item_id = (int) ( $input['orderItemId'] ?? 0 );

		if ( $item_id <= 0 ) {
			return Rest::invalid( 'fitting_garment_required', __( 'Choose the garment this fitting is for.', 'fashionos' ) );
		}

		if ( null === $this->orders->find_item( $context->id(), $item_id ) ) {
			return Rest::not_found( __( 'That garment could not be found.', 'fashionos' ), 'order_item_not_found' );
		}

		$scheduled_at = $this->normalise_datetime( $input['scheduledAt'] ?? '', 'fitting_scheduled_at_invalid', true );

		if ( $scheduled_at instanceof WP_Error ) {
			return $scheduled_at;
		}

		$assignee = $this->staff->normalise( $context->id(), $input['assignedTo'] ?? 0, 'fitting_assignee_invalid' );

		if ( $assignee instanceof WP_Error ) {
			return $assignee;
		}

		$location = $this->normalise_location( $input['location'] ?? '' );

		if ( $location instanceof WP_Error ) {
			return $location;
		}

		$notes = $this->normalise_text( $input['notes'] ?? '', 'fitting_notes_too_long' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$fitting_id = $this->fittings->create(
			array(
				'tenant_id'     => $context->id(),
				'order_item_id' => $item_id,
				'assigned_to'   => $assignee,
				'scheduled_at'  => $scheduled_at,
				'status'        => 'scheduled',
				'location'      => $location,
				'notes'         => $notes,
				'outcome'       => '',
				'created_by'    => $context->user_id(),
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);

		if ( 0 === $fitting_id ) {
			return Rest::invalid( 'fitting_create_failed', __( 'The fitting could not be scheduled. Please try again.', 'fashionos' ), 500 );
		}

		$this->audit->record(
			$context->id(),
			$context->user_id(),
			'fitting.created',
			'fitting',
			$fitting_id,
			array(
				'orderItemId' => $item_id,
				'scheduledAt' => $scheduled_at,
				'assignedTo'  => $assignee,
			)
		);

		return $this->find( $context, $fitting_id );
	}

	/**
	 * Reschedule, reassign, record the outcome of, or cancel a fitting.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Fitting id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( TenantContext $context, int $id, array $input ) {
		$existing = $this->fittings->find( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That fitting could not be found.', 'fashionos' ), 'fitting_not_found' );
		}

		$previous = (string) $existing['status'];
		$data     = array();
		$changed  = array();

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return Rest::invalid( 'fitting_status_invalid', __( 'Choose a valid fitting status.', 'fashionos' ) );
			}

			if ( $status !== $previous && ! in_array( $status, self::TRANSITIONS[ $previous ] ?? array(), true ) ) {
				return Rest::invalid(
					'fitting_transition_invalid',
					__( 'That fitting cannot move to the requested status.', 'fashionos' ),
					409
				);
			}

			$data['status'] = $status;
		}

		if ( array_key_exists( 'scheduledAt', $input ) ) {
			$scheduled_at = $this->normalise_datetime( $input['scheduledAt'], 'fitting_scheduled_at_invalid', true );

			if ( $scheduled_at instanceof WP_Error ) {
				return $scheduled_at;
			}

			$data['scheduled_at'] = $scheduled_at;
		}

		if ( array_key_exists( 'assignedTo', $input ) ) {
			$assignee = $this->staff->normalise( $context->id(), $input['assignedTo'], 'fitting_assignee_invalid' );

			if ( $assignee instanceof WP_Error ) {
				return $assignee;
			}

			$data['assigned_to'] = $assignee;
		}

		if ( array_key_exists( 'location', $input ) ) {
			$location = $this->normalise_location( $input['location'] );

			if ( $location instanceof WP_Error ) {
				return $location;
			}

			$data['location'] = $location;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_text( $input['notes'], 'fitting_notes_too_long' );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes'] = $notes;
		}

		if ( array_key_exists( 'outcome', $input ) ) {
			$outcome = $this->normalise_text( $input['outcome'], 'fitting_outcome_too_long' );

			if ( $outcome instanceof WP_Error ) {
				return $outcome;
			}

			$data['outcome'] = $outcome;
		}

		foreach ( array( 'status', 'scheduled_at', 'assigned_to', 'location', 'notes', 'outcome' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return $this->find( $context, $id );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		if ( ! $this->fittings->update( $context->id(), $id, $data ) ) {
			return Rest::invalid( 'fitting_update_failed', __( 'The fitting could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		if ( array() !== $changed ) {
			$metadata = array( 'changed' => $changed );

			if ( isset( $data['status'] ) ) {
				$metadata['status'] = (string) $data['status'];
			}

			$this->audit->record( $context->id(), $context->user_id(), 'fitting.updated', 'fitting', $id, $metadata );
		}

		return $this->find( $context, $id );
	}

	/**
	 * Paginated alterations.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function alterations( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $query['perPage'] ?? 25 ) ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? 'all' ) );

		if ( ! in_array( $status, array_merge( self::ALTERATION_STATUSES, array( 'all' ) ), true ) ) {
			$status = 'all';
		}

		$result = $this->fittings->alterations(
			$context->id(),
			array(
				'status'        => $status,
				'order_item_id' => max( 0, (int) ( $query['orderItemId'] ?? 0 ) ),
				'page'          => $page,
				'per_page'      => $per_page,
			)
		);

		return array(
			'alterations' => array_map( array( FittingPresenter::class, 'alteration' ), $result['rows'] ),
			'page'        => $page,
			'perPage'     => $per_page,
			'total'       => $result['total'],
			'totalPages'  => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * Raise an alteration from a fitting or directly against a garment.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_alteration( TenantContext $context, array $input ) {
		$fitting_id = (int) ( $input['fittingId'] ?? 0 );
		$item_id    = (int) ( $input['orderItemId'] ?? 0 );

		if ( $fitting_id > 0 ) {
			$fitting = $this->fittings->find( $context->id(), $fitting_id );

			if ( null === $fitting ) {
				return Rest::not_found( __( 'That fitting could not be found.', 'fashionos' ), 'fitting_not_found' );
			}

			if ( $item_id > 0 && $item_id !== (int) $fitting['order_item_id'] ) {
				return Rest::invalid( 'alteration_garment_mismatch', __( 'That fitting belongs to a different garment.', 'fashionos' ) );
			}

			$item_id = (int) $fitting['order_item_id'];
		}

		if ( $item_id <= 0 ) {
			return Rest::invalid( 'alteration_garment_required', __( 'Choose the garment this alteration is for.', 'fashionos' ) );
		}

		if ( null === $this->orders->find_item( $context->id(), $item_id ) ) {
			return Rest::not_found( __( 'That garment could not be found.', 'fashionos' ), 'order_item_not_found' );
		}

		$description = $this->normalise_text( $input['description'] ?? '', 'alteration_description_too_long' );

		if ( $description instanceof WP_Error ) {
			return $description;
		}

		if ( '' === $description ) {
			return Rest::invalid( 'alteration_description_required', __( 'Describe the alteration needed.', 'fashionos' ) );
		}

		$due_date = $this->normalise_date( $input['dueDate'] ?? '' );

		if ( $due_date instanceof WP_Error ) {
			return $due_date;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$alteration_id = $this->fittings->create_alteration(
			array(
				'tenant_id'     => $context->id(),
				'order_item_id' => $item_id,
				'fitting_id'    => max( 0, $fitting_id ),
				'description'   => $description,
				'status'        => 'open',
				'due_date'      => $due_date,
				'completed_at'  => '',
				'created_by'    => $context->user_id(),
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);

		if ( 0 === $alteration_id ) {
			return Rest::invalid( 'alteration_create_failed', __( 'The alteration could not be saved. Please try again.', 'fashionos' ), 500 );
		}

		$this->audit->record(
			$context->id(),
			$context->user_id(),
			'alteration.created',
			'alteration',
			$alteration_id,
			array(
				'orderItemId' => $item_id,
				'fittingId'   => max( 0, $fitting_id ),
			)
		);

		return $this->alteration( $context, $alteration_id );
	}

	/**
	 * Update one alteration.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Alteration id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_alteration( TenantContext $context, int $id, array $input ) {
		$existing = $this->fittings->alteration( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That alteration could not be found.', 'fashionos' ), 'alteration_not_found' );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::ALTERATION_STATUSES, true ) ) {
				return Rest::invalid( 'alteration_status_invalid', __( 'Choose a valid alteration status.', 'fashionos' ) );
			}

			$data['status']       = $status;
			$data['completed_at'] = 'completed' === $status ? gmdate( 'Y-m-d H:i:s' ) : '';
		}

		if ( array_key_exists( 'description', $input ) ) {
			$description = $this->normalise_text( $input['description'], 'alteration_description_too_long' );

			if ( $description instanceof WP_Error ) {
				return $description;
			}

			if ( '' === $description ) {
				return Rest::invalid( 'alteration_description_required', __( 'Describe the alteration needed.', 'fashionos' ) );
			}

			$data['description'] = $description;
		}

		if ( array_key_exists( 'dueDate', $input ) ) {
			$due_date = $this->normalise_date( $input['dueDate'] );

			if ( $due_date instanceof WP_Error ) {
				return $due_date;
			}

			$data['due_date'] = $due_date;
		}

		foreach ( array( 'status', 'description', 'due_date', 'completed_at' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return $this->alteration( $context, $id );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		if ( ! $this->fittings->update_alteration( $context->id(), $id, $data ) ) {
			return Rest::invalid( 'alteration_update_failed', __( 'The alteration could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		if ( array() !== $changed ) {
			$this->audit->record( $context->id(), $context->user_id(), 'alteration.updated', 'alteration', $id, array( 'changed' => $changed ) );
		}

		return $this->alteration( $context, $id );
	}

	/**
	 * One alteration.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Alteration id.
	 * @return array<string, mixed>|WP_Error
	 */
	private function alteration( TenantContext $context, int $id ) {
		$row = $this->fittings->alteration( $context->id(), $id );

		return null === $row
			? Rest::not_found( __( 'That alteration could not be found.', 'fashionos' ), 'alteration_not_found' )
			: FittingPresenter::alteration( $row );
	}

	/**
	 * Validate a required or optional date and time.
	 *
	 * @param mixed  $value      Candidate value.
	 * @param string $error_code Error code for an invalid value.
	 * @param bool   $required   Whether a blank value is an error.
	 * @return string|WP_Error
	 */
	private function normalise_datetime( $value, string $error_code, bool $required = false ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $required
				? Rest::invalid( $error_code, __( 'Choose the date and time of the fitting.', 'fashionos' ) )
				: '';
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return Rest::invalid( $error_code, __( 'Choose a valid date and time.', 'fashionos' ) );
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Validate an optional date.
	 *
	 * @param mixed $value Candidate date.
	 * @return string|WP_Error
	 */
	private function normalise_date( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return Rest::invalid( 'alteration_due_date_invalid', __( 'Enter a valid due date.', 'fashionos' ) );
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Validate a fitting location.
	 *
	 * @param mixed $value Candidate location.
	 * @return string|WP_Error
	 */
	private function normalise_location( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$location = sanitize_text_field( $value );

		if ( mb_strlen( $location ) > 191 ) {
			return Rest::invalid( 'fitting_location_too_long', __( 'Fitting locations may be at most 191 characters.', 'fashionos' ) );
		}

		return $location;
	}

	/**
	 * Validate and trim a free-text field.
	 *
	 * @param mixed  $value      Candidate text.
	 * @param string $error_code Error code for overlong text.
	 * @return string|WP_Error
	 */
	private function normalise_text( $value, string $error_code ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$text = sanitize_textarea_field( $value );

		if ( mb_strlen( $text ) > 2000 ) {
			return Rest::invalid( $error_code, __( 'That text may be at most 2000 characters.', 'fashionos' ) );
		}

		return $text;
	}
}
