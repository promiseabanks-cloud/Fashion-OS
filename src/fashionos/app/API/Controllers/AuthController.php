<?php
/**
 * Session routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Security\Policy;
use FashionOS\Tenancy\TenantResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes the signed-in user's session.
 *
 * `GET /auth/me` is the shell's bootstrap call: it lists the workspaces the
 * user belongs to and the permissions of the active one.
 */
final class AuthController {

	/**
	 * Tenant reads.
	 *
	 * @var TenantRepository
	 */
	private TenantRepository $tenants;

	/**
	 * Membership reads.
	 *
	 * @var MembershipRepository
	 */
	private MembershipRepository $memberships;

	/**
	 * Tenant resolver.
	 *
	 * @var TenantResolver
	 */
	private TenantResolver $resolver;

	/**
	 * Constructor.
	 *
	 * @param TenantRepository     $tenants     Tenant reads.
	 * @param MembershipRepository $memberships Membership reads.
	 * @param TenantResolver       $resolver    Tenant resolver.
	 */
	public function __construct(
		TenantRepository $tenants,
		MembershipRepository $memberships,
		TenantResolver $resolver
	) {
		$this->tenants     = $tenants;
		$this->memberships = $memberships;
		$this->resolver    = $resolver;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/auth/me',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'me' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return true|\WP_Error
	 */
	public function can_read() {
		if ( ! is_user_logged_in() ) {
			return Rest::unauthenticated();
		}

		return true;
	}

	/**
	 * Return the session payload.
	 *
	 * @param \WP_REST_Request $request Current request.
	 */
	public function me( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id    = get_current_user_id();
		$user       = wp_get_current_user();
		$workspaces = array();

		foreach ( $this->tenants->for_user( $user_id ) as $row ) {
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

		$context         = $this->resolver->from_request( $request );
		$active          = null;
		$workspace_error = null;

		if ( $context instanceof \WP_Error ) {
			// A missing or invalid selection is not an error for this endpoint:
			// the client shows the workspace chooser instead.
			$error_code      = $context->get_error_code();
			$workspace_error = in_array( $error_code, array( 'workspace_not_found', 'workspace_access_denied', 'workspace_required' ), true )
				? $error_code
				: null;
		} else {
			$active                = $context->present();
			$active['memberCount'] = $this->memberships->count_for_tenant( $context->id() );
		}

		return Response::success(
			array(
				'user'           => array(
					'id'                => (int) $user->ID,
					'displayName'       => (string) $user->display_name,
					'canManagePlatform' => Policy::is_platform_admin(),
				),
				'workspaces'     => $workspaces,
				'activeTenant'   => $active,
				'workspaceError' => $workspace_error,
			)
		);
	}
}
