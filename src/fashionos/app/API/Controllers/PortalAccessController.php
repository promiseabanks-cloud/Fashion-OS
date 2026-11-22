<?php
/**
 * Portal access link routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Portal\PortalAccessService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sharing and revoking a customer's portal link.
 *
 * These are staff routes: they run inside the selected workspace and require
 * `clients.manage`, because handing out access to a customer's records is a
 * customer-administration action rather than a finance one.
 */
final class PortalAccessController extends Controller {

	/**
	 * Portal access workflows.
	 *
	 * @var PortalAccessService
	 */
	private PortalAccessService $service;

	/**
	 * Constructor.
	 *
	 * @param PortalAccessService $service  Portal access workflows.
	 * @param TenantResolver      $resolver Tenant resolver.
	 */
	public function __construct( PortalAccessService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/clients/(?P<id>[\d]+)/portal-access',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_manage' ),
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
			'/portal-access/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'revoke' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Require customer management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize(
			$request,
			Permissions::CLIENTS_MANAGE,
			__( 'You cannot share portal access in this workspace.', 'fashionos' )
		);
	}

	/**
	 * Access links issued to one customer.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$access = $this->service->list( $context, (int) $request['id'] );

		if ( $access instanceof WP_Error ) {
			return $this->failure( $access );
		}

		return Response::success( $access );
	}

	/**
	 * Issue a new portal link.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$access = $this->service->issue( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $access instanceof WP_Error ) {
			return $this->failure( $access );
		}

		return Response::success( $access, array(), 201 );
	}

	/**
	 * Revoke a portal link.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function revoke( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$access = $this->service->revoke( $context, (int) $request['id'] );

		if ( $access instanceof WP_Error ) {
			return $this->failure( $access );
		}

		return Response::success( $access );
	}
}
