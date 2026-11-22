<?php
/**
 * Shared controller plumbing.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API;

use FashionOS\API\Responses\Response;
use FashionOS\Security\Policy;
use FashionOS\Tenancy\TenantContext;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base for controllers that act inside one workspace.
 *
 * Every such route follows the same shape: resolve the workspace from the
 * request, check one permission, then use the verified context. Doing that in
 * one place keeps the tenant rules identical across modules and stops the
 * per-controller copies of payload parsing and error mapping from drifting.
 */
abstract class Controller {

	/**
	 * Tenant resolver.
	 *
	 * @var TenantResolver
	 */
	protected TenantResolver $resolver;

	/**
	 * Context resolved during the permission callback.
	 *
	 * @var TenantContext|null
	 */
	protected ?TenantContext $context = null;

	/**
	 * Constructor.
	 *
	 * @param TenantResolver $resolver Tenant resolver.
	 */
	public function __construct( TenantResolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Require a signed-in user.
	 *
	 * @return true|WP_Error
	 */
	protected function signed_in() {
		if ( ! is_user_logged_in() ) {
			return Rest::unauthenticated();
		}

		return true;
	}

	/**
	 * Resolve the active workspace and require one permission.
	 *
	 * @param WP_REST_Request $request    Current request.
	 * @param string          $permission Permission key from the catalogue.
	 * @param string          $message    Message used when the permission is missing.
	 * @return true|WP_Error
	 */
	protected function authorize( WP_REST_Request $request, string $permission, string $message ) {
		$context = $this->resolver->from_request( $request );

		if ( $context instanceof WP_Error ) {
			return $context;
		}

		if ( ! Policy::allows( $context, $permission ) ) {
			return Rest::forbidden( $message );
		}

		$this->context = $context;

		return true;
	}

	/**
	 * The context resolved by the permission callback.
	 *
	 * @return TenantContext|WP_Error
	 */
	protected function active() {
		if ( null === $this->context ) {
			return Rest::invalid( 'workspace_required', __( 'No workspace is selected.', 'fashionos' ), 400 );
		}

		return $this->context;
	}

	/**
	 * Read the JSON body.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return array<string, mixed>
	 */
	protected function payload( WP_REST_Request $request ): array {
		$params = $request->get_json_params();

		return is_array( $params ) ? $params : array();
	}

	/**
	 * Convert a WP_Error into the response envelope.
	 *
	 * @param WP_Error $error Error to send.
	 */
	protected function failure( WP_Error $error ): WP_REST_Response {
		return Response::from_error( $error );
	}
}
