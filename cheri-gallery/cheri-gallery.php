<?php
/**
 * Plugin Name:       Cheri Gallery
 * Description:       Client galleries for Cheri Photography: public, password and private albums, watermarks, downloads, client accounts with favourites lists, and branded share emails.
 * Version:           0.6.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Cheri Photography
 * Text Domain:       cheri-gallery
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'CG_VERSION', '0.6.0' );
define( 'CG_DB_VERSION', 1 );
define( 'CG_FILE', __FILE__ );
define( 'CG_DIR', plugin_dir_path( __FILE__ ) );
define( 'CG_URL', plugin_dir_url( __FILE__ ) );

require_once CG_DIR . 'includes/helpers.php';
require_once CG_DIR . 'includes/class-cg-install.php';
require_once CG_DIR . 'includes/class-cg-settings.php';
require_once CG_DIR . 'includes/class-cg-galleries.php';
require_once CG_DIR . 'includes/class-cg-photos.php';
require_once CG_DIR . 'includes/class-cg-images.php';
require_once CG_DIR . 'includes/class-cg-access.php';
require_once CG_DIR . 'includes/class-cg-delivery.php';
require_once CG_DIR . 'includes/class-cg-auth.php';
require_once CG_DIR . 'includes/class-cg-favourites.php';
require_once CG_DIR . 'includes/class-cg-frontend.php';
require_once CG_DIR . 'includes/class-cg-email.php';
require_once CG_DIR . 'includes/class-cg-updater.php';

if ( is_admin() ) {
	require_once CG_DIR . 'includes/admin/class-cg-admin.php';
	require_once CG_DIR . 'includes/admin/class-cg-admin-gallery.php';
	require_once CG_DIR . 'includes/admin/class-cg-admin-clients.php';
	require_once CG_DIR . 'includes/admin/class-cg-admin-share.php';
	require_once CG_DIR . 'includes/admin/class-cg-admin-settings.php';
}

register_activation_hook( __FILE__, array( 'CG_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CG_Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		add_action( 'init', array( 'CG_Install', 'maybe_upgrade' ), 20 );
		CG_Galleries::init();
		CG_Delivery::init();
		CG_Auth::init();
		CG_Favourites::init();
		CG_Frontend::init();
		CG_Email::init();
		CG_Updater::init(); // Runs outside admin too, so scheduled update checks and auto-updates work.
		if ( is_admin() ) {
			CG_Admin::init();
			CG_Admin_Gallery::init();
			CG_Admin_Clients::init();
			CG_Admin_Share::init();
			CG_Admin_Settings::init();
		}
	}
);
