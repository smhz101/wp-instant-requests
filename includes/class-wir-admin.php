<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin menus, custom "All Requests" page (mailbox UI), settings, and AJAX for replies.
 * Prefix: WIR_
 * Text domain: wp-instant-requests
 */
class WIR_Admin {

	public static function ajax_get_header() {
		check_ajax_referer( 'wir_admin_nonce', 'nonce' );
		$id = absint( $_POST['request_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( 'Invalid', 400 );
		}

		$pid      = (int) get_post_meta( $id, '_wir_product_id', true );
		$assignee = (int) get_post_meta( $id, '_wir_assigned', true );
		$u        = $assignee ? get_user_by( 'id', $assignee ) : null;

		wp_send_json_success(
			array(
				'name'        => get_post_meta( $id, '_wir_name', true ) ?: __( 'Guest', 'wp-instant-requests' ),
				'email'       => get_post_meta( $id, '_wir_email', true ),
				'topic'       => get_post_meta( $id, '_wir_topic', true ),
				'product'     => $pid ? get_the_title( $pid ) : '',
				'product_url' => $pid ? get_permalink( $pid ) : '',
				'status'      => get_post_meta( $id, '_wir_status', true ) ?: 'open',
				'assignee'    => $u ? $u->display_name : '',
				'content'     => wp_strip_all_tags( get_post_field( 'post_content', $id ) ),
			)
		);
	}

	private static function load_thread( $id ) {
		$items = get_post_meta( $id, '_wir_thread', true );
		return is_array( $items ) ? $items : array();
	}

       private static function save_thread( $id, $items ) {
               update_post_meta( $id, '_wir_thread', array_values( $items ) );
       }

       private static function render_status_badge( $status ) {
               $colors = array(
                       'open'    => '#2563eb',
                       'replied' => '#059669',
                       'closed'  => '#6b7280',
               );
               $icons  = array(
                       'open'    => 'email-alt',
                       'replied' => 'yes',
                       'closed'  => 'no-alt',
               );
               $color  = $colors[ $status ] ?? '#2563eb';
               $icon   = $icons[ $status ] ?? 'email-alt';

               return sprintf(
                       '<span class="wir-badge wir-status-badge" style="background:%1$s1a;color:%1$s"><span class="dashicons dashicons-%2$s"></span>%3$s</span>',
                       esc_attr( $color ),
                       esc_attr( $icon ),
                       esc_html( $status )
               );
       }

	public static function ajax_get_thread() {
		check_ajax_referer( 'wir_admin_nonce', 'nonce' );

		$id = absint( $_POST['request_id'] ?? 0 );

		if ( ! $id ) {
			wp_send_json_error( 'Invalid', 400 );
		}

		$items = self::load_thread( $id );
		if ( ! $items ) {
			$content = wp_strip_all_tags( get_post_field( 'post_content', $id ) );
			if ( $content !== '' ) {
				$items = array(
					array(
						'type'    => 'user',
						'message' => $content,
						'time'    => get_post_time( 'U', true, $id ),
					),
				);
				self::save_thread( $id, $items );
			}
		}
		wp_send_json_success( array( 'items' => $items ) );
	}

	public static function ajax_save_note() {

		if ( ! current_user_can( 'edit_wir_requests' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'denied', 403 );
		}

		check_ajax_referer( 'wir_admin_nonce', 'nonce' );

		$id   = absint( $_POST['request_id'] ?? 0 );
		$note = wp_kses_post( wp_unslash( $_POST['note'] ?? '' ) );

		if ( ! $id || $note === '' ) {
			wp_send_json_error( 'Invalid', 400 );
		}

		$items = self::load_thread( $id );

		$items[] = array(
			'type'    => 'note',
			'message' => $note,
			'time'    => time(),
		);

		self::save_thread( $id, $items );

		// Also store last notes text (optional separate meta)
		$notes = get_post_meta( $id, '_wir_notes', true );
		if ( ! is_array( $notes ) ) {
			$notes = array();
		}

		$notes[] = array(
			'user' => get_current_user_id(),
			'note' => wp_strip_all_tags( $note ),
			'time' => time(),
		);

		update_post_meta( $id, '_wir_notes', $notes );

		wp_send_json_success( array( 'items' => $items ) );
	}

	public static function ajax_toggle_status() {
		if ( ! current_user_can( 'edit_wir_requests' ) ) {
			wp_send_json_error( 'denied', 403 );
		}
		check_ajax_referer( 'wir_admin_nonce', 'nonce' );
		$id   = absint( $_POST['request_id'] ?? 0 );
		$curr = get_post_meta( $id, '_wir_status', true ) ?: 'open';
		$next = ( $curr === 'open' ) ? 'closed' : ( ( $curr === 'closed' ) ? 'open' : 'closed' );
		update_post_meta( $id, '_wir_status', $next );
		wp_send_json_success( array( 'status' => $next ) );
	}

       public static function ajax_assign_me() {
               if ( ! current_user_can( 'edit_wir_requests' ) ) {
                               wp_send_json_error( 'denied', 403 );
               }
                       check_ajax_referer( 'wir_admin_nonce', 'nonce' );
                       $id  = absint( $_POST['request_id'] ?? 0 );
                       $uid = get_current_user_id();
                       update_post_meta( $id, '_wir_assigned', $uid );
                       $u = get_user_by( 'id', $uid );
                       wp_send_json_success( array( 'name' => $u ? $u->display_name : '' ) );
       }

       public static function ajax_mark_read() {
               if ( ! current_user_can( 'edit_wir_requests' ) ) {
                       wp_send_json_error( 'denied', 403 );
               }
               check_ajax_referer( 'wir_admin_nonce', 'nonce' );
               $id = absint( $_POST['request_id'] ?? 0 );
               if ( ! $id ) {
                       wp_send_json_error( 'Invalid', 400 );
               }
               if ( 'unread' === get_post_meta( $id, '_wir_status', true ) ) {
                       update_post_meta( $id, '_wir_status', 'open' );
               }
               wp_send_json_success( array( 'unread' => self::unread_count() ) );
       }

       public static function ajax_toggle_pin() {
               if ( ! current_user_can( 'edit_wir_requests' ) ) {
                       wp_send_json_error( 'denied', 403 );
               }
               check_ajax_referer( 'wir_admin_nonce', 'nonce' );
               $id = absint( $_POST['request_id'] ?? 0 );
               if ( ! $id ) {
                       wp_send_json_error( 'Invalid', 400 );
               }
               $curr = get_post_meta( $id, '_wir_pinned', true );
               if ( $curr ) {
                       delete_post_meta( $id, '_wir_pinned' );
               } else {
                       update_post_meta( $id, '_wir_pinned', 1 );
               }
               wp_send_json_success( array( 'pinned' => empty( $curr ) ) );
       }

       public static function ajax_toggle_star() {
               if ( ! current_user_can( 'edit_wir_requests' ) ) {
                       wp_send_json_error( 'denied', 403 );
               }
               check_ajax_referer( 'wir_admin_nonce', 'nonce' );
               $id = absint( $_POST['request_id'] ?? 0 );
               if ( ! $id ) {
                       wp_send_json_error( 'Invalid', 400 );
               }
               $curr = get_post_meta( $id, '_wir_starred', true );
               if ( $curr ) {
                       delete_post_meta( $id, '_wir_starred' );
               } else {
                       update_post_meta( $id, '_wir_starred', 1 );
               }
               wp_send_json_success( array( 'starred' => empty( $curr ) ) );
       }

       private static function render_list_item( $id ) {
                       $pid           = (int) get_post_meta( $id, '_wir_product_id', true );
                       $topic         = get_post_meta( $id, '_wir_topic', true );
                       $name          = get_post_meta( $id, '_wir_name', true );
                       $email         = get_post_meta( $id, '_wir_email', true );
                       $msg           = get_post_field( 'post_content', $id );
                       $status        = get_post_meta( $id, '_wir_status', true ) ?: 'open';
                       $assignee_id   = (int) get_post_meta( $id, '_wir_assigned', true );
                       $assignee_user = $assignee_id ? get_user_by( 'id', $assignee_id ) : null;
                       $assignee_name = $assignee_user ? $assignee_user->display_name : '';
                       $pinned        = (int) get_post_meta( $id, '_wir_pinned', true );
                       $starred       = (int) get_post_meta( $id, '_wir_starred', true );
                       $excerpt       = wp_html_excerpt( wp_strip_all_tags( $msg ), 140, '…' );
                       $title         = get_the_title( $pid );
                       $classes       = 'wir-item';
                       if ( 'unread' === $status ) {
                               $classes .= ' is-unread';
                       }
                       if ( $pinned ) {
                               $classes .= ' is-pinned';
                       }
                       if ( $starred ) {
                               $classes .= ' is-starred';
                       }
                       $time = get_post_time( 'U', true, $id );

                       ob_start();
               ?>
                               <div class="<?php echo esc_attr( $classes ); ?>" data-id="<?php echo esc_attr( $id ); ?>" data-time="<?php echo esc_attr( $time ); ?>" data-name="<?php echo esc_attr( $name ); ?>" data-email="<?php echo esc_attr( $email ); ?>" data-topic="<?php echo esc_attr( $topic ); ?>" data-object-id="<?php echo esc_attr( $pid ); ?>" data-object-title="<?php echo esc_attr( $title ); ?>" data-status="<?php echo esc_attr( $status ); ?>" data-assignee_name="<?php echo esc_attr( $assignee_name ); ?>" data-content="<?php echo esc_attr( wp_strip_all_tags( $msg ) ); ?>">
                                               <div class="wir-item-head">
                                                               <strong class="wir-item-name"><?php echo esc_html( $name ?: __( 'Guest', 'wp-instant-requests' ) ); ?></strong>
                                                               <div class="wir-item-head-right">
                                                                       <span class="wir-pin dashicons dashicons-admin-post" title="<?php esc_attr_e( 'Pin', 'wp-instant-requests' ); ?>"></span>
                                                                       <span class="wir-star dashicons <?php echo $starred ? 'dashicons-star-filled' : 'dashicons-star-empty'; ?>" title="<?php esc_attr_e( 'Star', 'wp-instant-requests' ); ?>"></span>
                                                                       <span class="wir-item-time"><?php echo esc_html( get_the_date( 'M j, Y H:i', $id ) ); ?></span>
                                                               </div>
                                               </div>
                                                 <div class="wir-item-sub">
                                                        <?php if ( $topic ) : ?>
                                                                                <span class="wir-badge"><?php echo esc_html( $topic ); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ( $title ) : ?>
                                                                                <span class="wir-dim">· <?php echo esc_html( $title ); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ( 'open' !== $status ) : ?>
                                                                                <?php echo self::render_status_badge( $status ); ?>
                                                        <?php endif; ?>
                                                 </div>
                                               <div class="wir-item-excerpt"><?php echo esc_html( $excerpt ); ?></div>
                               </div>
                               <?php
                               return ob_get_clean();
       }

       private static function unread_count() {
               $q = new WP_Query(
                       array(
                               'post_type'      => WIR_Plugin::CPT,
                               'post_status'    => 'publish',
                               'meta_key'       => '_wir_status',
                               'meta_value'     => 'unread',
                               'fields'         => 'ids',
                               'posts_per_page' => 1,
                               'no_found_rows'  => false,
                       )
               );
               $count = (int) $q->found_posts;
               wp_reset_postdata();
               return $count;
       }

	public static function ajax_check_new() {
		if ( ! current_user_can( 'edit_wir_requests' ) ) {
				wp_send_json_error( 'denied', 403 );
		}
			check_ajax_referer( 'wir_admin_nonce', 'nonce' );
			$last_id = absint( $_POST['last_id'] ?? 0 );

               $q      = new WP_Query(
                               array(
                                       'post_type'      => WIR_Plugin::CPT,
                                       'posts_per_page' => 20,
                                       'meta_key'       => '_wir_pinned',
                                       'orderby'        => array(
                                               'meta_value_num' => 'DESC',
                                               'date'           => 'DESC',
                                       ),
                               )
                       );
                       $items  = array();
                       $max_id = $last_id;
               while ( $q->have_posts() ) {
                               $q->the_post();
                               $id = get_the_ID();
                       if ( $id > $last_id ) {
                               if ( 'unread' !== get_post_meta( $id, '_wir_status', true ) ) {
                                       update_post_meta( $id, '_wir_status', 'unread' );
                               }
                               $items[] = self::render_list_item( $id );
                               if ( $id > $max_id ) {
                                               $max_id = $id;
                               }
                       }
               }
               wp_reset_postdata();

               $unread = self::unread_count();

               wp_send_json_success(
                       array(
                               'items'   => $items,
                               'last_id' => $max_id,
                               'unread'  => $unread,
                       )
               );
       }

	/** Register menus (Top: Requests; Subs: All Requests, Settings) */
       public static function menus() {
               $unread = self::unread_count();
               $title  = __( 'Requests', 'wp-instant-requests' );

		if ( $unread > 0 ) {
			$title .= sprintf(
				'<span class="update-plugins count-%1$d"><span class="plugin-count">%1$d</span></span>',
				$unread
			);
		}

		add_menu_page(
			__( 'Requests', 'wp-instant-requests' ),
			$title,
			'edit_wir_requests',
			'wir',
			array( __CLASS__, 'page_requests' ),
			'dashicons-email-alt2',
			56
		);

		add_submenu_page(
			'wir',
			__( 'All Requests', 'wp-instant-requests' ),
			__( 'All Requests', 'wp-instant-requests' ),
			'edit_wir_requests',
			'wir', // points to same as top-level
			array( __CLASS__, 'page_requests' )
		);

		add_submenu_page(
			'wir',
			__( 'Settings', 'wp-instant-requests' ),
			__( 'Settings', 'wp-instant-requests' ),
			'manage_options',
			'wir-settings',
			array( __CLASS__, 'settings_page' )
		);

		// Enqueue admin assets on our pages only
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		// AJAX: admin reply
		add_action( 'wp_ajax_wir_admin_reply', array( __CLASS__, 'ajax_admin_reply' ) );
	}

	private static function enqueue_menu_refresh() {
		add_action( 'admin_footer', array( __CLASS__, 'print_menu_refresh_script' ) );
	}

	public static function refresh_menu_badge() {
		self::enqueue_menu_refresh();
	}

	public static function transition_menu_badge( $new, $old, $post ) {
		if ( 'wir_request' !== $post->post_type ) {
						return;
		}
		self::enqueue_menu_refresh();
	}

       public static function print_menu_refresh_script() {
               $unread = self::unread_count();
               ?>
		<script>
		(function(){
			if ( typeof updateUnreadBadge === 'function' ) {
				updateUnreadBadge( <?php echo $unread; ?> );
				return;
			}
			
			let menu  = document.querySelector('#toplevel_page_wir .wp-menu-name');
			
			if ( ! menu ) { return; }
			
			let badge = menu.querySelector('.update-plugins');
			let count = <?php echo $unread; ?>;
		
			if ( count > 0 ) {
				if ( ! badge ) {
					badge = document.createElement('span');
					badge.className = 'update-plugins count-' + count;
					badge.innerHTML = '<span class="plugin-count">' + count + '</span>';
					menu.appendChild(badge);
				} else {
					badge.className = 'update-plugins count-' + count;
					badge.querySelector('.plugin-count').textContent = count;
				}
			} else if ( badge ) {
				badge.remove();
			}
		})();
		</script>
		<?php
	}

	/** Load CSS/JS only on our pages */
	public static function enqueue_admin_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( ! in_array( $page, array( 'wir', 'wir-settings' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'wir-admin', WIR_URL . 'assets/css/admin.css', array(), WIR_VERSION );
		wp_enqueue_script( 'wir-admin', WIR_URL . 'assets/js/admin.js', array( 'jquery' ), WIR_VERSION, true );

		wp_localize_script(
			'wir-admin',
			'WIRAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wir_admin_nonce' ),
				'i18n'  => array(
					'reply_sent'  => __( 'Reply sent successfully.', 'wp-instant-requests' ),
					'error'       => __( 'Something went wrong.', 'wp-instant-requests' ),
					'required'    => __( 'Please type a reply message.', 'wp-instant-requests' ),
					'no_messages' => __( 'No messages yet.', 'wp-instant-requests' ),
					'assignee'    => __( 'Assignee', 'wp-instant-requests' ),
					'saved'       => __( 'Saved.', 'wp-instant-requests' ),
				),
			)
		);
	}

