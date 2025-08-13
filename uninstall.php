<?php
/**
 * Clean removal (keeps posts by default; toggle via filter).
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$delete_posts = apply_filters( 'wir_uninstall_delete_posts', false );
if ( $delete_posts ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'wir_request',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	if ( $q->have_posts() ) {
		foreach ( $q->posts as $pid ) {
			wp_delete_post( $pid, true );
		}
	}
}
delete_option( 'wir_settings' );
