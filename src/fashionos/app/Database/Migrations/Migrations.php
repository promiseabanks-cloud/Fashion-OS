<?php
/**
 * Migration registry.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Database\Migrations;

use FashionOS\Database\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordered registry of every migration shipped with the plugin.
 *
 * Add new migrations to all() in ascending version order and never edit a
 * migration that has shipped.
 */
final class Migrations {

	/**
	 * Every migration, sorted by version.
	 *
	 * @return Migration[]
	 */
	public static function all(): array {
		$migrations = array(
			new Migration0001Tenancy(),
			new Migration0002ClientsMeasurements(),
			new Migration0003QuotationsOrders(),
			new Migration0004ProductionInventory(),
		);

		usort(
			$migrations,
			static fn ( Migration $a, Migration $b ): int => strcmp( $a->version(), $b->version() )
		);

		return $migrations;
	}

	/**
	 * Highest registered migration version, or "0000" when none exist.
	 */
	public static function latest_version(): string {
		$versions = array_map(
			static fn ( Migration $migration ): string => $migration->version(),
			self::all()
		);

		if ( array() === $versions ) {
			return '0000';
		}

		return (string) max( $versions );
	}
}
