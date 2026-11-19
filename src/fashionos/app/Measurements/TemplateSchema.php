<?php
/**
 * Measurement template schema rules.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Measurements;

use FashionOS\API\Rest;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and normalises template field definitions.
 *
 * A template is a list of named fields rather than a fixed column set, because
 * the specification explicitly rejects one universal set of body measurements.
 * Field keys are stable identifiers: recorded values refer to them, and each
 * version keeps its own copy of the definition.
 */
final class TemplateSchema {

	/**
	 * Supported capture units.
	 */
	public const UNITS = array( 'cm', 'in' );

	/**
	 * Maximum fields in one template.
	 */
	public const MAX_FIELDS = 40;

	/**
	 * Normalise a list of field definitions.
	 *
	 * @param mixed $fields Candidate fields.
	 * @return array<int, array{key: string, label: string, hint: string, required: bool}>|WP_Error
	 */
	public static function normalise( $fields ) {
		if ( ! is_array( $fields ) || array() === $fields ) {
			return Rest::invalid(
				'measurement_template_fields_required',
				__( 'Add at least one measurement field.', 'fashionos' )
			);
		}

		$fields = array_values( $fields );

		if ( count( $fields ) > self::MAX_FIELDS ) {
			return Rest::invalid(
				'measurement_template_too_many_fields',
				sprintf(
					/* translators: %d: maximum number of fields. */
					__( 'A template may have at most %d fields.', 'fashionos' ),
					self::MAX_FIELDS
				)
			);
		}

		$normalised = array();
		$seen       = array();

		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				return Rest::invalid(
					'measurement_template_field_invalid',
					__( 'Every measurement field needs a key and a label.', 'fashionos' ),
					422,
					array( 'index' => $index )
				);
			}

			$label = trim( sanitize_text_field( (string) ( $field['label'] ?? '' ) ) );
			$key   = sanitize_key( (string) ( $field['key'] ?? '' ) );

			if ( '' === $key ) {
				$key = sanitize_key( $label );
			}

			if ( '' === $key || '' === $label ) {
				return Rest::invalid(
					'measurement_template_field_invalid',
					__( 'Every measurement field needs a key and a label.', 'fashionos' ),
					422,
					array( 'index' => $index )
				);
			}

			if ( mb_strlen( $label ) > 120 ) {
				return Rest::invalid(
					'measurement_template_field_invalid',
					__( 'Measurement labels may be at most 120 characters.', 'fashionos' ),
					422,
					array( 'index' => $index )
				);
			}

			if ( isset( $seen[ $key ] ) ) {
				return Rest::invalid(
					'measurement_template_field_duplicate',
					sprintf(
						/* translators: %s: duplicate field key. */
						__( 'The field key "%s" is used more than once.', 'fashionos' ),
						$key
					),
					422,
					array( 'index' => $index )
				);
			}

			$seen[ $key ] = true;

			$hint = trim( sanitize_text_field( (string) ( $field['hint'] ?? '' ) ) );

			if ( mb_strlen( $hint ) > 200 ) {
				return Rest::invalid(
					'measurement_template_field_invalid',
					__( 'Field hints may be at most 200 characters.', 'fashionos' ),
					422,
					array( 'index' => $index )
				);
			}

			$normalised[] = array(
				'key'      => $key,
				'label'    => $label,
				'hint'     => $hint,
				'required' => ! empty( $field['required'] ),
			);
		}

		return $normalised;
	}

	/**
	 * Validate a capture unit.
	 *
	 * @param mixed $unit Candidate unit.
	 * @return string|WP_Error
	 */
	public static function normalise_unit( $unit ) {
		$unit = is_string( $unit ) ? strtolower( trim( $unit ) ) : '';

		if ( '' === $unit ) {
			return 'cm';
		}

		if ( ! in_array( $unit, self::UNITS, true ) ) {
			return Rest::invalid( 'measurement_unit_invalid', __( 'Choose centimetres or inches.', 'fashionos' ) );
		}

		return $unit;
	}

	/**
	 * Snapshot stored with a version so history stays interpretable.
	 *
	 * @param array<string, mixed> $template Template row including `fields`.
	 * @return array<string, mixed>
	 */
	public static function snapshot( array $template ): array {
		return array(
			'id'             => (int) ( $template['id'] ?? 0 ),
			'name'           => (string) ( $template['name'] ?? '' ),
			'garmentCategory' => (string) ( $template['garment_category'] ?? '' ),
			'unit'           => (string) ( $template['unit'] ?? 'cm' ),
			'schemaVersion'  => (int) ( $template['schema_version'] ?? 1 ),
			'fields'         => array_values( is_array( $template['fields'] ?? null ) ? $template['fields'] : array() ),
		);
	}
}
