<?php
/**
 * System routes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Controllers;

use FashionOS\API\Responses\Response;
use FashionOS\API\Rest;
use FashionOS\Core\Assets;
use FashionOS\Database\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only system health reporting for the application shell.
 *
 * This is deliberately small: it proves the REST namespace, authentication,
 * migration runner and build pipeline are wired up before Phase 1 adds the
 * real API surface.
 */
final class SystemController {

	/**
	 * Migration runner.
	 *
	 * @var Migrator
	 */
	private Migrator $migrator;

	/**
	 * Asset loader.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Constructor.
	 *
	 * @param Migrator $migrator Migration runner.
	 * @param Assets   $assets   Asset loader.
	 */
	public function __construct( Migrator $migrator, Assets $assets ) {
		$this->migrator = $migrator;
		$this->assets   = $assets;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/system/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
	}

	/**
	 * Permission check for system status.
	 *
	 * @return true|\WP_Error
	 */
	public function can_read() {
		if ( ! is_user_logged_in() ) {
			return Rest::unauthenticated();
		}

		if ( ! current_user_can( 'read' ) ) {
			return Rest::forbidden( __( 'You do not have permission to access this resource.', 'fashionos' ) );
		}

		return true;
	}

	/**
	 * Report plugin, database and build status.
	 */
	public function health(): \WP_REST_Response {
		return Response::success(
			array(
				'pluginVersion' => FASHIONOS_VERSION,
				'database'      => $this->migrator->status(),
				'build'         => array(
					'built'       => $this->assets->is_built(),
					'development' => Assets::is_development(),
				),
				'server'        => array(
					'php'       => PHP_VERSION,
					'wordpress' => get_bloginfo( 'version' ),
				),
			)
		);
	}
}
