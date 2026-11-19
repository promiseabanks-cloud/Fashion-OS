<?php
/**
 * Customer and measurement tests.
 *
 * @package FashionOS
 *
 * These tests require the WordPress test library and a test database:
 *
 *   WP_TESTS_DIR=/path/to/wordpress-tests-lib vendor/bin/phpunit
 *
 * They have not been executed in the development environment (no PHP runtime
 * or database); see docs/PHASE_2_PLAN.md.
 */

declare( strict_types=1 );

namespace FashionOS\Tests;

use FashionOS\Clients\ClientService;
use FashionOS\Database\Migrations\Migrations;
use FashionOS\Database\Migrator;
use FashionOS\Database\Repositories\AuditLogRepository;
use FashionOS\Database\Repositories\ClientRepository;
use FashionOS\Database\Repositories\MeasurementRepository;
use FashionOS\Database\Repositories\MeasurementTemplateRepository;
use FashionOS\Database\Repositories\MembershipRepository;
use FashionOS\Database\Repositories\RoleRepository;
use FashionOS\Database\Repositories\TenantRepository;
use FashionOS\Database\Transaction;
use FashionOS\Measurements\MeasurementService;
use FashionOS\Tenancy\TenantContext;
use FashionOS\Tenancy\TenantService;
use WP_Error;
use WP_UnitTestCase;

/**
 * Proves that customers and measurement history behave as the specification
 * requires: tenant-scoped records, duplicate detection and immutable versions.
 */
final class ClientMeasurementTest extends WP_UnitTestCase {

	/**
	 * Tenancy workflows, used to create workspaces.
	 *
	 * @var TenantService
	 */
	private TenantService $tenancy;

	/**
	 * Customer workflows under test.
	 *
	 * @var ClientService
	 */
	private ClientService $clients;

	/**
	 * Measurement workflows under test.
	 *
	 * @var MeasurementService
	 */
	private MeasurementService $measurements;

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

		$transactions = new Transaction();
		$audit        = new AuditLogRepository();
		$client_repo  = new ClientRepository();

		$this->tenancy = new TenantService(
			new TenantRepository(),
			new RoleRepository(),
			new MembershipRepository(),
			$audit,
			$transactions
		);

		$this->clients = new ClientService( $client_repo, $audit, $transactions );

