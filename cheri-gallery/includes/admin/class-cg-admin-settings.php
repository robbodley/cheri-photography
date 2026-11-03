<?php
/**
 * Plugin settings: email sender, studio details, image sizes, defaults.
 */

defined( 'ABSPATH' ) || exit;

class CG_Admin_Settings {

	public static function init() {
		add_action( 'admin_post_cg_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_cg_test_email', array( __CLASS__, 'handle_test_email' ) );
		add_action( 'admin_post_cg_check_updates', array( __CLASS__, 'handle_check_updates' ) );
		add_action( 'admin_notices', array( __CLASS__, 'mail_notice' ) );
	}

	/**
	 * Remind Cheri to set up an email sender until one is detected.
	 */
	public static function mail_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ! CG_Admin::is_our_screen() || self::has_mail_plugin() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . wp_kses_post(
			sprintf(
				/* translators: %s: settings link */
				__( '<strong>Cheri Gallery:</strong> no email sender is set up, so gallery emails and sign-in links may land in spam. Install the free WP Mail SMTP plugin and connect your photome@ mailbox. <a href="%s">Details</a>', 'cheri-gallery' ),
				esc_url( admin_url( 'edit.php?post_type=' . CG_Galleries::POST_TYPE . '&page=cg-settings#email' ) )
			)
		) . '</p></div>';
	}

	public static function has_mail_plugin() {
		return defined( 'WPMS_PLUGIN_VER' ) || class_exists( 'WPMailSMTP\Core' ) || class_exists( 'Postman' ) || function_exists( 'fluentSmtpInit' ) || defined( 'FLUENTMAIL' ) || class_exists( 'EasyWPSMTP' ) || has_action( 'phpmailer_init', 'brevo_phpmailer_init' );
	}

	public static function page() {
		if ( ! current_user_can( CG_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		$s = CG_Settings::all();
		?>
		<div class="wrap cg-wrap">
			<?php CG_Admin::notice_from_query(); ?>
			<form class="cg-admin" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cg_save_settings' ); ?>
				<input type="hidden" name="action" value="cg_save_settings">
				<section class="ch-panel cg-narrow-panel">
					<div class="ch-panel__head">
						<h1 class="ch-panel__title"><?php esc_html_e( 'Cheri Gallery settings', 'cheri-gallery' ); ?></h1>
						<button class="ch-btn ch-btn--sm" type="submit"><?php esc_html_e( 'Save', 'cheri-gallery' ); ?></button>
					</div>
					<div class="ch-panel__body ch-set">
						<div class="ch-set__group" id="email">
							<p class="ch-set__h"><?php esc_html_e( 'Email', 'cheri-gallery' ); ?></p>
							<div class="cg-grid2">
								<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Send from (and replies to)', 'cheri-gallery' ); ?></span><input class="ch-field__input" type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>" required></label>
								<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Sender name', 'cheri-gallery' ); ?></span><input class="ch-field__input" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>" required></label>
							</div>
							<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Tell me about new favourites at', 'cheri-gallery' ); ?></span><input class="ch-field__input" type="email" name="notify_email" value="<?php echo esc_attr( $s['notify_email'] ); ?>" required></label>
							<?php if ( self::has_mail_plugin() ) : ?>
								<p class="cg-hint">✓ <?php esc_html_e( 'An email sender plugin is active.', 'cheri-gallery' ); ?></p>
							<?php else : ?>
								<div class="cg-callout">
									<p><strong><?php esc_html_e( 'Set up an email sender (one-off, about 20 minutes)', 'cheri-gallery' ); ?></strong></p>
									<ol>
										<li><?php esc_html_e( 'Plugins → Add New → install and activate "WP Mail SMTP".', 'cheri-gallery' ); ?></li>
										<li><?php esc_html_e( 'In its setup wizard, choose the service that hosts your photome@ mailbox (Google, Microsoft/Outlook, or "Other SMTP" with your host\'s details). If that won\'t connect, pick Brevo (free for 300 emails a day).', 'cheri-gallery' ); ?></li>
										<li><?php esc_html_e( 'Add the SPF and DKIM records it shows you at your domain provider.', 'cheri-gallery' ); ?></li>
										<li><?php esc_html_e( 'Send a test below.', 'cheri-gallery' ); ?></li>
									</ol>
								</div>
							<?php endif; ?>
						</div>

						<div class="ch-set__group">
							<p class="ch-set__h"><?php esc_html_e( 'Studio details (email footer)', 'cheri-gallery' ); ?></p>
							<div class="cg-grid2">
								<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Website', 'cheri-gallery' ); ?></span><input class="ch-field__input" type="url" name="website_url" value="<?php echo esc_attr( $s['website_url'] ); ?>"></label>
								<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Instagram link', 'cheri-gallery' ); ?></span><input class="ch-field__input" type="url" name="instagram_url" value="<?php echo esc_attr( $s['instagram_url'] ); ?>" placeholder="https://instagram.com/…"></label>
							</div>
							<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Location', 'cheri-gallery' ); ?></span><input class="ch-field__input" name="location" value="<?php echo esc_attr( $s['location'] ); ?>"></label>
						</div>

						<div class="ch-set__group">
							<p class="ch-set__h"><?php esc_html_e( 'Photos', 'cheri-gallery' ); ?></p>
							<label class="ch-toggle">
								<span class="ch-toggle__text"><span class="ch-toggle__label"><?php esc_html_e( 'Default watermark for new galleries', 'cheri-gallery' ); ?></span></span>
								<select class="ch-select" name="default_watermark">
									<?php foreach ( CG_Images::watermark_styles() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['default_watermark'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="ch-toggle">
								<span class="ch-toggle__text"><span class="ch-toggle__label"><?php esc_html_e( 'Web size (long edge, px)', 'cheri-gallery' ); ?></span><span class="ch-toggle__hint"><?php esc_html_e( 'Used in the lightbox and for web-size downloads', 'cheri-gallery' ); ?></span></span>
								<input class="ch-select" type="number" min="1200" max="4096" step="1" name="web_size" value="<?php echo esc_attr( $s['web_size'] ); ?>">
							</label>
							<label class="ch-toggle">
								<span class="ch-toggle__text"><span class="ch-toggle__label"><?php esc_html_e( 'JPEG quality', 'cheri-gallery' ); ?></span></span>
								<input class="ch-select" type="number" min="60" max="95" name="jpeg_quality" value="<?php echo esc_attr( $s['jpeg_quality'] ); ?>">
							</label>
						</div>

						<div class="ch-set__group">
							<p class="ch-set__h"><?php esc_html_e( 'Pages', 'cheri-gallery' ); ?></p>
							<p class="cg-hint">
								<?php esc_html_e( 'Client sign in:', 'cheri-gallery' ); ?> <a href="<?php echo esc_url( CG_Auth::login_url() ); ?>" target="_blank"><?php echo esc_html( CG_Auth::login_url() ); ?></a><br>
								<?php esc_html_e( 'My galleries:', 'cheri-gallery' ); ?> <a href="<?php echo esc_url( CG_Auth::account_url() ); ?>" target="_blank"><?php echo esc_html( CG_Auth::account_url() ); ?></a><br>
								<?php esc_html_e( 'List public galleries on any page with the shortcode', 'cheri-gallery' ); ?> <code>[cheri_galleries]</code> <?php esc_html_e( 'or', 'cheri-gallery' ); ?> <code>[cheri_galleries type="portrait"]</code>.
							</p>
						</div>
					</div>
				</section>
			</form>

			<form class="cg-admin" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cg_test_email' ); ?>
				<input type="hidden" name="action" value="cg_test_email">
				<section class="ch-panel cg-narrow-panel">
					<div class="ch-panel__body ch-row" style="justify-content:space-between">
						<span><?php echo esc_html( sprintf( /* translators: %s: email */ __( 'Send a test email to %s', 'cheri-gallery' ), $s['from_email'] ) ); ?></span>
						<button class="ch-btn ch-btn--sm ch-btn--outline" type="submit"><?php esc_html_e( 'Send test', 'cheri-gallery' ); ?></button>
					</div>
				</section>
			</form>

			<?php self::updates_panel(); ?>
		</div>
		<?php
	}

	/**
	 * Updates: installed vs latest version, check now, optional GitHub token.
	 */
	private static function updates_panel() {
		$release = CG_Updater::latest();
		$update  = CG_Updater::has_update( $release );
		$token   = get_option( CG_Updater::TOKEN_OPT, '' );
		?>
		<form class="cg-admin" id="updates" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'cg_check_updates' ); ?>
			<input type="hidden" name="action" value="cg_check_updates">
			<section class="ch-panel cg-narrow-panel">
				<div class="ch-panel__head">
					<h2 class="ch-panel__title"><?php esc_html_e( 'Updates', 'cheri-gallery' ); ?></h2>
					<button class="ch-btn ch-btn--sm ch-btn--outline" type="submit"><?php esc_html_e( 'Check for updates now', 'cheri-gallery' ); ?></button>
				</div>
				<div class="ch-panel__body ch-set">
					<div class="ch-set__group">
						<p>
							<?php
							/* translators: %s: version */
							echo esc_html( sprintf( __( 'Installed: version %s', 'cheri-gallery' ), CG_VERSION ) );
							echo '<br>';
							if ( is_wp_error( $release ) ) {
								echo esc_html( __( 'Latest:', 'cheri-gallery' ) . ' ' . $release->get_error_message() );
							} else {
								/* translators: 1: version, 2: date */
								echo esc_html( sprintf( __( 'Latest on GitHub: version %1$s (%2$s)', 'cheri-gallery' ), $release['version'], $release['published'] ? mysql2date( 'j M Y', $release['published'] ) : '' ) );
							}
							?>
						</p>
						<?php if ( $update ) : ?>
							<p><a class="ch-btn ch-btn--sm" href="<?php echo esc_url( admin_url( 'plugins.php?plugin_status=upgrade' ) ); ?>"><?php esc_html_e( 'Go to the update', 'cheri-gallery' ); ?></a></p>
							<?php if ( $release['notes'] ) : ?>
								<div class="cg-callout"><p><strong><?php esc_html_e( 'What\'s new', 'cheri-gallery' ); ?></strong></p><?php echo wp_kses_post( wpautop( esc_html( $release['notes'] ) ) ); ?></div>
							<?php endif; ?>
						<?php elseif ( ! is_wp_error( $release ) ) : ?>
							<p class="cg-hint">✓ <?php esc_html_e( 'You have the latest version.', 'cheri-gallery' ); ?></p>
						<?php endif; ?>
						<p class="cg-hint"><?php esc_html_e( 'WordPress checks twice a day. When there\'s an update, Plugins shows "Update now". To install updates automatically, click "Enable auto-updates" next to Cheri Gallery on the Plugins page. Photos, clients and favourites are never touched by an update.', 'cheri-gallery' ); ?></p>
					</div>
					<div class="ch-set__group">
						<label class="ch-field">
							<span class="ch-field__label"><?php esc_html_e( 'GitHub token (only if the repository is made private)', 'cheri-gallery' ); ?></span>
							<input class="ch-field__input" type="password" name="github_token" value="<?php echo $token ? '••••••••' : ''; ?>" autocomplete="off" placeholder="<?php esc_attr_e( 'Leave empty for a public repository', 'cheri-gallery' ); ?>">
						</label>
						<p class="cg-hint"><?php esc_html_e( 'A fine-grained token with read-only "Contents" access to robbodley/cheri-photography. Saved when you press Check for updates now.', 'cheri-gallery' ); ?></p>
					</div>
				</div>
			</section>
		</form>
		<?php
	}

	public static function handle_check_updates() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		check_admin_referer( 'cg_check_updates' );
		$token = isset( $_POST['github_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['github_token'] ) ) ) : '';
		if ( '' === $token ) {
			delete_option( CG_Updater::TOKEN_OPT );
		} elseif ( false === strpos( $token, '•' ) ) {
			update_option( CG_Updater::TOKEN_OPT, $token, false );
		}
		$release = CG_Updater::check_now();
		$args    = is_wp_error( $release ) ? array( 'cg_err' => rawurlencode( $release->get_error_message() ) ) : array( 'cg_msg' => CG_Updater::has_update( $release ) ? 'update_found' : 'up_to_date' );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php?post_type=' . CG_Galleries::POST_TYPE . '&page=cg-settings' ) ) . '#updates' );
		exit;
	}

	public static function handle_save() {
		if ( ! current_user_can( CG_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		check_admin_referer( 'cg_save_settings' );
		$p      = wp_unslash( $_POST ); // phpcs:ignore
		$styles = CG_Images::watermark_styles();
		$before = CG_Settings::all();
		$values = array(
			'from_email'        => is_email( $p['from_email'] ?? '' ) ? sanitize_email( $p['from_email'] ) : $before['from_email'],
			'from_name'         => sanitize_text_field( $p['from_name'] ?? $before['from_name'] ),
			'notify_email'      => is_email( $p['notify_email'] ?? '' ) ? sanitize_email( $p['notify_email'] ) : $before['notify_email'],
			'website_url'       => esc_url_raw( $p['website_url'] ?? '' ),
			'instagram_url'     => esc_url_raw( $p['instagram_url'] ?? '' ),
			'location'          => sanitize_text_field( $p['location'] ?? '' ),
			'default_watermark' => isset( $styles[ $p['default_watermark'] ?? '' ] ) ? $p['default_watermark'] : 'off',
			'web_size'          => min( 4096, max( 1200, absint( $p['web_size'] ?? 2048 ) ) ),
			'jpeg_quality'      => min( 95, max( 60, absint( $p['jpeg_quality'] ?? 86 ) ) ),
		);
		CG_Settings::update( $values );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . CG_Galleries::POST_TYPE . '&page=cg-settings&cg_msg=settings_saved' ) );
		exit;
	}

	public static function handle_test_email() {
		if ( ! current_user_can( CG_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		check_admin_referer( 'cg_test_email' );
		$html = cg_render(
			'email-layout.php',
			array(
				'settings' => CG_Settings::all(),
				'content'  => '<p style="margin:0;">' . esc_html__( 'This is a test from Cheri Gallery. If it arrived in your inbox (not spam), emails are set up correctly.', 'cheri-gallery' ) . '</p>',
			)
		);
		$ok   = CG_Email::send( CG_Settings::get( 'from_email' ), __( 'Cheri Gallery test email', 'cheri-gallery' ), $html );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . CG_Galleries::POST_TYPE . '&page=cg-settings&cg_msg=' . ( $ok ? 'email_sent' : 'link_failed' ) ) );
		exit;
	}
}
