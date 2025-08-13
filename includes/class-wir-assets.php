<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles frontend assets + markup injection.
 * Renamed from UR_* to WIR_* and fixed inline CSS building (no ternary inside string).
 */
class WIR_Assets {
	/** Enqueue only where integration matches and plugin enabled. */
	public static function frontend() {
		$o = WIR_Plugin::settings();
		if ( ( $o['enabled'] ?? 'no' ) !== 'yes' ) {
			return;
		}

		$integration = WIR_Plugin::current_integration();
		if ( ! $integration || ! $integration->should_render() ) {
			return;
		}

		// CSS & JS (handles renamed to wir-*)
		wp_enqueue_style(
			'wir-frontend',
			plugins_url( '../assets/css/frontend.css', __FILE__ ),
			array(),
			WIR_Plugin::VERSION
		);
		wp_enqueue_script(
			'wir-frontend',
			plugins_url( '../assets/js/frontend.js', __FILE__ ),
			array( 'jquery' ),
			WIR_Plugin::VERSION,
			true
		);

		// Accent + side as inline CSS (build safely)
		$side    = ( $o['side'] === 'left' ) ? 'left' : 'right';
		$accent  = $o['accent'] ? sanitize_hex_color( $o['accent'] ) : '#2563eb';
		$inline  = ".wir-fab{background:{$accent};}";
		$inline .= ( $side === 'left' ) ? '.wir-fab{left:24px}' : '.wir-fab{right:24px}';
		wp_add_inline_style( 'wir-frontend', $inline );

		// Localize data (globals + labels)
		$product = $integration->get_context();
		$data    = array(
			'ajax'          => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( WIR_Plugin::NONCE ),
			'user'          => is_user_logged_in() ? wp_get_current_user()->display_name : '',
			'email'         => is_user_logged_in() ? wp_get_current_user()->user_email : '',
			'topics'        => self::topics(),
			'pid'           => $product['id'] ?? 0,
			'title'         => $product['title'] ?? '',
			'button'        => $o['button_text'] ?? '',
			'gdpr'          => $o['gdpr_label'] ?? '',
			'i18n_required' => __( 'Please fill required fields.', 'wp-instant-requests' ),
			'i18n_consent'  => __( 'Please confirm consent.', 'wp-instant-requests' ),
			'i18n_sent'     => __( 'Request sent. Thank you!', 'wp-instant-requests' ),
			'i18n_limit'    => __( 'Message too long. Please use 2000 characters or fewer.', 'wp-instant-requests' ),
		);

		if ( ! empty( $o['recaptcha_site'] ) ) {
			$data['recaptcha'] = $o['recaptcha_site'];
			wp_enqueue_script(
				'wir-recaptcha',
				'https://www.google.com/recaptcha/api.js?render=explicit',
				array(),
				null,
				true
			);
		}

		wp_localize_script( 'wir-frontend', 'WIRData', $data );

		// Floating button hook (integration decides placement if needed).
		add_action( 'wp_footer', array( __CLASS__, 'fab' ) );
	}

	/** Floating Action Button HTML (once). */
	public static function fab() {
		$o           = WIR_Plugin::settings();
		$integration = WIR_Plugin::current_integration();
		if ( ! $integration || ! $integration->should_render() ) {
			return;
		}

		echo '<button type="button" class="wir-fab" id="wir-open" aria-haspopup="dialog" aria-controls="wir-modal">
                <span aria-hidden="true">💬</span> <span class="wir-label">' . esc_html( $o['button_text'] ) . '</span>
              </button>';
	}

	/** Modal HTML (once). */
	public static function modal() {
		$o           = WIR_Plugin::settings();
		$integration = WIR_Plugin::current_integration();
		if ( ! $integration || ! $integration->should_render() ) {
			return;
		}

		$ctx = $integration->get_context();
		?>
		<div class="wir-backdrop" id="wir-backdrop"></div>
		<div class="wir-modal" id="wir-modal" role="dialog" aria-modal="true" aria-labelledby="wir-title">
			<div class="wir-card">
				<div class="wir-badge" style="margin-bottom:10px;">🛒 <?php echo esc_html( $ctx['title'] ?? '' ); ?></div>
				<h2 id="wir-title" style="margin:0 0 8px;font-size:20px;font-weight:800;"><?php esc_html_e( 'Send a quick request', 'wp-instant-requests' ); ?></h2>
				<p style="margin-top:0;color:#6b7280;"><?php esc_html_e( 'We’ll get back to you via email.', 'wp-instant-requests' ); ?></p>

				<div class="wir-row">
					<div class="col">
						<label class="wir-label"><?php esc_html_e( 'Your Name', 'wp-instant-requests' ); ?></label>
						<input class="wir-input" id="wir-name" type="text" placeholder="<?php esc_attr_e( 'John Doe', 'wp-instant-requests' ); ?>" />
					</div>
					<div class="col">
						<label class="wir-label"><?php esc_html_e( 'Your Email', 'wp-instant-requests' ); ?></label>
						<input class="wir-input" id="wir-email" type="email" placeholder="you@example.com" />
					</div>
				</div>

				<label class="wir-label" style="margin-top:10px;"><?php esc_html_e( 'Topic', 'wp-instant-requests' ); ?></label>
				<select class="wir-select" id="wir-topic"></select>

                               <label class="wir-label" style="margin-top:10px;"><?php esc_html_e( 'Message', 'wp-instant-requests' ); ?></label>
                               <textarea class="wir-textarea" id="wir-message" rows="5" maxlength="2000" placeholder="<?php esc_attr_e( 'Type your question or request…', 'wp-instant-requests' ); ?>"></textarea>

                               <input type="text" id="wir-hp" autocomplete="off" tabindex="-1" style="display:none" aria-hidden="true" />

                               <label style="display:flex;gap:8px;align-items:flex-start;margin-top:10px;">
                                        <input type="checkbox" id="wir-gdpr" />
                                        <span id="wir-gdpr-label"><?php echo esc_html( $o['gdpr_label'] ?? '' ); ?></span>
                               </label>

				<div class="wir-actions">
					<button class="wir-btn wir-btn-secondary" id="wir-cancel"><?php esc_html_e( 'Cancel', 'wp-instant-requests' ); ?></button>
					<button class="wir-btn wir-btn-primary" id="wir-send" style="background:<?php echo esc_attr( $o['accent'] ?? '#2563eb' ); ?>"><?php esc_html_e( 'Send', 'wp-instant-requests' ); ?></button>
				</div>

				<div id="wir-status" style="margin-top:10px;font-weight:600;"></div>
			</div>
		</div>
		<?php
	}

	/** Parse topics into array. */
	private static function topics() {
		$t   = (string) ( WIR_Plugin::settings()['topics'] ?? '' );
		$arr = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $t ) ) );
		return array_values( array_unique( $arr ) );
	}
}