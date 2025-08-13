<?php
defined( 'ABSPATH' ) || exit;

/**
 * Contract for feature integrations (Woo, Dokan, EDD, etc.)
 */
interface WIR_Integration {
	/** Return true if current page matches integration (e.g., is_product). */
	public function is_match(): bool;

	/** Should we show the button/modal given plugin settings? */
	public function should_render(): bool;

	/** Provide context array: ['id'=>int,'title'=>string] */
	public function get_context(): array;
}
