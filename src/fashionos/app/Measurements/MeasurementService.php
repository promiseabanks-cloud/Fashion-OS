<?php
/**
 * Measurement workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Measurements;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\MeasurementRepository;
use FashionOS\Database\Repositories\MeasurementTemplateRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Measurement templates, profiles and immutable versions.
 *
 * A version is written once: this service can create one but never edit or
 * delete one, and the repository deliberately exposes no such method. Every
 * version stores a snapshot of the template it was captured against, so a
 * later template edit cannot reinterpret old measurements.
 */
final class MeasurementService {

	/**
	 * Client reads.
	 *
	 * @var ClientRepository
	 */
	private ClientRepository $clients;

	/**
	 * Template reads and writes.
	 *
	 * @var MeasurementTemplateRepository
	 */
	private MeasurementTemplateRepository $templates;

	/**
	 * Profile, version and value reads and writes.
	 *
	 * @var MeasurementRepository
	 */
	private MeasurementRepository $measurements;

	/**
	 * Audit writes.
	 *
	 * @var AuditLogRepository
	 */
	private AuditLogRepository $audit;

	/**
	 * Transaction runner.
	 *
	 * @var Transaction
	 */
	private Transaction $transactions;

	/**
	 * Constructor.
	 *
	 * @param ClientRepository              $clients      Client reads.
	 * @param MeasurementTemplateRepository $templates    Template reads and writes.
	 * @param MeasurementRepository         $measurements Profile, version and value access.
	 * @param AuditLogRepository            $audit        Audit writes.
	 * @param Transaction                   $transactions Transaction runner.
	 */
	public function __construct(
		ClientRepository $clients,
		MeasurementTemplateRepository $templates,
		MeasurementRepository $measurements,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->clients      = $clients;
		$this->templates    = $templates;
		$this->measurements = $measurements;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Templates of the active workspace.
	 *
	 * @param TenantContext $context Verified context.
	 * @return array<int, array<string, mixed>>
	 */
	public function templates( TenantContext $context ): array {
		return array_map(
			array( MeasurementPresenter::class, 'template' ),
			$this->templates->for_tenant( $context->id() )
		);
	}

	/**
	 * Create a measurement template.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_template( TenantContext $context, array $input ) {
		$name = trim( sanitize_text_field( (string) ( $input['name'] ?? '' ) ) );

		if ( '' === $name ) {
			return Rest::invalid( 'measurement_template_name_required', __( 'Give the template a name.', 'fashionos' ) );
		}

		if ( mb_strlen( $name ) > 120 ) {
			return Rest::invalid( 'measurement_template_name_too_long', __( 'Template names may be at most 120 characters.', 'fashionos' ) );
		}

		$category = trim( sanitize_text_field( (string) ( $input['garmentCategory'] ?? '' ) ) );

		if ( '' === $category ) {
			return Rest::invalid( 'measurement_template_category_required', __( 'Name the garment category this template measures.', 'fashionos' ) );
		}

		if ( mb_strlen( $category ) > 60 ) {
			return Rest::invalid( 'measurement_template_category_too_long', __( 'Garment categories may be at most 60 characters.', 'fashionos' ) );
		}

		$unit = TemplateSchema::normalise_unit( $input['unit'] ?? 'cm' );

		if ( $unit instanceof WP_Error ) {
			return $unit;
		}

		$fields = TemplateSchema::normalise( $input['fields'] ?? array() );

		if ( $fields instanceof WP_Error ) {
			return $fields;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$template_id = $this->templates->create(
			array(
				'tenant_id'        => $context->id(),
				'uuid'             => wp_generate_uuid4(),
				'name'             => $name,
				'garment_category' => $category,
				'unit'             => $unit,
				'schema_json'      => (string) wp_json_encode( array( 'fields' => $fields ) ),
				'schema_version'   => 1,
				'status'           => 'active',
				'created_by'       => $context->user_id(),
				'created_at'       => $now,
				'updated_at'       => $now,
			)
		);

		if ( 0 === $template_id ) {
			return Rest::invalid( 'measurement_template_create_failed', __( 'The template could not be created. Please try again.', 'fashionos' ), 500 );
		}

		$this->audit->record(
			$context->id(),
			$context->user_id(),
			'measurement.template.created',
			'measurement_template',
			$template_id,
			array(
				'name'            => $name,
				'garmentCategory' => $category,
				'fields'          => count( $fields ),
			)
		);

		return $this->template( $context, $template_id );
	}

	/**
	 * Update a template, bumping its schema version when fields change.
	 *
	 * @param TenantContext        $context     Verified context.
	 * @param int                  $template_id Template id.
	 * @param array<string, mixed> $input       Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_template( TenantContext $context, int $template_id, array $input ) {
		$existing = $this->templates->find( $context->id(), $template_id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That measurement template could not be found.', 'fashionos' ), 'measurement_template_not_found' );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'name', $input ) ) {
			$name = trim( sanitize_text_field( (string) $input['name'] ) );

			if ( '' === $name ) {
				return Rest::invalid( 'measurement_template_name_required', __( 'Give the template a name.', 'fashionos' ) );
			}

			if ( mb_strlen( $name ) > 120 ) {
				return Rest::invalid( 'measurement_template_name_too_long', __( 'Template names may be at most 120 characters.', 'fashionos' ) );
			}

			$data['name'] = $name;
		}

		if ( array_key_exists( 'garmentCategory', $input ) ) {
			$category = trim( sanitize_text_field( (string) $input['garmentCategory'] ) );

			if ( '' === $category ) {
				return Rest::invalid( 'measurement_template_category_required', __( 'Name the garment category this template measures.', 'fashionos' ) );
			}

			if ( mb_strlen( $category ) > 60 ) {
				return Rest::invalid( 'measurement_template_category_too_long', __( 'Garment categories may be at most 60 characters.', 'fashionos' ) );
			}

			$data['garment_category'] = $category;
		}

		if ( array_key_exists( 'unit', $input ) ) {
			$unit = TemplateSchema::normalise_unit( $input['unit'] );

			if ( $unit instanceof WP_Error ) {
				return $unit;
			}

			$data['unit'] = $unit;
		}

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, array( 'active', 'archived' ), true ) ) {
				return Rest::invalid( 'measurement_template_status_invalid', __( 'Choose an active or archived template.', 'fashionos' ) );
			}

			$data['status'] = $status;
		}

		if ( array_key_exists( 'fields', $input ) ) {
			$fields = TemplateSchema::normalise( $input['fields'] );

			if ( $fields instanceof WP_Error ) {
				return $fields;
			}

			$encoded = (string) wp_json_encode( array( 'fields' => $fields ) );

			if ( $encoded !== (string) wp_json_encode( array( 'fields' => $existing['fields'] ) ) ) {
				$data['schema_json']    = $encoded;
				$data['schema_version'] = (int) $existing['schema_version'] + 1;
				$changed[]              = 'fields';
			}
		}

		foreach ( array( 'name' => 'name', 'garment_category' => 'garment_category', 'unit' => 'unit', 'status' => 'status' ) as $column => $key ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $key ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return MeasurementPresenter::template( $existing );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		if ( ! $this->templates->update( $context->id(), $template_id, $data ) ) {
			return Rest::invalid( 'measurement_template_update_failed', __( 'The template could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		if ( array() !== $changed ) {
			$this->audit->record(
				$context->id(),
				$context->user_id(),
				'measurement.template.updated',
				'measurement_template',
				$template_id,
				array( 'changed' => $changed )
			);
		}

		return $this->template( $context, $template_id );
	}

	/**
	 * One template, re-read after a write.
	 *
	 * @param TenantContext $context     Verified context.
	 * @param int           $template_id Template id.
	 * @return array<string, mixed>|WP_Error
	 */
	private function template( TenantContext $context, int $template_id ) {
		$row = $this->templates->find( $context->id(), $template_id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That measurement template could not be found.', 'fashionos' ), 'measurement_template_not_found' );
		}

		return MeasurementPresenter::template( $row );
	}

	/**
	 * Measurement profiles of one customer.
	 *
	 * @param TenantContext $context   Verified context.
	 * @param int           $client_id Client id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function profiles( TenantContext $context, int $client_id ) {
		$client = $this->clients->find( $context->id(), $client_id );

		if ( null === $client ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		return array(
			'clientId' => (int) $client['id'],
			'profiles' => array_map(
				array( MeasurementPresenter::class, 'profile' ),
				$this->measurements->profiles_for_client( $context->id(), $client_id )
			),
		);
	}

	/**
	 * Create a measurement profile for a customer.
	 *
	 * @param TenantContext        $context   Verified context.
	 * @param int                  $client_id Client id.
	 * @param array<string, mixed> $input     Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_profile( TenantContext $context, int $client_id, array $input ) {
		$client = $this->clients->find( $context->id(), $client_id );

		if ( null === $client ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		$name = trim( sanitize_text_field( (string) ( $input['profileName'] ?? '' ) ) );

		if ( '' === $name ) {
			return Rest::invalid( 'measurement_profile_name_required', __( 'Give the profile a name, for example “Wedding suit”.', 'fashionos' ) );
		}

		if ( mb_strlen( $name ) > 120 ) {
			return Rest::invalid( 'measurement_profile_name_too_long', __( 'Profile names may be at most 120 characters.', 'fashionos' ) );
		}

		$template_id = (int) ( $input['templateId'] ?? 0 );

		if ( $template_id <= 0 ) {
			return Rest::invalid( 'measurement_template_required', __( 'Choose a measurement template.', 'fashionos' ) );
		}

		$template = $this->templates->find( $context->id(), $template_id );

		if ( null === $template ) {
			return Rest::not_found( __( 'That measurement template could not be found.', 'fashionos' ), 'measurement_template_not_found' );
		}

		if ( 'active' !== (string) $template['status'] ) {
			return Rest::invalid( 'measurement_template_archived', __( 'That template is archived. Choose an active one.', 'fashionos' ) );
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$profile_id = $this->measurements->create_profile(
			array(
				'tenant_id'        => $context->id(),
				'client_id'        => $client_id,
				'template_id'      => $template_id,
				'uuid'             => wp_generate_uuid4(),
				'profile_name'     => $name,
				'garment_category' => (string) $template['garment_category'],
				'status'           => 'active',
				'created_by'       => $context->user_id(),
				'created_at'       => $now,
				'updated_at'       => $now,
			)
		);

		if ( 0 === $profile_id ) {
			return Rest::invalid( 'measurement_profile_create_failed', __( 'The profile could not be created. Please try again.', 'fashionos' ), 500 );
		}

		$this->audit->record(
			$context->id(),
			$context->user_id(),
			'measurement.profile.created',
			'measurement_profile',
			$profile_id,
			array(
				'clientId'    => $client_id,
				'profileName' => $name,
				'templateId'  => $template_id,
			)
		);

		return $this->profile( $context, $profile_id );
	}

	/**
	 * One profile with its version history.
	 *
	 * @param TenantContext $context    Verified context.
	 * @param int           $profile_id Profile id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function profile( TenantContext $context, int $profile_id ) {
		$row = $this->measurements->profile( $context->id(), $profile_id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That measurement profile could not be found.', 'fashionos' ), 'measurement_profile_not_found' );
		}

		$versions = $this->measurements->versions_for_profile( $context->id(), $profile_id );

		$row['version_count']  = count( $versions );
		$row['latest_version'] = array() === $versions ? null : array(
			'id'            => (int) $versions[0]['id'],
			'versionNumber' => (int) $versions[0]['version_number'],
			'takenAt'       => (string) $versions[0]['taken_at'],
			'takenByName'   => (string) ( $versions[0]['taken_by_name'] ?? '' ),
		);

		$profile            = MeasurementPresenter::profile( $row );
		$profile['history'] = array_map( array( MeasurementPresenter::class, 'version_summary' ), $versions );

		return $profile;
	}

	/**
	 * Record a new measurement version.
	 *
	 * @param TenantContext        $context    Verified context.
	 * @param int                  $profile_id Profile id.
	 * @param array<string, mixed> $input      Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_version( TenantContext $context, int $profile_id, array $input ) {
		$profile = $this->measurements->profile( $context->id(), $profile_id );

		if ( null === $profile ) {
			return Rest::not_found( __( 'That measurement profile could not be found.', 'fashionos' ), 'measurement_profile_not_found' );
		}

		if ( 'active' !== (string) $profile['status'] ) {
			return Rest::invalid( 'measurement_profile_archived', __( 'That profile is archived. Choose an active one.', 'fashionos' ) );
		}

		$template = $this->templates->find( $context->id(), (int) $profile['template_id'] );

		if ( null === $template ) {
			return Rest::not_found( __( 'The template for this profile could not be found.', 'fashionos' ), 'measurement_template_not_found' );
		}

		if ( 'active' !== (string) $template['status'] ) {
			return Rest::invalid( 'measurement_template_archived', __( 'That template is archived. Choose an active one.', 'fashionos' ) );
		}

		$unit = TemplateSchema::normalise_unit( $input['unit'] ?? $template['unit'] );

		if ( $unit instanceof WP_Error ) {
			return $unit;
		}

		$values = $this->normalise_values( $input['values'] ?? null, $template['fields'] );

		if ( $values instanceof WP_Error ) {
			return $values;
		}

		$notes = $this->normalise_notes( $input['notes'] ?? '' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$fit_notes = $this->normalise_notes( $input['fitNotes'] ?? '' );

		if ( $fit_notes instanceof WP_Error ) {
			return $fit_notes;
		}

		$taken_at = $this->normalise_taken_at( $input['takenAt'] ?? '' );

		if ( $taken_at instanceof WP_Error ) {
			return $taken_at;
		}

		$snapshot = TemplateSchema::snapshot( $template );

		$created = $this->transactions->run(
			function () use ( $context, $profile_id, $template, $snapshot, $values, $unit, $taken_at, $notes, $fit_notes ) {
				$version_number = $this->measurements->latest_version_number( $context->id(), $profile_id ) + 1;

				$version_id = $this->measurements->create_version(
					array(
						'tenant_id'              => $context->id(),
						'profile_id'             => $profile_id,
						'version_number'         => $version_number,
						'template_id'            => (int) $template['id'],
						'template_version'       => (int) $template['schema_version'],
						'template_snapshot_json' => (string) wp_json_encode( $snapshot ),
						'unit'                   => $unit,
						'taken_at'               => $taken_at,
						'taken_by'               => $context->user_id(),
						'notes'                  => $notes,
						'fit_notes'              => $fit_notes,
						'created_at'             => gmdate( 'Y-m-d H:i:s' ),
					)
				);

				if ( 0 === $version_id ) {
					return Rest::invalid( 'measurement_version_create_failed', __( 'The measurements could not be saved. Please try again.', 'fashionos' ), 500 );
				}

				foreach ( $values as $value ) {
					$saved = $this->measurements->create_value(
						array(
							'tenant_id'       => $context->id(),
							'version_id'      => $version_id,
							'measurement_key' => (string) $value['key'],
							'label'           => (string) $value['label'],
							'value'           => (string) $value['value'],
							'unit'            => $unit,
							'sort_order'      => (int) $value['sortOrder'],
						)
					);

					if ( ! $saved ) {
						return Rest::invalid( 'measurement_version_create_failed', __( 'The measurements could not be saved. Please try again.', 'fashionos' ), 500 );
					}
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'measurement.version.created',
					'measurement_version',
					$version_id,
					array(
						'profileId'     => $profile_id,
						'versionNumber' => $version_number,
						'unit'          => $unit,
					)
				);

				return $version_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return $this->version( $context, (int) $created );
	}

	/**
	 * One recorded version with values and its template snapshot.
	 *
	 * @param TenantContext $context    Verified context.
	 * @param int           $version_id Version id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function version( TenantContext $context, int $version_id ) {
		$row = $this->measurements->version( $context->id(), $version_id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That measurement version could not be found.', 'fashionos' ), 'measurement_version_not_found' );
		}

		$snapshot = json_decode( (string) $row['template_snapshot_json'], true );
		$snapshot = is_array( $snapshot ) ? $snapshot : array();

		return MeasurementPresenter::version(
			$row,
			array(
				'id'             => (int) ( $snapshot['id'] ?? 0 ),
				'name'           => (string) ( $snapshot['name'] ?? '' ),
				'garmentCategory' => (string) ( $snapshot['garmentCategory'] ?? '' ),
				'unit'           => (string) ( $snapshot['unit'] ?? $row['unit'] ),
				'schemaVersion'  => (int) ( $snapshot['schemaVersion'] ?? $row['template_version'] ),
				'fields'         => array_values( is_array( $snapshot['fields'] ?? null ) ? $snapshot['fields'] : array() ),
			),
			$this->measurements->values_for_version( $context->id(), $version_id )
		);
	}

	/**
	 * Validate submitted values against the template fields.
	 *
	 * Optional fields may be omitted or left blank; required fields must carry
	 * a value. Values are stored as `DECIMAL(10,2)` strings.
	 *
	 * @param mixed                            $values Candidate values keyed by field.
	 * @param array<int, array<string, mixed>> $fields Template fields.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function normalise_values( $values, array $fields ) {
		if ( ! is_array( $values ) ) {
			return Rest::invalid( 'measurement_values_required', __( 'Enter the measurements before saving.', 'fashionos' ) );
		}

		$valid_keys = array_map( static fn ( array $field ): string => (string) $field['key'], $fields );

		foreach ( array_keys( $values ) as $key ) {
			if ( ! in_array( (string) $key, $valid_keys, true ) ) {
				return Rest::invalid(
					'measurement_value_unknown',
					sprintf(
						/* translators: %s: unknown measurement key. */
						__( '"%s" is not a field in this template.', 'fashionos' ),
						(string) $key
					),
					422,
					array( 'key' => (string) $key )
				);
			}
		}

		$normalised = array();
		$sort_order = 0;

		foreach ( $fields as $field ) {
			$key       = (string) $field['key'];
			$submitted = $values[ $key ] ?? '';
			$text      = is_int( $submitted ) || is_float( $submitted ) ? (string) $submitted : ( is_string( $submitted ) ? trim( $submitted ) : '' );

			if ( '' === $text ) {
				if ( ! empty( $field['required'] ) ) {
					return Rest::invalid(
						'measurement_value_required',
						sprintf(
							/* translators: %s: measurement label. */
							__( 'Enter a value for %s.', 'fashionos' ),
							(string) $field['label']
						),
						422,
						array( 'key' => $key )
					);
				}

				continue;
			}

			if ( ! preg_match( '/^\d{1,4}(\.\d{1,2})?$/', $text ) ) {
				return Rest::invalid(
					'measurement_value_invalid',
					sprintf(
						/* translators: %s: measurement label. */
						__( '%s must be a number between 0 and 9999.99.', 'fashionos' ),
						(string) $field['label']
					),
					422,
					array( 'key' => $key )
				);
			}

			$normalised[] = array(
				'key'       => $key,
				'label'     => (string) $field['label'],
				'value'     => number_format( (float) $text, 2, '.', '' ),
				'sortOrder' => $sort_order,
			);

			++$sort_order;
		}

		if ( array() === $normalised ) {
			return Rest::invalid( 'measurement_values_required', __( 'Enter at least one measurement.', 'fashionos' ) );
		}

		return $normalised;
	}

	/**
	 * Validate free-text notes.
	 *
	 * @param mixed $value Candidate text.
	 * @return string|WP_Error
	 */
	private function normalise_notes( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$text = sanitize_textarea_field( $value );

		if ( mb_strlen( $text ) > 2000 ) {
			return Rest::invalid( 'measurement_notes_too_long', __( 'Notes may be at most 2000 characters.', 'fashionos' ) );
		}

		return $text;
	}

	/**
	 * Validate the capture timestamp.
	 *
	 * @param mixed $taken_at Candidate timestamp; blank means now.
	 * @return string|WP_Error
	 */
	private function normalise_taken_at( $taken_at ) {
		if ( ! is_string( $taken_at ) || '' === trim( $taken_at ) ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		$timestamp = strtotime( $taken_at );

		if ( false === $timestamp || $timestamp > time() + DAY_IN_SECONDS ) {
			return Rest::invalid( 'measurement_taken_at_invalid', __( 'Enter a valid date and time for these measurements.', 'fashionos' ) );
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
