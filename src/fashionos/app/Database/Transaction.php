<?php
/**
 * Database transactions.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs a unit of work atomically.
 *
 * A `WP_Error` result rolls the transaction back, which lets services return
 * validation errors from inside the callback instead of throwing. Hosting
 * engines without InnoDB ignore the statements; docs/DEPLOYMENT.md requires
 * InnoDB to be confirmed before release.
 */
final class Transaction {

	/**
	 * Database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional database handle (tests).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->db = $db ?? $wpdb;
	}

	/**
	 * Run a callback inside a database transaction.
	 *
	 * @param callable $callback Work to run.
	 * @return mixed
	 */
	public function run( callable $callback ) {
		$this->db->query( 'START TRANSACTION' );

		try {
			$result = $callback();
		} catch ( \Throwable $exception ) {
			$this->db->query( 'ROLLBACK' );

			throw $exception;
		}

		if ( $result instanceof WP_Error ) {
			$this->db->query( 'ROLLBACK' );

			return $result;
		}

		$this->db->query( 'COMMIT' );

		return $result;
	}
}
