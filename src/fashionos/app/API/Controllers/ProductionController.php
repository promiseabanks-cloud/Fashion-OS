<?php
/**
 * Production routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Production\ProductionService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Production stages, jobs, tasks and the production dashboard.
 *
 * Jobs are addressed individually so a garment can be assigned and moved
 * without touching the rest of its order. Every route checks a production
 * permission on the server, not in the browser.
 */
final class ProductionController extends Controller {

	/**
	 * Production workflows.
	 *
	 * @var ProductionService
	 */
	private ProductionService $service;

	/**
	 * Constructor.
	 *
	 * @param ProductionService $service  Production workflows.
	 * @param TenantResolver    $resolver Tenant resolver.
	 */
	public function __construct( ProductionService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/production/stages',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'stages' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'include_inactive' => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_stage' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/production/stages/(?P<id>[\d]+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_stage' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/production/jobs',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'jobs' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'search'        => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status'        => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_key',
						),
						'assigned_to'   => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
						),
						'order_item_id' => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
						),
						'page'          => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'      => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 25,
							'minimum'           => 1,
							'maximum'           => 100,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_job' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/production/jobs/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show_job' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_job' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/production/jobs/(?P<id>[\d]+)/tasks',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_task' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/production/tasks/(?P<id>[\d]+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_task' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/production/dashboard',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'dashboard' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
	}

	/**
	 * Require production read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::PRODUCTION_VIEW, __( 'You cannot view production in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require production management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::PRODUCTION_MANAGE, __( 'You cannot change production in this workspace.', 'fashionos' ) );
	}

	/**
	 * Stage list.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function stages( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return Response::success(
			$this->service->stages( $context, filter_var( $request['include_inactive'], FILTER_VALIDATE_BOOLEAN ) )
		);
	}

	/**
	 * Add a stage.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create_stage( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$stage = $this->service->create_stage( $context, $this->payload( $request ) );

		if ( $stage instanceof WP_Error ) {
			return $this->failure( $stage );
		}

		return Response::success( $stage, array(), 201 );
	}

	/**
	 * Update a stage.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update_stage( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$stage = $this->service->update_stage( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $stage instanceof WP_Error ) {
			return $this->failure( $stage );
		}

		return Response::success( $stage );
	}

	/**
	 * Paginated jobs.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function jobs( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return Response::success(
			$this->service->jobs(
				$context,
				array(
					'search'      => $request['search'],
					'status'      => $request['status'],
					'assignedTo'  => $request['assigned_to'],
					'orderItemId' => $request['order_item_id'],
					'page'        => $request['page'],
					'perPage'     => $request['per_page'],
				)
			)
		);
	}

	/**
	 * One job with its tasks.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show_job( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$job = $this->service->job( $context, (int) $request['id'] );

		if ( $job instanceof WP_Error ) {
			return $this->failure( $job );
		}

		return Response::success( $job );
	}

	/**
	 * Plan production for a garment.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create_job( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$job = $this->service->create_job( $context, $this->payload( $request ) );

		if ( $job instanceof WP_Error ) {
			return $this->failure( $job );
		}

		return Response::success( $job, array(), 201 );
	}

	/**
	 * Update a job.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update_job( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$job = $this->service->update_job( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $job instanceof WP_Error ) {
			return $this->failure( $job );
		}

		return Response::success( $job );
	}

	/**
	 * Add a task to a job.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function add_task( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$job = $this->service->add_task( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $job instanceof WP_Error ) {
			return $this->failure( $job );
		}

		return Response::success( $job, array(), 201 );
	}

	/**
	 * Update a task.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update_task( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$job = $this->service->update_task( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $job instanceof WP_Error ) {
			return $this->failure( $job );
		}

		return Response::success( $job );
	}

	/**
	 * Production dashboard counters and short lists.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function dashboard( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return Response::success( $this->service->dashboard( $context ) );
	}
}
