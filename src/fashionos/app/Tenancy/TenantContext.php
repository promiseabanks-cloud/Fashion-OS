<?php
/**
 * Resolved tenant context.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Tenancy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The workspace, membership and role resolved for the current request.
 *
 * Created only after a membership row has been verified, so holding one is
 * proof that the user may act inside that workspace. Nothing here is taken
 * from the browser except the workspace selector that was validated.
 */
final class TenantContext {

	/**
	 * Tenant row.
	 *
	 * @var array<string, mixed>
	 */
	private array $tenant;

	/**
	 * Membership row including role fields.
	 *
	 * @var array<string, mixed>
	 */
	private array $membership;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $tenant     Tenant row.
	 * @param array<string, mixed> $membership Membership row with role fields.
	 */
	public function __construct( array $tenant, array $membership ) {
		$this->tenant     = $tenant;
		$this->membership = $membership;
	}

	/**
	 * Tenant id.
	 */
	public function id(): int {
		return (int) $this->tenant['id'];
	}

	/**
	 * Tenant lifecycle status.
	 */
	public function status(): string {
		return (string) ( $this->tenant['status'] ?? 'inactive' );
	}

	/**
	 * WordPress user id that owns this membership.
	 */
	public function user_id(): int {
		return (int) $this->membership['user_id'];
	}

	/**
	 * Role slug inside this tenant.
	 */
	public function role_slug(): string {
		return (string) ( $this->membership['role_slug'] ?? '' );
	}

	/**
	 * Role display name.
	 */
	public function role_name(): string {
		return (string) ( $this->membership['role_name'] ?? '' );
	}

	/**
	 * Permission keys granted to this membership.
	 *
	 * @return string[]
	 */
	public function permissions(): array {
		$permissions = $this->membership['role_permissions'] ?? array();

		return is_array( $permissions ) ? array_values( $permissions ) : array();
	}

	/**
	 * Whether this membership holds a permission.
	 *
	 * Inactive workspaces grant nothing, whatever the role says.
	 *
	 * @param string $permission Permission key.
	 */
	public function can( string $permission ): bool {
		if ( 'active' !== $this->status() ) {
			return false;
		}

		$permissions = $this->permissions();

		return in_array( '*', $permissions, true ) || in_array( $permission, $permissions, true );
	}

	/**
	 * Whether this membership is the workspace owner.
	 */
	public function is_owner(): bool {
		return Roles::OWNER === $this->role_slug();
	}

	/**
	 * Public tenant fields.
	 *
	 * @return array<string, mixed>
	 */
	public function tenant(): array {
		return TenantPresenter::to_array( $this->tenant );
	}

	/**
	 * Response shape for the active workspace.
	 *
	 * @return array<string, mixed>
	 */
	public function present(): array {
		return array(
			'tenant'      => $this->tenant(),
			'role'        => array(
				'slug' => $this->role_slug(),
				'name' => $this->role_name(),
			),
			'permissions' => $this->permissions(),
			'isOwner'     => $this->is_owner(),
		);
	}
}
