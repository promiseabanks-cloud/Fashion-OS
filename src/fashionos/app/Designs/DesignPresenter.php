<?php
/**
 * Design response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Designs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps design rows to the API's public field names.
 */
final class DesignPresenter {

	/**
	 * Public design fields.
	 *
	 * @param array<string, mixed> $row Design row with joined names.
	 * @return array<string, mixed>
	 */
	public static function to_array( array $row ): array {
		return array(
			'id'                       => (int) ( $row['id'] ?? 0 ),
			'uuid'                     => (string) ( $row['uuid'] ?? '' ),
			'reference'                => (string) ( $row['reference'] ?? '' ),
			'clientId'                 => (int) ( $row['client_id'] ?? 0 ),
			'clientName'               => (string) ( $row['client_name'] ?? '' ),
			'measurementProfileId'     => (int) ( $row['measurement_profile_id'] ?? 0 ),
			'profileName'              => (string) ( $row['profile_name'] ?? '' ),
			'measurementVersionId'     => (int) ( $row['measurement_version_id'] ?? 0 ),
			'measurementVersionNumber' => (int) ( $row['measurement_version_number'] ?? 0 ),
			'measurementTakenAt'       => (string) ( $row['measurement_taken_at'] ?? '' ),
			'title'                    => (string) ( $row['title'] ?? '' ),
			'garmentCategory'          => (string) ( $row['garment_category'] ?? '' ),
			'brief'                    => (string) ( $row['brief'] ?? '' ),
			'status'                   => (string) ( $row['status'] ?? 'draft' ),
			'createdAt'                => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'                => (string) ( $row['updated_at'] ?? '' ),
		);
	}
}
