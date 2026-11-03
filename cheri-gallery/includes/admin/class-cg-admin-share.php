<?php
/**
 * Share gallery screen: write the email, see it live, send it (to yourself by default).
 */

defined( 'ABSPATH' ) || exit;

class CG_Admin_Share {

	public static function init() {
		add_action( 'admin_post_cg_share_send', array( __CLASS__, 'handle_send' ) );
		add_action( 'wp_ajax_cg_share_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_cg_template_save', array( __CLASS__, 'ajax_template_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'cg-share' ) ) {
			return;
		}
		$gid = isset( $_GET['gallery'] ) ? absint( $_GET['gallery'] ) : 0; // phpcs:ignore
		wp_enqueue_script( 'cg-admin-share', CG_URL . 'assets/js/admin-share.js', array(), CG_VERSION, true );
		wp_localize_script(
			'cg-admin-share',
			'CG_SHARE',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'gallery'   => $gid,
				'nonce'     => wp_create_nonce( 'cg_share_' . $gid ),
				'templates' => CG_Settings::get( 'templates' ),
				'fill'      => CG_Galleries::is_gallery( $gid ) ? array(
					'{names}'    => get_the_title( $gid ),
					'{date}'     => cg_format_date( CG_Galleries::setting( $gid, 'event_date' ) ),
					'{link}'     => CG_Galleries::link( $gid ),
					'{password}' => CG_Galleries::setting( $gid, 'password' ),
				) : array(),
				'i18n'      => array(
					'templateName' => __( 'Name this template (e.g. Wedding)', 'cheri-gallery' ),
					'saved'        => __( 'Template saved', 'cheri-gallery' ),
				),
			)
		);
	}

