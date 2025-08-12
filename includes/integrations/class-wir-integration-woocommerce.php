<?php
defined('ABSPATH') || exit;

/** WooCommerce integration (product pages). */
class WIR_Integration_WooCommerce implements WIR_Integration {
    public function is_match(): bool {
        return function_exists('is_product') && is_product();
    }

    public function should_render(): bool {
        $o = WIR_Plugin::settings();
        if ($o['enabled'] !== 'yes') return false;
        return in_array($o['show_on'], ['product','both'], true) && $this->is_match();
    }

    public function get_context(): array {
        return [
            'id'    => get_the_ID(),
            'title' => get_the_title(),
        ];
    }
}
