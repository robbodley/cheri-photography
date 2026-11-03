<?php
/**
 * Who can see a gallery, and who can download from it.
 *
 * Every photo request and download goes through these checks on the server,
 * so hiding a button is never the only lock.
 */

defined( 'ABSPATH' ) || exit;

class CG_Access {

	const COOKIE_DAYS = 30;

	/** Is the current user the photographer (or another admin)? */
	public static function is_admin() {
		return current_user_can( CG_CAP );
	}

	/** Is the current user one of this gallery's clients? */
	public static function is_client_of( $gallery_id ) {
		$uid = get_current_user_id();
		return $uid && in_array( $uid, CG_Galleries::setting( $gallery_id, 'clients' ), true );
	}

	/**
	 * Why a visitor can't view a gallery: '' (they can), 'password', 'private', 'expired', 'draft'.
	 */
	public static function view_block( $gallery_id ) {
		if ( self::is_admin() ) {
			return '';
		}
		if ( 'publish' !== get_post_status( $gallery_id ) ) {
			return 'draft';
		}
		if ( CG_Galleries::is_expired( $gallery_id ) ) {
			return 'expired';
		}
		if ( self::is_client_of( $gallery_id ) ) {
			return '';
		}
		$s = CG_Galleries::get( $gallery_id );
		if ( 'public' === $s['visibility'] ) {
			return '';
		}
		if ( 'password' === $s['visibility'] ) {
			return self::has_cookie( $gallery_id, 'pass', $s['password'] ) ? '' : 'password';
		}
		return self::has_cookie( $gallery_id, 'key', $s['private_key'] ) ? '' : 'private';
	}

	public static function can_view( $gallery_id ) {
		return '' === self::view_block( $gallery_id );
	}

	/**
	 * The cover of a password gallery is shown on its locked page, so it can be seen before the
	 * password. Only that one photo, only for password (not private or closed) galleries.
	 */
	public static function is_locked_cover( $photo ) {
		$gid = (int) $photo->gallery_id;
		return 'password' === self::view_block( $gid )
			&& (int) CG_Galleries::cover_photo( $gid ) === (int) $photo->id
			&& ! $photo->hidden;
	}

	/**
	 * Downloads: whole album or a single photo.
	 *
	 * @param int         $gallery_id Gallery.
	 * @param object|null $photo      Photo row for single downloads; null for the whole album.
	 */
	public static function can_download( $gallery_id, $photo = null ) {
		if ( ! self::can_view( $gallery_id ) ) {
			return false;
		}
		if ( self::is_admin() ) {
			return true;
		}
		$s = CG_Galleries::get( $gallery_id );
		if ( 'client' === $s['download_who'] && ! self::is_client_of( $gallery_id ) ) {
			return false;
		}
		if ( null === $photo ) {
			return (bool) $s['album_download'];
		}
		return $s['photo_download'] && ! $photo->hidden && ! $photo->no_download && (int) $photo->gallery_id === (int) $gallery_id;
	}

	/**
	 * Is the download button worth showing to someone who isn't signed in yet?
	 * (They'd be able to download once they sign in as the client.)
	 */
	public static function download_needs_sign_in( $gallery_id ) {
		if ( is_user_logged_in() ) {
			return false;
		}
		$s = CG_Galleries::get( $gallery_id );
		return 'client' === $s['download_who'] && ( $s['album_download'] || $s['photo_download'] );
	}

	public static function can_favourite( $gallery_id ) {
		return self::can_view( $gallery_id )
			&& CG_Galleries::setting( $gallery_id, 'favourites' )
			&& ( self::is_client_of( $gallery_id ) || self::is_admin() );
	}

	/* ------------------------------------------------------------------ */
	/* Access cookies                                                      */
	/* ------------------------------------------------------------------ */

	private static function cookie_name( $gallery_id, $kind ) {
		return 'cg_' . $kind . '_' . absint( $gallery_id );
	}

	/**
	 * Cookie value is a signature over the gallery and its current secret, so changing the
	 * password (or private link) locks out everyone who used the old one.
	 */
	private static function signature( $gallery_id, $kind, $secret ) {
		return hash_hmac( 'sha256', $kind . '|' . absint( $gallery_id ) . '|' . $secret, wp_salt( 'auth' ) );
	}

	private static function has_cookie( $gallery_id, $kind, $secret ) {
		if ( '' === (string) $secret ) {
			return false;
		}
		$name = self::cookie_name( $gallery_id, $kind );
		if ( empty( $_COOKIE[ $name ] ) ) {
			return false;
		}
		$value = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		return hash_equals( self::signature( $gallery_id, $kind, $secret ), $value );
	}

	public static function grant( $gallery_id, $kind ) {
		$s      = CG_Galleries::get( $gallery_id );
		$secret = ( 'pass' === $kind ) ? $s['password'] : $s['private_key'];
		$name   = self::cookie_name( $gallery_id, $kind );
		$value  = self::signature( $gallery_id, $kind, $secret );
		setcookie(
			$name,
			$value,
			array(
				'expires'  => time() + self::COOKIE_DAYS * DAY_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ $name ] = $value;
	}

	/**
	 * Check a typed gallery password (case-sensitive), with a limit on attempts.
	 *
	 * @return true|string True on success, or an error message.
	 */
	public static function try_password( $gallery_id, $typed ) {
		if ( ! cg_rate_limit( 'pw|' . $gallery_id . '|' . cg_client_ip(), 10, 10 * MINUTE_IN_SECONDS ) ) {
			return __( 'Too many tries. Wait ten minutes and try again.', 'cheri-gallery' );
		}
		$real = (string) CG_Galleries::setting( $gallery_id, 'password' );
		if ( '' !== $real && hash_equals( $real, (string) $typed ) ) {
			self::grant( $gallery_id, 'pass' );
			return true;
		}
		return __( "That password doesn't match. Check your welcome email.", 'cheri-gallery' );
	}

	/**
	 * A private link (?key=...) opens the gallery and remembers it.
	 */
	public static function try_key( $gallery_id, $key ) {
		$real = (string) CG_Galleries::setting( $gallery_id, 'private_key' );
		if ( '' !== $real && hash_equals( $real, (string) $key ) ) {
			self::grant( $gallery_id, 'key' );
			return true;
		}
		return false;
	}
}
