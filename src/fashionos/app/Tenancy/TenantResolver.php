<?php
/**
 * Tenant resolution.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Tenancy;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\TenantRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a workspace selector into a verified tenant context.
 *
 * The browser may name a workspace, but access is granted only when an active
 * membership row exists for the authenticated user. A missing selector falls
 * back to the user's only workspace and is otherwise ambiguous by design.
 */
final class TenantResolver {

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
	 * Contexts resolved during this request, keyed by user and selector.
	 *
	 * @var array<string, TenantContext|\WP_Error>
	 */
	private array $resolved = array();

	/**
	 * Constructor.
	 *
	 * @param TenantRepository     $tenants     Tenant reads.
	 * @param MembershipRepository $memberships Membership reads.
	 */
	public function __construct( TenantRepository $tenants, MembershipRepository $memberships ) {
		$this->tenants     = $tenants;
		$this->memberships = $memberships;
	}

	/**
	 * Resolve the workspace named by the request header.
	 *
	 * @param \WP_REST_Request $request Current request.
	 * @return TenantContext|\WP_Error
	 */
	public function from_request( \WP_REST_Request $request ) {
		$selector = $request->get_header( Rest::WORKSPACE_HEADER );

		return $this->resolve( get_current_user_id(), is_string( $selector ) ? $selector : '' );
	}

	/**
	 * Resolve a workspace for a user.
	 *
	 * @param int    $user_id  WordPress user id.
	 * @param string $selector Tenant id, tenant slug, or an empty string.
	 * @return TenantContext|\WP_Error
	 */
	public function resolve( int $user_id, string $selector = '' ) {
		$selector = trim( sanitize_text_field( $selector ) );
		$cache_key = $user_id . '|' . $selector;

		if ( isset( $this->resolved[ $cache_key ] ) ) {
			return $this->resolved[ $cache_key ];
		}

		$context = $this->do_resolve( $user_id, $selector );

		$this->resolved[ $cache_key ] = $context;

		return $context;
	}

	/**
	 * Perform the resolution and verification.
	 *
	 * @param int    $user_id  WordPress user id.
	 * @param string $selector Tenant id, tenant slug, or an empty string.
	 * @return TenantContext|\WP_Error
	 */
	private function do_resolve( int $user_id, string $selector ) {
		if ( $user_id <= 0 ) {
			return Rest::unauthenticated();
		}

		$tenant = $this->find_tenant( $user_id, $selector );

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$membership = $this->memberships->find_for_user( (int) $tenant['id'], $user_id );

		if ( null === $membership || 'active' !== (string) $membership['status'] ) {
			return Rest::forbidden( __( 'You do not have access to this workspace.', 'fashionos' ), 'workspace_access_denied' );
		}

		return new TenantContext( $tenant, $membership );
	}

	/**
	 * Find the requested tenant, or the user's only workspace.
	 *
	 * @param int    $user_id  WordPress user id.
	 * @param string $selector Tenant id, tenant slug, or an empty string.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function find_tenant( int $user_id, string $selector ) {
		if ( '' !== $selector ) {
			$tenant = ctype_digit( $selector )
				? $this->tenants->find( (int) $selector )
				: $this->tenants->find_by_slug( $selector );

			if ( null === $tenant ) {
				return Rest::not_found( __( 'That workspace could not be found.', 'fashionos' ), 'workspace_not_found' );
			}

			return $tenant;
		}

		$workspaces = $this->tenants->for_user( $user_id );

		if ( array() === $workspaces ) {
			return Rest::forbidden( __( 'You do not belong to a workspace yet.', 'fashionos' ), 'workspace_required' );
		}

		if ( count( $workspaces ) > 1 ) {
			return Rest::invalid(
				'workspace_required',
				__( 'Choose which workspace you want to work in.', 'fashionos' ),
				400
			);
		}

		return $workspaces[0];
	}
}
