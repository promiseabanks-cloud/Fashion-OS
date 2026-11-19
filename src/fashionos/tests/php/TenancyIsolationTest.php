<?php
/**
 * Cross-tenant isolation tests.
 *
 * @package FashionOS
 *
 * These tests require the WordPress test library and a test database:
 *
 *   WP_TESTS_DIR=/path/to/wordpress-tests-lib vendor/bin/phpunit
 *
 * They have not been executed in the development environment (no PHP runtime
 * or database); see docs/PHASE_1_PLAN.md.
 */

declare( strict_types=1 );

namespace FashionOS\Tests;

use FashionOS\Database\Migrator;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Tenancy\Permissions;
use FashionOS\Tenancy\Roles;
use FashionOS\Tenancy\TenantContext;
use FashionOS\Tenancy\TenantResolver;
use FashionOS\Tenancy\TenantService;
use WP_Error;
use WP_UnitTestCase;

/**
 * Proves that a membership in one workspace grants nothing in another.
 */
final class TenancyIsolationTest extends WP_UnitTestCase {

	/**
	 * Tenancy workflows under test.
	 *
	 * @var TenantService
	 */
	private TenantService $service;

	/**
	 * Resolver under test.
	 *
	 * @var TenantResolver
	 */
	private TenantResolver $resolver;

	/**
	 * Install the plugin schema once for the suite.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		( new Migrator() )->migrate();
	}

	/**
	 * Build the services against the test database.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->service = new TenantService(
			new TenantRepository(),
			new RoleRepository(),
			new MembershipRepository(),
			new AuditLogRepository()
		);

		$this->resolver = new TenantResolver( new TenantRepository(), new MembershipRepository() );
	}

	/**
	 * Creating a workspace makes the creator its owner with audited activity.
	 */
	public function test_creator_becomes_owner(): void {
		$user_id = self::factory()->user->create();

		$context = $this->service->create( $user_id, array( 'name' => 'Adaeze Atelier' ) );

		$this->assertInstanceOf( TenantContext::class, $context );
		$this->assertSame( 'active', $context->status() );
		$this->assertSame( Roles::OWNER, $context->role_slug() );
		$this->assertTrue( $context->can( Permissions::AUDIT_VIEW ) );
		$this->assertSame( 1, $this->memberships()->count_for_tenant( $context->id() ) );
	}

	/**
	 * A user cannot resolve a workspace they do not belong to.
	 */
	public function test_foreign_workspace_selector_is_refused(): void {
		$owner_id    = self::factory()->user->create();
		$outsider_id = self::factory()->user->create();

		$own     = $this->service->create( $owner_id, array( 'name' => 'Owner Studio' ) );
		$foreign = $this->service->create( $outsider_id, array( 'name' => 'Other Studio' ) );

		$result = $this->resolver->resolve( $owner_id, (string) $foreign->id() );

		$this->assertWPError( $result );
		$this->assertSame( 'workspace_access_denied', $result->get_error_code() );

		$allowed = $this->resolver->resolve( $owner_id, (string) $own->id() );

		$this->assertInstanceOf( TenantContext::class, $allowed );
	}

	/**
	 * Roles differ per workspace for the same person.
	 */
	public function test_permissions_differ_per_workspace(): void {
		$user_id = self::factory()->user->create();

		$owned = $this->service->create( $user_id, array( 'name' => 'Owned Studio' ) );

		$other_owner_id = self::factory()->user->create();
		$joined         = $this->service->create( $other_owner_id, array( 'name' => 'Joined Studio' ) );

		$this->assertInstanceOf( TenantContext::class, $joined );

		$added = $this->service->add_member(
			$this->context_for( $joined->id(), $other_owner_id ),
			array(
				'email' => get_userdata( $user_id )->user_email,
				'role'  => Roles::TAILOR,
			)
		);

		$this->assertNotWPError( $added );

		$tailor = $this->context_for( $joined->id(), $user_id );

		$this->assertTrue( $tailor->can( Permissions::WORKSPACE_VIEW ) );
		$this->assertFalse( $tailor->can( Permissions::TEAM_MANAGE ) );
		$this->assertTrue( $owned->can( Permissions::TEAM_MANAGE ) );
	}

	/**
	 * The owner cannot be removed or re-roled, and unknown emails are refused.
	 */
	public function test_owner_and_unknown_members_are_protected(): void {
		$owner_id = self::factory()->user->create();
		$context  = $this->service->create( $owner_id, array( 'name' => 'Protected Studio' ) );

		$this->assertInstanceOf( TenantContext::class, $context );

		$removed = $this->service->remove_member( $context, $owner_id );
		$this->assertWPError( $removed );
		$this->assertSame( 'owner_role_protected', $removed->get_error_code() );

		$re_roled = $this->service->change_member_role( $context, $owner_id, Roles::MANAGER );
		$this->assertWPError( $re_roled );
		$this->assertSame( 'owner_role_protected', $re_roled->get_error_code() );

		$unknown = $this->service->add_member(
			$context,
			array(
				'email' => 'nobody@example.test',
				'role'  => Roles::DESIGNER,
			)
		);
		$this->assertWPError( $unknown );
		$this->assertSame( 'member_account_required', $unknown->get_error_code() );
	}

	/**
	 * Build an owner context for a workspace created in the test.
	 *
	 * @param int $tenant_id Tenant id.
	 * @param int $user_id   Owner user id.
	 */
	private function context_for( int $tenant_id, int $user_id ): TenantContext {
		$context = $this->service->context_for( $tenant_id, $user_id );

		$this->assertInstanceOf( TenantContext::class, $context );

		return $context;
	}

	/**
	 * Membership repository for assertions.
	 */
	private function memberships(): MembershipRepository {
		return new MembershipRepository();
	}
}
