<?php
defined( 'ABSPATH' ) || exit;

/** Metabox + save for admin replies. */
class WIR_Replies {
	public static function metabox() {
		add_meta_box( 'wir_details', __( 'Request Details & Reply', 'wp-instant-requests' ), array( __CLASS__, 'render' ), WIR_Plugin::CPT, 'normal', 'high' );
	}

	public static function render( $post ) {
		if ( ! current_user_can( 'edit_wir_request', $post->ID ) ) {
			echo esc_html__( 'You do not have permission.', 'wp-instant-requests' );
			return;
		}
		wp_nonce_field( WIR_Plugin::NONCE, WIR_Plugin::NONCE );
		$name  = get_post_meta( $post->ID, '_wir_name', true );
		$email = get_post_meta( $post->ID, '_wir_email', true );
		$topic = get_post_meta( $post->ID, '_wir_topic', true );
		$pid   = (int) get_post_meta( $post->ID, '_wir_product_id', true );
		$last  = get_post_meta( $post->ID, '_wir_last_reply', true );
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'From', 'wp-instant-requests' ); ?></th>
				<td><?php echo esc_html( $name ); ?> — <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Topic', 'wp-instant-requests' ); ?></th>
				<td><?php echo esc_html( $topic ?: '-' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Object', 'wp-instant-requests' ); ?></th>
				<td>
					<?php if ( $pid ) : ?>
						<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a>
						&nbsp;|&nbsp;<a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'wp-instant-requests' ); ?></a>
						<?php
					else :
						echo '-';
endif;
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Reply to user', 'wp-instant-requests' ); ?></th>
				<td>
					<textarea name="wir_reply" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Type your reply to the user…', 'wp-instant-requests' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Sends an email to the user and stores the reply on this request.', 'wp-instant-requests' ); ?></p>
					<?php if ( $last ) : ?>
						<p><strong><?php esc_html_e( 'Last Reply:', 'wp-instant-requests' ); ?></strong> <?php echo esc_html( $last ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST[ WIR_Plugin::NONCE ] ) || ! wp_verify_nonce( $_POST[ WIR_Plugin::NONCE ], WIR_Plugin::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_wir_request', $post_id ) ) {
			return;
		}

		$reply = isset( $_POST['wir_reply'] ) ? wp_kses_post( wp_unslash( $_POST['wir_reply'] ) ) : '';
		if ( ! $reply ) {
			return;
		}

		$email   = get_post_meta( $post_id, '_wir_email', true );
		$subject = sprintf( __( 'Reply: %s', 'wp-instant-requests' ), get_the_title( $post_id ) );
		$body    = sprintf( "%s\n\n---\n%s", wp_strip_all_tags( $reply ), home_url() );
		if ( $email && is_email( $email ) ) {
			wp_mail( $email, $subject, $body );
		}

		update_post_meta( $post_id, '_wir_last_reply', wp_strip_all_tags( $reply ) );
	}
}
