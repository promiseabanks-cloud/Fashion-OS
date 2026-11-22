<?php
/**
 * Resolved portal context.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Portal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The workspace, customer and access link resolved for a portal request.
 *
 * Created only after a portal token has been matched to a live access row and
 * that row's customer has been found inside the row's workspace. Holding one is
 * therefore proof that the request may read that one customer's records; there
 * is no WordPress user behind it and no permission list to consult.
 */
final class PortalContext {

	/**
	 * Tenant row.
	 *
	 * @var array<string, mixed>
	 */
	private array $tenant;

	/**
	 * Customer row.
	 *
	 * @var array<string, mixed>
	 */
	private array $client;

	/**
	 * Access-link row.
	 *
	 * @var array<string, mixed>
	 */
	private array $access;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $tenant Tenant row.
	 * @param array<string, mixed> $client Customer row.
	 * @param array<string, mixed> $access Access-link row.
	 */
	public function __construct( array $tenant, array $client, array $access ) {
		$this->tenant = $tenant;
		$this->client = $client;
		$this->access = $access;
	}

	/**
	 * Tenant id.
	 */
	public function tenant_id(): int {
		return (int) $this->tenant['id'];
	}

	/**
	 * Customer id.
	 */
	public function client_id(): int {
		return (int) $this->client['id'];
	}

	/**
	 * Tenant row, for presenters to shape.
	 *
	 * @return array<string, mixed>
	 */
	public function tenant(): array {
		return $this->tenant;
	}

	/**
	 * Customer row, for presenters to shape.
	 *
	 * @return array<string, mixed>
	 */
	public function client(): array {
		return $this->client;
	}

	/**
	 * Access-link row, for presenters to shape.
	 *
	 * @return array<string, mixed>
	 */
	public function access(): array {
		return $this->access;
	}
}
