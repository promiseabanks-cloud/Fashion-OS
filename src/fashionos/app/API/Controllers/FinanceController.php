<?php
/**
 * Finance dashboard route.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Finance\FinanceService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Balances, collections and expenses in one read.
 */
final class FinanceController extends Controller {

	/**
	 * Finance dashboard.
	 *
	 * @var FinanceService
	 */
	private FinanceService $service;

	/**
	 * Constructor.
	 *
	 * @param FinanceService $service  Finance dashboard.
	 * @param TenantResolver $resolver Tenant resolver.
	 */
	public function __construct( FinanceService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/finance/summary',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'summary' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
	}

	/**
	 * Require invoice read access; the summary is made of invoices and payments.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::INVOICES_VIEW, __( 'You cannot view the finance summary in this workspace.', 'fashionos' ) );
	}

	/**
	 * Financial position for the active workspace.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function summary( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return Response::success( $this->service->summary( $context ) );
	}
}
