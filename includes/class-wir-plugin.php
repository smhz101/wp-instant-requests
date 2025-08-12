<?php
defined('ABSPATH') || exit;

/**
 * Core plugin orchestrator (singleton).
 */
final class WIR_Plugin {
    const VERSION = '1.0.0';
    const OPTION  = 'wir_settings';
    const NONCE   = 'wir_nonce';
    const CPT     = 'wir_request';

    /** @var WIR_Plugin */
    private static $instance;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Register CPT, assets, admin, ajax, replies.
        add_action('init', [WIR_CPT::class, 'register']);
        add_action('wp_enqueue_scripts', [WIR_Assets::class, 'frontend']);
        add_action('wp_footer', [WIR_Assets::class, 'modal']);
        add_action('wp_ajax_wir_submit', [WIR_Ajax::class, 'submit']);
        add_action('wp_ajax_nopriv_wir_submit', [WIR_Ajax::class, 'submit']);

        add_action('admin_menu', [WIR_Admin::class, 'menus']);
        add_action('admin_init', [WIR_Admin::class, 'register_settings']);
        add_filter('manage_edit-' . self::CPT . '_columns', [WIR_Admin::class, 'columns']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [WIR_Admin::class, 'column_content'], 10, 2);
        add_action('add_meta_boxes', [WIR_Replies::class, 'metabox']);
        add_action('save_post_' . self::CPT, [WIR_Replies::class, 'save']);

        // Register integrations (Woo first, then generic fallback).
        add_filter('wir_integrations', function ($list) {
            $list[] = new WIR_Integration_WooCommerce();
            $list[] = new WIR_Integration_Generic();
            return $list;
        });

        add_action('admin_init', function () {
            add_action('wp_ajax_wir_admin_reply',   [WIR_Admin::class, 'ajax_admin_reply']);
            add_action('wp_ajax_wir_get_header',    [WIR_Admin::class, 'ajax_get_header']); 
            add_action('wp_ajax_wir_get_thread',    [WIR_Admin::class, 'ajax_get_thread']);
            add_action('wp_ajax_wir_save_note',     [WIR_Admin::class, 'ajax_save_note']);
            add_action('wp_ajax_wir_toggle_status', [WIR_Admin::class, 'ajax_toggle_status']);
            add_action('wp_ajax_wir_assign_me',     [WIR_Admin::class, 'ajax_assign_me']);
            add_action('admin_post_wir_export_csv', [WIR_Admin::class, 'export_csv']);
        });

        // Ensure WPML/Polylang admin strings are registered.
        add_action('update_option_' . self::OPTION, [WIR_Admin::class, 'register_strings_for_translation'], 10, 2);
    }

    /** Helper: get settings (merged with defaults). */
    public static function settings() {
        $defaults = [
            'button_text' => __('Ask about this item', 'wp-instant-requests'),
            'side'        => 'right',
            'accent'      => '#2563eb',
            'topics'      => '',
            'notify'      => 'yes',
            'gdpr_label'  => __('I agree to be contacted about this request.', 'wp-instant-requests'),
            'enabled'     => 'yes',
            'show_on'     => 'product',
        ];
        return wp_parse_args(get_option(self::OPTION, []), $defaults);
    }

    /** Resolve the first integration that matches current screen. */
    public static function current_integration() {
        $integrations = apply_filters('wir_integrations', []);
        foreach ($integrations as $i) {
            if ($i instanceof WIR_Integration && $i->is_match()) return $i;
        }
        return null;
    }
}
