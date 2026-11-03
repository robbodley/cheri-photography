<?php
/**
 * Activation: database tables, client role, protected storage, rewrite rules.
 */

defined( 'ABSPATH' ) || exit;

class CG_Install {

	public static function activate() {
		self::create_tables();
		self::add_roles();
		self::protect_storage();
		CG_Galleries::register_post_type();
		CG_Delivery::add_rewrites();
		CG_Auth::add_rewrites();
		flush_rewrite_rules();
		update_option( 'cg_db_version', CG_DB_VERSION );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Run upgrades when the DB version changes (e.g. after updating the plugin files).
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( 'cg_db_version' ) !== CG_DB_VERSION ) {
			self::activate();
		}
	}

	public static function tables() {
		global $wpdb;
		return array(
			'photos' => $wpdb->prefix . 'cg_photos',
			'lists'  => $wpdb->prefix . 'cg_fav_lists',
			'items'  => $wpdb->prefix . 'cg_fav_items',
			'emails' => $wpdb->prefix . 'cg_email_log',
		);
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = self::tables();

		dbDelta(
			"CREATE TABLE {$t['photos']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				gallery_id bigint(20) unsigned NOT NULL,
				set_name varchar(100) NOT NULL DEFAULT '',
				file varchar(191) NOT NULL,
				original_name varchar(255) NOT NULL DEFAULT '',
				width int(10) unsigned NOT NULL DEFAULT 0,
				height int(10) unsigned NOT NULL DEFAULT 0,
				filesize bigint(20) unsigned NOT NULL DEFAULT 0,
				hidden tinyint(1) NOT NULL DEFAULT 0,
				no_download tinyint(1) NOT NULL DEFAULT 0,
				sort_order int(11) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY gallery_id (gallery_id)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['lists']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				gallery_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				name varchar(100) NOT NULL,
				note text NOT NULL,
				status varchar(10) NOT NULL DEFAULT 'open',
				sent_at datetime NULL,
				seen_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY gallery_user (gallery_id,user_id)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['items']} (
				list_id bigint(20) unsigned NOT NULL,
				photo_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (list_id,photo_id),
				KEY photo_id (photo_id)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['emails']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				gallery_id bigint(20) unsigned NOT NULL,
				recipients text NOT NULL,
				subject varchar(255) NOT NULL,
				sent_by bigint(20) unsigned NOT NULL DEFAULT 0,
				sent_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY gallery_id (gallery_id)
			) $c;"
		);
	}

	public static function add_roles() {
		if ( ! get_role( CG_ROLE ) ) {
			add_role( CG_ROLE, __( 'Gallery client', 'cheri-gallery' ), array( 'read' => true ) );
		}
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( CG_CAP ) ) {
				$role->add_cap( CG_CAP );
			}
		}
	}

	/**
	 * Originals and generated copies live outside public view. The .htaccess blocks direct
	 * access on Apache/LiteSpeed; file names are random so they can't be guessed on Nginx either.
	 */
	public static function protect_storage() {
		$dir = cg_storage_dir();
		cg_ensure_dir( $dir );
		cg_ensure_dir( $dir . '/originals' );
		cg_ensure_dir( $dir . '/cache' );
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( // phpcs:ignore
				$htaccess,
				"# Cheri Gallery: photos are served through WordPress so access can be checked.\n" .
				"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
				"<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
			);
		}
	}
}