	public static function page() {
		if ( ! current_user_can( CG_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		$gid = isset( $_GET['gallery'] ) ? absint( $_GET['gallery'] ) : 0; // phpcs:ignore
		if ( ! CG_Galleries::is_gallery( $gid ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Gallery not found.', 'cheri-gallery' ) . '</p></div>';
			return;
		}
		$s         = CG_Galleries::get( $gid );
		$templates = CG_Settings::get( 'templates' );
		$tkey      = isset( $templates[ $s['session_type'] ] ) ? $s['session_type'] : key( $templates );
		$template  = $templates[ $tkey ];
		$me        = CG_Settings::get( 'from_email' );
		$photos    = CG_Photos::for_gallery( $gid, array( 'include_hidden' => false ) );
		$cover     = CG_Galleries::cover_photo( $gid );
		?>
		<div class="wrap cg-wrap">
			<?php CG_Admin::notice_from_query(); ?>
			<p><a href="<?php echo esc_url( get_edit_post_link( $gid ) ); ?>">← <?php echo esc_html( get_the_title( $gid ) ); ?></a></p>
			<?php if ( 'publish' !== get_post_status( $gid ) ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'This gallery isn\'t published yet, so the link won\'t work for anyone else. Publish it first.', 'cheri-gallery' ); ?></p></div>
			<?php endif; ?>
			<form class="cg-admin ch-share" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-cg-share-form>
				<?php wp_nonce_field( 'cg_share_send_' . $gid ); ?>
				<input type="hidden" name="action" value="cg_share_send">
				<input type="hidden" name="gallery" value="<?php echo esc_attr( $gid ); ?>">

				<div class="ch-share__compose">
					<h1 class="ch-panel__title"><?php echo esc_html( sprintf( /* translators: %s: gallery */ __( 'Share gallery · %s', 'cheri-gallery' ), get_the_title( $gid ) ) ); ?></h1>
					<label class="ch-share__line"><span><?php esc_html_e( 'To', 'cheri-gallery' ); ?></span><input name="to" value="<?php echo esc_attr( $me ); ?>" data-cg-to required></label>
					<p class="cg-hint"><?php esc_html_e( 'Starts with your own address so you can forward it. Add more, separated by commas.', 'cheri-gallery' ); ?>
						<?php
						$client_emails = array();
						foreach ( $s['clients'] as $cid ) {
							$cu = get_userdata( $cid );
							if ( $cu ) {
								$client_emails[] = $cu->user_email;
							}
						}
						if ( $client_emails ) :
							?>
							<button type="button" class="ch-textlink" data-cg-add-to="<?php echo esc_attr( implode( ', ', $client_emails ) ); ?>"><?php esc_html_e( 'Add the client', 'cheri-gallery' ); ?></button>
						<?php endif; ?>
					</p>
					<label class="ch-share__line"><span><?php esc_html_e( 'Subject', 'cheri-gallery' ); ?></span><input name="subject" value="<?php echo esc_attr( CG_Email::fill( $template['subject'], $gid ) ); ?>" data-cg-subject required></label>
					<textarea class="ch-share__msg" name="message" aria-label="<?php esc_attr_e( 'Message', 'cheri-gallery' ); ?>" data-cg-message><?php echo esc_textarea( CG_Email::fill( $template['body'], $gid ) ); ?></textarea>
					<div class="ch-row" style="justify-content:space-between">
						<label class="cg-inline"><?php esc_html_e( 'Template', 'cheri-gallery' ); ?>
							<select class="ch-select" data-cg-template>
								<?php foreach ( $templates as $key => $t ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $tkey ); ?>><?php echo esc_html( $t['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<button type="button" class="ch-textlink" data-cg-template-save><?php esc_html_e( 'Save as template', 'cheri-gallery' ); ?></button>
					</div>
					<p class="cg-hint"><?php esc_html_e( 'Use {names}, {date}, {link} and {password} in templates; they\'re filled in for each gallery.', 'cheri-gallery' ); ?></p>

					<div class="ch-share__opts">
						<?php
						if ( 'password' === $s['visibility'] ) {
							CG_Admin::toggle( 'include_password', true, __( 'Include gallery password', 'cheri-gallery' ), __( 'Anyone with the email can view the gallery', 'cheri-gallery' ), 'data-cg-preview-field' );
						}
						if ( $s['clients'] ) {
							CG_Admin::toggle( 'include_signin', true, __( 'Include client sign-in link', 'cheri-gallery' ), __( 'For favourites and downloads. It emails a fresh link to the client, so it\'s safe to forward.', 'cheri-gallery' ), 'data-cg-preview-field' );
						}
						?>
						<?php if ( $photos ) : ?>
							<label class="ch-toggle">
								<span class="ch-toggle__text"><span class="ch-toggle__label"><?php esc_html_e( 'Cover photo', 'cheri-gallery' ); ?></span></span>
								<select class="ch-select" name="cover_photo" data-cg-preview-field>
									<?php foreach ( $photos as $p ) : ?>
										<option value="<?php echo esc_attr( $p->id ); ?>" <?php selected( $p->id, $cover ); ?>><?php echo esc_html( ( (int) $p->id === (int) $cover ? __( 'Gallery cover', 'cheri-gallery' ) . ' — ' : '' ) . $p->original_name ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endif; ?>
					</div>

					<div class="ch-share__actions">
						<button type="button" class="ch-btn ch-btn--outline ch-btn--sm" data-cg-copy="<?php echo esc_attr( CG_Galleries::link( $gid ) ); ?>"><?php esc_html_e( 'Copy direct link', 'cheri-gallery' ); ?></button>
						<div class="ch-row" style="gap:8px">
							<button class="ch-btn ch-btn--outline ch-btn--sm" type="submit" name="test" value="1"><?php esc_html_e( 'Send test to me', 'cheri-gallery' ); ?></button>
							<button class="ch-btn ch-btn--sm" type="submit"><?php esc_html_e( 'Send', 'cheri-gallery' ); ?></button>
						</div>
					</div>
				</div>
				<div class="ch-share__preview">
					<iframe class="cg-preview-frame" title="<?php esc_attr_e( 'Email preview', 'cheri-gallery' ); ?>" data-cg-preview sandbox=""></iframe>
				</div>
			</form>
		</div>
		<?php
	}

	private static function opts_from_request( $src ) {
		return array(
			'message'          => isset( $src['message'] ) ? sanitize_textarea_field( wp_unslash( $src['message'] ) ) : '',
			'include_password' => ! empty( $src['include_password'] ),
			'include_signin'   => ! empty( $src['include_signin'] ),
			'cover_photo'      => isset( $src['cover_photo'] ) ? absint( $src['cover_photo'] ) : 0,
		);
	}

	public static function ajax_preview() {
		$gid = isset( $_POST['gallery'] ) ? absint( $_POST['gallery'] ) : 0;
		if ( ! check_ajax_referer( 'cg_share_' . $gid, 'nonce', false ) || ! current_user_can( CG_CAP ) || ! CG_Galleries::is_gallery( $gid ) ) {
			cg_json_fail( 'Forbidden', 403 );
		}
		list( $html ) = CG_Email::gallery_email( $gid, self::opts_from_request( $_POST ) ); // phpcs:ignore
		cg_json_ok( array( 'html' => $html ) );
	}

	public static function ajax_template_save() {
		$gid = isset( $_POST['gallery'] ) ? absint( $_POST['gallery'] ) : 0;
		if ( ! check_ajax_referer( 'cg_share_' . $gid, 'nonce', false ) || ! current_user_can( CG_CAP ) ) {
			cg_json_fail( 'Forbidden', 403 );
		}
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			cg_json_fail( __( 'Give the template a name.', 'cheri-gallery' ) );
		}
		$key       = sanitize_key( $name );
		$templates = CG_Settings::get( 'templates' );
		// Turn this gallery's names back into placeholders so the template works for others.
		$names = get_the_title( $gid );
		$swap  = function ( $text ) use ( $names ) {
			return $names ? str_replace( $names, '{names}', $text ) : $text;
		};
		$templates[ $key ] = array(
			'name'    => $name,
			'subject' => $swap( isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '' ),
			'body'    => $swap( isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '' ),
		);
		CG_Settings::update( array( 'templates' => $templates ) );
		cg_json_ok( array( 'key' => $key, 'templates' => $templates ) );
	}

	public static function handle_send() {
		$gid = isset( $_POST['gallery'] ) ? absint( $_POST['gallery'] ) : 0;
		if ( ! current_user_can( CG_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		check_admin_referer( 'cg_share_send_' . $gid );
		if ( ! CG_Galleries::is_gallery( $gid ) ) {
			wp_die( esc_html__( 'Gallery not found.', 'cheri-gallery' ) );
		}
		$back = admin_url( 'admin.php?page=cg-share&gallery=' . $gid );
		$test = ! empty( $_POST['test'] );

		$to = array();
		if ( $test ) {
			$to = array( CG_Settings::get( 'from_email' ) );
		} else {
			$raw = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
			foreach ( preg_split( '/[\s,;]+/', $raw ) as $addr ) {
				$addr = sanitize_email( $addr );
				if ( is_email( $addr ) ) {
					$to[] = $addr;
				}
			}
			$to = array_unique( $to );
		}
		if ( ! $to ) {
			wp_safe_redirect( add_query_arg( 'cg_err', rawurlencode( __( 'Add at least one email address.', 'cheri-gallery' ) ), $back ) );
			exit;
		}
		if ( count( $to ) > 20 ) {
			wp_safe_redirect( add_query_arg( 'cg_err', rawurlencode( __( 'Send to 20 people or fewer at a time.', 'cheri-gallery' ) ), $back ) );
			exit;
		}

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : get_the_title( $gid );
		if ( $test ) {
			/* translators: %s: subject */
			$subject = sprintf( __( '[Test] %s', 'cheri-gallery' ), $subject );
		}
		list( $html, $text ) = CG_Email::gallery_email( $gid, self::opts_from_request( $_POST ) ); // phpcs:ignore

		// Each person gets their own copy so addresses aren't shared between guests.
		$sent = 0;
		foreach ( $to as $addr ) {
			if ( CG_Email::send( $addr, $subject, $html, $text ) ) {
				++$sent;
			}
		}
		if ( ! $sent ) {
			wp_safe_redirect( add_query_arg( 'cg_msg', 'link_failed', $back ) );
			exit;
		}
		if ( ! $test ) {
			CG_Email::log( $gid, $to, $subject );
		}
		wp_safe_redirect( add_query_arg( 'cg_msg', 'email_sent', $back ) );
		exit;
	}
}
