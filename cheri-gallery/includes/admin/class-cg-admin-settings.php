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

						<?php self::session_types_group(); ?>

						<div class="ch-set__group">
							<p class="ch-set__h"><?php esc_html_e( 'Pages', 'cheri-gallery' ); ?></p>
							<?php CG_Admin::toggle( 'password_signin', $s['password_signin'], __( 'Clients sign in with their email and the gallery password', 'cheri-gallery' ), __( 'Clients you\'ve added to a gallery go straight in, with no email link. Anyone who has the forwarded email knows the password, so they could sign in as the client and download. Turn off to always use email links.', 'cheri-gallery' ) ); ?>
							<?php
							$cg_header_choices = array(
								'none'    => __( 'No header (cover photo to the top)', 'cheri-gallery' ),
								'theme'   => __( 'Website header (menu)', 'cheri-gallery' ),
								'gallery' => __( 'Gallery header (Cheri logo)', 'cheri-gallery' ),
							);
							?>
							<label class="ch-toggle">
								<span class="ch-toggle__text"><span class="ch-toggle__label"><?php esc_html_e( 'Header on a gallery', 'cheri-gallery' ); ?></span><span class="ch-toggle__hint"><?php esc_html_e( 'The page with the cover photo and the photos', 'cheri-gallery' ); ?></span></span>
								<select class="ch-select" name="gallery_header">
									<?php foreach ( $cg_header_choices as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['gallery_header'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="ch-toggle">
								<span class="ch-toggle__text"><span class="ch-toggle__label"><?php esc_html_e( 'Header on other pages', 'cheri-gallery' ); ?></span><span class="ch-toggle__hint"><?php esc_html_e( 'Password, client sign in and My galleries', 'cheri-gallery' ); ?></span></span>
								<select class="ch-select" name="pages_header">
									<?php foreach ( array( 'theme', 'gallery' ) as $key ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['pages_header'], $key ); ?>><?php echo esc_html( $cg_header_choices[ $key ] ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="ch-toggle">
								<span class="ch-toggle__text"><span class="ch-toggle__label"><?php esc_html_e( 'Footer on all gallery pages', 'cheri-gallery' ); ?></span></span>
								<select class="ch-select" name="site_footer">
									<option value="theme" <?php selected( $s['site_footer'], 'theme' ); ?>><?php esc_html_e( 'Website footer', 'cheri-gallery' ); ?></option>
									<option value="gallery" <?php selected( $s['site_footer'], 'gallery' ); ?>><?php esc_html_e( 'Gallery footer (Cheri logo)', 'cheri-gallery' ); ?></option>
								</select>
							</label>
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
	 * How many galleries (any status) use each session type.
	 */
	private static function session_type_counts() {
		$counts = array();
		$ids    = get_posts(
			array(
				'post_type'   => CG_Galleries::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$t            = CG_Galleries::setting( $id, 'session_type' );
			$counts[ $t ] = ( $counts[ $t ] ?? 0 ) + 1;
		}
		return $counts;
	}

	/**
	 * Settings → Session types: rename, reorder, add and remove the types offered on a gallery.
	 */
	private static function session_types_group() {
		$counts = self::session_type_counts();
		$row    = function ( $i, $key, $t, $used ) {
			?>
			<tr data-cg-type-row>
				<td><input class="ch-field__input" name="types[<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $t['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Family & children', 'cheri-gallery' ); ?>" aria-label="<?php esc_attr_e( 'Name', 'cheri-gallery' ); ?>">
					<input type="hidden" name="types[<?php echo esc_attr( $i ); ?>][key]" value="<?php echo esc_attr( $key ); ?>"></td>
				<td><input class="ch-field__input" name="types[<?php echo esc_attr( $i ); ?>][short]" value="<?php echo esc_attr( $t['short'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Family', 'cheri-gallery' ); ?>" aria-label="<?php esc_attr_e( 'Card label', 'cheri-gallery' ); ?>"></td>
				<td class="cg-types__key"><?php echo $key ? '<code>' . esc_html( $key ) . '</code>' : '<span class="cg-hint">' . esc_html__( 'new', 'cheri-gallery' ) . '</span>'; ?></td>
				<td class="cg-types__used"><?php echo esc_html( (string) $used ); ?></td>
				<td class="cg-types__btns">
					<button type="button" class="ch-iconbtn" data-cg-type-move="-1" aria-label="<?php esc_attr_e( 'Move up', 'cheri-gallery' ); ?>">↑</button>
					<button type="button" class="ch-iconbtn" data-cg-type-move="1" aria-label="<?php esc_attr_e( 'Move down', 'cheri-gallery' ); ?>">↓</button>
					<?php if ( $used ) : ?>
						<span class="cg-hint" title="<?php esc_attr_e( 'Used by a gallery, so it can\'t be removed', 'cheri-gallery' ); ?>"><?php esc_html_e( 'In use', 'cheri-gallery' ); ?></span>
					<?php else : ?>
						<label class="cg-types__remove"><input type="checkbox" name="types[<?php echo esc_attr( $i ); ?>][remove]" value="1"> <?php esc_html_e( 'Remove', 'cheri-gallery' ); ?></label>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		};
		?>
		<div class="ch-set__group" id="session-types">
			<p class="ch-set__h"><?php esc_html_e( 'Session types', 'cheri-gallery' ); ?></p>
			<p class="cg-hint"><?php esc_html_e( 'The choices in a gallery\'s "Session type" box, in this order. The card label shows above the gallery name on gallery cards. A type used by a gallery can be renamed but not removed. The shortcode value is for [cheri_galleries type="…"] and never changes.', 'cheri-gallery' ); ?></p>
			<table class="ch-table cg-types">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'cheri-gallery' ); ?></th>
					<th><?php esc_html_e( 'Card label', 'cheri-gallery' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', 'cheri-gallery' ); ?></th>
					<th title="<?php esc_attr_e( 'Galleries using it', 'cheri-gallery' ); ?>"><?php esc_html_e( 'Used', 'cheri-gallery' ); ?></th>
					<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'cheri-gallery' ); ?></span></th>
				</tr></thead>
				<tbody data-cg-types>
					<?php
					$i = 0;
					foreach ( cg_session_type_list() as $key => $t ) {
						$row( $i++, $key, $t, $counts[ $key ] ?? 0 );
					}
					$row( $i, '', array( 'name' => '', 'short' => '' ), 0 );
					?>
				</tbody>
			</table>
			<p><button type="button" class="ch-btn ch-btn--sm ch-btn--outline" data-cg-type-add><?php esc_html_e( '+ Add another', 'cheri-gallery' ); ?></button>
				<span class="cg-hint"><?php esc_html_e( 'Type a name in the empty row to add a type, then press Save.', 'cheri-gallery' ); ?></span></p>
		</div>
		<?php
	}

	/**
	 * The session types from the form: keys kept, new ones get a key from their name,
	 * types still used by a gallery are never removed. Returns array( types, kept_in_use ).
	 */
	private static function parse_session_types( $rows ) {
		$before = cg_session_type_list();
		$counts = self::session_type_counts();
		$out    = array();
		$kept   = false;
		foreach ( (array) $rows as $r ) {
			$key   = sanitize_key( $r['key'] ?? '' );
			$name  = sanitize_text_field( $r['name'] ?? '' );
			$short = sanitize_text_field( $r['short'] ?? '' );
			if ( $key && ! isset( $before[ $key ] ) ) {
				$key = ''; // Not one of ours: treat as new.
			}
			$used = $key && ! empty( $counts[ $key ] );
			if ( ! empty( $r['remove'] ) || '' === $name ) {
				if ( ! $used ) {
					continue; // Removed, or an empty new row.
				}
				$kept = true;
				if ( '' === $name ) {
					$name = $before[ $key ]['name'];
				}
			}
			if ( ! $key ) {
				$base = sanitize_key( sanitize_title( $short ? $short : $name ) );
				$base = $base ? substr( $base, 0, 30 ) : 'type';
				$key  = $base;
				for ( $n = 2; isset( $out[ $key ] ) || isset( $before[ $key ] ); $n++ ) {
					$key = $base . '-' . $n; // Never reuse an existing key.
				}
			}
			$out[ $key ] = array( 'name' => $name, 'short' => $short );
		}
		// A type used by a gallery that's missing from the form (shouldn't happen) stays.
		foreach ( $counts as $key => $n ) {
			if ( isset( $before[ $key ] ) && ! isset( $out[ $key ] ) ) {
				$out[ $key ] = $before[ $key ];
				$kept        = true;
			}
		}
		return array( $out ? $out : $before, $kept );
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
							echo '<strong>' . esc_html( sprintf( __( 'On this website: version %s', 'cheri-gallery' ), CG_VERSION ) ) . '</strong><br>';
							if ( is_wp_error( $release ) ) {
								echo '<span class="cg-error">' . esc_html( $release->get_error_message() ) . '</span>';
							} else {
								/* translators: 1: version, 2: date */
								echo esc_html( sprintf( __( 'Newest available: version %1$s (released %2$s)', 'cheri-gallery' ), $release['version'], $release['published'] ? mysql2date( 'j M Y', $release['published'] ) : '' ) );
								if ( ! empty( $release['checked'] ) ) {
									/* translators: %s: time ago */
									echo '<br><span class="cg-hint">' . esc_html( sprintf( __( 'Checked %s ago', 'cheri-gallery' ), human_time_diff( (int) $release['checked'] ) ) ) . '</span>';
								}
							}
							?>
						</p>
						<?php if ( $update ) : ?>
							<?php if ( current_user_can( 'update_plugins' ) ) : ?>
								<p><a class="ch-btn" href="<?php echo esc_url( CG_Updater::update_url() ); ?>">
									<?php
									/* translators: %s: version */
									echo esc_html( sprintf( __( 'Update to %s now', 'cheri-gallery' ), $release['version'] ) );
									?>
								</a></p>
								<p class="cg-hint"><?php esc_html_e( 'Checking only looks for a new version; this button installs it. It takes a few seconds and your photos, clients and favourites aren\'t touched.', 'cheri-gallery' ); ?></p>
							<?php endif; ?>
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
			'password_signin'   => empty( $p['password_signin'] ) ? 0 : 1,
			'jpeg_quality'      => min( 95, max( 60, absint( $p['jpeg_quality'] ?? 86 ) ) ),
			'gallery_header'    => in_array( $p['gallery_header'] ?? '', array( 'none', 'theme', 'gallery' ), true ) ? $p['gallery_header'] : 'none',
			'pages_header'      => ( $p['pages_header'] ?? '' ) === 'gallery' ? 'gallery' : 'theme',
			'site_footer'       => ( $p['site_footer'] ?? '' ) === 'gallery' ? 'gallery' : 'theme',
		);
		$kept = false;
		if ( isset( $p['types'] ) ) {
			list( $values['session_types'], $kept ) = self::parse_session_types( $p['types'] );
		}
		CG_Settings::update( $values );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . CG_Galleries::POST_TYPE . '&page=cg-settings&cg_msg=' . ( $kept ? 'types_in_use' : 'settings_saved' ) ) . ( isset( $p['types'] ) ? '#session-types' : '' ) );
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
