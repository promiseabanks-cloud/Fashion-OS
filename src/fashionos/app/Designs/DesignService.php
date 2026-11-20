<?php
/**
 * Design workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Designs;

use FashionOS\API\Rest;
use FashionOS\Database\References;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\DesignRepository;
use FashionOS\Database\Repositories\MeasurementRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design records and the measurements they were drafted against.
 *
 * A design may point at one measurement profile and one exact measurement
 * version. The version is validated against the same customer, so a design can
 * never cite another customer's measurements by guessing an id.
 */
final class DesignService {

	/**
	 * Allowed design statuses.
	 */
	public const STATUSES = array( 'draft', 'approved', 'archived' );

	/**
	 * Design reads and writes.
	 *
	 * @var DesignRepository
	 */
	private DesignRepository $designs;

	/**
	 * Customer reads.
	 *
	 * @var ClientRepository
	 */
	private ClientRepository $clients;

	/**
	 * Measurement reads.
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
	 * @param DesignRepository      $designs      Design reads and writes.
	 * @param ClientRepository      $clients      Customer reads.
	 * @param MeasurementRepository $measurements Measurement reads.
	 * @param AuditLogRepository    $audit        Audit writes.
	 * @param Transaction           $transactions Transaction runner.
	 */
	public function __construct(
		DesignRepository $designs,
		ClientRepository $clients,
		MeasurementRepository $measurements,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->designs      = $designs;
		$this->clients      = $clients;
		$this->measurements = $measurements;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Search and paginate designs.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function search( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $query['perPage'] ?? 25 ) ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? 'all' ) );

		if ( ! in_array( $status, array_merge( self::STATUSES, array( 'all' ) ), true ) ) {
			$status = 'all';
		}

		$result = $this->designs->search(
			$context->id(),
			array(
				'search'    => mb_substr( trim( sanitize_text_field( (string) ( $query['search'] ?? '' ) ) ), 0, 100 ),
				'status'    => $status,
				'client_id' => max( 0, (int) ( $query['clientId'] ?? 0 ) ),
				'page'      => $page,
				'per_page'  => $per_page,
			)
		);

		return array(
			'designs'    => array_map( array( DesignPresenter::class, 'to_array' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One design.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Design id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function find( TenantContext $context, int $id ) {
		$row = $this->designs->find( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That design could not be found.', 'fashionos' ), 'design_not_found' );
		}

		return DesignPresenter::to_array( $row );
	}

	/**
	 * Create a design for a customer.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$title = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );

		if ( '' === $title ) {
			return Rest::invalid( 'design_title_required', __( 'Give the design a title.', 'fashionos' ) );
		}

		if ( mb_strlen( $title ) > 120 ) {
			return Rest::invalid( 'design_title_too_long', __( 'Design titles may be at most 120 characters.', 'fashionos' ) );
		}

		$client_id = (int) ( $input['clientId'] ?? 0 );

		if ( $client_id <= 0 ) {
			return Rest::invalid( 'design_client_required', __( 'Choose the customer this design is for.', 'fashionos' ) );
		}

		$client = $this->clients->find( $context->id(), $client_id );

		if ( null === $client ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		$references = $this->references(
			$context,
			$client_id,
			(int) ( $input['measurementProfileId'] ?? 0 ),
			(int) ( $input['measurementVersionId'] ?? 0 )
		);

		if ( $references instanceof WP_Error ) {
			return $references;
		}

		$category = $this->normalise_category( $input['garmentCategory'] ?? '' );

		if ( $category instanceof WP_Error ) {
			return $category;
		}

		if ( '' === $category ) {
			$category = $references['garment_category'];
		}

		$brief = $this->normalise_brief( $input['brief'] ?? '' );

		if ( $brief instanceof WP_Error ) {
			return $brief;
		}

		$created = $this->transactions->run(
			function () use ( $context, $client_id, $title, $category, $brief, $references ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				$design_id = $this->designs->create(
					array(
						'tenant_id'               => $context->id(),
						'uuid'                    => wp_generate_uuid4(),
						'reference'               => References::placeholder(),
						'client_id'               => $client_id,
						'measurement_profile_id'  => $references['profile_id'],
						'measurement_version_id'  => $references['version_id'],
						'title'                   => $title,
						'garment_category'        => $category,
						'brief'                   => $brief,
						'status'                  => 'draft',
						'created_by'              => $context->user_id(),
						'created_at'              => $now,
						'updated_at'              => $now,
					)
				);

				if ( 0 === $design_id ) {
					return Rest::invalid( 'design_create_failed', __( 'The design could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$reference = References::format( 'D', $design_id );

				if ( ! $this->designs->update( $context->id(), $design_id, array( 'reference' => $reference ) ) ) {
					return Rest::invalid( 'design_create_failed', __( 'The design could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'design.created',
					'design',
					$design_id,
					array(
						'reference'            => $reference,
						'title'                => $title,
						'clientId'             => $client_id,
						'measurementVersionId' => $references['version_id'],
					)
				);

				return $design_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return $this->find( $context, (int) $created );
	}

	/**
	 * Update a design.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param int                  $id      Design id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( TenantContext $context, int $id, array $input ) {
		$existing = $this->designs->find( $context->id(), $id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That design could not be found.', 'fashionos' ), 'design_not_found' );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'title', $input ) ) {
			$title = trim( sanitize_text_field( (string) $input['title'] ) );

			if ( '' === $title ) {
				return Rest::invalid( 'design_title_required', __( 'Give the design a title.', 'fashionos' ) );
			}

			if ( mb_strlen( $title ) > 120 ) {
				return Rest::invalid( 'design_title_too_long', __( 'Design titles may be at most 120 characters.', 'fashionos' ) );
			}

			$data['title'] = $title;
		}

		if ( array_key_exists( 'garmentCategory', $input ) ) {
			$category = $this->normalise_category( $input['garmentCategory'] );

			if ( $category instanceof WP_Error ) {
				return $category;
			}

			$data['garment_category'] = $category;
		}

		if ( array_key_exists( 'brief', $input ) ) {
			$brief = $this->normalise_brief( $input['brief'] );

			if ( $brief instanceof WP_Error ) {
				return $brief;
			}

			$data['brief'] = $brief;
		}

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return Rest::invalid( 'design_status_invalid', __( 'Choose a draft, approved or archived design.', 'fashionos' ) );
			}

			$data['status'] = $status;
		}

		if ( array_key_exists( 'measurementProfileId', $input ) || array_key_exists( 'measurementVersionId', $input ) ) {
			$references = $this->references(
				$context,
				(int) $existing['client_id'],
				(int) ( $input['measurementProfileId'] ?? $existing['measurement_profile_id'] ),
				(int) ( $input['measurementVersionId'] ?? $existing['measurement_version_id'] )
			);

			if ( $references instanceof WP_Error ) {
				return $references;
			}

			$data['measurement_profile_id'] = $references['profile_id'];
			$data['measurement_version_id'] = $references['version_id'];
		}

		foreach ( array( 'title', 'garment_category', 'brief', 'status', 'measurement_profile_id', 'measurement_version_id' ) as $column ) {
			if ( array_key_exists( $column, $data ) && (string) $existing[ $column ] !== (string) $data[ $column ] ) {
				$changed[] = $column;
			}
		}

		if ( array() === $data ) {
			return DesignPresenter::to_array( $existing );
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		if ( ! $this->designs->update( $context->id(), $id, $data ) ) {
			return Rest::invalid( 'design_update_failed', __( 'The design could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		if ( array() !== $changed ) {
			$metadata = array( 'changed' => $changed );

			if ( array_key_exists( 'status', $data ) ) {
				$metadata['status'] = (string) $data['status'];
			}

			$this->audit->record( $context->id(), $context->user_id(), 'design.updated', 'design', $id, $metadata );
		}

		return $this->find( $context, $id );
	}

	/**
	 * Validate a design's measurement references against its customer.
	 *
	 * A version implies its profile, so passing both only has to agree; a
	 * version from another customer's profile is refused rather than silently
	 * attached.
	 *
	 * @param TenantContext $context    Verified context.
	 * @param int           $client_id  Design customer.
	 * @param int           $profile_id Requested profile id.
	 * @param int           $version_id Requested version id.
	 * @return array{profile_id: int, version_id: int, garment_category: string}|WP_Error
	 */
	private function references( TenantContext $context, int $client_id, int $profile_id, int $version_id ) {
		$category = '';

		if ( $version_id > 0 ) {
			$version = $this->measurements->version( $context->id(), $version_id );

			if ( null === $version ) {
				return Rest::not_found( __( 'That measurement version could not be found.', 'fashionos' ), 'measurement_version_not_found' );
			}

			if ( (int) $version['client_id'] !== $client_id ) {
				return Rest::invalid(
					'design_measurement_mismatch',
					__( 'That measurement version belongs to a different customer.', 'fashionos' )
				);
			}

			if ( $profile_id > 0 && $profile_id !== (int) $version['profile_id'] ) {
				return Rest::invalid(
					'design_measurement_mismatch',
					__( 'That measurement version belongs to a different measurement profile.', 'fashionos' )
				);
			}

			$profile_id = (int) $version['profile_id'];
		}

		if ( $profile_id > 0 ) {
			$profile = $this->measurements->profile( $context->id(), $profile_id );

			if ( null === $profile ) {
				return Rest::not_found( __( 'That measurement profile could not be found.', 'fashionos' ), 'measurement_profile_not_found' );
			}

			if ( (int) $profile['client_id'] !== $client_id ) {
				return Rest::invalid(
					'design_measurement_mismatch',
					__( 'That measurement profile belongs to a different customer.', 'fashionos' )
				);
			}

			$category = (string) $profile['garment_category'];
		}

		return array(
			'profile_id'       => max( 0, $profile_id ),
			'version_id'       => max( 0, $version_id ),
			'garment_category' => $category,
		);
	}

	/**
	 * Validate a garment category.
	 *
	 * @param mixed $value Candidate category.
	 * @return string|WP_Error
	 */
	private function normalise_category( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$category = trim( sanitize_text_field( $value ) );

		if ( mb_strlen( $category ) > 60 ) {
			return Rest::invalid( 'design_garment_category_too_long', __( 'Garment categories may be at most 60 characters.', 'fashionos' ) );
		}

		return $category;
	}

	/**
	 * Validate the design brief.
	 *
	 * @param mixed $value Candidate brief.
	 * @return string|WP_Error
	 */
	private function normalise_brief( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$brief = sanitize_textarea_field( $value );

		if ( mb_strlen( $brief ) > 5000 ) {
			return Rest::invalid( 'design_brief_too_long', __( 'Design briefs may be at most 5000 characters.', 'fashionos' ) );
		}

		return $brief;
	}
}
