<?php
/**
 * Invoice routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Finance\InvoiceService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice list, detail and status changes.
 */
final class InvoiceController extends Controller {

	/**
	 * Invoice workflows.
	 *
	 * @var InvoiceService
	 */
	private InvoiceService $service;

	/**
	 * Constructor.
	 *
	 * @param InvoiceService $service  Invoice workflows.
	 * @param TenantResolver $resolver Tenant resolver.
	 */
	public function __construct( InvoiceService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/invoices',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'search'    => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'description'       => __( 'Match reference, customer or order.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status'    => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'description'       => __( 'draft, issued, partially_paid, paid, void or all.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'client_id' => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
						),
						'overdue'   => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'description'       => __( 'When set, only open invoices past their due date.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_text_field',
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
			'/invoices/(?P<id>[\d]+)',
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
	 * Require invoice read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::INVOICES_VIEW, __( 'You cannot view invoices in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require invoice management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::INVOICES_MANAGE, __( 'You cannot change invoices in this workspace.', 'fashionos' ) );
	}

	/**
	 * Paginated invoice list.
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
					'overdue'  => filter_var( $request['overdue'], FILTER_VALIDATE_BOOLEAN ),
					'page'     => $request['page'],
					'perPage'  => $request['per_page'],
				)
			)
		);
	}

	/**
	 * One invoice.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$invoice = $this->service->find( $context, (int) $request['id'] );

		if ( $invoice instanceof WP_Error ) {
			return $this->failure( $invoice );
		}

		return Response::success( $invoice );
	}

	/**
	 * Create an invoice.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$invoice = $this->service->create( $context, $this->payload( $request ) );

		if ( $invoice instanceof WP_Error ) {
			return $this->failure( $invoice );
		}

		return Response::success( $invoice, array(), 201 );
	}

	/**
	 * Update an invoice.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$invoice = $this->service->update( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $invoice instanceof WP_Error ) {
			return $this->failure( $invoice );
		}

		return Response::success( $invoice );
	}
}
