<?php
/**
 * Workspace routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantContext;
use FashionOS\Tenancy\TenantResolver;
use FashionOS\Tenancy\TenantService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists, creates and updates workspaces.
 *
 * Creating a workspace does not require an active tenant: the creator becomes
 * its owner. Everything scoped to one workspace resolves and authorizes the
 * context in its permission callback.
 */
final class TenantController extends Controller {

	/**
	 * Tenant reads.
	 *
	 * @var TenantRepository
	 */
	private TenantRepository $tenants;

	/**
	 * Role reads.
	 *
	 * @var RoleRepository
	 */
	private RoleRepository $roles;

	/**
	 * Membership reads.
	 *
	 * @var MembershipRepository
	 */
	private MembershipRepository $memberships;

	/**
	 * Tenancy workflows.
	 *
	 * @var TenantService
	 */
	private TenantService $service;

	/**
	 * Constructor.
	 *
	 * @param TenantRepository     $tenants     Tenant reads.
	 * @param RoleRepository       $roles       Role reads.
	 * @param MembershipRepository $memberships Membership reads.
	 * @param TenantResolver       $resolver    Tenant resolver.
	 * @param TenantService        $service     Tenancy workflows.
	 */
	public function __construct(
		TenantRepository $tenants,
		RoleRepository $roles,
		MembershipRepository $memberships,
		TenantResolver $resolver,
		TenantService $service
	) {
		parent::__construct( $resolver );

		$this->tenants     = $tenants;
		$this->roles       = $roles;
		$this->memberships = $memberships;
		$this->service     = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/tenants',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => array(
						'name'     => array(
							'type'        => 'string',
							'required'    => true,
							'description' => __( 'Workspace name.', 'fashionos' ),
						),
						'timezone' => array(
							'type'        => 'string',
							'required'    => false,
							'description' => __( 'IANA timezone identifier.', 'fashionos' ),
						),
						'currency' => array(
							'type'        => 'string',
							'required'    => false,
							'description' => __( 'Three-letter currency code.', 'fashionos' ),
						),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/tenants/current',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'current' ),
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
	 * Any signed-in user may list their own workspaces or create one.
	 *
	 * @return true|WP_Error
	 */
	public function can_read() {
		return $this->signed_in();
	}

	/**
	 * Resolve the active workspace for read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::WORKSPACE_VIEW, __( 'You cannot view this workspace.', 'fashionos' ) );
	}

	/**
	 * Resolve the active workspace and require management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::WORKSPACE_MANAGE, __( 'You cannot change this workspace.', 'fashionos' ) );
	}

	/**
	 * Workspaces of the signed-in user.
	 */
	public function index(): WP_REST_Response {
		$workspaces = array();

		foreach ( $this->tenants->for_user( get_current_user_id() ) as $row ) {
			$workspaces[] = array(
				'id'     => (int) $row['id'],
				'name'   => (string) $row['name'],
				'slug'   => (string) $row['slug'],
				'status' => (string) $row['status'],
				'role'   => array(
					'slug' => (string) $row['role_slug'],
					'name' => (string) $row['role_name'],
				),
			);
		}

		return Response::success( array( 'workspaces' => $workspaces ) );
	}

	/**
	 * Create a workspace for the signed-in user.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->service->create( get_current_user_id(), $this->payload( $request ) );

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return Response::success( $this->present( $context ), array(), 201 );
	}

	/**
	 * The active workspace, its role and its team size.
	 */
	public function current(): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		return Response::success( $this->present( $context ) );
	}

	/**
	 * Update the active workspace profile.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$updated = $this->service->update( $context, $this->payload( $request ) );

		if ( $updated instanceof WP_Error ) {
			return $this->failure( $updated );
		}

		return Response::success( $this->present( $updated ) );
	}

	/**
	 * Response shape for a workspace context.
	 *
	 * @param TenantContext $context Resolved context.
	 * @return array<string, mixed>
	 */
	private function present( TenantContext $context ): array {
		$data                = $context->present();
		$data['memberCount'] = $this->memberships->count_for_tenant( $context->id() );
		$data['roles']       = array_map(
			static fn ( array $role ): array => array(
				'slug'        => (string) $role['slug'],
				'name'        => (string) $role['name'],
				'permissions' => (array) $role['permissions'],
			),
			$this->roles->for_tenant( $context->id() )
		);

		return $data;
	}
}
