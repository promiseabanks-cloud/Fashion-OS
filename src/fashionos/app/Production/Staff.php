<?php
/**
 * Staff assignment validation.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Production;

use FashionOS\API\Rest;
use FashionOS\Database\Repositories\MembershipRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves who production work is assigned to.
 *
 * Only current members of the workspace can be assigned, so a stale user id
 * from another business can never appear on a job or task. An empty value is
 * valid and means "unassigned".
 */
final class Staff {

	/**
	 * Membership reads.
	 *
	 * @var MembershipRepository
	 */
	private MembershipRepository $memberships;

	/**
	 * Constructor.
	 *
	 * @param MembershipRepository $memberships Membership reads.
	 */
	public function __construct( MembershipRepository $memberships ) {
		$this->memberships = $memberships;
	}

	/**
	 * Validate an optional assignee.
	 *
	 * @param int    $tenant_id  Tenant id.
	 * @param mixed  $value      Candidate WordPress user id, or blank.
	 * @param string $error_code Error code returned for an invalid person.
	 * @return int|WP_Error 0 when unassigned.
	 */
	public function normalise( int $tenant_id, $value, string $error_code ) {
		if ( null === $value || '' === $value || 0 === $value || '0' === $value ) {
			return 0;
		}

		if ( ! is_numeric( $value ) ) {
			return Rest::invalid( $error_code, __( 'Choose a team member from this workspace.', 'fashionos' ) );
		}

		$user_id = (int) $value;

		if ( null === $this->memberships->find_for_user( $tenant_id, $user_id ) ) {
			return Rest::invalid( $error_code, __( 'That person is not a member of this workspace.', 'fashionos' ) );
		}

		return $user_id;
	}
}
