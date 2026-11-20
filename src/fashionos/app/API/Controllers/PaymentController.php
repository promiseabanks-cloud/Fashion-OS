<?php
/**
 * Payment routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Finance\PaymentService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment recording and verification.
 *
 * Recording returns the existing payment when the same idempotency key is
 * submitted twice, with 200 instead of 201, so a retried request is safe.
 */
final class PaymentController extends Controller {

	/**
	 * Payment workflows.
	 *
	 * @var PaymentService
	 */
	private PaymentService $service;

	/**
	 * Constructor.
	 *
	 * @param PaymentService $service  Payment workflows.
	 * @param TenantResolver $resolver Tenant resolver.
	 */
	public function __construct( PaymentService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/payments',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'invoice_id' => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
						),
						'status'     => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'description'       => __( 'pending, verified, rejected or all.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'page'       => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'   => array(
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
			'/payments/(?P<id>[\d]+)',
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
	 * Require payment read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::PAYMENTS_VIEW, __( 'You cannot view payments in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require payment management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::PAYMENTS_MANAGE, __( 'You cannot record or verify payments in this workspace.', 'fashionos' ) );
	}

	/**
	 * Paginated payment list.
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
					'invoiceId' => $request['invoice_id'],
					'status'    => $request['status'],
					'page'      => $request['page'],
					'perPage'   => $request['per_page'],
				)
			)
		);
	}

	/**
	 * One payment.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$payment = $this->service->find( $context, (int) $request['id'] );

		if ( $payment instanceof WP_Error ) {
			return $this->failure( $payment );
		}

		return Response::success( $payment );
	}

	/**
	 * Record a payment.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$result = $this->service->create( $context, $this->payload( $request ) );

		if ( $result instanceof WP_Error ) {
			return $this->failure( $result );
		}

		return Response::success( $result['payment'], array(), $result['duplicate'] ? 200 : 201 );
	}

	/**
	 * Verify or reject a payment.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$payment = $this->service->update( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $payment instanceof WP_Error ) {
			return $this->failure( $payment );
		}

		return Response::success( $payment );
	}
}
