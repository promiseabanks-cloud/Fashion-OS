<?php
/**
 * Production workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Production;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\FittingRepository;
use FashionOS\Database\Repositories\MaterialRepository;
use FashionOS\Database\Repositories\OrderRepository;
use FashionOS\Database\Repositories\ProductionRepository;
use FashionOS\Database\Repositories\QualityRepository;
use FashionOS\Database\Transaction;
use FashionOS\Fittings\FittingPresenter;
use FashionOS\Inventory\MaterialPresenter;
use FashionOS\Quality\QualityPresenter;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Production stages, garment jobs, tasks and the dashboard.
 *
 * Jobs hang off a garment rather than an order, so each piece can be assigned,
 * scheduled and completed on its own. Stage names and order are workspace data:
 * the defaults are only a starting point, seeded once per workspace.
 */
final class ProductionService {

	/**
	 * Allowed job statuses.
	 */
	public const JOB_STATUSES = array( 'queued', 'in_progress', 'blocked', 'completed', 'cancelled' );

	/**
	 * Allowed task statuses.
	 */
	public const TASK_STATUSES = array( 'pending', 'in_progress', 'done', 'cancelled' );

	/**
	 * Allowed job status moves.
	 *
	 * Completed and cancelled jobs are terminal; anything else can be paused or
	 * cancelled. Reopening a finished garment is a deliberate new job, not a
	 * silent status edit, so the history stays truthful.
	 *
	 * @var array<string, string[]>
	 */
	private const JOB_TRANSITIONS = array(
		'queued'      => array( 'in_progress', 'blocked', 'cancelled' ),
		'in_progress' => array( 'blocked', 'completed', 'cancelled' ),
		'blocked'     => array( 'in_progress', 'cancelled' ),
		'completed'   => array(),
		'cancelled'   => array(),
	);

	/**
	 * Production reads and writes.
	 *
	 * @var ProductionRepository
	 */
	private ProductionRepository $production;

	/**
	 * Garment reads.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $orders;

	/**
	 * Fitting reads, for the dashboard.
	 *
	 * @var FittingRepository
	 */
	private FittingRepository $fittings;

	/**
	 * Quality reads, for the dashboard.
	 *
	 * @var QualityRepository
	 */
	private QualityRepository $quality;

	/**
	 * Material reads, for the dashboard.
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
	 * Assignee validation.
	 *
	 * @var Staff
	 */
	private Staff $staff;

	/**
	 * Constructor.
	 *
	 * @param ProductionRepository $production   Production reads and writes.
	 * @param OrderRepository      $orders       Garment reads.
	 * @param FittingRepository    $fittings     Fitting reads.
	 * @param QualityRepository    $quality      Quality reads.
	 * @param MaterialRepository   $materials    Material reads.
	 * @param AuditLogRepository   $audit        Audit writes.
	 * @param Transaction          $transactions Transaction runner.
	 * @param Staff                $staff        Assignee validation.
	 */
	public function __construct(
		ProductionRepository $production,
		OrderRepository $orders,
		FittingRepository $fittings,
		QualityRepository $quality,
		MaterialRepository $materials,
		AuditLogRepository $audit,
		Transaction $transactions,
		Staff $staff
	) {
		$this->production   = $production;
		$this->orders       = $orders;
		$this->fittings     = $fittings;
		$this->quality      = $quality;
		$this->materials    = $materials;
		$this->audit        = $audit;
		$this->transactions = $transactions;
		$this->staff        = $staff;
	}

	/**
	 * Stages of the workspace, seeding the starter workflow on first use.
	 *
	 * @param TenantContext $context         Verified context.
	 * @param bool          $include_inactive Also return switched-off stages.
	 * @return array<string, mixed>
	 */
	public function stages( TenantContext $context, bool $include_inactive = false ): array {
		$this->ensure_defaults( $context );

		return array(
			'stages' => array_map(
				array( ProductionPresenter::class, 'stage' ),
				$this->production->stages( $context->id(), $include_inactive )
			),
		);
	}

