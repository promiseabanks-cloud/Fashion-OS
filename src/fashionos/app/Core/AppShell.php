<?php
/**
 * WordPress application-shell integration.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the React mount point inside a WordPress page.
 *
 * The shortcode keeps the shell usable with ordinary WordPress pages; a
 * dedicated application route follows when routing exists.
 */
final class AppShell {

	/**
	 * Shortcode tag that renders the application.
	 */
	public const SHORTCODE = 'fashionos_app';

	/**
	 * Asset loader.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Constructor.
	 *
	 * @param Assets $assets Asset loader.
	 */
	public function __construct( Assets $assets ) {
		$this->assets = $assets;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_build_notice' ) );
		}
	}

	/**
	 * Render the application shell.
	 */
	public function render(): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_signed_out();
		}

		if ( ! $this->assets->is_built() && ! Assets::is_development() ) {
			return '<div class="fashionos-notice">' . esc_html__(
				'The FashionOS interface has not been built yet. Run "npm install && npm run build" in the plugin directory.',
				'fashionos'
			) . '</div>';
		}

		$this->assets->enqueue();

		ob_start();
		require FASHIONOS_DIR . 'templates/app-shell.php';

		return (string) ob_get_clean();
	}

	/**
	 * Prompt anonymous visitors to sign in.
	 */
	private function render_signed_out(): string {
		$login_url = wp_login_url( get_permalink() );

		return sprintf(
			'<div class="fashionos-notice"><p>%1$s</p><p><a class="fashionos-notice__link" href="%2$s">%3$s</a></p></div>',
			esc_html__( 'FashionOS is a private workspace. Sign in to continue.', 'fashionos' ),
			esc_url( $login_url ),
			esc_html__( 'Sign in to FashionOS', 'fashionos' )
		);
	}

	/**
	 * Explain a missing build to administrators instead of showing a blank page.
	 */
	public function render_missing_build_notice(): void {
		if ( Assets::is_development() || $this->assets->is_built() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__(
				'FashionOS is active but its frontend has not been built. Run "npm install && npm run build" in the plugin directory, or define FASHIONOS_VITE_DEV for local development.',
				'fashionos'
			)
		);
	}
}
