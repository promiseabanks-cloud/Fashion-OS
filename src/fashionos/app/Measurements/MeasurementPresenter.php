<?php
/**
 * Measurement response shapes.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Measurements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps template, profile, version and value rows to public field names.
 */
final class MeasurementPresenter {

	/**
	 * Public template fields.
	 *
	 * @param array<string, mixed> $row Template row with `fields`.
	 * @return array<string, mixed>
	 */
	public static function template( array $row ): array {
		return array(
			'id'              => (int) ( $row['id'] ?? 0 ),
			'uuid'            => (string) ( $row['uuid'] ?? '' ),
			'name'            => (string) ( $row['name'] ?? '' ),
			'garmentCategory' => (string) ( $row['garment_category'] ?? '' ),
			'unit'            => (string) ( $row['unit'] ?? 'cm' ),
			'status'          => (string) ( $row['status'] ?? 'active' ),
			'schemaVersion'   => (int) ( $row['schema_version'] ?? 1 ),
			'fields'          => array_map(
				static fn ( array $field ): array => array(
					'key'      => (string) ( $field['key'] ?? '' ),
					'label'    => (string) ( $field['label'] ?? '' ),
					'hint'     => (string) ( $field['hint'] ?? '' ),
					'required' => (bool) ( $field['required'] ?? false ),
				),
				array_values( is_array( $row['fields'] ?? null ) ? $row['fields'] : array() )
			),
			'createdAt'       => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'       => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public profile fields, including its latest version summary.
	 *
	 * @param array<string, mixed> $row Profile row with `latest_version`.
	 * @return array<string, mixed>
	 */
	public static function profile( array $row ): array {
		$latest = is_array( $row['latest_version'] ?? null ) ? $row['latest_version'] : null;

		return array(
			'id'              => (int) ( $row['id'] ?? 0 ),
			'uuid'            => (string) ( $row['uuid'] ?? '' ),
			'clientId'        => (int) ( $row['client_id'] ?? 0 ),
			'profileName'     => (string) ( $row['profile_name'] ?? '' ),
			'garmentCategory' => (string) ( $row['garment_category'] ?? '' ),
			'templateId'      => (int) ( $row['template_id'] ?? 0 ),
			'templateName'    => (string) ( $row['template_name'] ?? '' ),
			'status'          => (string) ( $row['status'] ?? 'active' ),
			'versionCount'    => (int) ( $row['version_count'] ?? 0 ),
			'latestVersion'   => null === $latest ? null : array(
				'id'            => (int) ( $latest['id'] ?? 0 ),
				'versionNumber' => (int) ( $latest['versionNumber'] ?? 0 ),
				'takenAt'       => (string) ( $latest['takenAt'] ?? '' ),
				'takenByName'   => (string) ( $latest['takenByName'] ?? '' ),
			),
			'createdAt'       => (string) ( $row['created_at'] ?? '' ),
			'updatedAt'       => (string) ( $row['updated_at'] ?? '' ),
		);
	}

	/**
	 * Public version fields for a history list.
	 *
	 * @param array<string, mixed> $row Version row.
	 * @return array<string, mixed>
	 */
	public static function version_summary( array $row ): array {
		return array(
			'id'              => (int) ( $row['id'] ?? 0 ),
			'versionNumber'   => (int) ( $row['version_number'] ?? 0 ),
			'templateId'      => (int) ( $row['template_id'] ?? 0 ),
			'templateVersion' => (int) ( $row['template_version'] ?? 1 ),
			'unit'            => (string) ( $row['unit'] ?? 'cm' ),
			'takenAt'         => (string) ( $row['taken_at'] ?? '' ),
			'takenBy'         => array(
				'id'   => (int) ( $row['taken_by'] ?? 0 ),
				'name' => (string) ( $row['taken_by_name'] ?? '' ),
			),
			'notes'           => (string) ( $row['notes'] ?? '' ),
			'fitNotes'        => (string) ( $row['fit_notes'] ?? '' ),
			'valueCount'      => (int) ( $row['value_count'] ?? 0 ),
			'createdAt'       => (string) ( $row['created_at'] ?? '' ),
		);
	}

	/**
	 * Public version fields for a single record, including template and values.
	 *
	 * @param array<string, mixed>             $row        Version row.
	 * @param array<string, mixed>             $template   Template snapshot.
	 * @param array<int, array<string, mixed>> $values     Stored values.
	 * @return array<string, mixed>
	 */
	public static function version( array $row, array $template, array $values ): array {
		$data                     = self::version_summary( $row );
		$data['profileId']        = (int) ( $row['profile_id'] ?? 0 );
		$data['profileName']      = (string) ( $row['profile_name'] ?? '' );
		$data['clientId']         = (int) ( $row['client_id'] ?? 0 );
		$data['template']         = $template;
		$data['values']           = array_map(
			static fn ( array $value ): array => array(
				'key'   => (string) ( $value['measurement_key'] ?? '' ),
				'label' => (string) ( $value['label'] ?? '' ),
				'value' => round( (float) ( $value['value'] ?? 0 ), 2 ),
				'unit'  => (string) ( $value['unit'] ?? 'cm' ),
			),
			$values
		);
		$data['valueCount']       = count( $data['values'] );

		return $data;
	}
}
