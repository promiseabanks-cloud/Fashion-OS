<?php
/**
 * PHPUnit bootstrap for the WordPress test library.
 *
 * Set WP_TESTS_DIR to a checkout of wordpress-tests-lib (wp-env and the
 * WordPress plugin test scaffold both provide one). The plugin is loaded as a
 * must-use plugin so its hooks run against the test database.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

$fashionos_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! is_string( $fashionos_tests_dir ) || '' === $fashionos_tests_dir ) {
	$fashionos_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $fashionos_tests_dir . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"Could not find the WordPress test library at {$fashionos_tests_dir}.\n" .
		"Set WP_TESTS_DIR to its location before running PHPUnit.\n"
	);

	exit( 1 );
}

require_once $fashionos_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/fashionos.php';
	}
);

require $fashionos_tests_dir . '/includes/bootstrap.php';
