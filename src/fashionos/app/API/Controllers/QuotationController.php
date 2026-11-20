<?php
/**
 * Quotation routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Quotations\QuotationService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quotation list, detail and status changes.
 */
final class QuotationController extends Controller {

	/**
	 * Quotation workflows.
	 *
	 * @var QuotationService
	 */
	private QuotationService $service;

	/**
	 * Constructor.
	 *
	 * @param QuotationService $service  Quotation workflows.
	 * @param TenantResolver   $resolver Tenant resolver.
	 */
	public function __construct( QuotationService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/quotations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'search'    => array(
							'type'              => 'string',
							'required'          => false,
							'description'       => __( 'Match reference, customer or design.', 'fashionos' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status'    => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'all',
							'description'       => __( 'draft, sent, accepted, declined, expired or all.', 'fashionos' ),
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
			'/quotations/(?P<id>[\d]+)',
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
	 * Require quotation read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::QUOTATIONS_VIEW, __( 'You cannot view quotations in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require quotation management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::QUOTATIONS_MANAGE, __( 'You cannot change quotations in this workspace.', 'fashionos' ) );
	}

	/**
	 * Paginated quotation list.
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
	 * One quotation.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$quotation = $this->service->find( $context, (int) $request['id'] );

		if ( $quotation instanceof WP_Error ) {
			return $this->failure( $quotation );
		}

		return Response::success( $quotation );
	}

	/**
	 * Create a quotation.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$quotation = $this->service->create( $context, $this->payload( $request ) );

		if ( $quotation instanceof WP_Error ) {
			return $this->failure( $quotation );
		}

		return Response::success( $quotation, array(), 201 );
	}

	/**
	 * Update a quotation.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$quotation = $this->service->update( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $quotation instanceof WP_Error ) {
			return $this->failure( $quotation );
		}

		return Response::success( $quotation );
	}
}
