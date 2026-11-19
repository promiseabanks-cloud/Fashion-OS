<?php
/**
 * Customer routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Clients\ClientService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer list, profile and preferences.
 *
 * Every route is scoped to the active workspace by the shared controller base;
 * a customer id from another workspace is indistinguishable from a missing one.
 */
final class ClientController extends Controller {

	/**
	 * Customer workflows.
	 *
	 * @var ClientService
	 */
	private ClientService $service;

	/**
	 * Constructor.
	 *
	 * @param ClientService  $service  Customer workflows.
	 * @param TenantResolver $resolver Tenant resolver.
	 */
	public function __construct( ClientService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/clients',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'search'   => array(
							'type'              => 'string',
							'required'          => false,
							'description'       => __( 'Match name, email, phone or reference.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status'   => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'active',
							'description'       => __( 'active, archived or all.', 'fashionos' ),
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
			'/clients/(?P<id>[\d]+)',
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
	 * Require customer read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::CLIENTS_VIEW, __( 'You cannot view customers in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require customer management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::CLIENTS_MANAGE, __( 'You cannot change customers in this workspace.', 'fashionos' ) );
	}

	/**
	 * Paginated customer list.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$result = $this->service->search(
			$context,
			array(
				'search'   => $request['search'],
				'status'   => $request['status'],
				'page'     => $request['page'],
				'perPage'  => $request['per_page'],
			)
		);

		return Response::success( $result );
	}

	/**
	 * One customer.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$client = $this->service->find( $context, (int) $request['id'] );

		if ( $client instanceof WP_Error ) {
			return $this->failure( $client );
		}

		return Response::success( $client );
	}

	/**
	 * Create a customer.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$client = $this->service->create( $context, $this->payload( $request ) );

		if ( $client instanceof WP_Error ) {
			return $this->failure( $client );
		}

		return Response::success( $client, array(), 201 );
	}

	/**
	 * Update a customer.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$client = $this->service->update( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $client instanceof WP_Error ) {
			return $this->failure( $client );
		}

		return Response::success( $client );
	}
}
