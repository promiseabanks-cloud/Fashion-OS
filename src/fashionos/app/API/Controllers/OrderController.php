<?php
/**
 * Order routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Orders\OrderService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order list, order detail, garment progress and order notes.
 *
 * Garments are updated through their own route so one garment can move through
 * production without touching the others on the same order.
 */
final class OrderController extends Controller {

	/**
	 * Order workflows.
	 *
	 * @var OrderService
	 */
	private OrderService $service;

	/**
	 * Constructor.
	 *
	 * @param OrderService   $service  Order workflows.
	 * @param TenantResolver $resolver Tenant resolver.
	 */
	public function __construct( OrderService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/orders',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'search'    => array(
							'type'              => 'string',
							'required'          => false,
							'description'       => __( 'Match reference, customer or garment.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status'    => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'description'       => __( 'Order status, or all.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'client_id' => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
						),
						'page'      => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'  => array(
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
			'/orders/(?P<id>[\d]+)',
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

		register_rest_route(
			Rest::NAMESPACE,
			'/orders/(?P<id>[\d]+)/events',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_note' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/order-items/(?P<id>[\d]+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Require order read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::ORDERS_VIEW, __( 'You cannot view orders in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require order management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::ORDERS_MANAGE, __( 'You cannot change orders in this workspace.', 'fashionos' ) );
	}

	/**
	 * Paginated order list.
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
					'status'   => $request['status'],
					'clientId' => $request['client_id'],
					'page'     => $request['page'],
					'perPage'  => $request['per_page'],
				)
			)
		);
	}

	/**
	 * One order with garments and timeline.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$order = $this->service->find( $context, (int) $request['id'] );

		if ( $order instanceof WP_Error ) {
			return $this->failure( $order );
		}

		return Response::success( $order );
	}

	/**
	 * Create an order.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$order = $this->service->create( $context, $this->payload( $request ) );

		if ( $order instanceof WP_Error ) {
			return $this->failure( $order );
		}

		return Response::success( $order, array(), 201 );
	}

	/**
	 * Update an order's status, due date or notes.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$order = $this->service->update( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $order instanceof WP_Error ) {
			return $this->failure( $order );
		}

		return Response::success( $order );
	}

	/**
	 * Move one garment through production.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update_item( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$order = $this->service->update_item( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $order instanceof WP_Error ) {
			return $this->failure( $order );
		}

		return Response::success( $order );
	}

	/**
	 * Add a note to an order's timeline.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function add_note( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$order = $this->service->add_note( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $order instanceof WP_Error ) {
			return $this->failure( $order );
		}

		return Response::success( $order );
	}
}
