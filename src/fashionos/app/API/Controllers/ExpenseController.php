<?php
/**
 * Expense routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Finance\ExpenseService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expense list, detail and voiding.
 */
final class ExpenseController extends Controller {

	/**
	 * Expense workflows.
	 *
	 * @var ExpenseService
	 */
	private ExpenseService $service;

	/**
	 * Constructor.
	 *
	 * @param ExpenseService $service  Expense workflows.
	 * @param TenantResolver $resolver Tenant resolver.
	 */
	public function __construct( ExpenseService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/expenses',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'search'   => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'description'       => __( 'Match description or reference.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'category' => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'description'       => __( 'An expense category or all.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'status'   => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'description'       => __( 'recorded, void or all.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'page'     => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
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
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/expenses/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Require expense read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::EXPENSES_VIEW, __( 'You cannot view expenses in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require expense management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::EXPENSES_MANAGE, __( 'You cannot change expenses in this workspace.', 'fashionos' ) );
	}

	/**
	 * Paginated expense list.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return Response::success(
			$this->service->search(
				$context,
				array(
					'search'   => $request['search'],
					'category' => $request['category'],
					'status'   => $request['status'],
					'page'     => $request['page'],
					'perPage'  => $request['per_page'],
				)
			)
		);
	}

	/**
	 * One expense.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$expense = $this->service->find( $context, (int) $request['id'] );

		if ( $expense instanceof WP_Error ) {
			return $this->failure( $expense );
		}

		return Response::success( $expense );
	}

	/**
	 * Record an expense.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$expense = $this->service->create( $context, $this->payload( $request ) );

		if ( $expense instanceof WP_Error ) {
			return $this->failure( $expense );
		}

		return Response::success( $expense, array(), 201 );
	}

	/**
	 * Update or void an expense.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$expense = $this->service->update( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $expense instanceof WP_Error ) {
			return $this->failure( $expense );
		}

		return Response::success( $expense );
	}
}
