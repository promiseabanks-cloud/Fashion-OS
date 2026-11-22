<?php
/**
 * Plugin Name:       FashionOS
 * Plugin URI:        https://fashionos.app
 * Description:       Multi-tenant business operating system for bespoke fashion designers, tailors and studios.
 * Version:           0.7.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            FashionOS
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fashionos
 * Domain Path:       /languages
 *
 * @package FashionOS
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FASHIONOS_VERSION', '0.7.0' );
define( 'FASHIONOS_MIN_PHP', '8.1.0' );
define( 'FASHIONOS_FILE', __FILE__ );
define( 'FASHIONOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FASHIONOS_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( PHP_VERSION, FASHIONOS_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'FashionOS requires PHP %1$s or newer. This server runs PHP %2$s.', 'fashionos' ),
						FASHIONOS_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);

	return;
}

/**
 * Autoload plugin classes from app/ using the FashionOS\ namespace.
 *
 * A hand-rolled PSR-4 loader keeps the plugin runnable on shared hosting
 * without requiring `composer install` in production.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'FashionOS\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = FASHIONOS_DIR . 'app/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

require_once FASHIONOS_DIR . 'app/Core/Plugin.php';

register_activation_hook( __FILE__, array( FashionOS\Core\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( FashionOS\Core\Plugin::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		FashionOS\Core\Plugin::instance()->boot();
	}
);
