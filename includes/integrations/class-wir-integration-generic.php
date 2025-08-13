<?php
defined( 'ABSPATH' ) || exit;

/** Generic integration for single posts/pages as fallback. */
class WIR_Integration_Generic implements WIR_Integration {
	public function is_match(): bool {
		return is_singular() && ! is_front_page();
	}

	public function should_render(): bool {
		$o = WIR_Plugin::settings();
		if ( $o['enabled'] !== 'yes' ) {
			return false;
		}
		return in_array( $o['show_on'], array( 'single', 'both' ), true ) && $this->is_match();
	}

	public function get_context(): array {
		return array(
			'id'    => get_the_ID(),
			'title' => get_the_title(),
		);
	}
}
