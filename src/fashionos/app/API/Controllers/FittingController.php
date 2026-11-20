<?php
/**
 * Fitting and alteration routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Fittings\FittingService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fittings and alterations, both garment level.
 */
final class FittingController extends Controller {

	/**
	 * Fitting workflows.
	 *
	 * @var FittingService
	 */
	private FittingService $service;

	/**
	 * Constructor.
	 *
	 * @param FittingService $service  Fitting workflows.
	 * @param TenantResolver $resolver Tenant resolver.
	 */
	public function __construct( FittingService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/fittings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'status'        => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_key',
						),
						'upcoming'      => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
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
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/fittings/(?P<id>[\d]+)',
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
			'/alterations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'alterations' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'status'        => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_key',
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
					'callback'            => array( $this, 'create_alteration' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/alterations/(?P<id>[\d]+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_alteration' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Require fitting read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::FITTINGS_VIEW, __( 'You cannot view fittings in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require fitting management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::FITTINGS_MANAGE, __( 'You cannot change fittings in this workspace.', 'fashionos' ) );
	}

	/**
	 * Paginated fittings.
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
					'status'      => $request['status'],
					'upcoming'    => $request['upcoming'],
					'orderItemId' => $request['order_item_id'],
					'page'        => $request['page'],
					'perPage'     => $request['per_page'],
				)
			)
		);
	}

	/**
	 * One fitting.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$fitting = $this->service->find( $context, (int) $request['id'] );

		if ( $fitting instanceof WP_Error ) {
			return $this->failure( $fitting );
		}

		return Response::success( $fitting );
	}

	/**
	 * Schedule a fitting.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$fitting = $this->service->create( $context, $this->payload( $request ) );

		if ( $fitting instanceof WP_Error ) {
			return $this->failure( $fitting );
		}

		return Response::success( $fitting, array(), 201 );
	}

	/**
	 * Update a fitting.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$fitting = $this->service->update( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $fitting instanceof WP_Error ) {
			return $this->failure( $fitting );
		}

		return Response::success( $fitting );
	}

	/**
	 * Paginated alterations.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function alterations( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return Response::success(
			$this->service->alterations(
				$context,
				array(
					'status'      => $request['status'],
					'orderItemId' => $request['order_item_id'],
					'page'        => $request['page'],
					'perPage'     => $request['per_page'],
				)
			)
		);
	}

	/**
	 * Raise an alteration.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create_alteration( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$alteration = $this->service->create_alteration( $context, $this->payload( $request ) );

		if ( $alteration instanceof WP_Error ) {
			return $this->failure( $alteration );
		}

		return Response::success( $alteration, array(), 201 );
	}

	/**
	 * Update an alteration.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update_alteration( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$alteration = $this->service->update_alteration( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $alteration instanceof WP_Error ) {
			return $this->failure( $alteration );
		}

		return Response::success( $alteration );
	}
}
