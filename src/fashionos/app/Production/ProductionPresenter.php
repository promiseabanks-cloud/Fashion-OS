<?php
/**
 * Production response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Production;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps stages, jobs, tasks and unplanned garments to public field names.
 */
final class ProductionPresenter {

	/**
	 * Public stage fields.
	 *
	 * @param array<string, mixed> $row Stage row.
	 * @return array<string, mixed>
	 */
	public static function stage( array $row ): array {
		return array(
			'id'        => (int) ( $row['id'] ?? 0 ),
			'name'      => (string) ( $row['name'] ?? '' ),
			'slug'      => (string) ( $row['slug'] ?? '' ),
			'sequence'  => (int) ( $row['sequence'] ?? 0 ),
			'isActive'  => (int) ( $row['is_active'] ?? 1 ) === 1,
			'createdAt' => (string) ( $row['created_at'] ?? '' ),
			'updatedAt' => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public job fields.
	 *
	 * @param array<string, mixed>             $row   Job row with joined names.
	 * @param array<int, array<string, mixed>> $tasks Tasks, when included.
	 * @return array<string, mixed>
	 */
	public static function job( array $row, array $tasks = array() ): array {
		return array(
			'id'              => (int) ( $row['id'] ?? 0 ),
			'orderItemId'     => (int) ( $row['order_item_id'] ?? 0 ),
			'orderId'         => (int) ( $row['order_id'] ?? 0 ),
			'orderReference'  => (string) ( $row['order_reference'] ?? '' ),
			'orderDueDate'    => (string) ( $row['order_due_date'] ?? '' ),
			'clientName'      => (string) ( $row['client_name'] ?? '' ),
			'garmentName'     => (string) ( $row['garment_name'] ?? '' ),
			'garmentCategory' => (string) ( $row['garment_category'] ?? '' ),
			'garmentStatus'   => (string) ( $row['garment_status'] ?? '' ),
			'status'          => (string) ( $row['status'] ?? 'queued' ),
			'assignedTo'      => (int) ( $row['assigned_to'] ?? 0 ),
			'assigneeName'    => (string) ( $row['assignee_name'] ?? '' ),
			'startDate'       => (string) ( $row['start_date'] ?? '' ),
			'dueDate'         => (string) ( $row['due_date'] ?? '' ),
			'notes'           => (string) ( $row['notes'] ?? '' ),
			'taskTotal'       => (int) ( $row['task_total'] ?? count( $tasks ) ),
			'taskDone'        => (int) ( $row['task_done'] ?? 0 ),
			'tasks'           => array_map( array( self::class, 'task' ), $tasks ),
			'createdAt'       => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'       => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public task fields.
	 *
	 * @param array<string, mixed> $row Task row with stage and assignee names.
	 * @return array<string, mixed>
	 */
	public static function task( array $row ): array {
		return array(
			'id'           => (int) ( $row['id'] ?? 0 ),
			'jobId'        => (int) ( $row['job_id'] ?? 0 ),
			'stageId'      => (int) ( $row['stage_id'] ?? 0 ),
			'stageName'    => (string) ( $row['stage_name'] ?? '' ),
			'stageSlug'    => (string) ( $row['stage_slug'] ?? '' ),
			'status'       => (string) ( $row['status'] ?? 'pending' ),
			'assignedTo'   => (int) ( $row['assigned_to'] ?? 0 ),
			'assigneeName' => (string) ( $row['assignee_name'] ?? '' ),
			'dueDate'      => (string) ( $row['due_date'] ?? '' ),
			'completedAt'  => (string) ( $row['completed_at'] ?? '' ),
			'notes'        => (string) ( $row['notes'] ?? '' ),
		);
	}

	/**
	 * Public garment-without-a-job fields.
	 *
	 * @param array<string, mixed> $row Garment row.
	 * @return array<string, mixed>
	 */
	public static function unplanned( array $row ): array {
		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'garmentName'    => (string) ( $row['garment_name'] ?? '' ),
			'garmentStatus'  => (string) ( $row['status'] ?? '' ),
			'orderId'        => (int) ( $row['order_id'] ?? 0 ),
			'orderReference' => (string) ( $row['order_reference'] ?? '' ),
			'clientName'     => (string) ( $row['client_name'] ?? '' ),
		);
	}
}