		$this->measurements = new MeasurementService(
			$client_repo,
			new MeasurementTemplateRepository(),
			new MeasurementRepository(),
			$audit,
			$transactions
		);
	}

	/**
	 * Migration 0002 is the latest registered schema change.
	 */
	public function test_latest_migration_is_0002(): void {
		$this->assertSame( '0002', Migrations::latest_version() );
	}

	/**
	 * A customer gets a reference and is scoped to its workspace.
	 */
	public function test_customer_is_created_with_reference_and_audit(): void {
		$context = $this->workspace();

		$client = $this->clients->create(
			$context,
			array(
				'name'        => 'Amina Yusuf',
				'email'       => 'Amina@Example.test',
				'phone'       => '+234 803 555 0101',
				'preferences' => array(
					'preferredContact' => 'whatsapp',
					'styleNotes'       => 'Structured shoulders.',
				),
			)
		);

		$this->assertIsArray( $client );
		$this->assertMatchesRegularExpression( '/^C-\d{4}$/', $client['reference'] );
		$this->assertSame( 'active', $client['status'] );
		$this->assertSame( 'amina@example.test', $client['email'] );
		$this->assertSame( 'whatsapp', $client['preferences']['preferredContact'] );

		$entries = ( new AuditLogRepository() )->recent( $context->id() );
		$this->assertSame( 'client.created', $entries[0]['action'] );
	}

	/**
	 * Duplicate emails are refused; another workspace cannot see the customer.
	 */
	public function test_customer_is_tenant_scoped_and_deduplicated(): void {
		$owner    = $this->workspace();
		$other    = $this->workspace( 'Other Studio' );
		$customer = $this->clients->create( $owner, array( 'name' => 'Chidi Okonkwo', 'email' => 'chidi@example.test' ) );

		$this->assertIsArray( $customer );

		$duplicate = $this->clients->create( $owner, array( 'name' => 'Second Chidi', 'email' => 'chidi@example.test' ) );

		$this->assertWPError( $duplicate );
		$this->assertSame( 'client_duplicate', $duplicate->get_error_code() );

		$foreign = $this->clients->find( $other, (int) $customer['id'] );

		$this->assertWPError( $foreign );
		$this->assertSame( 'client_not_found', $foreign->get_error_code() );
	}

	/**
	 * The detail endpoint reports the real number of measurement profiles.
	 *
	 * The list query selected the count as a subquery while the single-record
	 * query did not, so the detail payload always said zero.
	 */
	public function test_customer_detail_reports_real_profile_count(): void {
		$context  = $this->workspace();
		$customer = $this->clients->create( $context, array( 'name' => 'Amina Yusuf' ) );
		$template = $this->template( $context, 'Two-piece suit', array( 'chest', 'waist' ) );

		$this->assertIsArray( $customer );
		$this->assertSame( 0, $customer['profileCount'] );

		foreach ( array( 'Wedding suit', 'Business suit' ) as $profile_name ) {
			$profile = $this->measurements->create_profile(
				$context,
				(int) $customer['id'],
				array( 'profileName' => $profile_name, 'templateId' => $template['id'] )
			);

			$this->assertIsArray( $profile );
		}

		$detail = $this->clients->find( $context, (int) $customer['id'] );

		$this->assertIsArray( $detail );
		$this->assertSame( 2, $detail['profileCount'] );

		$list = $this->clients->search( $context, array( 'search' => 'Amina' ) );

		$this->assertSame( 2, $list['clients'][0]['profileCount'] );
	}

	/**
	 * Search filters and paginates inside one workspace.
	 */
	public function test_customer_search_filters_and_paginates(): void {
		$context = $this->workspace();

		$this->clients->create( $context, array( 'name' => 'Amina Yusuf' ) );
		$this->clients->create( $context, array( 'name' => 'Amina Bello' ) );
		$this->clients->create( $context, array( 'name' => 'Chidi Okonkwo' ) );

		$result = $this->clients->search( $context, array( 'search' => 'Amina', 'page' => 1, 'perPage' => 1 ) );

		$this->assertSame( 2, $result['total'] );
		$this->assertCount( 1, $result['clients'] );
		$this->assertSame( 2, $result['totalPages'] );
	}

	/**
	 * Editing template fields bumps its schema version.
	 */
	public function test_template_edit_bumps_schema_version(): void {
		$context  = $this->workspace();
		$template = $this->template( $context, 'Two-piece suit', array( 'chest', 'waist' ) );

		$this->assertSame( 1, $template['schemaVersion'] );

		$updated = $this->measurements->update_template(
			$context,
			(int) $template['id'],
			array(
				'fields' => array(
					array( 'label' => 'Chest', 'required' => true ),
					array( 'label' => 'Waist', 'required' => true ),
					array( 'label' => 'Hips', 'required' => false ),
				),
			)
		);

		$this->assertIsArray( $updated );
		$this->assertSame( 2, $updated['schemaVersion'] );
		$this->assertCount( 3, $updated['fields'] );
	}

	/**
	 * A profile can be created for a customer and records versions.
	 */
	public function test_profile_and_versions_are_recorded(): void {
		$context  = $this->workspace();
		$customer = $this->clients->create( $context, array( 'name' => 'Amina Yusuf' ) );

		$this->assertIsArray( $customer );

		$template = $this->template( $context, 'Evening dress', array( 'bust', 'waist' ) );

		$profile = $this->measurements->create_profile(
			$context,
			(int) $customer['id'],
			array( 'profileName' => 'Reception dress', 'templateId' => $template['id'] )
		);

		$this->assertIsArray( $profile );
		$this->assertSame( 0, $profile['versionCount'] );

		$version = $this->measurements->create_version(
			$context,
			(int) $profile['id'],
			array( 'values' => array( 'bust' => '92', 'waist' => '74.5' ) )
		);

		$this->assertIsArray( $version );
		$this->assertSame( 1, $version['versionNumber'] );
		$this->assertSame( 2, $version['valueCount'] );
		$this->assertSame( 74.5, $version['values'][1]['value'] );
	}

	/**
	 * Required fields, unknown keys and non-numeric values are refused.
	 */
	public function test_version_values_are_validated(): void {
		$context = $this->workspace();
		$profile = $this->profile( $context );

		$missing = $this->measurements->create_version( $context, (int) $profile['id'], array( 'values' => array( 'waist' => '80' ) ) );

		$this->assertWPError( $missing );
		$this->assertSame( 'measurement_value_required', $missing->get_error_code() );

		$unknown = $this->measurements->create_version(
			$context,
			(int) $profile['id'],
			array( 'values' => array( 'chest' => '96', 'waist' => '80', 'neck' => '40' ) )
		);

		$this->assertWPError( $unknown );
		$this->assertSame( 'measurement_value_unknown', $unknown->get_error_code() );

		$invalid = $this->measurements->create_version(
			$context,
			(int) $profile['id'],
			array( 'values' => array( 'chest' => '96cm', 'waist' => '80' ) )
		);

		$this->assertWPError( $invalid );
		$this->assertSame( 'measurement_value_invalid', $invalid->get_error_code() );
	}

	/**
	 * Historical versions never change, even after the template is edited.
	 */
	public function test_measurement_versions_are_immutable(): void {
		$context = $this->workspace();
		$profile = $this->profile( $context );

		$first = $this->measurements->create_version(
			$context,
			(int) $profile['id'],
			array( 'values' => array( 'chest' => '96', 'waist' => '82' ) )
		);

		$this->assertIsArray( $first );

		$updated = $this->measurements->update_template(
			$context,
			(int) $profile['templateId'],
			array(
				'fields' => array(
					array( 'label' => 'Chest', 'required' => true ),
					array( 'label' => 'Waist', 'required' => true ),
					array( 'label' => 'Hips', 'required' => false ),
				),
			)
		);

		$this->assertIsArray( $updated );

		$second = $this->measurements->create_version(
			$context,
			(int) $profile['id'],
			array( 'values' => array( 'chest' => '93', 'waist' => '79', 'hips' => '98' ) )
		);

		$this->assertIsArray( $second );
		$this->assertSame( 2, $second['versionNumber'] );

		$reloaded = $this->measurements->version( $context, (int) $first['id'] );

		$this->assertIsArray( $reloaded );
		$this->assertSame( 1, $reloaded['versionNumber'] );
		$this->assertSame( 96.0, $reloaded['values'][0]['value'] );
		$this->assertCount( 2, $reloaded['template']['fields'] );
		$this->assertSame( 1, $reloaded['template']['schemaVersion'] );

		$this->assertFalse(
			method_exists( MeasurementRepository::class, 'update_version' ),
			'Versions must have no update path in the repository.'
		);
		$this->assertFalse(
			method_exists( MeasurementRepository::class, 'delete_version' ),
			'Versions must have no delete path in the repository.'
		);
	}

	/**
	 * Measurement records of another workspace are invisible.
	 */
	public function test_measurements_are_tenant_scoped(): void {
		$owner = $this->workspace();
		$other = $this->workspace( 'Other Studio' );

		$profile = $this->profile( $owner );

		$foreign_profile = $this->measurements->profile( $other, (int) $profile['id'] );

		$this->assertWPError( $foreign_profile );
		$this->assertSame( 'measurement_profile_not_found', $foreign_profile->get_error_code() );

		$version = $this->measurements->create_version(
			$owner,
			(int) $profile['id'],
			array( 'values' => array( 'chest' => '96', 'waist' => '82' ) )
		);

		$this->assertIsArray( $version );

		$foreign_version = $this->measurements->version( $other, (int) $version['id'] );

		$this->assertWPError( $foreign_version );
		$this->assertSame( 'measurement_version_not_found', $foreign_version->get_error_code() );
	}

	/**
	 * Create a workspace owned by a fresh user.
	 *
	 * @param string $name Workspace name.
	 */
	private function workspace( string $name = 'Measurement Studio' ): TenantContext {
		$context = $this->tenancy->create( self::factory()->user->create(), array( 'name' => $name ) );

		$this->assertInstanceOf( TenantContext::class, $context );

		return $context;
	}

	/**
	 * Create a template with the given field labels.
	 *
	 * @param TenantContext $context Tenant context.
	 * @param string        $name    Template name.
	 * @param string[]      $labels  Field labels.
	 * @return array<string, mixed>
	 */
	private function template( TenantContext $context, string $name, array $labels ): array {
		$fields = array_map(
			static fn ( string $label ): array => array(
				'label'    => ucfirst( $label ),
				'required' => true,
			),
			$labels
		);

		$template = $this->measurements->create_template(
			$context,
			array(
				'name'            => $name,
				'garmentCategory' => 'Suit',
				'unit'            => 'cm',
				'fields'          => $fields,
			)
		);

		$this->assertIsArray( $template );

		return $template;
	}

	/**
	 * Create a customer, template and profile for a workspace.
	 *
	 * @param TenantContext $context Tenant context.
	 * @return array<string, mixed>
	 */
	private function profile( TenantContext $context ): array {
		$customer = $this->clients->create( $context, array( 'name' => 'Amina Yusuf' ) );
		$template = $this->template( $context, 'Two-piece suit', array( 'chest', 'waist' ) );

		$this->assertIsArray( $customer );

		$profile = $this->measurements->create_profile(
			$context,
			(int) $customer['id'],
			array( 'profileName' => 'Wedding suit', 'templateId' => $template['id'] )
		);

		$this->assertIsArray( $profile );

		$profile['templateId'] = (int) $template['id'];

		return $profile;
	}
}
