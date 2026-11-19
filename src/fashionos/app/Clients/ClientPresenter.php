<?php
/**
 * Client response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Clients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps customer rows to the API's public field names.
 *
 * Internal columns (`created_by`, `tenant_id`) never reach the client: the
 * workspace is implied by the request that returned the record.
 */
final class ClientPresenter {

	/**
	 * Preference keys exposed by the API, with their blank defaults.
	 *
	 * @return array<string, string>
	 */
	public static function empty_preferences(): array {
		return array(
			'preferredContact' => '',
			'styleNotes'       => '',
			'fitNotes'         => '',
		);
	}

	/**
	 * Public customer fields.
	 *
	 * @param array<string, mixed> $row         Client row.
	 * @param array<string, mixed> $preferences Preference values.
	 * @return array<string, mixed>
	 */
	public static function to_array( array $row, array $preferences = array() ): array {
		$values = array_merge( self::empty_preferences(), $preferences );

		return array(
			'id'                 => (int) ( $row['id'] ?? 0 ),
			'uuid'               => (string) ( $row['uuid'] ?? '' ),
			'reference'          => (string) ( $row['reference'] ?? '' ),
			'name'               => (string) ( $row['name'] ?? '' ),
			'email'              => (string) ( $row['email'] ?? '' ),
			'phone'              => (string) ( $row['phone'] ?? '' ),
			'notes'              => (string) ( $row['notes'] ?? '' ),
			'status'             => (string) ( $row['status'] ?? '' ),
			'preferences'        => array(
				'preferredContact' => (string) $values['preferredContact'],
				'styleNotes'       => (string) $values['styleNotes'],
				'fitNotes'         => (string) $values['fitNotes'],
			),
			'profileCount'       => (int) ( $row['profile_count'] ?? 0 ),
			'createdAt'          => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'          => (string) ( $row['updated_at'] ?? '' ),
		);
	}
}
