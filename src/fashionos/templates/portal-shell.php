<?php
/**
 * Customer portal mount point.
 *
 * @package FashionOS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="fashionos-root" class="fashionos-shell fashionos-shell--portal">
	<div class="fashionos-boot" role="status" aria-live="polite">
		<p class="fashionos-boot__wordmark">FashionOS</p>
		<p class="fashionos-boot__text"><?php esc_html_e( 'Opening your portal…', 'fashionos' ); ?></p>
	</div>
	<noscript>
		<p class="fashionos-boot__text">
			<?php esc_html_e( 'The FashionOS portal needs JavaScript enabled in this browser.', 'fashionos' ); ?>
		</p>
	</noscript>
</div>
