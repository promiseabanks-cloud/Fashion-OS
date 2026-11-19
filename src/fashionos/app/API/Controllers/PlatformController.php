<?php
/**
 * Platform administration routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Security\Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deliberate, audited platform-level access.
 *
 * Platform administration is separate from tenant membership: it is available
 * only to WordPress administrators and every use is recorded with tenant id 0
 * so it never blends into a tenant's own audit trail. Tenant management tools
 * arrive in Phase 8; this phase provides the read-only platform view.
 */
final class PlatformController {

	/**
	 * Tenant reads.
	 *
	 * @var TenantRepository
	 */
	private TenantRepository $tenants;

	/**
	 * Audit writes.
	 *
	 * @var AuditLogRepository
	 */
	private AuditLogRepository $audit;

	/**
	 * Constructor.
	 *
	 * @param TenantRepository   $tenants Tenant reads.
	 * @param AuditLogRepository $audit   Audit writes.
	 */
	public function __construct( TenantRepository $tenants, AuditLogRepository $audit ) {
		$this->tenants = $tenants;
		$this->audit   = $audit;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/platform/tenants',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
	}

	/**
	 * Require a WordPress platform administrator.
	 *
	 * @return true|\WP_Error
	 */
	public function can_view() {
		if ( ! is_user_logged_in() ) {
			return Rest::unauthenticated();
		}

		if ( ! Policy::is_platform_admin() ) {
			return Rest::forbidden( __( 'Platform administration is restricted to platform administrators.', 'fashionos' ) );
		}

		return true;
	}

	/**
	 * List workspaces across the platform.
	 */
	public function index(): \WP_REST_Response {
		$tenants = array();

		foreach ( $this->tenants->all_with_counts() as $row ) {
			$tenants[] = array(
				'id'          => (int) $row['id'],
				'name'        => (string) $row['name'],
				'slug'        => (string) $row['slug'],
				'status'      => (string) $row['status'],
				'memberCount' => (int) $row['member_count'],
				'createdAt'   => (string) $row['created_at'],
			);
		}

		$this->audit->record(
			0,
			get_current_user_id(),
			'platform.tenants_viewed',
			'platform',
			0,
			array( 'count' => count( $tenants ) )
		);

		return Response::success( array( 'tenants' => $tenants ) );
	}
}
