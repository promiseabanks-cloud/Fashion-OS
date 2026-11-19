<?php
/**
 * Error envelope normalization.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Responses;

use FashionOS\API\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps WordPress REST errors in the FashionOS envelope.
 *
 * Permission callbacks return WP_Error, which WordPress normally sends as
 * `{ code, message, data }`. The React client expects `{ success: false, error }`
 * everywhere, so errors from this namespace are converted once, centrally.
 */
final class ErrorEnvelope {

	/**
	 * Register the filter.
	 */
	public static function register(): void {
		add_filter( 'rest_post_dispatch', array( self::class, 'normalize' ), 10, 3 );
	}

	/**
	 * Convert a FashionOS REST error into the standard envelope.
	 *
	 * @param mixed            $response Response being dispatched.
	 * @param mixed            $server   REST server.
	 * @param \WP_REST_Request $request  Current request.
	 * @return mixed
	 */
	public static function normalize( $response, $server, $request ) {
		unset( $server );

		if ( ! $response instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		if ( ! str_starts_with( $request->get_route(), '/' . Rest::NAMESPACE . '/' ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! is_array( $data ) || isset( $data['success'] ) || ! isset( $data['code'], $data['message'] ) ) {
			return $response;
		}

		$details = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();

		unset( $details['status'] );

		return Response::error(
			(string) $data['code'],
			(string) $data['message'],
			(int) $response->get_status(),
			$details
		);
	}
}
