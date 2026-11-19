<?php
/**
 * Tenancy business rules.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Tenancy;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Transaction;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Workspace and team workflows.
 *
 * Controllers delegate here so HTTP concerns and business rules stay apart.
 * Every team operation receives an already-verified TenantContext.
 */
final class TenantService {

	/**
	 * Currencies accepted for new workspaces.
	 *
	 * Intentionally small and explicit; expanding it is a product decision, not
	 * a formatting concern.
	 */
	public const CURRENCIES = array( 'NGN', 'USD', 'GBP', 'EUR', 'GHS', 'KES', 'ZAR', 'CAD' );

	/**
	 * Tenant reads.
	 *
	 * @var TenantRepository
	 */
	private TenantRepository $tenants;

	/**
	 * Role reads and writes.
	 *
	 * @var RoleRepository
	 */
	private RoleRepository $roles;

	/**
	 * Membership reads and writes.
	 *
	 * @var MembershipRepository
	 */
	private MembershipRepository $memberships;

	/**
	 * Audit trail.
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
	 * @param TenantRepository     $tenants      Tenant reads.
	 * @param RoleRepository       $roles        Role reads and writes.
	 * @param MembershipRepository $memberships Membership reads and writes.
	 * @param AuditLogRepository   $audit        Audit trail.
	 * @param Transaction          $transactions Transaction runner.
	 */
	public function __construct(
		TenantRepository $tenants,
		RoleRepository $roles,
		MembershipRepository $memberships,
		AuditLogRepository $audit,
		Transaction $transactions
	) {
		$this->tenants      = $tenants;
		$this->roles        = $roles;
		$this->memberships  = $memberships;
		$this->audit        = $audit;
		$this->transactions = $transactions;
	}