	/**
	 * Add a stage.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_stage( TenantContext $context, array $input ) {
		$name = trim( sanitize_text_field( (string) ( $input['name'] ?? '' ) ) );

		if ( '' === $name ) {
			return Rest::invalid( 'production_stage_name_required', __( 'Give the stage a name.', 'fashionos' ) );
		}

		if ( mb_strlen( $name ) > 80 ) {
			return Rest::invalid( 'production_stage_name_too_long', __( 'Stage names may be at most 80 characters.', 'fashionos' ) );
		}

		$this->ensure_defaults( $context );

		$slug = sanitize_title( (string) ( $input['slug'] ?? $name ) );

		if ( '' === $slug ) {
			$slug = 'stage-' . wp_generate_password( 6, false );
		}

		if ( null !== $this->production->stage_by_slug( $context->id(), $slug ) ) {
			return Rest::invalid( 'production_stage_slug_exists', __( 'A stage with that name already exists.', 'fashionos' ), 409 );
		}

		$sequence = $this->sequence( $input['sequence'] ?? 0 );

		if ( $sequence instanceof WP_Error ) {
			return $sequence;
		}

		$is_active = ! array_key_exists( 'isActive', $input ) || (bool) $input['isActive'];

		$now      = gmdate( 'Y-m-d H:i:s' );
		$stage_id = $this->production->create_stage(
			array(
				'tenant_id'  => $context->id(),
				'name'       => $name,
				'slug'       => $slug,
				'sequence'   => $sequence,
				'is_active'  => $is_active ? 1 : 0,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		if ( 0 === $stage_id ) {
			return Rest::invalid( 'production_stage_create_failed', __( 'The stage could not be created. Please try again.', 'fashionos' ), 500 );
		}

		$this->audit->record(
			$context->id(),
			$context->user_id(),
			'production.stage.created',
			'production_stage',
			$stage_id,
			array(
				'name' => $name,
				'slug' => $slug,
			)
		);

		$stage = $this->production->stage( $context->id(), $stage_id );

		return null === $stage
			? Rest::invalid( 'production_stage_create_failed', __( 'The stage could not be created. Please try again.', 'fashionos' ), 500 )
			: ProductionPresenter::stage( $stage );
	}

	/**
	 * Rename, reorder, activate or deactivate a stage.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Stage id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_stage( TenantContext $context, int $id, array $input ) {
		$existing = $this->production->stage( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That production stage could not be found.', 'fashionos' ), 'production_stage_not_found' );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'name', $input ) ) {
			$name = trim( sanitize_text_field( (string) $input['name'] ) );

			if ( '' === $name ) {
				return Rest::invalid( 'production_stage_name_required', __( 'Give the stage a name.', 'fashionos' ) );
			}

			if ( mb_strlen( $name ) > 80 ) {
				return Rest::invalid( 'production_stage_name_too_long', __( 'Stage names may be at most 80 characters.', 'fashionos' ) );
			}

			$data['name'] = $name;
		}

		if ( array_key_exists( 'sequence', $input ) ) {
			$sequence = $this->sequence( $input['sequence'] );

			if ( $sequence instanceof WP_Error ) {
				return $sequence;
			}

			$data['sequence'] = $sequence;
		}

		if ( array_key_exists( 'isActive', $input ) ) {
			$data['is_active'] = (bool) $input['isActive'] ? 1 : 0;
		}

		foreach ( array( 'name', 'sequence', 'is_active' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return ProductionPresenter::stage( $existing );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		if ( ! $this->production->update_stage( $context->id(), $id, $data ) ) {
			return Rest::invalid( 'production_stage_update_failed', __( 'The stage could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		if ( array() !== $changed ) {
			$this->audit->record( $context->id(), $context->user_id(), 'production.stage.updated', 'production_stage', $id, array( 'changed' => $changed ) );
		}

		$stage = $this->production->stage( $context->id(), $id );

		return null === $stage
			? Rest::not_found( __( 'That production stage could not be found.', 'fashionos' ), 'production_stage_not_found' )
			: ProductionPresenter::stage( $stage );
	}

	/**
	 * Paginated job list.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function jobs( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $query['perPage'] ?? 25 ) ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? 'all' ) );

		if ( ! in_array( $status, array_merge( self::JOB_STATUSES, array( 'all' ) ), true ) ) {
			$status = 'all';
		}

		$result = $this->production->jobs(
			$context->id(),
			array(
				'search'        => mb_substr( trim( sanitize_text_field( (string) ( $query['search'] ?? '' ) ) ), 0, 100 ),
				'status'        => $status,
				'assigned_to'   => max( 0, (int) ( $query['assignedTo'] ?? 0 ) ),
				'order_item_id' => max( 0, (int) ( $query['orderItemId'] ?? 0 ) ),
				'page'          => $page,
				'per_page'      => $per_page,
			)
		);

		return array(
			'jobs'       => array_map( array( ProductionPresenter::class, 'job' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One job with its tasks.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Job id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function job( TenantContext $context, int $id ) {
		$row = $this->production->job( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That production job could not be found.', 'fashionos' ), 'production_job_not_found' );
		}

		return ProductionPresenter::job( $row, $this->production->tasks( $context->id(), $id ) );
	}

	/**
	 * Plan production for one garment.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_job( TenantContext $context, array $input ) {
		$item_id = (int) ( $input['orderItemId'] ?? 0 );

		if ( $item_id <= 0 ) {
			return Rest::invalid( 'production_garment_required', __( 'Choose the garment to plan production for.', 'fashionos' ) );
		}

		$garment = $this->orders->find_item( $context->id(), $item_id );

		if ( null === $garment ) {
			return Rest::not_found( __( 'That garment could not be found.', 'fashionos' ), 'order_item_not_found' );
		}

		if ( null !== $this->production->job_for_item( $context->id(), $item_id ) ) {
			return Rest::invalid( 'production_job_exists', __( 'That garment already has a production job.', 'fashionos' ), 409 );
		}

		$assignee = $this->staff->normalise( $context->id(), $input['assignedTo'] ?? 0, 'production_assignee_invalid' );

		if ( $assignee instanceof WP_Error ) {
			return $assignee;
		}

		$start_date = $this->normalise_date( $input['startDate'] ?? '', 'production_start_date_invalid' );

		if ( $start_date instanceof WP_Error ) {
			return $start_date;
		}

		$due_date = $this->normalise_date( $input['dueDate'] ?? '', 'production_due_date_invalid' );

		if ( $due_date instanceof WP_Error ) {
			return $due_date;
		}

		$notes = $this->normalise_notes( $input['notes'] ?? '', 'production_notes_too_long' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$order_id     = (int) $garment['order_id'];
		$garment_name = (string) $garment['garment_name'];

		$created = $this->transactions->run(
			function () use ( $context, $item_id, $assignee, $start_date, $due_date, $notes, $order_id, $garment_name ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				$job_id = $this->production->create_job(
					array(
						'tenant_id'     => $context->id(),
						'order_item_id' => $item_id,
						'assigned_to'   => $assignee,
						'status'        => 'queued',
						'start_date'    => $start_date,
						'due_date'      => $due_date,
						'notes'         => $notes,
						'created_by'    => $context->user_id(),
						'created_at'    => $now,
						'updated_at'    => $now,
					)
				);

				if ( 0 === $job_id ) {
					return Rest::invalid( 'production_job_create_failed', __( 'The production job could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'production.job.created',
					'production_job',
					$job_id,
					array(
						'orderItemId' => $item_id,
						'orderId'     => $order_id,
						'assignedTo'  => $assignee,
					)
				);

				$this->record_order_event(
					$context,
					$order_id,
					$item_id,
					sprintf(
						/* translators: %s: garment name. */
						__( 'Production job opened for %s.', 'fashionos' ),
						$garment_name
					)
				);

				return $job_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return $this->job( $context, (int) $created );
	}

	/**
	 * Assign, schedule or move one job.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Job id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_job( TenantContext $context, int $id, array $input ) {
		$existing = $this->production->job( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That production job could not be found.', 'fashionos' ), 'production_job_not_found' );
		}

		$previous = (string) $existing['status'];
		$data     = array();
		$changed  = array();

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::JOB_STATUSES, true ) ) {
				return Rest::invalid( 'production_job_status_invalid', __( 'Choose a valid job status.', 'fashionos' ) );
			}

			if ( $status !== $previous && ! in_array( $status, self::JOB_TRANSITIONS[ $previous ] ?? array(), true ) ) {
				return Rest::invalid(
					'production_job_transition_invalid',
					__( 'That job cannot move to the requested status.', 'fashionos' ),
					409
				);
			}

			$data['status'] = $status;

			if ( 'in_progress' === $status && '' === (string) $existing['start_date'] ) {
				$data['start_date'] = gmdate( 'Y-m-d' );
			}
		}

		if ( array_key_exists( 'assignedTo', $input ) ) {
			$assignee = $this->staff->normalise( $context->id(), $input['assignedTo'], 'production_assignee_invalid' );

			if ( $assignee instanceof WP_Error ) {
				return $assignee;
			}

			$data['assigned_to'] = $assignee;
		}

		if ( array_key_exists( 'startDate', $input ) ) {
			$start_date = $this->normalise_date( $input['startDate'], 'production_start_date_invalid' );

			if ( $start_date instanceof WP_Error ) {
				return $start_date;
			}

			$data['start_date'] = $start_date;
		}

		if ( array_key_exists( 'dueDate', $input ) ) {
			$due_date = $this->normalise_date( $input['dueDate'], 'production_due_date_invalid' );

			if ( $due_date instanceof WP_Error ) {
				return $due_date;
			}

			$data['due_date'] = $due_date;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_notes( $input['notes'], 'production_notes_too_long' );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes'] = $notes;
		}

		foreach ( array( 'status', 'assigned_to', 'start_date', 'due_date', 'notes' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return $this->job( $context, $id );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$order_id           = (int) $existing['order_id'];
		$item_id            = (int) $existing['order_item_id'];
		$garment_name       = (string) $existing['garment_name'];

		$updated = $this->transactions->run(
			function () use ( $context, $id, $data, $changed, $previous, $order_id, $item_id, $garment_name ) {
				if ( ! $this->production->update_job( $context->id(), $id, $data ) ) {
					return Rest::invalid( 'production_job_update_failed', __( 'The job could not be updated. Please try again.', 'fashionos' ), 500 );
				}

				if ( array() !== $changed ) {
					$metadata = array( 'changed' => $changed );

					if ( isset( $data['status'] ) ) {
						$metadata['status'] = (string) $data['status'];
					}

					if ( isset( $data['assigned_to'] ) ) {
						$metadata['assignedTo'] = (int) $data['assigned_to'];
					}

					$this->audit->record( $context->id(), $context->user_id(), 'production.job.updated', 'production_job', $id, $metadata );
				}

				if ( isset( $data['status'] ) && $previous !== (string) $data['status'] ) {
					$this->record_order_event(
						$context,
						$order_id,
						$item_id,
						sprintf(
							/* translators: 1: garment name, 2: previous job status, 3: new job status. */
							__( 'Production for %1$s moved from %2$s to %3$s.', 'fashionos' ),
							$garment_name,
							$this->status_label( $previous ),
							$this->status_label( (string) $data['status'] )
						)
					);
				}

				return true;
			}
		);

		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		return $this->job( $context, $id );
	}

	/**
	 * Add a task for a stage of a job.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $job_id  Job id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function add_task( TenantContext $context, int $job_id, array $input ) {
		$job = $this->production->job( $context->id(), $job_id );

		if ( null === $job ) {
			return Rest::not_found( __( 'That production job could not be found.', 'fashionos' ), 'production_job_not_found' );
		}

		$stage_id = (int) ( $input['stageId'] ?? 0 );
		$stage    = $this->production->stage( $context->id(), $stage_id );

		if ( null === $stage ) {
			return Rest::not_found( __( 'That production stage could not be found.', 'fashionos' ), 'production_stage_not_found' );
		}

		if ( 1 !== (int) $stage['is_active'] ) {
			return Rest::invalid( 'production_stage_inactive', __( 'That stage is switched off. Choose an active stage.', 'fashionos' ) );
		}

		$assignee = $this->staff->normalise( $context->id(), $input['assignedTo'] ?? 0, 'production_assignee_invalid' );

		if ( $assignee instanceof WP_Error ) {
			return $assignee;
		}

		$due_date = $this->normalise_date( $input['dueDate'] ?? '', 'production_due_date_invalid' );

		if ( $due_date instanceof WP_Error ) {
			return $due_date;
		}

		$notes = $this->normalise_notes( $input['notes'] ?? '', 'production_notes_too_long' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$task_id = $this->production->create_task(
			array(
				'tenant_id'    => $context->id(),
				'job_id'       => $job_id,
				'stage_id'     => $stage_id,
				'assigned_to'  => $assignee,
				'status'       => 'pending',
				'due_date'     => $due_date,
				'completed_at' => '',
				'notes'        => $notes,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);

		if ( 0 === $task_id ) {
			return Rest::invalid( 'production_task_create_failed', __( 'The task could not be created. Please try again.', 'fashionos' ), 500 );
		}

		$this->production->update_job( $context->id(), $job_id, array( 'updated_at' => $now ) );

		$this->audit->record(
			$context->id(),
			$context->user_id(),
			'production.task.created',
			'production_task',
			$task_id,
			array(
				'jobId'   => $job_id,
				'stageId' => $stage_id,
			)
		);

		return $this->job( $context, $job_id );
	}

	/**
	 * Update one task's status, assignee, due date or notes.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $task_id Task id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_task( TenantContext $context, int $task_id, array $input ) {
		$task = $this->production->task( $context->id(), $task_id );

		if ( null === $task ) {
			return Rest::not_found( __( 'That production task could not be found.', 'fashionos' ), 'production_task_not_found' );
		}

		$data           = array();
		$status_changed = false;
		$previous       = (string) $task['status'];

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::TASK_STATUSES, true ) ) {
				return Rest::invalid( 'production_task_status_invalid', __( 'Choose a valid task status.', 'fashionos' ) );
			}

			$status_changed       = $status !== $previous;
			$data['status']       = $status;
			$data['completed_at'] = 'done' === $status ? gmdate( 'Y-m-d H:i:s' ) : '';
		}

		if ( array_key_exists( 'assignedTo', $input ) ) {
			$assignee = $this->staff->normalise( $context->id(), $input['assignedTo'], 'production_assignee_invalid' );

			if ( $assignee instanceof WP_Error ) {
				return $assignee;
			}

			$data['assigned_to'] = $assignee;
		}

		if ( array_key_exists( 'dueDate', $input ) ) {
			$due_date = $this->normalise_date( $input['dueDate'], 'production_due_date_invalid' );

			if ( $due_date instanceof WP_Error ) {
				return $due_date;
			}

			$data['due_date'] = $due_date;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_notes( $input['notes'], 'production_notes_too_long' );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes'] = $notes;
		}

		if ( array() === $data ) {
			return $this->job( $context, (int) $task['job_id'] );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		if ( ! $this->production->update_task( $context->id(), $task_id, $data ) ) {
			return Rest::invalid( 'production_task_update_failed', __( 'The task could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		$this->production->update_job( $context->id(), (int) $task['job_id'], array( 'updated_at' => $data['updated_at'] ) );

		if ( $status_changed ) {
			$this->audit->record(
				$context->id(),
				$context->user_id(),
				'production.task.status_changed',
				'production_task',
				$task_id,
				array(
					'jobId' => (int) $task['job_id'],
					'from'  => $previous,
					'to'    => (string) $data['status'],
				)
			);
		}

		return $this->job( $context, (int) $task['job_id'] );
	}

	/**
	 * Counters and short lists for the production dashboard.
	 *
	 * @param TenantContext $context Verified context.
	 * @return array<string, mixed>
	 */
	public function dashboard( TenantContext $context ): array {
		$now      = gmdate( 'Y-m-d H:i:s' );
		$counts   = $this->production->dashboard_counts( $context->id(), gmdate( 'Y-m-d' ) );
		$upcoming = $this->fittings->upcoming( $context->id(), $now, gmdate( 'Y-m-d H:i:s', strtotime( '+14 days' ) ), 5 );
		$failures = $this->quality->recent_failures( $context->id(), 5 );
		$low      = $this->materials->low_stock( $context->id(), 5 );

		return array(
			'counts'            => array(
				'jobs'              => $counts['jobs'],
				'jobsTotal'         => (int) $counts['jobsTotal'],
				'overdueJobs'       => (int) $counts['overdueJobs'],
				'unassignedJobs'    => (int) $counts['unassignedJobs'],
				'openTasks'         => (int) $counts['openTasks'],
				'unplannedGarments' => (int) $counts['unplannedGarments'],
				'failedChecks'      => $this->quality->count_result( $context->id(), 'failed' ),
				'needsRework'       => $this->quality->count_result( $context->id(), 'needs_rework' ),
			),
			'unplannedGarments' => array_map(
				array( ProductionPresenter::class, 'unplanned' ),
				$this->production->unplanned_garments( $context->id(), 5 )
			),
			'upcomingFittings'  => array_map( array( FittingPresenter::class, 'fitting' ), $upcoming ),
			'recentFailures'    => array_map( array( QualityPresenter::class, 'check' ), $failures ),
			'lowStock'          => array_map( array( MaterialPresenter::class, 'material' ), $low ),
		);
	}

	/**
	 * Seed the starter workflow the first time a workspace asks for stages.
	 *
	 * A workspace that deliberately deleted every stage gets the defaults back;
	 * that is the only sane starting point for creating a job, and the audit
	 * trail still records the stage changes that emptied the list.
	 *
	 * @param TenantContext $context Verified context.
	 */
	private function ensure_defaults( TenantContext $context ): void {
		if ( $this->production->count_stages( $context->id() ) > 0 ) {
			return;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		foreach ( Stages::defaults() as $stage ) {
			$this->production->create_stage(
				array(
					'tenant_id'  => $context->id(),
					'name'       => (string) $stage['name'],
					'slug'       => (string) $stage['slug'],
					'sequence'   => (int) $stage['sequence'],
					'is_active'  => 1,
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
		}
	}

	/**
	 * Append a production entry to the order's activity timeline.
	 *
	 * @param TenantContext $context  Verified context.
	 * @param int           $order_id Order id.
	 * @param int           $item_id  Garment id.
	 * @param string        $message  Human-readable message.
	 */
	private function record_order_event( TenantContext $context, int $order_id, int $item_id, string $message ): void {
		$this->orders->create_event(
			array(
				'tenant_id'     => $context->id(),
				'order_id'      => $order_id,
				'order_item_id' => max( 0, $item_id ),
				'event_type'    => 'production',
				'message'       => $message,
				'actor_id'      => $context->user_id(),
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Validate a stage sequence.
	 *
	 * @param mixed $value Candidate sequence.
	 * @return int|WP_Error
	 */
	private function sequence( $value ) {
		if ( ! is_numeric( $value ) ) {
			return Rest::invalid( 'production_stage_sequence_invalid', __( 'Enter a whole number for the stage order.', 'fashionos' ) );
		}

		$sequence = (int) $value;

		if ( $sequence < 0 || $sequence > 9999 ) {
			return Rest::invalid( 'production_stage_sequence_invalid', __( 'Stage order must be between 0 and 9999.', 'fashionos' ) );
		}

		return $sequence;
	}

	/**
	 * Validate an optional calendar date.
	 *
	 * @param mixed  $value      Candidate date; blank clears it.
	 * @param string $error_code Error code for an invalid date.
	 * @return string|WP_Error
	 */
	private function normalise_date( $value, string $error_code ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return Rest::invalid( $error_code, __( 'Enter a valid date.', 'fashionos' ) );
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Validate production notes.
	 *
	 * @param mixed  $value      Candidate notes.
	 * @param string $error_code Error code for overlong notes.
	 * @return string|WP_Error
	 */
	private function normalise_notes( $value, string $error_code ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$notes = sanitize_textarea_field( $value );

		if ( mb_strlen( $notes ) > 2000 ) {
			return Rest::invalid( $error_code, __( 'Notes may be at most 2000 characters.', 'fashionos' ) );
		}

		return $notes;
	}

	/**
	 * Human-readable status name for timeline messages.
	 *
	 * @param string $status Stored status key.
	 */
	private function status_label( string $status ): string {
		$labels = array(
			'queued'      => __( 'Queued', 'fashionos' ),
			'in_progress' => __( 'In progress', 'fashionos' ),
			'blocked'     => __( 'Blocked', 'fashionos' ),
			'completed'   => __( 'Completed', 'fashionos' ),
			'cancelled'   => __( 'Cancelled', 'fashionos' ),
		);

		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}
}