	/**
	 * Custom "All Requests" mailbox page (div-based).
	 * Features:
	 * - Search (by keyword)
	 * - Filter by topic (optional)
	 * - Two-pane layout: list (left) + preview & reply (right)
	 */
	public static function page_requests() {
		if ( ! current_user_can( 'edit_wir_requests' ) ) {
			wp_die( __( 'You do not have permission.', 'wp-instant-requests' ) );
		}

		$count = wp_count_posts( 'wir_request' );
		$open  = isset( $count->open ) ? (int) $count->open : 0;
		
		update_option( 'wir_last_seen_open', $open );
		self::enqueue_menu_refresh();

		// Inputs
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$topic  = isset( $_GET['topic'] ) ? sanitize_text_field( wp_unslash( $_GET['topic'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		// Build query
               $args = array(
                       'post_type'      => 'wir_request',
                       'post_status'    => 'any',
                       'posts_per_page' => 20,
                       'paged'          => $paged,
                       's'              => $search,
                       'meta_key'       => '_wir_pinned',
                       'orderby'        => array(
                               'meta_value_num' => 'DESC',
                               'date'           => 'DESC',
                       ),
               );
		if ( $topic !== '' ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_wir_topic',
					'value'   => $topic,
					'compare' => '=',
				),
			);
		}
		if ( ! empty( $_GET['status'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_wir_status',
				'value'   => sanitize_text_field( $_GET['status'] ),
				'compare' => '=',
			);
		}
		if ( ! empty( $_GET['mine'] ) && get_current_user_id() ) {
			$args['meta_query'][] = array(
				'key'     => '_wir_assigned',
				'value'   => get_current_user_id(),
				'compare' => '=',
			);
		}

		if ( empty( $args['meta_query'] ) ) {
			unset( $args['meta_query'] );
		}

		$q = new WP_Query( $args );

		// Topics list for filter (from settings)
		// $topics = array_values(array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) WIR_Plugin::settings()['topics'])))));
		// $status   = get_post_meta(get_the_ID(), '_wir_status', true) ?: 'open';
		// $assignee = (int) get_post_meta(get_the_ID(), '_wir_assigned', true);
		// $assignee_name = $assignee ? get_user_by('id', $assignee)->display_name : '';
		// $thread = get_post_meta(get_the_ID(), '_wir_thread', true); // array

		// Topics list for filter (from settings)
		$topics = array_values(
			array_unique(
				array_filter(
					array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) WIR_Plugin::settings()['topics'] ) )
				)
			)
		);
		?>
		<div class="wir-wrap">
			<h1 class="wir-title"><?php esc_html_e( 'All Requests', 'wp-instant-requests' ); ?></h1>

