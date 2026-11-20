<?php
/**
 * Material and stock routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Inventory\MaterialService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Material catalogue, stock movements and garment allocations.
 *
 * Stock is never edited directly: a movement records the quantity, the person
 * and the reason, and allocations name their supply source so customer fabric
 * is never reported as studio stock.
 */
final class MaterialController extends Controller {

	/**
	 * Material workflows.
	 *
	 * @var MaterialService
	 */
	private MaterialService $service;

	/**
	 * Constructor.
	 *
	 * @param MaterialService $service  Material workflows.
	 * @param TenantResolver  $resolver Tenant resolver.
	 */
	public function __construct( MaterialService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/materials',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'search'           => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'type'             => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_key',
						),
						'low_stock'        => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'include_inactive' => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'page'             => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'         => array(
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
			'/materials/(?P<id>[\d]+)',
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
			'/materials/(?P<id>[\d]+)/movements',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'movement' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/order-items/(?P<id>[\d]+)/materials',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'allocations' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'allocate' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/material-allocations/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'release' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Require material read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::MATERIALS_VIEW, __( 'You cannot view inventory in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require material management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::MATERIALS_MANAGE, __( 'You cannot change inventory in this workspace.', 'fashionos' ) );
	}

	/**
	 * Paginated catalogue.
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
					'search'          => $request['search'],
					'type'            => $request['type'],
					'lowStock'        => $request['low_stock'],
					'includeInactive' => $request['include_inactive'],
					'page'            => $request['page'],
					'perPage'         => $request['per_page'],
				)
			)
		);
	}

	/**
	 * One material with recent movements.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$material = $this->service->find( $context, (int) $request['id'] );

		if ( $material instanceof WP_Error ) {
			return $this->failure( $material );
		}

		return Response::success( $material );
	}

	/**
	 * Add a material.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$material = $this->service->create( $context, $this->payload( $request ) );

		if ( $material instanceof WP_Error ) {
			return $this->failure( $material );
		}

		return Response::success( $material, array(), 201 );
	}

	/**
	 * Edit a material.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$material = $this->service->update( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $material instanceof WP_Error ) {
			return $this->failure( $material );
		}

		return Response::success( $material );
	}

	/**
	 * Record a stock movement.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function movement( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$material = $this->service->movement( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $material instanceof WP_Error ) {
			return $this->failure( $material );
		}

		return Response::success( $material );
	}

	/**
	 * Allocations of one garment.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function allocations( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$allocations = $this->service->allocations( $context, (int) $request['id'] );

		if ( $allocations instanceof WP_Error ) {
			return $this->failure( $allocations );
		}

		return Response::success( $allocations );
	}

	/**
	 * Allocate material to a garment.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function allocate( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$allocations = $this->service->allocate( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $allocations instanceof WP_Error ) {
			return $this->failure( $allocations );
		}

		return Response::success( $allocations, array(), 201 );
	}

	/**
	 * Release an allocation.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function release( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$allocations = $this->service->release( $context, (int) $request['id'] );

		if ( $allocations instanceof WP_Error ) {
			return $this->failure( $allocations );
		}

		return Response::success( $allocations );
	}
}
