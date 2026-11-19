<?php
/**
 * Audit routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Controller;
use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\TenantResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only access to the active workspace's audit trail.
 */
final class AuditController extends Controller {

	/**
	 * Audit reads.
	 *
	 * @var AuditLogRepository
	 */
	private AuditLogRepository $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogRepository $audit    Audit reads.
	 * @param TenantResolver     $resolver Tenant resolver.
	 */
	public function __construct( AuditLogRepository $audit, TenantResolver $resolver ) {
		parent::__construct( $resolver );

		$this->audit = $audit;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/audit',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'limit' => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 50,
						'minimum'           => 1,
						'maximum'           => 200,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Require a workspace with audit read access.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->authorize( $request, Permissions::AUDIT_VIEW, __( 'You cannot view the audit trail.', 'fashionos' ) );
	}

	/**
	 * Recent audit entries for the active workspace.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$context = $this->active();

		if ( $context instanceof WP_Error ) {
			return $this->failure( $context );
		}

		$entries = array();

		foreach ( $this->audit->recent( $context->id(), (int) $request['limit'] ) as $row ) {
			$entries[] = array(
				'id'         => (int) $row['id'],
				'action'     => (string) $row['action'],
				'actorId'    => (int) $row['actor_id'],
				'actorName'  => (string) ( $row['actor_name'] ?? '' ),
				'entityType' => (string) $row['entity_type'],
				'entityId'   => (int) $row['entity_id'],
				'metadata'   => (object) ( $row['metadata'] ?? array() ),
				'createdAt'  => (string) $row['created_at'],
			);
		}

		return Response::success( array( 'entries' => $entries ) );
	}
}
