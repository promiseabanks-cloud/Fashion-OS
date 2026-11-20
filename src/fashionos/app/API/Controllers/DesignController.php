<?php
/**
 * Design routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Designs\DesignService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design list, detail and status changes.
 *
 * A design may cite one measurement profile and version; both are validated
 * against the design's own customer by the service.
 */
final class DesignController extends Controller {

	/**
	 * Design workflows.
	 *
	 * @var DesignService
	 */
	private DesignService $service;

	/**
	 * Constructor.
	 *
	 * @param DesignService  $service  Design workflows.
	 * @param TenantResolver $resolver Tenant resolver.
	 */
	public function __construct( DesignService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/designs',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => $this->list_args(),
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
			'/designs/(?P<id>[\d]+)',
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
	 * Shared list query arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function list_args(): array {
		return array(
			'search'    => array(
				'type'              => 'string',
				'required'          => false,
				'description'       => __( 'Match title, reference, category or customer.', 'fashionos' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'    => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => 'all',
				'description'       => __( 'draft, approved, archived or all.', 'fashionos' ),
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
		);
	}

	/**
	 * Require design read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::DESIGNS_VIEW, __( 'You cannot view designs in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require design management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::DESIGNS_MANAGE, __( 'You cannot change designs in this workspace.', 'fashionos' ) );
	}

	/**
	 * Paginated design list.
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
	 * One design.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$design = $this->service->find( $context, (int) $request['id'] );

		if ( $design instanceof WP_Error ) {
			return $this->failure( $design );
		}

		return Response::success( $design );
	}

	/**
	 * Create a design.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$design = $this->service->create( $context, $this->payload( $request ) );

		if ( $design instanceof WP_Error ) {
			return $this->failure( $design );
		}

		return Response::success( $design, array(), 201 );
	}

	/**
	 * Update a design.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$design = $this->service->update( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $design instanceof WP_Error ) {
			return $this->failure( $design );
		}

		return Response::success( $design );
	}
}
