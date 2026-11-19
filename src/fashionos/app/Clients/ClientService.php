<?php
/**
 * Client workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Clients;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer validation, duplicate detection and auditing.
 *
 * Contact details are validated here rather than in the controller so the same
 * rules apply whether a customer arrives from the studio or (later) the portal.
 */
final class ClientService {

	/**
	 * Allowed customer statuses.
	 */
	public const STATUSES = array( 'active', 'archived' );

	/**
	 * Allowed contact channels.
	 */
	public const CONTACT_CHANNELS = array( '', 'email', 'phone', 'whatsapp', 'none' );

	/**
	 * Client reads and writes.
	 *
	 * @var ClientRepository
	 */
	private ClientRepository $clients;

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
	 * @param ClientRepository   $clients      Client reads and writes.
	 * @param AuditLogRepository $audit        Audit writes.
	 * @param Transaction        $transactions Transaction runner.
	 */
	public function __construct( ClientRepository $clients, AuditLogRepository $audit, Transaction $transactions ) {
		$this->clients      = $clients;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Search and paginate customers.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $query   Raw query parameters.
	 * @return array<string, mixed>
	 */
	public function search( TenantContext $context, array $query ): array {
		$page     = max( 1, (int) ( $query['page'] ?? 1 ) );
		$per_page = (int) ( $query['perPage'] ?? 25 );
		$per_page = max( 1, min( 100, $per_page ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? 'active' ) );
		$search   = trim( sanitize_text_field( (string) ( $query['search'] ?? '' ) ) );

		if ( ! in_array( $status, array_merge( self::STATUSES, array( 'all' ) ), true ) ) {
			$status = 'active';
		}

		$result = $this->clients->search(
			$context->id(),
			array(
				'search'   => mb_substr( $search, 0, 100 ),
				'status'   => $status,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		return array(
			'clients'    => array_map( array( $this, 'present' ), $result['rows'] ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $result['total'],
			'totalPages' => $result['total'] > 0 ? (int) ceil( $result['total'] / $per_page ) : 0,
		);
	}

	/**
	 * One customer with preferences.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $client_id Client id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function find( TenantContext $context, int $client_id ) {
		$row = $this->clients->find( $context->id(), $client_id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		return $this->present( $row );
	}

	/**
	 * Create a customer and their preferences.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( TenantContext $context, array $input ) {
		$name = trim( sanitize_text_field( (string) ( $input['name'] ?? '' ) ) );

		if ( '' === $name ) {
			return Rest::invalid( 'client_name_required', __( 'Give the customer a name.', 'fashionos' ) );
		}

		if ( mb_strlen( $name ) > 191 ) {
			return Rest::invalid( 'client_name_too_long', __( 'Customer names may be at most 191 characters.', 'fashionos' ) );
		}

		$email = $this->normalise_email( $input['email'] ?? '' );

		if ( $email instanceof WP_Error ) {
			return $email;
		}

		$phone = $this->normalise_phone( $input['phone'] ?? '' );

		if ( $phone instanceof WP_Error ) {
			return $phone;
		}

		$notes = $this->normalise_text( $input['notes'] ?? '', 5000, 'client_notes_too_long' );

		if ( $notes instanceof WP_Error ) {
			return $notes;
		}

		$preferences = $this->normalise_preferences( $input['preferences'] ?? array() );

		if ( $preferences instanceof WP_Error ) {
			return $preferences;
		}

		if ( '' !== $email ) {
			$duplicate = $this->clients->find_by_email( $context->id(), $email );

			if ( null !== $duplicate ) {
				return Rest::invalid(
					'client_duplicate',
					sprintf(
						/* translators: 1: existing customer name, 2: existing customer reference. */
						__( '%1$s (%2$s) already uses that email address.', 'fashionos' ),
						(string) $duplicate['name'],
						(string) $duplicate['reference']
					),
					409,
					array(
						'clientId'  => (int) $duplicate['id'],
						'reference' => (string) $duplicate['reference'],
					)
				);
			}
		}

