<?php
/**
 * Small shared helpers.
 */

defined( 'ABSPATH' ) || exit;

/** Capability needed to manage galleries and clients. */
const CG_CAP = 'manage_cheri_gallery';

/** Role given to client accounts. */
const CG_ROLE = 'gallery_client';

/**
 * Absolute path of the plugin's protected storage folder (no trailing slash).
 */
function cg_storage_dir() {
	$uploads = wp_upload_dir( null, false );
	return trailingslashit( $uploads['basedir'] ) . 'cheri-gallery';
}

/**
 * Folder for one gallery's originals or generated copies.
 *
 * @param int    $gallery_id Gallery post ID.
 * @param string $kind       'originals' or 'cache'.
 */
function cg_gallery_dir( $gallery_id, $kind = 'originals' ) {
	$kind = ( 'cache' === $kind ) ? 'cache' : 'originals';
	return cg_storage_dir() . '/' . $kind . '/' . absint( $gallery_id );
}

/**
 * Create a folder (and parents) and drop an index.php in it.
 */
function cg_ensure_dir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore
	}
	return is_dir( $dir );
}

/**
 * Plugin template path, overridable by the theme at cheri-gallery/<name>.
 */
function cg_template( $name ) {
	$theme = locate_template( 'cheri-gallery/' . $name );
	return $theme ? $theme : CG_DIR . 'templates/' . $name;
}

/**
 * Render a template to a string.
 */
function cg_render( $name, array $vars = array() ) {
	ob_start();
	extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
	include cg_template( $name );
	return ob_get_clean();
}

/**
 * Inline SVG icon (line style, 1.5 stroke) from the design system.
 */
function cg_icon( $name ) {
	$paths = array(
		'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'nodl'     => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14M3 3l18 18"/>',
		'heart'    => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
		'share'    => '<path d="M14 5l6 6-6 6M20 11H10a6 6 0 0 0-6 6v1"/>',
		'play'     => '<path d="M8 5l11 7-11 7z"/>',
		'lock'     => '<rect x="5" y="11" width="14" height="9"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		'globe'    => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c3 3 3 13 0 16M12 4c-3 3-3 13 0 16"/>',
		'eyeoff'   => '<path d="M3 3l18 18M10.6 6.1A9 9 0 0 1 21 12a13 13 0 0 1-2.4 3.2M6.6 6.6A13 13 0 0 0 3 12s3.5 6 9 6a8 8 0 0 0 4-1"/>',
		'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
		'prev'     => '<path d="M15 5l-7 7 7 7"/>',
		'next'     => '<path d="M9 5l7 7-7 7"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'list'     => '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
		'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Session types offered for galleries.
 */
function cg_session_types() {
	$out = array();
	foreach ( cg_session_type_list() as $key => $t ) {
		$out[ $key ] = $t['name'];
	}
	return $out;
}

/**
 * The stored session types (Settings → Session types), always at least one.
 */
function cg_session_type_list() {
	$types = CG_Settings::get( 'session_types' );
	$types = is_array( $types ) ? array_filter( $types, 'is_array' ) : array();
	return $types ? $types : CG_Settings::default_session_types();
}

/**
 * Short label for a session type (used as the eyebrow on cards).
 */
function cg_session_label( $type ) {
	$types = cg_session_type_list();
	if ( ! isset( $types[ $type ] ) ) {
		return '';
	}
	return '' !== (string) ( $types[ $type ]['short'] ?? '' ) ? $types[ $type ]['short'] : $types[ $type ]['name'];
}

/**
 * Format a Y-m-d date the way the design system shows it: "15th May, 2026".
 */
function cg_format_date( $ymd, $format = 'jS F, Y' ) {
	if ( ! $ymd ) {
		return '';
	}
	$ts = strtotime( $ymd . ' 12:00:00' );
	return $ts ? date_i18n( $format, $ts ) : '';
}

/**
 * JSON response helpers for AJAX handlers.
 */
function cg_json_ok( $data = array() ) {
	wp_send_json_success( $data );
}
function cg_json_fail( $message, $status = 400 ) {
	wp_send_json_error( array( 'message' => $message ), $status );
}

/**
 * Current visitor's IP, used only for rate limiting.
 */
function cg_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return $ip ? $ip : 'unknown';
}

/**
 * Simple rate limiter using transients. Returns false once $max hits are used up in $window seconds.
 */
function cg_rate_limit( $key, $max, $window ) {
	$tkey  = 'cg_rl_' . md5( $key );
	$count = (int) get_transient( $tkey );
	if ( $count >= $max ) {
		return false;
	}
	set_transient( $tkey, $count + 1, $window );
	return true;
}
