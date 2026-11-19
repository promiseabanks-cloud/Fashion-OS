<?php
/**
 * Measurement routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Measurements\MeasurementService;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Measurement templates, profiles and recorded versions.
 *
 * Templates are the configurable part; profiles group measurements per garment
 * within a customer; versions are append-only snapshots.
 */
final class MeasurementController extends Controller {

	/**
	 * Measurement workflows.
	 *
	 * @var MeasurementService
	 */
	private MeasurementService $service;

	/**
	 * Constructor.
	 *
	 * @param MeasurementService $service  Measurement workflows.
	 * @param TenantResolver     $resolver Tenant resolver.
	 */
	public function __construct( MeasurementService $service, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->service = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/measurement-templates',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'templates' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_template' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/measurement-templates/(?P<id>[\d]+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_template' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/clients/(?P<client_id>[\d]+)/measurements',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'profiles' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_profile' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/measurement-profiles/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'profile' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/measurement-profiles/(?P<id>[\d]+)/versions',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_version' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/measurement-versions/(?P<id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'version' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
	}

	/**
	 * Require measurement read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::MEASUREMENTS_VIEW, __( 'You cannot view measurements in this workspace.', 'fashionos' ) );
	}

	/**
	 * Require measurement management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::MEASUREMENTS_MANAGE, __( 'You cannot record measurements in this workspace.', 'fashionos' ) );
	}

	/**
	 * Templates of the active workspace.
	 */
	public function templates(): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return Response::success( array( 'templates' => $this->service->templates( $context ) ) );
	}

	/**
	 * Create a template.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create_template( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$template = $this->service->create_template( $context, $this->payload( $request ) );

		if ( $template instanceof WP_Error ) {
			return $this->failure( $template );
		}

		return Response::success( $template, array(), 201 );
	}

	/**
	 * Update a template.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update_template( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$template = $this->service->update_template( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $template instanceof WP_Error ) {
			return $this->failure( $template );
		}

		return Response::success( $template );
	}

	/**
	 * Profiles of one customer.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function profiles( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$profiles = $this->service->profiles( $context, (int) $request['client_id'] );

		if ( $profiles instanceof WP_Error ) {
			return $this->failure( $profiles );
		}

		return Response::success( $profiles );
	}

	/**
	 * Create a profile for one customer.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create_profile( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$profile = $this->service->create_profile( $context, (int) $request['client_id'], $this->payload( $request ) );

		if ( $profile instanceof WP_Error ) {
			return $this->failure( $profile );
		}

		return Response::success( $profile, array(), 201 );
	}

	/**
	 * One profile with its version history.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function profile( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$profile = $this->service->profile( $context, (int) $request['id'] );

		if ( $profile instanceof WP_Error ) {
			return $this->failure( $profile );
		}

		return Response::success( $profile );
	}

	/**
	 * Record a new version for a profile.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create_version( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$version = $this->service->create_version( $context, (int) $request['id'], $this->payload( $request ) );

		if ( $version instanceof WP_Error ) {
			return $this->failure( $version );
		}

		return Response::success( $version, array(), 201 );
	}

	/**
	 * One recorded version.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function version( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$version = $this->service->version( $context, (int) $request['id'] );

		if ( $version instanceof WP_Error ) {
			return $this->failure( $version );
		}

		return Response::success( $version );
	}
}
