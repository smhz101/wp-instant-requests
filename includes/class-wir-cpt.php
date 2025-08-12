<?php
defined('ABSPATH') || exit;

/** Registers CPT to store requests. */
class WIR_CPT {
    public static function register() {
        $labels = [
            'name'          => __('Requests', 'wp-instant-requests'),
            'singular_name' => __('Request', 'wp-instant-requests'),
            'menu_name'     => __('Requests', 'wp-instant-requests'),
            'add_new_item'  => __('Add New Request', 'wp-instant-requests'),
            'edit_item'     => __('View & Reply', 'wp-instant-requests'),
            'search_items'  => __('Search Requests', 'wp-instant-requests'),
        ];
        register_post_type('wir_request', [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capability_type' => ['wir_request', 'wir_requests'],
            'map_meta_cap' => true,
            'supports' => ['title', 'editor', 'author'],
        ]);

        // Ensure administrators can manage.
        $role = get_role('administrator');
        if ($role) {
            $caps = [
                'read_wir_request','read_private_wir_requests','edit_wir_request','edit_wir_requests','edit_others_wir_requests',
                'publish_wir_requests','delete_wir_request','delete_wir_requests','delete_private_wir_requests','delete_published_wir_requests','delete_others_wir_requests'
            ];
            foreach ($caps as $c) { if (!$role->has_cap($c)) $role->add_cap($c); }
        }
    }
}