	/**
	 * Create a workspace and make the creator its owner.
	 *
	 * @param int                  $user_id Creator's WordPress user id.
	 * @param array<string, mixed> $input   Request payload.
	 * @return TenantContext|\WP_Error
	 */
	public function create( int $user_id, array $input ) {
		$name = trim( sanitize_text_field( (string) ( $input['name'] ?? '' ) ) );

		if ( '' === $name ) {
			return Rest::invalid( 'workspace_name_required', __( 'Give your workspace a name.', 'fashionos' ) );
		}

		if ( mb_strlen( $name ) > 120 ) {
			return Rest::invalid( 'workspace_name_too_long', __( 'Workspace names may be at most 120 characters.', 'fashionos' ) );
		}

		$timezone = $this->valid_timezone( $input['timezone'] ?? '' );
		if ( $timezone instanceof \WP_Error ) {
			return $timezone;
		}

		$currency = $this->valid_currency( $input['currency'] ?? '' );
		if ( $currency instanceof \WP_Error ) {
			return $currency;
		}

		$created = $this->transactions->run(
			function () use ( $user_id, $name, $timezone, $currency ) {
				$now       = gmdate( 'Y-m-d H:i:s' );
				$tenant_id = $this->tenants->create(
					array(
						'uuid'       => wp_generate_uuid4(),
						'name'       => $name,
						'slug'       => $this->unique_slug( $name ),
						'status'     => 'active',
						'timezone'   => $timezone,
						'currency'   => $currency,
						'logo_id'    => 0,
						'created_by' => $user_id,
						'created_at' => $now,
						'updated_at' => $now,
					)
				);

				if ( 0 === $tenant_id ) {
					return Rest::invalid( 'workspace_create_failed', __( 'The workspace could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$owner_role_id = 0;

				foreach ( Roles::defaults() as $slug => $definition ) {
					$role_id = $this->roles->create( $tenant_id, (string) $slug, $definition['name'], $definition['permissions'] );

					if ( 0 === $role_id ) {
						return Rest::invalid( 'workspace_create_failed', __( 'The workspace roles could not be created. Please try again.', 'fashionos' ), 500 );
					}

					if ( Roles::OWNER === $slug ) {
						$owner_role_id = $role_id;
					}
				}

				if ( 0 === $owner_role_id || 0 === $this->memberships->create( $tenant_id, $user_id, $owner_role_id ) ) {
					return Rest::invalid( 'workspace_create_failed', __( 'The workspace membership could not be created. Please try again.', 'fashionos' ), 500 );
				}

				$this->audit->record(
					$tenant_id,
					$user_id,
					'tenant.created',
					'tenant',
					$tenant_id,
					array( 'name' => $name )
				);

				return $this->context_for( $tenant_id, $user_id );
			}
		);

		return $created;
	}

	/**
	 * Update the workspace profile.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload.
	 * @return TenantContext|\WP_Error
	 */
	public function update( TenantContext $context, array $input ) {
		$data    = array();
		$changes = array();

		if ( array_key_exists( 'name', $input ) ) {
			$name = trim( sanitize_text_field( (string) $input['name'] ) );

			if ( '' === $name ) {
				return Rest::invalid( 'workspace_name_required', __( 'Give your workspace a name.', 'fashionos' ) );
			}

			if ( mb_strlen( $name ) > 120 ) {
				return Rest::invalid( 'workspace_name_too_long', __( 'Workspace names may be at most 120 characters.', 'fashionos' ) );
			}

			$data['name'] = $name;
		}

		if ( array_key_exists( 'timezone', $input ) ) {
			$timezone = $this->valid_timezone( $input['timezone'] );

			if ( $timezone instanceof \WP_Error ) {
				return $timezone;
			}

			$data['timezone'] = $timezone;
		}

		if ( array_key_exists( 'currency', $input ) ) {
			$currency = $this->valid_currency( $input['currency'] );

			if ( $currency instanceof \WP_Error ) {
				return $currency;
			}

			$data['currency'] = $currency;
		}

		if ( array() === $data ) {
			return $context;
		}

		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		foreach ( $data as $key => $value ) {
			if ( 'updated_at' !== $key && (string) ( $context->tenant()[ $key ] ?? '' ) !== (string) $value ) {
				$changes[ $key ] = $value;
			}
		}

		if ( ! $this->tenants->update( $context->id(), $data ) ) {
			return Rest::invalid( 'workspace_update_failed', __( 'The workspace could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		if ( array() !== $changes ) {
			$this->audit->record(
				$context->id(),
				$context->user_id(),
				'tenant.updated',
				'tenant',
				$context->id(),
				$changes
			);
		}

		return $this->context_for( $context->id(), $context->user_id() );
	}

	/**
	 * Add an existing WordPress user to the workspace.
	 *
	 * @param TenantContext        $context Verified context.
	 * @param array<string, mixed> $input   Request payload with email and role.
	 * @return TenantContext|\WP_Error
	 */
	public function add_member( TenantContext $context, array $input ) {
		$email = sanitize_email( (string) ( $input['email'] ?? '' ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return Rest::invalid( 'member_email_invalid', __( 'Enter a valid email address.', 'fashionos' ) );
		}

		$role = $this->assignable_role( $context, (string) ( $input['role'] ?? '' ) );

		if ( $role instanceof \WP_Error ) {
			return $role;
		}

		$user = get_user_by( 'email', $email );

		if ( ! $user instanceof \WP_User ) {
			return Rest::invalid(
				'member_account_required',
				__( 'That person does not have a WordPress account yet. They need to sign in once before you can add them.', 'fashionos' )
			);
		}

		$existing = $this->memberships->find_for_user( $context->id(), (int) $user->ID );

		if ( null !== $existing ) {
			return Rest::invalid( 'member_already_exists', __( 'That person is already on this team.', 'fashionos' ), 409 );
		}

		if ( 0 === $this->memberships->create( $context->id(), (int) $user->ID, (int) $role['id'] ) ) {
			return Rest::invalid( 'member_create_failed', __( 'The team member could not be added. Please try again.', 'fashionos' ), 500 );
		}

		$this->audit->record(
			$context->id(),
			$context->user_id(),
			'membership.added',
			'membership',
			(int) $user->ID,
			array(
				'email' => $email,
				'role'  => (string) $role['slug'],
			)
		);

		return $context;
	}

	/**
	 * Change a team member's role.
	 *
	 * @param TenantContext $context      Verified context.
	 * @param int           $member_id    Target WordPress user id.
	 * @param string        $role_slug    New role slug.
	 * @return true|\WP_Error
	 */
	public function change_member_role( TenantContext $context, int $member_id, string $role_slug ) {
		$membership = $this->memberships->find_for_user( $context->id(), $member_id );

		if ( null === $membership ) {
			return Rest::not_found( __( 'That person is not on this team.', 'fashionos' ), 'member_not_found' );
		}

		if ( Roles::OWNER === (string) $membership['role_slug'] ) {
			return Rest::invalid( 'owner_role_protected', __( 'The workspace owner cannot be re-roled.', 'fashionos' ) );
		}

		$role = $this->assignable_role( $context, $role_slug );

		if ( $role instanceof \WP_Error ) {
			return $role;
		}

		if ( (int) $membership['role_id'] === (int) $role['id'] ) {
			return true;
		}

		if ( ! $this->memberships->update_role( $context->id(), $member_id, (int) $role['id'] ) ) {
			return Rest::invalid( 'member_update_failed', __( 'The team member could not be updated. Please try again.', 'fashionos' ), 500 );
		}

		$this->audit->record(
			$context->id(),
			$context->user_id(),
			'membership.role_changed',
			'membership',
			$member_id,
			array(
				'from' => (string) $membership['role_slug'],
				'to'   => (string) $role['slug'],
			)
		);

		return true;
	}

	/**
	 * Remove a team member.
	 *
	 * @param TenantContext $context   Verified context.
	 * @param int           $member_id Target WordPress user id.
	 * @return true|\WP_Error
	 */
	public function remove_member( TenantContext $context, int $member_id ) {
		$membership = $this->memberships->find_for_user( $context->id(), $member_id );

		if ( null === $membership ) {
			return Rest::not_found( __( 'That person is not on this team.', 'fashionos' ), 'member_not_found' );
		}

		if ( Roles::OWNER === (string) $membership['role_slug'] ) {
			return Rest::invalid( 'owner_role_protected', __( 'The workspace owner cannot be removed.', 'fashionos' ) );
		}

		if ( ! $this->memberships->delete( $context->id(), $member_id ) ) {
			return Rest::invalid( 'member_remove_failed', __( 'The team member could not be removed. Please try again.', 'fashionos' ), 500 );
		}

		$this->audit->record(
			$context->id(),
			$context->user_id(),
			'membership.removed',
			'membership',
			$member_id,
			array( 'role' => (string) $membership['role_slug'] )
		);

		return true;
	}

	/**
	 * Rebuild the context for a user after a write.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $user_id   WordPress user id.
	 * @return TenantContext|\WP_Error
	 */
	public function context_for( int $tenant_id, int $user_id ) {
		$tenant = $this->tenants->find( $tenant_id );

		if ( null === $tenant ) {
			return Rest::not_found( __( 'That workspace could not be found.', 'fashionos' ), 'workspace_not_found' );
		}

		$membership = $this->memberships->find_for_user( $tenant_id, $user_id );

		if ( null === $membership ) {
			return Rest::forbidden( __( 'You do not have access to this workspace.', 'fashionos' ), 'workspace_access_denied' );
		}

		return new TenantContext( $tenant, $membership );
	}

	/**
	 * Look up an assignable role by slug.
	 *
	 * @param TenantContext $context   Verified context.
	 * @param string        $role_slug Role slug from the request.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function assignable_role( TenantContext $context, string $role_slug ) {
		$role_slug = sanitize_key( $role_slug );

		if ( Roles::OWNER === $role_slug || ! array_key_exists( $role_slug, Roles::assignable() ) ) {
			return Rest::invalid( 'member_role_invalid', __( 'Choose a valid team role.', 'fashionos' ) );
		}

		$role = $this->roles->find_by_slug( $context->id(), $role_slug );

		if ( null === $role ) {
			return Rest::invalid( 'member_role_invalid', __( 'Choose a valid team role.', 'fashionos' ) );
		}

		return $role;
	}

	/**
	 * Validate a timezone identifier.
	 *
	 * @param mixed $timezone Candidate timezone; a blank value uses the site timezone.
	 * @return string|\WP_Error
	 */
	private function valid_timezone( $timezone ) {
		$timezone = is_string( $timezone ) ? trim( $timezone ) : '';

		if ( '' === $timezone ) {
			return wp_timezone_string();
		}

		if ( ! in_array( $timezone, timezone_identifiers_list( \DateTimeZone::ALL_WITH_BC ), true ) ) {
			return Rest::invalid( 'workspace_timezone_invalid', __( 'Choose a valid timezone.', 'fashionos' ) );
		}

		return $timezone;
	}

	/**
	 * Validate a currency code.
	 *
	 * @param mixed $currency Candidate currency; a blank value defaults to NGN.
	 * @return string|\WP_Error
	 */
	private function valid_currency( $currency ) {
		$currency = is_string( $currency ) ? strtoupper( trim( $currency ) ) : '';

		if ( '' === $currency ) {
			return 'NGN';
		}

		if ( ! in_array( $currency, self::CURRENCIES, true ) ) {
			return Rest::invalid( 'workspace_currency_invalid', __( 'Choose a supported currency.', 'fashionos' ) );
		}

		return $currency;
	}

	/**
	 * Build a unique slug from a workspace name.
	 *
	 * @param string $name Workspace name.
	 */
	private function unique_slug( string $name ): string {
		$base = substr( sanitize_title( $name ), 0, 80 );

		if ( '' === $base ) {
			$base = 'workspace';
		}

		$slug   = $base;
		$suffix = 2;

		while ( $this->tenants->slug_exists( $slug ) && $suffix < 100 ) {
			$slug = $base . '-' . $suffix;
			++$suffix;
		}

		return $slug;
	}
}
