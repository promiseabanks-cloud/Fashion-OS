<?php
/**
 * Production data access.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database\Repositories;

use FashionOS\Database\ColumnFormats;
use FashionOS\Database\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes production stages, jobs, tasks and the dashboard counters.
 *
 * A job belongs to one garment (`fos_order_items`), which is what allows a
 * jacket and its trousers to sit at different stages and with different
 * assignees. Stage names and order are workspace data, never hardcoded here.
 */
final class ProductionRepository {

	use ColumnFormats;

	/**
	 * Columns stored as integers.
	 *
	 * @var string[]
	 */
	private const INT_COLUMNS = array(
		'tenant_id',
		'order_item_id',
		'job_id',
		'stage_id',
		'assigned_to',
		'created_by',
		'sequence',
		'is_active',
		'checked_by',
	);

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Stages table.
	 *
	 * @var string
	 */
	private string $stages;

	/**
	 * Jobs table.
	 *
	 * @var string
	 */
	private string $jobs;

	/**
	 * Tasks table.
	 *
	 * @var string
	 */
	private string $tasks;

	/**
	 * Garments table.
	 *
	 * @var string
	 */
	private string $items;

	/**
	 * Orders table.
	 *
	 * @var string
	 */
	private string $orders;

	/**
	 * Clients table.
	 *
	 * @var string
	 */
	private string $clients;