			<form method="get" class="wir-toolbar">
				<input type="hidden" name="page" value="wir" />
				<div class="wir-search">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search requests…', 'wp-instant-requests' ); ?>" />
					<button class="button"><?php esc_html_e( 'Search', 'wp-instant-requests' ); ?></button>
				</div>
				<div class="wir-filters">
					<select name="topic">
						<option value=""><?php esc_html_e( 'All topics', 'wp-instant-requests' ); ?></option>
						<?php foreach ( $topics as $_t ) : ?>
							<option value="<?php echo esc_attr( $_t ); ?>" <?php selected( $topic, $_t ); ?>><?php echo esc_html( $_t ); ?></option>
						<?php endforeach; ?>
					</select>

						<select name="status">
						<?php $status_q = sanitize_text_field( $_GET['status'] ?? '' ); ?>
						<option value=""><?php esc_html_e( 'All statuses', 'wp-instant-requests' ); ?></option>
						<?php foreach ( array( 'open', 'replied', 'closed' ) as $_s ) : ?>
						<option value="<?php echo esc_attr( $_s ); ?>" <?php selected( $status_q, $_s ); ?>><?php echo esc_html( ucfirst( $_s ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<label><input type="checkbox" name="mine" value="1" <?php checked( ! empty( $_GET['mine'] ) ); ?>> <?php esc_html_e( 'Assigned to me', 'wp-instant-requests' ); ?></label>
					<button class="button"><?php esc_html_e( 'Filter', 'wp-instant-requests' ); ?></button>

					<!-- CSV export preserves filters -->
					<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( $_GET, array( 'action' => 'wir_export_csv' ) ), admin_url( 'admin-post.php' ) ), 'wir_export_csv' ) ); ?>">
						<?php esc_html_e( 'Export CSV', 'wp-instant-requests' ); ?>
					</a>
				</div>
			</form>

			<div class="wir-mailbox">
				<aside class="wir-list">
					<div class="wir-list-inner">
                                       <?php if ( $q->have_posts() ) : ?>
                                               <?php while ( $q->have_posts() ) : $q->the_post(); ?>
                                                       <?php echo self::render_list_item( get_the_ID() ); ?>
                                               <?php endwhile; else : ?>
                                               <div class="wir-empty"><?php esc_html_e( 'No requests found.', 'wp-instant-requests' ); ?></div>
                                               <?php endif; wp_reset_postdata(); ?>
                                       </div>
	
					<?php
					$total = (int) $q->max_num_pages;
					if ( $total > 1 ) :
						?>
						<div class="wir-pagination">
						<?php
						echo paginate_links(
							array(
								'base'      => add_query_arg( array( 'paged' => '%#%' ) ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total,
								'prev_text' => '‹',
								'next_text' => '›',
							)
						);
						?>
						</div>
					<?php endif; ?>                    
				</aside>

				<section class="wir-preview">
					<div class="wir-preview-inner">
						<div class="wir-preview-empty">
							<h2><?php esc_html_e( 'Select a request', 'wp-instant-requests' ); ?></h2>
							<p><?php esc_html_e( 'Choose an item from the list to read and reply.', 'wp-instant-requests' ); ?></p>
						</div>

						<!-- <div class="wir-preview-body" style="display:none;">
							<div class="wir-preview-head">
								<div>
									<h2 class="wir-preview-name"></h2>
									<div class="wir-preview-meta">
										<a class="wir-preview-email" href="#"></a>
										<span class="wir-preview-topic"></span>
										<span class="wir-preview-sep">·</span>
										<a class="wir-preview-object" href="#" target="_blank"></a>
									</div>
								</div>
								<div class="wir-preview-actions">
									<a class="button" target="_blank" rel="noopener" href="#" id="wir-view-post"><?php esc_html_e( 'Open in editor', 'wp-instant-requests' ); ?></a>
								</div>
							</div>

							<div class="wir-preview-content"></div>

							<div class="wir-reply">
								<h3><?php // esc_html_e('Reply', 'wp-instant-requests'); ?></h3>
								<textarea id="wir-reply-text" rows="6" placeholder="<?php // esc_attr_e('Type your reply…', 'wp-instant-requests'); ?>"></textarea>
								<div class="wir-reply-actions">
									<?php // wp_nonce_field('wir_admin_nonce', 'wir_admin_nonce'); ?>
									<button class="button button-primary" id="wir-send-reply"><?php // esc_html_e('Send Reply', 'wp-instant-requests'); ?></button>
									<span class="wir-reply-status" aria-live="polite"></span>
								</div>
							</div>
						</div> -->

						<div class="wir-preview-body" style="display:none;">
							<div class="wir-preview-head">
								<div>
								<h2 class="wir-preview-name"></h2>
								<div class="wir-preview-meta">
									<a class="wir-preview-email" href="#"></a>
									<span class="wir-preview-topic"></span>
									<span class="wir-preview-sep">·</span>
									<a class="wir-preview-object" href="#" target="_blank"></a>
									<span class="wir-preview-sep">·</span>
									<span class="wir-preview-status-badge"></span>
									<span class="wir-preview-sep">·</span>
									<span class="wir-preview-assignee"></span>
								</div>
								</div>
								<div class="wir-preview-actions">
								<a class="button" target="_blank" rel="noopener" href="#" id="wir-view-post"><?php esc_html_e( 'Open in editor', 'wp-instant-requests' ); ?></a>
								<button class="button" id="wir-toggle-status"><?php esc_html_e( 'Toggle Status', 'wp-instant-requests' ); ?></button>
								<button class="button" id="wir-assign-me"><?php esc_html_e( 'Assign to me', 'wp-instant-requests' ); ?></button>
								</div>
							</div>

							<!-- Message thread -->
							<div class="wir-thread"></div>

							<div class="wir-reply">
								<h3><?php esc_html_e( 'Reply', 'wp-instant-requests' ); ?></h3>
								<textarea id="wir-reply-text" rows="6" placeholder="<?php esc_attr_e( 'Type your reply…', 'wp-instant-requests' ); ?>"></textarea>
								<div class="wir-reply-actions">
								<?php wp_nonce_field( 'wir_admin_nonce', 'wir_admin_nonce' ); ?>
								<button class="button button-primary" id="wir-send-reply"><?php esc_html_e( 'Send Reply', 'wp-instant-requests' ); ?></button>
								<span class="wir-reply-status" aria-live="polite"></span>
								</div>

								<h4 style="margin-top:12px;"><?php esc_html_e( 'Private note (not emailed)', 'wp-instant-requests' ); ?></h4>
								<textarea id="wir-note-text" rows="3" placeholder="<?php esc_attr_e( 'Add an internal note…', 'wp-instant-requests' ); ?>"></textarea>
								<div class="wir-reply-actions">
								<button class="button" id="wir-save-note"><?php esc_html_e( 'Save Note', 'wp-instant-requests' ); ?></button>
								<span class="wir-note-status" aria-live="polite"></span>
								</div>
							</div>
						</div>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	/** Settings API (unchanged) */
	public static function register_settings() {
		register_setting(
			'wir_settings',
			'wir_settings',
			function ( $input ) {
				$out                = array();
				$out['button_text'] = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '';
				$side               = isset( $input['side'] ) ? strtolower( $input['side'] ) : 'right';
				$out['side']        = in_array( $side, array( 'left', 'right' ), true ) ? $side : 'right';
				$out['accent']      = isset( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : '#2563eb';
				$out['topics']      = isset( $input['topics'] ) ? implode( "\n", array_filter( array_map( 'sanitize_text_field', array_map( 'trim', preg_split( '/\r\n|\r|\n/', $input['topics'] ) ) ) ) ) : '';
				$out['notify']      = ( ! empty( $input['notify'] ) && $input['notify'] === 'yes' ) ? 'yes' : 'no';
				$out['gdpr_label']  = isset( $input['gdpr_label'] ) ? sanitize_text_field( $input['gdpr_label'] ) : '';
				$show               = isset( $input['show_on'] ) ? strtolower( $input['show_on'] ) : 'product';
				$out['show_on']     = in_array( $show, array( 'product', 'single', 'both' ), true ) ? $show : 'product';
				$out['enabled']     = ( ! empty( $input['enabled'] ) && $input['enabled'] === 'yes' ) ? 'yes' : 'no';

				$out['tpl_subject']      = isset( $input['tpl_subject'] ) ? sanitize_text_field( $input['tpl_subject'] ) : '';
				$out['tpl_body']         = isset( $input['tpl_body'] ) ? wp_kses_post( $input['tpl_body'] ) : '';
				$out['recaptcha_site']   = isset( $input['recaptcha_site'] ) ? sanitize_text_field( $input['recaptcha_site'] ) : '';
				$out['recaptcha_secret'] = isset( $input['recaptcha_secret'] ) ? sanitize_text_field( $input['recaptcha_secret'] ) : '';

				return $out;
			}
		);
	}

	/** WPML/Polylang: register admin strings for translation */
	public static function register_strings_for_translation( $old, $new ) {
		if ( function_exists( 'icl_register_string' ) && is_array( $new ) ) {
			icl_register_string( 'wp-instant-requests', 'button_text', $new['button_text'] ?? '' );
			icl_register_string( 'wp-instant-requests', 'gdpr_label', $new['gdpr_label'] ?? '' );
			icl_register_string( 'wp-instant-requests', 'topics', $new['topics'] ?? '' );
		}
		if ( function_exists( 'pll_register_string' ) && is_array( $new ) ) {
			pll_register_string( 'WIR Button Text', $new['button_text'] ?? '', 'WP Instant Requests', true );
			pll_register_string( 'WIR GDPR Label', $new['gdpr_label'] ?? '', 'WP Instant Requests', true );
			pll_register_string( 'WIR Topics', $new['topics'] ?? '', 'WP Instant Requests', true );
		}
	}

	/** Settings page UI (unchanged title) */
	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission.', 'wp-instant-requests' ) );
		}
		$o = WIR_Plugin::settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'wp-instant-requests' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wir_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="button_text"><?php esc_html_e( 'Floating Button Text', 'wp-instant-requests' ); ?></label></th>
						<td><input name="wir_settings[button_text]" id="button_text" type="text" value="<?php echo esc_attr( $o['button_text'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Button Side', 'wp-instant-requests' ); ?></th>
						<td>
							<select name="wir_settings[side]">
								<option value="right" <?php selected( $o['side'], 'right' ); ?>><?php esc_html_e( 'Right', 'wp-instant-requests' ); ?></option>
								<option value="left"  <?php selected( $o['side'], 'left' ); ?>><?php esc_html_e( 'Left', 'wp-instant-requests' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="accent"><?php esc_html_e( 'Accent Color', 'wp-instant-requests' ); ?></label></th>
						<td><input name="wir_settings[accent]" id="accent" type="text" value="<?php echo esc_attr( $o['accent'] ); ?>" class="regular-text" placeholder="#2563eb" /></td>
					</tr>
					<tr>
						<th><label for="topics"><?php esc_html_e( 'Topics (one per line)', 'wp-instant-requests' ); ?></label></th>
						<td><textarea name="wir_settings[topics]" id="topics" rows="6" class="large-text code"><?php echo esc_textarea( $o['topics'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Render On', 'wp-instant-requests' ); ?></th>
						<td>
							<select name="wir_settings[show_on]">
								<option value="product" <?php selected( $o['show_on'], 'product' ); ?>><?php esc_html_e( 'Products (WooCommerce)', 'wp-instant-requests' ); ?></option>
								<option value="single"  <?php selected( $o['show_on'], 'single' ); ?>><?php esc_html_e( 'Single Posts/Pages', 'wp-instant-requests' ); ?></option>
								<option value="both"    <?php selected( $o['show_on'], 'both' ); ?>><?php esc_html_e( 'Both', 'wp-instant-requests' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Email Notifications', 'wp-instant-requests' ); ?></th>
						<td><label><input type="checkbox" name="wir_settings[notify]" value="yes" <?php checked( $o['notify'], 'yes' ); ?> /> <?php esc_html_e( 'Notify admin on new request', 'wp-instant-requests' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="gdpr_label"><?php esc_html_e( 'Consent Checkbox Label', 'wp-instant-requests' ); ?></label></th>
						<td><input name="wir_settings[gdpr_label]" id="gdpr_label" type="text" value="<?php echo esc_attr( $o['gdpr_label'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Enabled', 'wp-instant-requests' ); ?></th>
						<td><label><input type="checkbox" name="wir_settings[enabled]" value="yes" <?php checked( $o['enabled'], 'yes' ); ?> /> <?php esc_html_e( 'Enable floating request button', 'wp-instant-requests' ); ?></label></td>
					</tr>

					<tr>
						<th><label for="tpl_subject"><?php esc_html_e( 'Email Subject Template', 'wp-instant-requests' ); ?></label></th>
						<td>
							<input name="wir_settings[tpl_subject]" id="tpl_subject" type="text"value="<?php echo esc_attr( $o['tpl_subject'] ?? 'Reply: {{product}}' ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Tokens: {{name}} {{product}} {{message}}', 'wp-instant-requests' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="tpl_body"><?php esc_html_e( 'Email Body Template', 'wp-instant-requests' ); ?></label></th>
						<td><textarea name="wir_settings[tpl_body]" id="tpl_body" rows="6" class="large-text code"><?php echo esc_textarea( $o['tpl_body'] ?? "{{message}}\n\n---\n" . home_url() ); ?></textarea></td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'reCAPTCHA (frontend)', 'wp-instant-requests' ); ?></th>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Site key', 'wp-instant-requests' ); ?>:</th>
						<td><input name="wir_settings[recaptcha_site]" type="text" value="<?php echo esc_attr( $o['recaptcha_site'] ?? '' ); ?>" class="regular-text" /></label><br></td>
					</tr>

					<tr>
						<th><label><?php esc_html_e( 'Secret key', 'wp-instant-requests' ); ?>:</th>
						<td><input name="wir_settings[recaptcha_secret]" type="text" value="<?php echo esc_attr( $o['recaptcha_secret'] ?? '' ); ?>" class="regular-text" /></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p class="description"><?php esc_html_e( 'Extensible via integrations (WooCommerce included). Add Dokan/EDD by implementing WIR_Integration.', 'wp-instant-requests' ); ?></p>
		</div>
		<?php
	}

	/**
	 * AJAX: send admin reply from custom page.
	 * Input: request_id, message, _wpnonce
	 */
	public static function ajax_admin_reply() {
		if ( ! current_user_can( 'edit_wir_requests' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-instant-requests' ), 403 );
		}
		check_ajax_referer( 'wir_admin_nonce', 'nonce' );

		$request_id = absint( $_POST['request_id'] ?? 0 );
		$message    = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );
		if ( ! $request_id || '' === trim( $message ) ) {
			wp_send_json_error( __( 'Invalid data.', 'wp-instant-requests' ), 400 );
		}
		if ( get_post_type( $request_id ) !== WIR_Plugin::CPT ) {
			wp_send_json_error( __( 'Invalid request ID.', 'wp-instant-requests' ), 400 );
		}

		// email with template
		$email      = get_post_meta( $request_id, '_wir_email', true );
		$o          = WIR_Plugin::settings();
		$product_id = (int) get_post_meta( $request_id, '_wir_product_id', true );
		$tokens     = array(
			'{{name}}'    => get_post_meta( $request_id, '_wir_name', true ),
			'{{product}}' => $product_id ? get_the_title( $product_id ) : '',
			'{{message}}' => wp_strip_all_tags( $message ),
		);
		$subject    = strtr( $o['tpl_subject'] ?? __( 'Reply: {{product}}', 'wp-instant-requests' ), $tokens );
		$bodyTpl    = $o['tpl_body'] ?? "{{message}}\n\n---\n" . home_url();
		$body       = strtr( $bodyTpl, $tokens );

		$sent = false;
		if ( $email && is_email( $email ) ) {
			$sent = wp_mail( $email, $subject, $body );
		}

		// append to thread
		$items   = self::load_thread( $request_id );
		$items[] = array(
			'type'    => 'out',
			'message' => wp_strip_all_tags( $message ),
			'time'    => time(),
			'status'  => $sent ? __( 'Email sent', 'wp-instant-requests' ) : __( 'Email NOT sent', 'wp-instant-requests' ),
		);
		self::save_thread( $request_id, $items );

		// mark status= replied
		update_post_meta( $request_id, '_wir_status', 'replied' );
		update_post_meta( $request_id, '_wir_last_reply', wp_strip_all_tags( $message ) );

		wp_send_json_success( array( 'items' => $items ) );
	}

	public static function export_csv() {
		if ( ! current_user_can( 'edit_wir_requests' ) ) {
			wp_die( 'denied' );
		}
		check_admin_referer( 'wir_export_csv' );

		// Rebuild same query from $_GET
		$args = array(
			'post_type'      => 'wir_request',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => sanitize_text_field( $_GET['s'] ?? '' ),
		);
		$meta = array();
		if ( ! empty( $_GET['topic'] ) ) {
			$meta[] = array(
				'key'     => '_wir_topic',
				'value'   => sanitize_text_field( $_GET['topic'] ),
				'compare' => '=',
			);
		}
		if ( ! empty( $_GET['status'] ) ) {
			$meta[] = array(
				'key'     => '_wir_status',
				'value'   => sanitize_text_field( $_GET['status'] ),
				'compare' => '=',
			);
		}
		if ( ! empty( $_GET['mine'] ) ) {
			$meta[] = array(
				'key'     => '_wir_assigned',
				'value'   => get_current_user_id(),
				'compare' => '=',
			);
		}
		if ( $meta ) {
			$args['meta_query'] = $meta;
		}
		$q = new WP_Query( $args );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wir-requests.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Date', 'Name', 'Email', 'Topic', 'Product', 'Status', 'Assignee', 'Message' ) );
		while ( $q->have_posts() ) {
			$q->the_post();
			$id = get_the_ID();
			fputcsv(
				$out,
				array(
					$id,
					get_the_date( 'Y-m-d H:i', $id ),
					get_post_meta( $id, '_wir_name', true ),
					get_post_meta( $id, '_wir_email', true ),
					get_post_meta( $id, '_wir_topic', true ),
					get_the_title( (int) get_post_meta( $id, '_wir_product_id', true ) ),
					get_post_meta( $id, '_wir_status', true ) ?: 'open',
					( function ( $uid ) {
						$u = get_user_by( 'id', (int) $uid );
						return $u ? $u->display_name : ''; } )( get_post_meta( $id, '_wir_assigned', true ) ),
					wp_strip_all_tags( get_post_field( 'post_content', $id ) ),
				)
			);
		}
		wp_reset_postdata();
		fclose( $out );
		exit;
	}
}

add_action( 'save_post_wir_request', array( WIR_Admin::class, 'refresh_menu_badge' ) );
add_action( 'transition_post_status', array( WIR_Admin::class, 'transition_menu_badge' ), 10, 3 );
add_action( 'wp_ajax_wir_mark_read', array( WIR_Admin::class, 'ajax_mark_read' ) );
add_action( 'wp_ajax_wir_toggle_pin', array( WIR_Admin::class, 'ajax_toggle_pin' ) );
add_action( 'wp_ajax_wir_toggle_star', array( WIR_Admin::class, 'ajax_toggle_star' ) );
