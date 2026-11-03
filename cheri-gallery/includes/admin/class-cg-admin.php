<?php
/**
 * Admin menu and shared admin assets.
 */

defined( 'ABSPATH' ) || exit;

class CG_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CG_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	public static function menu() {
		$parent = 'edit.php?post_type=' . CG_Galleries::POST_TYPE;
		$unseen = CG_Favourites::unseen_count();
		$bubble = $unseen ? ' <span class="awaiting-mod">' . absint( $unseen ) . '</span>' : '';

		add_submenu_page( $parent, __( 'Clients', 'cheri-gallery' ), __( 'Clients', 'cheri-gallery' ) . $bubble, CG_CAP, 'cg-clients', array( 'CG_Admin_Clients', 'page' ) );
		add_submenu_page( $parent, __( 'Settings', 'cheri-gallery' ), __( 'Settings', 'cheri-gallery' ), CG_CAP, 'cg-settings', array( 'CG_Admin_Settings', 'page' ) );
		// Share screen: reached from a gallery, not shown in the menu.
		add_submenu_page( 'options.php', __( 'Share gallery', 'cheri-gallery' ), __( 'Share gallery', 'cheri-gallery' ), CG_CAP, 'cg-share', array( 'CG_Admin_Share', 'page' ) );

		// Show new favourites on the top-level menu too.
		if ( $unseen ) {
			global $menu;
			foreach ( (array) $menu as $i => $item ) {
				if ( isset( $item[2] ) && $parent === $item[2] ) {
					$menu[ $i ][0] .= $bubble; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				}
			}
		}
	}

	public static function is_our_screen() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		return CG_Galleries::POST_TYPE === $screen->post_type || false !== strpos( $screen->id, 'cg-' );
	}

	public static function assets() {
		if ( ! self::is_our_screen() ) {
			return;
		}
		wp_enqueue_style( 'cg-fonts', 'https://fonts.googleapis.com/css2?family=Marcellus&family=Open+Sans:wght@400;600&display=swap', array(), null ); // phpcs:ignore
		wp_enqueue_style( 'cg-components', CG_URL . 'assets/css/components.css', array( 'cg-fonts' ), CG_VERSION );
		wp_enqueue_style( 'cg-admin', CG_URL . 'assets/css/admin.css', array( 'cg-components' ), CG_VERSION );
		wp_enqueue_script( 'cg-admin', CG_URL . 'assets/js/admin.js', array(), CG_VERSION, true );
		wp_localize_script( 'cg-admin', 'CG_ADMIN_COMMON', array( 'copied' => __( 'Copied', 'cheri-gallery' ) ) );
	}

	public static function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=cg-settings' ) ) . '">' . esc_html__( 'Settings', 'cheri-gallery' ) . '</a>' );
		return $links;
	}

	/**
	 * A small on/off switch matching the design system Toggle.
	 */
	public static function toggle( $name, $checked, $label, $hint = '', $attrs = '' ) {
		?>
		<label class="ch-toggle">
			<span class="ch-toggle__text">
				<span class="ch-toggle__label"><?php echo esc_html( $label ); ?></span>
				<?php if ( $hint ) : ?>
					<span class="ch-toggle__hint"><?php echo esc_html( $hint ); ?></span>
				<?php endif; ?>
			</span>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> <?php echo $attrs; // phpcs:ignore ?>>
			<span class="ch-toggle__track"></span>
		</label>
		<?php
	}

	/**
	 * Admin notice from a redirect (?cg_msg=...).
	 */
	public static function notice_from_query() {
		$msg = isset( $_GET['cg_msg'] ) ? sanitize_key( $_GET['cg_msg'] ) : ''; // phpcs:ignore
		$map = array(
			'client_added'   => array( 'success', __( 'Client added.', 'cheri-gallery' ) ),
			'client_saved'   => array( 'success', __( 'Client saved.', 'cheri-gallery' ) ),
			'link_sent'      => array( 'success', __( 'Sign-in link sent.', 'cheri-gallery' ) ),
			'link_failed'    => array( 'error', __( 'The email could not be sent. Check your email sender (WP Mail SMTP) is set up.', 'cheri-gallery' ) ),
			'settings_saved' => array( 'success', __( 'Settings saved.', 'cheri-gallery' ) ),
			'email_sent'     => array( 'success', __( 'Gallery email sent.', 'cheri-gallery' ) ),
			'update_found'   => array( 'warning', __( 'A new version of Cheri Gallery is ready. Press "Update now" in the Updates panel below to install it.', 'cheri-gallery' ) ),
			'up_to_date'     => array( 'success', __( 'Cheri Gallery is up to date.', 'cheri-gallery' ) ),
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $msg ][0] ), esc_html( $map[ $msg ][1] ) );
		}
		$err = isset( $_GET['cg_err'] ) ? sanitize_text_field( wp_unslash( $_GET['cg_err'] ) ) : ''; // phpcs:ignore
		if ( $err ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $err ) );
		}
	}
}
