<?php
/**
 * Portal access link workflows.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Portal;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\PortalAccessRepository;
use FashionOS\Database\Transaction;
use FashionOS\Tenancy\TenantContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Staff-side issuing and revocation of customer portal links.
 *
 * A link is a bearer credential: the token is generated here, returned once and
 * stored only as a SHA-256 hash. Staff can see when a link was created, whether
 * it is still active and when it was last used, but never the token itself.
 */
final class PortalAccessService {

	/**
	 * Maximum lifetime offered, in days.
	 */
	public const MAX_DAYS = 365;

	/**
	 * Access reads and writes.
	 *
	 * @var PortalAccessRepository
	 */
	private PortalAccessRepository $access;

	/**
	 * Customer reads.
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
	 * @param PortalAccessRepository $access       Access reads and writes.
	 * @param ClientRepository       $clients      Customer reads.
	 * @param AuditLogRepository     $audit        Audit writes.
	 * @param Transaction            $transactions Transaction runner.
	 */
	public function __construct(
		PortalAccessRepository $access,
		ClientRepository $clients,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->access       = $access;
		$this->clients      = $clients;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Access links issued to one customer.
	 *
	 * @param TenantContext $context   Verified context.
	 * @param int           $client_id Customer id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function list( TenantContext $context, int $client_id ) {
		$client = $this->clients->find( $context->id(), $client_id );

		if ( null === $client ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		return array(
			'access' => array_map(
				array( PortalPresenter::class, 'access' ),
				$this->access->for_client( $context->id(), $client_id )
			),
		);
	}

	/**
	 * Issue a new portal link for one customer.
	 *
	 * @param TenantContext        $context   Verified context.
	 * @param int                  $client_id Customer id.
	 * @param array<string, mixed> $input     Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function issue( TenantContext $context, int $client_id, array $input ) {
		$client = $this->clients->find( $context->id(), $client_id );

		if ( null === $client ) {
			return Rest::not_found( __( 'That customer could not be found.', 'fashionos' ), 'client_not_found' );
		}

		if ( 'archived' === (string) $client['status'] ) {
			return Rest::invalid(
				'portal_client_archived',
				__( 'Restore this customer before sharing a portal link.', 'fashionos' ),
				409
			);
		}

		$label = $this->normalise_label( $input['label'] ?? '' );

		if ( $label instanceof WP_Error ) {
			return $label;
		}

		$days = $this->normalise_days( $input['expiresInDays'] ?? 0 );

		if ( $days instanceof WP_Error ) {
			return $days;
		}

		$token      = bin2hex( random_bytes( 32 ) );
		$expires_at = $days > 0 ? gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) ) : '';

		$created = $this->transactions->run(
			function () use ( $context, $client_id, $label, $expires_at, $token ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				$access_id = $this->access->create(
					array(
						'tenant_id'    => $context->id(),
						'uuid'         => wp_generate_uuid4(),
						'client_id'    => $client_id,
						'token_hash'   => hash( 'sha256', $token ),
						'label'        => $label,
						'expires_at'   => $expires_at,
						'revoked_at'   => '',
						'last_used_at' => '',
						'created_by'   => $context->user_id(),
						'created_at'   => $now,
						'updated_at'   => $now,
					)
				);

				if ( 0 === $access_id ) {
					return Rest::invalid(
						'portal_access_create_failed',
						__( 'The portal link could not be created. Please try again.', 'fashionos' ),
						500
					);
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'portal.access_issued',
					'portal_access',
					$access_id,
					array(
						'clientId'  => $client_id,
						'expiresAt' => $expires_at,
					)
				);

				return $access_id;
			}
		);

		if ( $created instanceof WP_Error ) {
			return $created;
		}

		$row = $this->access->find( $context->id(), (int) $created );

		if ( null === $row ) {
			return Rest::not_found( __( 'That portal link could not be found.', 'fashionos' ), 'portal_access_not_found' );
		}

		return array(
			'access' => PortalPresenter::access( $row ),
			'token'  => $token,
			'url'    => $this->link( $token, $context->id() ),
		);
	}

	/**
	 * Revoke one portal link.
	 *
	 * @param TenantContext $context Verified context.
	 * @param int           $id      Access id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function revoke( TenantContext $context, int $id ) {
		$row = $this->access->find( $context->id(), $id );

		if ( null === $row ) {
			return Rest::not_found( __( 'That portal link could not be found.', 'fashionos' ), 'portal_access_not_found' );
		}

		if ( '' !== (string) $row['revoked_at'] ) {
			return PortalPresenter::access( $row );
		}

		$revoked = $this->transactions->run(
			function () use ( $context, $id, $row ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				if ( ! $this->access->update( $context->id(), $id, array( 'revoked_at' => $now, 'updated_at' => $now ) ) ) {
					return Rest::invalid(
						'portal_access_update_failed',
						__( 'The portal link could not be revoked. Please try again.', 'fashionos' ),
						500
					);
				}

				$this->audit->record(
					$context->id(),
					$context->user_id(),
					'portal.access_revoked',
					'portal_access',
					$id,
					array( 'clientId' => (int) $row['client_id'] )
				);

				return true;
			}
		);

		if ( $revoked instanceof WP_Error ) {
			return $revoked;
		}

		$fresh = $this->access->find( $context->id(), $id );

		return null === $fresh
			? Rest::not_found( __( 'That portal link could not be found.', 'fashionos' ), 'portal_access_not_found' )
			: PortalPresenter::access( $fresh );
	}

	/**
	 * Build the shareable portal URL for a token.
	 *
	 * The portal renders wherever the `[fashionos_portal]` shortcode is placed,
	 * so the filter exists for a site that mounts it on its own page.
	 *
	 * @param string $token     Raw token.
	 * @param int    $tenant_id Tenant id.
	 */
	private function link( string $token, int $tenant_id ): string {
		$url = add_query_arg( 'fos_portal', $token, home_url( '/' ) );

		return (string) apply_filters( 'fashionos_portal_url', $url, $tenant_id );
	}

	/**
	 * Validate the link label.
	 *
	 * @param mixed $value Candidate label.
	 * @return string|WP_Error
	 */
	private function normalise_label( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$label = trim( sanitize_text_field( $value ) );

		if ( mb_strlen( $label ) > 100 ) {
			return Rest::invalid( 'portal_access_label_too_long', __( 'Labels may be at most 100 characters.', 'fashionos' ) );
		}

		return $label;
	}

	/**
	 * Validate the optional lifetime in days.
	 *
	 * @param mixed $value Candidate lifetime; 0 means no expiry.
	 * @return int|WP_Error
	 */
	private function normalise_days( $value ) {
		$days = (int) $value;

		if ( $days < 0 || $days > self::MAX_DAYS ) {
			return Rest::invalid(
				'portal_access_expiry_invalid',
				sprintf(
					/* translators: %d: maximum number of days. */
					__( 'A portal link may last between 0 and %d days.', 'fashionos' ),
					self::MAX_DAYS
				)
			);
		}

		return $days;
	}
}
