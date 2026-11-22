<?php
/**
 * Customer portal shell.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the React portal entry point.
 *
 * The portal is reached from a link the studio issues, so it renders for
 * visitors who are not WordPress users. The token is read from the link once,
 * handed to the React application in its runtime configuration, and stripped
 * from the address bar there; the responses are marked no-cache and no-index so
 * a shared computer or a proxy cannot serve one customer's page to another.
 */
final class PortalShell {

	/**
	 * Shortcode tag that renders the portal.
	 */
	public const SHORTCODE = 'fashionos_portal';

	/**
	 * Query parameter carrying the portal token on first load.
	 */
	public const TOKEN_PARAM = 'fos_portal';

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
		add_action( 'template_redirect', array( $this, 'harden_response' ) );
	}

	/**
	 * Keep a portal page out of caches and search indexes.
	 */
	public function harden_response(): void {
		if ( '' === $this->token() ) {
			return;
		}

		nocache_headers();

		// The token is in the URL until the application strips it, so it must not
		// be forwarded to the asset and link requests made in the meantime.
		if ( ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}

		add_filter(
			'wp_robots',
			static function ( array $robots ): array {
				$robots['noindex']  = true;
				$robots['nofollow'] = true;

				return $robots;
			}
		);
	}

	/**
	 * Render the portal mount point.
	 */
	public function render(): string {
		if ( ! $this->assets->is_built() && ! Assets::is_development() ) {
			return '<div class="fashionos-notice">' . esc_html__(
				'The FashionOS portal has not been built yet. Run "npm install && npm run build" in the plugin directory.',
				'fashionos'
			) . '</div>';
		}

		$this->assets->enqueue();

		wp_add_inline_script(
			Assets::HANDLE,
			'window.FashionOS = Object.assign( window.FashionOS || {}, ' . wp_json_encode(
				array(
					'mode'        => 'portal',
					'portalToken' => $this->token(),
				)
			) . ' );',
			'before'
		);

		ob_start();
		require FASHIONOS_DIR . 'templates/portal-shell.php';

		return (string) ob_get_clean();
	}

	/**
	 * The token carried by the current request, if any.
	 */
	private function token(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bearer token from a link the studio issued; it is not a form submission.
		$raw = isset( $_GET[ self::TOKEN_PARAM ] ) ? wp_unslash( (string) $_GET[ self::TOKEN_PARAM ] ) : '';

		return mb_substr( sanitize_text_field( $raw ), 0, 191 );
	}
}