	/**
	 * Users table.
	 *
	 * @var string
	 */
	private string $users;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional database handle (tests).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->db      = $db ?? $wpdb;
		$this->stages  = Tables::production_stages();
		$this->jobs    = Tables::production_jobs();
		$this->tasks   = Tables::production_tasks();
		$this->items   = Tables::order_items();
		$this->orders  = Tables::orders();
		$this->clients = Tables::clients();
		$this->users   = $this->db->users;
	}

	/**
	 * Stages of one workspace.
	 *
	 * @param int  $tenant_id       Tenant id.
	 * @param bool $include_inactive Also return switched-off stages.
	 * @return array<int, array<string, mixed>>
	 */
	public function stages( int $tenant_id, bool $include_inactive = false ): array {
		if ( $tenant_id <= 0 ) {
			return array();
		}

		$where = 'tenant_id = %d';
		$args  = array( $tenant_id );

		if ( ! $include_inactive ) {
			$where .= ' AND is_active = %d';
			$args[] = 1;
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->stages} WHERE {$where} ORDER BY sequence ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$args
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count stages in a workspace, including inactive ones.
	 *
	 * @param int $tenant_id Tenant id.
	 */
	public function count_stages( int $tenant_id ): int {
		return (int) $this->db->get_var(
			$this->db->prepare( "SELECT COUNT(*) FROM {$this->stages} WHERE tenant_id = %d", $tenant_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
		);
	}

	/**
	 * Find one stage inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Stage id.
	 * @return array<string, mixed>|null
	 */
	public function stage( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->stages} WHERE tenant_id = %d AND id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find a stage by its workspace-unique slug.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $slug      Stage slug.
	 * @return array<string, mixed>|null
	 */
	public function stage_by_slug( int $tenant_id, string $slug ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->stages} WHERE tenant_id = %d AND slug = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$slug
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a stage.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New stage id, or 0 on failure.
	 */
	public function create_stage( array $data ): int {
		$inserted = $this->db->insert( $this->stages, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a stage inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Stage id.
	 * @param array<string, mixed> $data      Changed columns.
	 */
	public function update_stage( int $tenant_id, int $id, array $data ): bool {
		if ( $tenant_id <= 0 || $id <= 0 || array() === $data ) {
			return false;
		}

		$updated = $this->db->update(
			$this->stages,
			$data,
			array(
				'tenant_id' => $tenant_id,
				'id'        => $id,
			),
			$this->formats( $data, self::INT_COLUMNS ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Search jobs with pagination.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param array<string, mixed> $query     Validated search arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function jobs( int $tenant_id, array $query ): array {
		$where  = 'j.tenant_id = %d';
		$params = array( $tenant_id );

		if ( 'all' !== (string) $query['status'] ) {
			$where   .= ' AND j.status = %s';
			$params[] = (string) $query['status'];
		}

		if ( (int) $query['assigned_to'] > 0 ) {
			$where   .= ' AND j.assigned_to = %d';
			$params[] = (int) $query['assigned_to'];
		}

		if ( (int) $query['order_item_id'] > 0 ) {
			$where   .= ' AND j.order_item_id = %d';
			$params[] = (int) $query['order_item_id'];
		}

		if ( '' !== (string) $query['search'] ) {
			$like     = '%' . $this->db->esc_like( (string) $query['search'] ) . '%';
			$where   .= ' AND ( oi.garment_name LIKE %s OR o.reference LIKE %s OR c.name LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$from = "{$this->joins()} WHERE {$where}";

		$total = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) {$from}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		$page     = (int) $query['page'];
		$per_page = (int) $query['per_page'];

		$select = $this->job_select();

		$sql = "SELECT {$select} {$from}
			ORDER BY j.due_date = '' ASC, j.due_date ASC, j.updated_at DESC, j.id DESC
			LIMIT %d OFFSET %d";

		$rows = $this->db->get_results(
			$this->db->prepare( $sql, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.
			ARRAY_A
		);

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Find one job inside a workspace.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Job id.
	 * @return array<string, mixed>|null
	 */
	public function job( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$select = $this->job_select();

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT {$select} {$this->joins()} WHERE j.tenant_id = %d AND j.id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.
				$tenant_id,
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Job for one garment, if production has been planned.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $item_id   Garment id.
	 * @return array<string, mixed>|null
	 */
	public function job_for_item( int $tenant_id, int $item_id ): ?array {
		if ( $tenant_id <= 0 || $item_id <= 0 ) {
			return null;
		}

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->jobs} WHERE tenant_id = %d AND order_item_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id,
				$item_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a job.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New job id, or 0 on failure.
	 */
	public function create_job( array $data ): int {
		$inserted = $this->db->insert( $this->jobs, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a job inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Job id.
	 * @param array<string, mixed> $data      Changed columns.
	 */
	public function update_job( int $tenant_id, int $id, array $data ): bool {
		if ( $tenant_id <= 0 || $id <= 0 || array() === $data ) {
			return false;
		}

		$updated = $this->db->update(
			$this->jobs,
			$data,
			array(
				'tenant_id' => $tenant_id,
				'id'        => $id,
			),
			$this->formats( $data, self::INT_COLUMNS ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Tasks of one job, in workflow order.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $job_id    Job id.
	 * @return array<int, array<string, mixed>>
	 */
	public function tasks( int $tenant_id, int $job_id ): array {
		if ( $tenant_id <= 0 || $job_id <= 0 ) {
			return array();
		}

		$sql = "SELECT t.*, s.name AS stage_name, s.slug AS stage_slug, s.sequence AS stage_sequence,
				u.display_name AS assignee_name
			FROM {$this->tasks} t
			INNER JOIN {$this->stages} s ON s.id = t.stage_id AND s.tenant_id = t.tenant_id
			LEFT JOIN {$this->users} u ON u.ID = t.assigned_to
			WHERE t.tenant_id = %d AND t.job_id = %d
			ORDER BY s.sequence ASC, t.id ASC";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $job_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find one task with its job and garment.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $id        Task id.
	 * @return array<string, mixed>|null
	 */
	public function task( int $tenant_id, int $id ): ?array {
		if ( $tenant_id <= 0 || $id <= 0 ) {
			return null;
		}

		$sql = "SELECT t.*, s.name AS stage_name, j.order_item_id AS order_item_id,
				oi.garment_name AS garment_name, o.id AS order_id, o.reference AS order_reference
			FROM {$this->tasks} t
			INNER JOIN {$this->stages} s ON s.id = t.stage_id AND s.tenant_id = t.tenant_id
			INNER JOIN {$this->jobs} j ON j.id = t.job_id AND j.tenant_id = t.tenant_id
			INNER JOIN {$this->items} oi ON oi.id = j.order_item_id AND oi.tenant_id = j.tenant_id
			INNER JOIN {$this->orders} o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
			WHERE t.tenant_id = %d AND t.id = %d
			LIMIT 1";

		$row = $this->db->get_row( $this->db->prepare( $sql, $tenant_id, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a task.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @return int New task id, or 0 on failure.
	 */
	public function create_task( array $data ): int {
		$inserted = $this->db->insert( $this->tasks, $data, $this->formats( $data, self::INT_COLUMNS ) );

		return false === $inserted ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Update a task inside a workspace.
	 *
	 * @param int                  $tenant_id Tenant id.
	 * @param int                  $id        Task id.
	 * @param array<string, mixed> $data      Changed columns.
	 */
	public function update_task( int $tenant_id, int $id, array $data ): bool {
		if ( $tenant_id <= 0 || $id <= 0 || array() === $data ) {
			return false;
		}

		$updated = $this->db->update(
			$this->tasks,
			$data,
			array(
				'tenant_id' => $tenant_id,
				'id'        => $id,
			),
			$this->formats( $data, self::INT_COLUMNS ),
			array( '%d', '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Garments in live orders that have no production job yet.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function unplanned_garments( int $tenant_id, int $limit = 10 ): array {
		if ( $tenant_id <= 0 ) {
			return array();
		}

		$sql = "SELECT oi.id, oi.garment_name, oi.status, o.reference AS order_reference, o.id AS order_id,
				c.name AS client_name
			FROM {$this->items} oi
			INNER JOIN {$this->orders} o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
			INNER JOIN {$this->clients} c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
			LEFT JOIN {$this->jobs} j ON j.order_item_id = oi.id AND j.tenant_id = oi.tenant_id
			WHERE oi.tenant_id = %d AND j.id IS NULL AND o.status NOT IN ('cancelled')
			ORDER BY o.due_date = '' ASC, o.due_date ASC, oi.id ASC
			LIMIT %d";

		$rows = $this->db->get_results( $this->db->prepare( $sql, $tenant_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Counters for the production dashboard.
	 *
	 * Each number is derived from the current rows; nothing is cached, because a
	 * stale production count is worse than an extra indexed query.
	 *
	 * @param int    $tenant_id Tenant id.
	 * @param string $today     Today's date in `Y-m-d`.
	 * @return array<string, mixed>
	 */
	public function dashboard_counts( int $tenant_id, string $today ): array {
		$status_rows = $this->db->get_results(
			$this->db->prepare( "SELECT status, COUNT(*) AS total FROM {$this->jobs} WHERE tenant_id = %d GROUP BY status", $tenant_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
			ARRAY_A
		);

		$by_status = array(
			'queued'      => 0,
			'in_progress' => 0,
			'blocked'     => 0,
			'completed'   => 0,
			'cancelled'   => 0,
		);

		foreach ( is_array( $status_rows ) ? $status_rows : array() as $row ) {
			$by_status[ (string) $row['status'] ] = (int) $row['total'];
		}

		$active = "status IN ('queued','in_progress','blocked')";

		$overdue = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->jobs} WHERE tenant_id = %d AND due_date <> '' AND due_date < %s AND {$active}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.
				$tenant_id,
				$today
			)
		);

		$unassigned = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->jobs} WHERE tenant_id = %d AND assigned_to = 0 AND {$active}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.
				$tenant_id
			)
		);

		$open_tasks = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->tasks} WHERE tenant_id = %d AND status IN ('pending','in_progress')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix.
				$tenant_id
			)
		);

		$unplanned = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->items} oi
				INNER JOIN {$this->orders} o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
				LEFT JOIN {$this->jobs} j ON j.order_item_id = oi.id AND j.tenant_id = oi.tenant_id
				WHERE oi.tenant_id = %d AND j.id IS NULL AND o.status <> 'cancelled'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.
				$tenant_id
			)
		);

		return array(
			'jobs'        => $by_status,
			'jobsTotal'   => array_sum( $by_status ),
			'overdueJobs' => $overdue,
			'unassignedJobs' => $unassigned,
			'openTasks'   => $open_tasks,
			'unplannedGarments' => $unplanned,
		);
	}

	/**
	 * Shared joins for job reads.
	 */
	private function joins(): string {
		return "FROM {$this->jobs} j
			INNER JOIN {$this->items} oi ON oi.id = j.order_item_id AND oi.tenant_id = j.tenant_id
			INNER JOIN {$this->orders} o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
			INNER JOIN {$this->clients} c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
			LEFT JOIN {$this->users} u ON u.ID = j.assigned_to";
	}

	/**
	 * Shared job columns, including task progress.
	 */
	private function job_select(): string {
		return "j.*, oi.garment_name AS garment_name, oi.garment_category AS garment_category,
			oi.status AS garment_status, o.id AS order_id, o.reference AS order_reference,
			o.due_date AS order_due_date, c.name AS client_name, u.display_name AS assignee_name,
			( SELECT COUNT(*) FROM {$this->tasks} t WHERE t.tenant_id = j.tenant_id AND t.job_id = j.id ) AS task_total,
			( SELECT COUNT(*) FROM {$this->tasks} t WHERE t.tenant_id = j.tenant_id AND t.job_id = j.id AND t.status = 'done' ) AS task_done";
	}
}
