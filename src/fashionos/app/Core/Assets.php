<?php
/**
 * Frontend asset registration.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the compiled React application or the Vite dev server.
 *
 * Production reads the Vite manifest so hashed filenames are cache-safe. No
 * Node.js runtime is involved at request time.
 */
final class Assets {

	/**
	 * Script/style handle for the application.
	 */
	public const HANDLE = 'fashionos-app';

	/**
	 * Directory containing the built assets.
	 *
	 * @var string
	 */
	private string $build_dir;

	/**
	 * Decoded manifest, read at most once per request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $manifest = null;

	/**
	 * Constructor.
	 *
	 * @param string|null $build_dir Optional build directory override (tests).
	 */
	public function __construct( ?string $build_dir = null ) {
		$this->build_dir = $build_dir ?? FASHIONOS_DIR . 'public/build';
	}

	/**
	 * Whether a Vite development server URL has been configured.
	 *
	 * Define `FASHIONOS_VITE_DEV` in wp-config.php during local development,
	 * for example: define( 'FASHIONOS_VITE_DEV', 'http://localhost:5173' );
	 */
	public static function is_development(): bool {
		return defined( 'FASHIONOS_VITE_DEV' ) && is_string( FASHIONOS_VITE_DEV ) && '' !== FASHIONOS_VITE_DEV;
	}

	/**
	 * Read and cache the Vite manifest.
	 *
	 * @return array<string, mixed>
	 */
	public function manifest(): array {
		if ( null !== $this->manifest ) {
			return $this->manifest;
		}

		$path = trailingslashit( $this->build_dir ) . 'manifest.json';

		if ( ! is_readable( $path ) ) {
			$this->manifest = array();

			return $this->manifest;
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local build manifest.
		$decoded  = is_string( $contents ) ? json_decode( $contents, true ) : null;

		$this->manifest = is_array( $decoded ) ? $decoded : array();

		return $this->manifest;
	}

	/**
	 * Whether compiled assets with a readable manifest exist.
	 */
	public function is_built(): bool {
		$manifest = $this->manifest();

		return isset( $manifest['resources/js/app/main.tsx']['file'] );
	}

	/**
	 * Enqueue the application entry point.
	 */
	public function enqueue(): void {
		if ( self::is_development() ) {
			$this->enqueue_development();
			return;
		}

		$manifest = $this->manifest();
		$entry    = $manifest['resources/js/app/main.tsx'] ?? null;

		if ( ! is_array( $entry ) || empty( $entry['file'] ) || ! is_string( $entry['file'] ) ) {
			return;
		}

		$base = trailingslashit( FASHIONOS_URL . 'public/build' );

		wp_enqueue_script(
			self::HANDLE,
			$base . ltrim( $entry['file'], './' ),
			array(),
			FASHIONOS_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		foreach ( (array) ( $entry['css'] ?? array() ) as $css_file ) {
			if ( is_string( $css_file ) && '' !== $css_file ) {
				wp_enqueue_style(
					self::HANDLE,
					$base . ltrim( $css_file, './' ),
					array(),
					FASHIONOS_VERSION
				);
				break;
			}
		}

		$this->add_config_script();
		add_filter( 'script_loader_tag', array( $this, 'module_script_tag' ), 10, 3 );
	}

	/**
	 * Enqueue the Vite dev client and entry for local development.
	 */
	private function enqueue_development(): void {
		$origin = untrailingslashit( (string) FASHIONOS_VITE_DEV );

		wp_enqueue_script(
			self::HANDLE . '-client',
			$origin . '/@vite/client',
			array(),
			null,
			array( 'in_footer' => true )
		);

		wp_enqueue_script(
			self::HANDLE,
			$origin . '/resources/js/app/main.tsx',
			array( self::HANDLE . '-client' ),
			null,
			array( 'in_footer' => true )
		);

		$this->add_config_script();
		add_filter( 'script_loader_tag', array( $this, 'module_script_tag' ), 10, 3 );
	}

	/**
	 * Provide runtime configuration to the React application.
	 */
	private function add_config_script(): void {
		$user = wp_get_current_user();

		$config = array(
			'version'       => FASHIONOS_VERSION,
			'restUrl'       => esc_url_raw( rest_url( 'fashionos/v1' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'homeUrl'       => home_url( '/' ),
			'logoutUrl'     => wp_logout_url( home_url( '/' ) ),
			'isDevelopment' => self::is_development(),
			'user'          => array(
				'id'          => (int) $user->ID,
				'displayName' => (string) $user->display_name,
			),
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.FashionOS = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Mark our bundle as an ES module; Vite output is ESM.
	 *
	 * @param string $tag    Script tag markup.
	 * @param string $handle Script handle.
	 * @param string $src    Script source URL.
	 */
	public function module_script_tag( string $tag, string $handle, string $src ): string {
		if ( ! in_array( $handle, array( self::HANDLE, self::HANDLE . '-client' ), true ) ) {
			return $tag;
		}

		return sprintf(
			'<script type="module" src="%s" id="%s-js"></script>' . "\n",
			esc_url( $src ),
			esc_attr( $handle )
		);
	}

}
