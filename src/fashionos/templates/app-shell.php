<?php
/**
 * React application mount point.
 *
 * @package FashionOS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="fashionos-root" class="fashionos-shell">
	<div class="fashionos-boot" role="status" aria-live="polite">
		<p class="fashionos-boot__wordmark">FashionOS</p>
		<p class="fashionos-boot__text"><?php esc_html_e( 'Opening your studio…', 'fashionos' ); ?></p>
	</div>
	<noscript>
		<p class="fashionos-boot__text">
			<?php esc_html_e( 'FashionOS needs JavaScript enabled in this browser.', 'fashionos' ); ?>
		</p>
	</noscript>
</div>
