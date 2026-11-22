<?php
/**
 * Customer portal routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Portal\PortalContext;
use FashionOS\Portal\PortalResolver;
use FashionOS\Portal\PortalService;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a customer can read about their own records.
 *
 * These routes are the one place in the namespace that does not authenticate a
 * WordPress user: the portal token is the credential, it is resolved into one
 * workspace and one customer, and every read is scoped by that pair. All five
 * routes are read-only, so there is no CSRF surface to guard with a nonce.
 */
final class PortalController extends Controller {

	/**
	 * Portal reads.
	 *
	 * @var PortalService
	 */
	private PortalService $service;

	/**
	 * Portal token resolver.
	 *
	 * @var PortalResolver
	 */
	private PortalResolver $portal;

	/**
	 * Context resolved during the permission callback.
	 *
	 * @var PortalContext|null
	 */
	private ?PortalContext $portal_context = null;

	/**
	 * Constructor.
	 *
	 * @param PortalService  $service  Portal reads.
	 * @param PortalResolver $portal   Portal token resolver.
	 * @param TenantResolver $resolver Tenant resolver, used by the shared base.
	 */
	public function __construct( PortalService $service, PortalResolver $portal, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
		$this->portal  = $portal;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		$list_args = array(
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
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 50,
				'sanitize_callback' => 'absint',
			),
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/portal/session',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'session' ),
				'permission_callback' => array( $this, 'authorize_portal' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/portal/orders',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index_orders' ),
				'permission_callback' => array( $this, 'authorize_portal' ),
				'args'                => $list_args,
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/portal/orders/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show_order' ),
				'permission_callback' => array( $this, 'authorize_portal' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/portal/invoices',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index_invoices' ),
				'permission_callback' => array( $this, 'authorize_portal' ),
				'args'                => $list_args,
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/portal/invoices/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show_invoice' ),
				'permission_callback' => array( $this, 'authorize_portal' ),
			)
		);
	}

	/**
	 * Resolve the portal token carried by the request.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function authorize_portal( WP_REST_Request $request ) {
		$context = $this->portal->from_request( $request );

		if ( $context instanceof WP_Error ) {
			return $context;
		}

		$this->portal_context = $context;

		return true;
	}

	/**
	 * The resolved portal context.
	 *
	 * @return PortalContext|WP_Error
	 */
	private function context() {
		if ( null === $this->portal_context ) {
			return Rest::portal_unauthorized();
		}

		return $this->portal_context;
	}

	/**
	 * Keep a portal response out of shared caches and search indexes.
	 *
	 * Every route in this controller answers with one customer's records behind
	 * a bearer token, and every request shares the same URL. A proxy that keys
	 * on the URL alone would otherwise be free to hand one customer's response
	 * to the next request, so both success and error envelopes say `no-store`.
	 *
	 * @param WP_REST_Response $response Response to stamp.
	 */
	private function stamp( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );

		return $response;
	}

	/**
	 * Send a portal payload.
	 *
	 * @param array<string, mixed> $data Response payload.
	 */
	private function respond( array $data ): WP_REST_Response {
		return $this->stamp( Response::success( $data ) );
	}

	/**
	 * Send a portal failure with the same cache directives as a success.
	 *
	 * @param WP_Error $error Error to send.
	 */
	protected function failure( WP_Error $error ): WP_REST_Response {
		return $this->stamp( parent::failure( $error ) );
	}

	/**
	 * Studio branding, the customer's profile and a summary of what is open.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function session( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$context = $this->context();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return $this->respond( $this->service->session( $context ) );
	}

	/**
	 * The customer's own orders.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function index_orders( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->context();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return $this->respond(
			$this->service->orders(
				$context,
				array(
					'page'    => $request['page'],
					'perPage' => $request['per_page'],
				)
			)
		);
	}

	/**
	 * One of the customer's orders.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show_order( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->context();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$order = $this->service->order( $context, (int) $request['id'] );

		if ( $order instanceof WP_Error ) {
			return $this->failure( $order );
		}

		return $this->respond( $order );
	}

	/**
	 * The customer's issued invoices.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function index_invoices( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->context();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return $this->respond(
			$this->service->invoices(
				$context,
				array(
					'page'    => $request['page'],
					'perPage' => $request['per_page'],
				)
			)
		);
	}

	/**
	 * One of the customer's invoices.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show_invoice( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->context();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$invoice = $this->service->invoice( $context, (int) $request['id'] );

		if ( $invoice instanceof WP_Error ) {
			return $this->failure( $invoice );
		}

		return $this->respond( $invoice );
	}
}