		$created = $this->transactions->run(
			function () use ( $context, $name, $email, $phone, $notes, $preferences ) {
				$now   = gmdate( 'Y-m-d H:i:s' );
				$token = 'T' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 28 );

				$client_id = $this->clients->create(
					array(
						'tenant_id'  => $context->id(),
						'uuid'       => wp_generate_uuid4(),
						'reference'  => $token,
						'name'       => $name,
						'email'      => $email,
						'phone'      => $phone,
						'notes'      => $notes,
						'status'     => 'active',
						'created_by' => $context->user_id(),
						'created_at' => $now,
						'updated_at' => $now,
					)
				);

				if ( 0 === $client_id ) {
					return Rest::invalid( 'client_create_failed', __( 'The customer could not be created. Please try again.', 'fashionos' ), 500 );
				}

				// The reference is derived from the row id so it is stable and
				// sequential per workspace without a second sequence table.
				$updated = $this->clients->update(
					$context->id(),
					$client_id,
					array( 'reference' => sprintf( 'C-%04d', $client_id ) )
				);

				if ( ! $updated || ! $this->clients->save_preferences( $context->id(), $client_id, $preferences ) ) {
					return Rest::invalid( 'client_create_failed', __( 'The customer could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$row = $this->clients->find( $context->id(), $client_id );

				if ( null === $row ) {
					return Rest::invalid( 'client_create_failed', __( 'The customer could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'client.created',
					'client',
					$client_id,
					array(
						'reference' => (string) $row['reference'],
						'name'      => (string) $row['name'],
					)
				);

				return array( $row, $preferences );
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return ClientPresenter::to_array( $created[0], $created[1] );
	}

	/**
	 * Update a customer's profile, preferences or status.
	 *
	 * @param TenantContext        $context   Verified context.
	 * @param int                  $client_id Client id.
	 * @param array<string, mixed> $input     Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( TenantContext $context, int $client_id, array $input ) {
		$existing = $this->clients->find( $context->id(), $client_id );

		if ( null === $existing ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		$data    = array();
		$changed = array();

		if ( array_key_exists( 'name', $input ) ) {
			$name = trim( sanitize_text_field( (string) $input['name'] ) );

			if ( '' === $name ) {
				return Rest::invalid( 'client_name_required', __( 'Give the customer a name.', 'fashionos' ) );
			}

			if ( mb_strlen( $name ) > 191 ) {
				return Rest::invalid( 'client_name_too_long', __( 'Customer names may be at most 191 characters.', 'fashionos' ) );
			}

			$data['name'] = $name;
		}

		if ( array_key_exists( 'email', $input ) ) {
			$email = $this->normalise_email( $input['email'] );

			if ( $email instanceof WP_Error ) {
				return $email;
			}

			if ( '' !== $email ) {
				$duplicate = $this->clients->find_by_email( $context->id(), $email, $client_id );

				if ( null !== $duplicate ) {
					return Rest::invalid(
						'client_duplicate',
						sprintf(
							/* translators: 1: existing customer name, 2: existing customer reference. */
							__( '%1$s (%2$s) already uses that email address.', 'fashionos' ),
							(string) $duplicate['name'],
							(string) $duplicate['reference']
						),
						409,
						array(
							'clientId'  => (int) $duplicate['id'],
							'reference' => (string) $duplicate['reference'],
						)
					);
				}
			}

			$data['email'] = $email;
		}

		if ( array_key_exists( 'phone', $input ) ) {
			$phone = $this->normalise_phone( $input['phone'] );

			if ( $phone instanceof WP_Error ) {
				return $phone;
			}

			$data['phone'] = $phone;
		}

		if ( array_key_exists( 'notes', $input ) ) {
			$notes = $this->normalise_text( $input['notes'], 5000, 'client_notes_too_long' );

			if ( $notes instanceof WP_Error ) {
				return $notes;
			}

			$data['notes'] = $notes;
		}

		if ( array_key_exists( 'status', $input ) ) {
			$status = sanitize_key( (string) $input['status'] );

			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return Rest::invalid( 'client_status_invalid', __( 'Choose an active or archived customer.', 'fashionos' ) );
			}

