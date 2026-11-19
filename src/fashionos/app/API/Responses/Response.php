<?php
/**
 * API response helpers.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API\Responses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the consistent FashionOS response envelope.
 *
 * Success: { "success": true,  "data": {}, "meta": {} }
 * Error:   { "success": false, "error": { "code": "", "message": "" } }
 */
final class Response {

	/**
	 * Success response.
	 *
	 * @param array<string, mixed> $data   Response payload.
	 * @param array<string, mixed> $meta   Pagination and related metadata.
	 * @param int                  $status HTTP status code.
	 */
	public static function success( array $data = array(), array $meta = array(), int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
				'meta'    => (object) $meta,
			),
			$status
		);
	}

	/**
	 * Error response.
	 *
	 * @param string               $code    Stable machine-readable error code.
	 * @param string               $message Human-readable message.
	 * @param int                  $status  HTTP status code.
	 * @param array<string, mixed> $details Optional field-level details.
	 */
	public static function error( string $code, string $message, int $status = 400, array $details = array() ): \WP_REST_Response {
		$error = array(
			'code'    => $code,
			'message' => $message,
		);

		if ( array() !== $details ) {
			$error['details'] = (object) $details;
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'error'   => (object) $error,
			),
			$status
		);
	}
}
