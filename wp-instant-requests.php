<?php
/**
 * Plugin Name: WP Instant Requests (Product/Page Inquiry)
 * Description: Floating request/inquiry modal for WooCommerce products and single content. Saves entries (CPT), admin replies, settings, and an integration layer (Dokan/EDD-ready). Fully translation-ready.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wp-instant-requests
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

/**
 * Plugin constants
 * - WIR_FILE: absolute path to this file
 * - WIR_DIR:  absolute dir path for includes
 * - WIR_URL:  base URL for assets
 * - WIR_VERSION: plugin version for cache-busting
 */
if (!defined('WIR_FILE'))    define('WIR_FILE', __FILE__);
if (!defined('WIR_DIR'))     define('WIR_DIR', plugin_dir_path(WIR_FILE));
if (!defined('WIR_URL'))     define('WIR_URL', plugin_dir_url(WIR_FILE));
if (!defined('WIR_VERSION')) define('WIR_VERSION', '1.0.0');

/**
 * Manual includes (no Composer / no autoloader).
 * Keep interface first, then core classes, then integrations.
 */
require_once WIR_DIR . 'includes/integrations/interface-wir-integration.php';

// Core classes
require_once WIR_DIR . 'includes/class-wir-plugin.php';
require_once WIR_DIR . 'includes/class-wir-cpt.php';
require_once WIR_DIR . 'includes/class-wir-assets.php';
require_once WIR_DIR . 'includes/class-wir-ajax.php';
require_once WIR_DIR . 'includes/class-wir-admin.php';
require_once WIR_DIR . 'includes/class-wir-replies.php';

// Integrations
require_once WIR_DIR . 'includes/integrations/class-wir-integration-woocommerce.php';
require_once WIR_DIR . 'includes/integrations/class-wir-integration-generic.php';

/**
 * Load text domain early so strings are translatable in hooks.
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain('wp-instant-requests', false, basename(WIR_DIR) . '/languages');

    // Boot the plugin after translations are available.
    if (class_exists('WIR_Plugin')) {
        WIR_Plugin::instance();
    }
});

/**
 * Activation: register CPT, seed defaults, flush rewrites.
 */
register_activation_hook(WIR_FILE, function () {
    if (class_exists('WIR_CPT')) {
        WIR_CPT::register();
    }
    // Seed defaults only once.
    if (!get_option('wir_settings')) {
        add_option('wir_settings', [
            'button_text' => __('Ask about this item', 'wp-instant-requests'),
            'side'        => 'right',          // left|right
            'accent'      => '#2563eb',        // CSS accent color
            'topics'      => "General Inquiry\nRequest a Quote\nAvailability\nShipping",
            'notify'      => 'yes',            // send admin email on new request
            'gdpr_label'  => __('I agree to be contacted about this request.', 'wp-instant-requests'),
            'enabled'     => 'yes',            // master switch
            'show_on'     => 'product',        // product|single|both
        ]);
    }
    flush_rewrite_rules(false);
});

/**
 * Deactivation: flush only (no data removal).
 */
register_deactivation_hook(WIR_FILE, function () {
    flush_rewrite_rules(false);
});