			$data['status'] = $status;
		}

		$preferences = null;

		if ( array_key_exists( 'preferences', $input ) ) {
			$preferences = $this->normalise_preferences( $input['preferences'] );

			if ( $preferences instanceof WP_Error ) {
				return $preferences;
			}
		}

		foreach ( $data as $column => $value ) {
			if ( (string) $existing[ $column ] !== (string) $value ) {
				$changed[] = $column;
			}
		}

		if ( array() !== $data ) {
			$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

			if ( ! $this->clients->update( $context->id(), $client_id, $data ) ) {
				return Rest::invalid( 'client_update_failed', __( 'The customer could not be updated. Please try again.', 'fashionos' ), 500 );
			}
		}

		if ( null !== $preferences && ! $this->clients->save_preferences( $context->id(), $client_id, $preferences ) ) {
			return Rest::invalid( 'client_update_failed', __( 'The customer could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		if ( array() !== $changed || null !== $preferences ) {
			$metadata = array();

			if ( array() !== $changed ) {
				$metadata['fields'] = $changed;
			}

			if ( array_key_exists( 'status', $data ) ) {
				$metadata['status'] = (string) $data['status'];
			}

			if ( null !== $preferences ) {
				$metadata['preferences'] = true;
			}

			$this->audit->record( $context->id(), $context->user_id(), 'client.updated', 'client', $client_id, $metadata );
		}

		return $this->find( $context, $client_id );
	}

	/**
	 * Public shape for a client row, including preferences.
	 *
	 * @param array<string, mixed> $row Client row.
	 * @return array<string, mixed>
	 */
	private function present( array $row ): array {
		return ClientPresenter::to_array(
			$row,
			$this->clients->preferences( (int) $row['tenant_id'], (int) $row['id'] )
		);
	}

	/**
	 * Validate and normalise an email address.
	 *
	 * @param mixed $email Candidate email.
	 * @return string|WP_Error
	 */
	private function normalise_email( $email ) {
		if ( ! is_string( $email ) || '' === trim( $email ) ) {
			return '';
		}

		$normalised = strtolower( sanitize_email( $email ) );

		if ( '' === $normalised || ! is_email( $normalised ) ) {
			return Rest::invalid( 'client_email_invalid', __( 'Enter a valid email address.', 'fashionos' ) );
		}

		return $normalised;
	}

	/**
	 * Validate and normalise a phone number.
	 *
	 * @param mixed $phone Candidate phone number.
	 * @return string|WP_Error
	 */
	private function normalise_phone( $phone ) {
		if ( ! is_string( $phone ) ) {
			return '';
		}

		$normalised = trim( sanitize_text_field( $phone ) );

		if ( mb_strlen( $normalised ) > 40 ) {
			return Rest::invalid( 'client_phone_too_long', __( 'Phone numbers may be at most 40 characters.', 'fashionos' ) );
		}

		return $normalised;
	}

	/**
	 * Validate a free-text field.
	 *
	 * @param mixed  $value    Candidate value.
	 * @param int    $max      Maximum length.
	 * @param string $error_code Error code when too long.
	 * @return string|WP_Error
	 */
	private function normalise_text( $value, int $max, string $error_code ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$text = sanitize_textarea_field( $value );

		if ( mb_strlen( $text ) > $max ) {
			return Rest::invalid( $error_code, __( 'That text is too long.', 'fashionos' ) );
		}

		return $text;
	}

	/**
	 * Validate preference values.
	 *
	 * Unknown keys are ignored so a newer client cannot break an older server.
	 *
	 * @param mixed $preferences Candidate preferences.
	 * @return array<string, string>|WP_Error
	 */
	private function normalise_preferences( $preferences ) {
		if ( ! is_array( $preferences ) ) {
			if ( null === $preferences || '' === $preferences ) {
				return ClientPresenter::empty_preferences();
			}

			return Rest::invalid( 'client_preferences_invalid', __( 'Preferences must be a set of values.', 'fashionos' ) );
		}

		$values = array_merge( ClientPresenter::empty_preferences(), array_intersect_key( $preferences, ClientPresenter::empty_preferences() ) );

		if ( ! is_string( $values['preferredContact'] ) ) {
			return Rest::invalid( 'client_preferences_invalid', __( 'Choose a valid contact preference.', 'fashionos' ) );
		}

		$channel = sanitize_key( $values['preferredContact'] );

		if ( ! in_array( $channel, self::CONTACT_CHANNELS, true ) ) {
			return Rest::invalid( 'client_preferences_invalid', __( 'Choose a valid contact preference.', 'fashionos' ) );
		}

		$style = $this->normalise_text( $values['styleNotes'], 2000, 'client_preferences_invalid' );

		if ( $style instanceof WP_Error ) {
			return $style;
		}

		$fit = $this->normalise_text( $values['fitNotes'], 2000, 'client_preferences_invalid' );

		if ( $fit instanceof WP_Error ) {
			return $fit;
		}

		return array(
			'preferredContact' => $channel,
			'styleNotes'       => $style,
			'fitNotes'         => $fit,
		);
	}
}
