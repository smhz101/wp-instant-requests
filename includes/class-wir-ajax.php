<?php
defined( 'ABSPATH' ) || exit;

/** AJAX handler to save request + notify admin. */
class WIR_Ajax {
	public static function submit() {
		check_ajax_referer( WIR_Plugin::NONCE, 'nonce' );

		$pid   = isset( $_POST['pid'] ) ? absint( $_POST['pid'] ) : 0;
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$msg   = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';
		$gdpr  = isset( $_POST['gdpr'] ) && $_POST['gdpr'] === '1';

		if ( ! $name || ! $email || ! is_email( $email ) || ! $msg ) {
			wp_send_json_error( __( 'Missing or invalid fields.', 'wp-instant-requests' ), 400 );
		}

		if ( ! $gdpr ) {
			wp_send_json_error( __( 'Consent is required.', 'wp-instant-requests' ), 400 );
		}

		if ( strlen( $msg ) > 2000 ) {
			wp_send_json_error( __( 'Message too long.', 'wp-instant-requests' ), 400 );
		}

		$title   = sprintf( __( 'Request from %1$s on #%2$d', 'wp-instant-requests' ), $name, $pid );
		$post_id = wp_insert_post(
			array(
				'post_type'    => WIR_Plugin::CPT,
				'post_title'   => $title,
				'post_content' => $msg,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id() ?: 0,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( $post_id->get_error_message(), 500 );
		}

		update_post_meta( $post_id, '_wir_name', $name );
		update_post_meta( $post_id, '_wir_email', $email );
		update_post_meta( $post_id, '_wir_topic', $topic );
		update_post_meta( $post_id, '_wir_product_id', $pid );
		update_post_meta( $post_id, '_wir_consent', $gdpr ? 'yes' : 'no' );
		update_post_meta(
			$post_id,
			'_wir_thread',
			array(
				array(
					'type'    => 'user',
					'message' => wp_strip_all_tags( $msg ),
					'time'    => time(),
				),
			)
		);
               update_post_meta( $post_id, '_wir_status', 'unread' );

		// Optional email notify.
		$o = WIR_Plugin::settings();

		if ( ! empty( $o['recaptcha_secret'] ) ) {
			$token = isset( $_POST['g_recaptcha_response'] ) ? sanitize_text_field( wp_unslash( $_POST['g_recaptcha_response'] ) ) : '';
			if ( ! $token ) {
				wp_send_json_error( __( 'Captcha missing.', 'wp-instant-requests' ), 400 );
			}
			$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$resp      = wp_safe_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'timeout' => 8,
					'body'    => array(
						'secret'   => $o['recaptcha_secret'],
						'response' => $token,
						'remoteip' => $remote_ip,
					),
				)
			);
			$ok        = false;
			if ( ! is_wp_error( $resp ) ) {
				$body = json_decode( wp_remote_retrieve_body( $resp ), true );
				$ok   = ! empty( $body['success'] );
			}
			if ( ! $ok ) {
				wp_send_json_error( __( 'Captcha failed.', 'wp-instant-requests' ), 400 );
			}
		}

		if ( $o['notify'] === 'yes' ) {
			$to      = get_option( 'admin_email' );
			$subject = sprintf( '[%s] %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), __( 'New Request', 'wp-instant-requests' ) );
			$body    = sprintf(
				"Name: %s\nEmail: %s\nTopic: %s\nObject ID: %d\n\nMessage:\n%s\n\nView: %s",
				$name,
				$email,
				$topic ?: '-',
				$pid,
				wp_strip_all_tags( $msg ),
				admin_url( 'post.php?post=' . $post_id . '&action=edit' )
			);
			wp_mail( $to, $subject, $body );
		}

		do_action(
			'wir_request_created',
			$post_id,
			array(
				'name'       => $name,
				'email'      => $email,
				'topic'      => $topic,
				'product_id' => $pid,
				'message'    => wp_strip_all_tags( $msg ),
			)
		);

		wp_send_json_success( array( 'id' => $post_id ) );
	}
}
