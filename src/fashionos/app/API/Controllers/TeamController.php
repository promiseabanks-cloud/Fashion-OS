<?php
/**
 * Team routes.
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
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\Roles;
use FashionOS\Tenancy\TenantResolver;
use FashionOS\Tenancy\TenantService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages who belongs to the active workspace and with which role.
 *
 * Memberships are per workspace, so the same person can be a manager in one
 * business and a tailor in another.
 */
final class TeamController extends Controller {

	/**
	 * Membership reads.
	 *
	 * @var MembershipRepository
	 */
	private MembershipRepository $memberships;

	/**
	 * Role reads.
	 *
	 * @var RoleRepository
	 */
	private RoleRepository $roles;

	/**
	 * Tenancy workflows.
	 *
	 * @var TenantService
	 */
	private TenantService $service;

	/**
	 * Constructor.
	 *
	 * @param MembershipRepository $memberships Membership reads.
	 * @param RoleRepository       $roles       Role reads.
	 * @param TenantResolver       $resolver    Tenant resolver.
	 * @param TenantService        $service     Tenancy workflows.
	 */
	public function __construct(
		MembershipRepository $memberships,
		RoleRepository $roles,
		TenantResolver $resolver,
		TenantService $service
	) {
		parent::__construct( $resolver );

		$this->memberships = $memberships;
		$this->roles       = $roles;
		$this->service     = $service;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/team',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
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
			'/team/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'destroy' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Require a workspace with team read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::TEAM_VIEW, __( 'You cannot view this team.', 'fashionos' ) );
	}

	/**
	 * Require a workspace with team management rights.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_manage( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::TEAM_MANAGE, __( 'You do not have permission to manage this team.', 'fashionos' ) );
	}

	/**
	 * Team list and assignable roles.
	 */
	public function index(): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$members = array();

		foreach ( $this->memberships->for_tenant( $context->id() ) as $row ) {
			$members[] = array(
				'userId'   => (int) $row['user_id'],
				'name'     => (string) $row['display_name'],
				'email'    => (string) $row['user_email'],
				'role'     => (string) $row['role_slug'],
				'roleName' => (string) $row['role_name'],
				'status'   => (string) $row['status'],
				'joinedAt' => (string) $row['created_at'],
				'isOwner'  => Roles::OWNER === (string) $row['role_slug'],
			);
		}

		$assignable = array_values(
			array_filter(
				$this->roles->for_tenant( $context->id() ),
				static fn ( array $role ): bool => Roles::OWNER !== (string) $role['slug']
			)
		);

		return Response::success(
			array(
				'members' => $members,
				'roles'   => array_map(
					static fn ( array $role ): array => array(
						'slug' => (string) $role['slug'],
						'name' => (string) $role['name'],
					),
					$assignable
				),
			)
		);
	}

	/**
	 * Add a member by email.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$result = $this->service->add_member( $context, $this->payload( $request ) );

		if ( $result instanceof WP_Error ) {
			return $this->failure( $result );
		}

		return Response::success( array( 'added' => true ), array(), 201 );
	}

	/**
	 * Change a member's role.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$payload = $this->payload( $request );
		$result  = $this->service->change_member_role(
			$context,
			(int) $request['user_id'],
			(string) ( $payload['role'] ?? '' )
		);

		if ( $result instanceof WP_Error ) {
			return $this->failure( $result );
		}

		return Response::success( array( 'updated' => true ) );
	}

	/**
	 * Remove a member.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$result = $this->service->remove_member( $context, (int) $request['user_id'] );

		if ( $result instanceof WP_Error ) {
			return $this->failure( $result );
		}

		return Response::success( array( 'removed' => true ) );
	}
}
