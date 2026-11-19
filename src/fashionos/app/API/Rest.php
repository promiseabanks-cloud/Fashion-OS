<?php
/**
 * REST namespace helpers.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared REST constants and error constructors.
 *
 * Every FashionOS route lives in one namespace and returns the same error
 * envelope, so the client can branch on stable codes everywhere.
 */
final class Rest {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'fashionos/v1';

	/**
	 * Header carrying the selected workspace (id or slug).
	 *
	 * The value is a selector, never proof of access: the server checks the
	 * authenticated user's membership before honouring it.
	 */
	public const WORKSPACE_HEADER = 'X-FashionOS-Workspace';

	/**
	 * 401 for missing authentication.
	 */
	public static function unauthenticated(): \WP_Error {
		return new \WP_Error(
			'not_authenticated',
			__( 'You must be signed in to access this resource.', 'fashionos' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * 403 for an authenticated user without access.
	 *
	 * @param string $message  Human-readable message.
	 * @param string $error_code Stable machine-readable code.
	 */
	public static function forbidden( string $message, string $error_code = 'forbidden' ): \WP_Error {
		return new \WP_Error( $error_code, $message, array( 'status' => 403 ) );
	}

	/**
	 * 404 for a missing record.
	 *
	 * @param string $message    Human-readable message.
	 * @param string $error_code Stable machine-readable code.
	 */
	public static function not_found( string $message, string $error_code = 'not_found' ): \WP_Error {
		return new \WP_Error( $error_code, $message, array( 'status' => 404 ) );
	}

	/**
	 * Validation or business-rule error.
	 *
	 * @param string               $error_code Stable machine-readable code.
	 * @param string               $message    Human-readable message.
	 * @param int                  $status     HTTP status.
	 * @param array<string, mixed> $details    Optional field-level details.
	 */
	public static function invalid( string $error_code, string $message, int $status = 422, array $details = array() ): \WP_Error {
		$data = array( 'status' => $status );

		if ( array() !== $details ) {
			$data['details'] = $details;
		}

		return new \WP_Error( $error_code, $message, $data );
	}
}
