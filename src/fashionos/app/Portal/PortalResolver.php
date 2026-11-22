<?php
/**
 * Portal token resolution.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Portal;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\PortalAccessRepository;
use FashionOS\Database\Repositories\TenantRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a portal token into a verified portal context.
 *
 * The token is the only credential a customer presents. It is hashed and
 * compared, then checked for revocation, expiry, an active workspace and a
 * customer that still exists. Every failure returns the same 401 so a caller
 * cannot tell a wrong token from an expired one.
 */
final class PortalResolver {

	/**
	 * Access reads.
	 *
	 * @var PortalAccessRepository
	 */
	private PortalAccessRepository $access;

	/**
	 * Tenant reads.
	 *
	 * @var TenantRepository
	 */
	private TenantRepository $tenants;

	/**
	 * Customer reads.
	 *
	 * @var ClientRepository
	 */
	private ClientRepository $clients;

	/**
	 * Constructor.
	 *
	 * @param PortalAccessRepository $access  Access reads.
	 * @param TenantRepository       $tenants Tenant reads.
	 * @param ClientRepository       $clients Customer reads.
	 */
	public function __construct(
		PortalAccessRepository $access,
		TenantRepository $tenants,
		ClientRepository $clients
	) {
		$this->access  = $access;
		$this->tenants = $tenants;
		$this->clients = $clients;
	}

	/**
	 * Resolve the token carried by a request.
	 *
	 * @param \WP_REST_Request $request Current request.
	 * @return PortalContext|WP_Error
	 */
	public function from_request( \WP_REST_Request $request ) {
		$token = $request->get_header( Rest::PORTAL_HEADER );

		return $this->resolve( is_string( $token ) ? $token : '' );
	}

	/**
	 * Resolve a raw portal token.
	 *
	 * @param string $token Token presented by the customer.
	 * @return PortalContext|WP_Error
	 */
	public function resolve( string $token ) {
		$token = trim( $token );

		if ( '' === $token || mb_strlen( $token ) > 191 ) {
			return Rest::portal_unauthorized();
		}

		$access = $this->access->find_by_hash( hash( 'sha256', $token ) );

		if ( null === $access ) {
			return Rest::portal_unauthorized();
		}

		if ( '' !== (string) ( $access['revoked_at'] ?? '' ) ) {
			return Rest::portal_unauthorized();
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		$expires_at = (string) ( $access['expires_at'] ?? '' );

		if ( '' !== $expires_at && $expires_at < $now ) {
			return Rest::portal_unauthorized();
		}

		$tenant = $this->tenants->find( (int) ( $access['tenant_id'] ?? 0 ) );

		if ( null === $tenant || 'active' !== (string) ( $tenant['status'] ?? '' ) ) {
			return Rest::portal_unauthorized();
		}

		$client = $this->clients->find( (int) $tenant['id'], (int) ( $access['client_id'] ?? 0 ) );

		if ( null === $client || 'archived' === (string) ( $client['status'] ?? '' ) ) {
			return Rest::portal_unauthorized();
		}

		$this->access->touch(
			(int) $tenant['id'],
			(int) $access['id'],
			$now,
			gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
		);

		return new PortalContext( $tenant, $client, $access );
	}
}
