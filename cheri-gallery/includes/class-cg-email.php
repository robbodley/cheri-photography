<?php
/**
 * Sending email: the branded gallery email, sign-in links and favourites alerts.
 */

defined( 'ABSPATH' ) || exit;

class CG_Email {

	/** Plain-text alternative for the message currently being sent. */
	private static $alt_body = '';

	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'add_alt_body' ) );
	}

	/**
	 * Send an HTML email with a plain-text version, from and replying to the studio address.
	 *
	 * @param string|array $to Recipients.
	 */
	public static function send( $to, $subject, $html, $text = '' ) {
		$from_email = CG_Settings::get( 'from_email' );
		$from_name  = CG_Settings::get( 'from_name' );
		$headers    = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', self::header_safe( $from_name ), $from_email ),
			sprintf( 'Reply-To: %s <%s>', self::header_safe( $from_name ), $from_email ),
		);
		self::$alt_body = $text ? $text : wp_strip_all_tags( $html );
		$ok             = wp_mail( $to, $subject, $html, $headers );
		self::$alt_body = '';
		return $ok;
	}

	private static function header_safe( $s ) {
		return trim( str_replace( array( "\r", "\n", '<', '>', '"' ), '', (string) $s ) );
	}

	public static function add_alt_body( $phpmailer ) {
		if ( self::$alt_body ) {
			$phpmailer->AltBody = self::$alt_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		}
	}

	/* ------------------------------------------------------------------ */
	/* The gallery email                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Replace {names}, {date}, {link} and {password} in template text.
	 */
	public static function fill( $text, $gallery_id ) {
		$s = CG_Galleries::get( $gallery_id );
		return strtr(
			(string) $text,
			array(
				'{names}'    => get_the_title( $gallery_id ),
				'{date}'     => cg_format_date( $s['event_date'] ),
				'{link}'     => CG_Galleries::link( $gallery_id ),
				'{password}' => $s['password'],
			)
		);
	}

	/**
	 * Build the gallery email.
	 *
	 * @param int   $gallery_id Gallery.
	 * @param array $opts       message, include_password, include_signin, cover_photo.
	 * @return array [ html, text ]
	 */
	public static function gallery_email( $gallery_id, array $opts ) {
		$s       = CG_Galleries::get( $gallery_id );
		$message = self::fill( isset( $opts['message'] ) ? $opts['message'] : '', $gallery_id );
		$cover   = ! empty( $opts['cover_photo'] ) ? CG_Photos::get( absint( $opts['cover_photo'] ) ) : null;
		if ( ! $cover || (int) $cover->gallery_id !== (int) $gallery_id ) {
			$cid   = CG_Galleries::cover_photo( $gallery_id );
			$cover = $cid ? CG_Photos::get( $cid ) : null;
		}
		$show_password = ! empty( $opts['include_password'] ) && 'password' === $s['visibility'] && '' !== $s['password'];
		$show_signin   = ! empty( $opts['include_signin'] ) && $s['clients'];

		$vars = array(
			'names'         => get_the_title( $gallery_id ),
			'date'          => cg_format_date( $s['event_date'] ),
			'cover_url'     => $cover ? self::cover_url( $cover ) : '',
			'message'       => $message,
			'password'      => $show_password ? $s['password'] : '',
			'gallery_url'   => CG_Galleries::link( $gallery_id ),
			'signin_url'    => $show_signin ? CG_Auth::login_url( CG_Galleries::link( $gallery_id ) ) : '',
			'client_names'  => $show_signin ? self::client_first_names( $s['clients'] ) : '',
			'settings'      => CG_Settings::all(),
		);
		$html = cg_render( 'email-gallery.php', $vars );

		$text  = $message . "\n\n";
		$text .= __( 'View the gallery:', 'cheri-gallery' ) . ' ' . $vars['gallery_url'] . "\n";
		if ( $vars['password'] ) {
			$text .= __( 'Password (case-sensitive):', 'cheri-gallery' ) . ' ' . $vars['password'] . "\n";
		}
		if ( $vars['signin_url'] ) {
			$text .= "\n" . sprintf(
				/* translators: 1: names, 2: link */
				__( '%1$s: sign in to make favourites and download: %2$s', 'cheri-gallery' ),
				$vars['client_names'],
				$vars['signin_url']
			) . "\n";
		}
		$text .= "\n" . CG_Settings::get( 'from_name' ) . "\n" . CG_Settings::get( 'website_url' ) . "\n";
		return array( $html, $text );
	}

	private static function client_first_names( array $ids ) {
		$names = array();
		foreach ( $ids as $id ) {
			$u = get_userdata( $id );
			if ( $u ) {
				$names[] = $u->first_name ? $u->first_name : $u->display_name;
			}
		}
		return implode( ' & ', $names );
	}

	/**
	 * Email programs can't sign in to see protected photos, so the cover gets a public copy
	 * (1200px, no watermark) with an unguessable name. Only the photo chosen as the cover.
	 */
	public static function cover_url( $photo ) {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . 'cheri-gallery-email';
		cg_ensure_dir( $dir );
		$pos  = (string) CG_Galleries::setting( $photo->gallery_id, 'cover_position' );
		$pos  = in_array( $pos, array( 'top', 'bottom' ), true ) ? $pos : 'center';
		$name = absint( $photo->gallery_id ) . '-' . substr( hash_hmac( 'sha256', $photo->id . '|' . $photo->file . '|' . $pos, wp_salt( 'auth' ) ), 0, 20 ) . '.jpg';
		$path = $dir . '/' . $name;
		if ( ! file_exists( $path ) ) {
			$editor = wp_get_image_editor( CG_Photos::original_path( $photo ) );
			if ( is_wp_error( $editor ) ) {
				return '';
			}
			if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
				$editor->maybe_exif_rotate();
			}
			$editor->resize( 1200, 800, array( 'center', $pos ) );
			$editor->set_quality( 82 );
			$saved = $editor->save( $path, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				return '';
			}
		}
		return trailingslashit( $uploads['baseurl'] ) . 'cheri-gallery-email/' . $name;
	}

	/**
	 * Log a sent gallery email.
	 */
	public static function log( $gallery_id, array $recipients, $subject ) {
		global $wpdb;
		$t = CG_Install::tables();
		$wpdb->insert(
			$t['emails'],
			array(
				'gallery_id' => $gallery_id,
				'recipients' => implode( ', ', $recipients ),
				'subject'    => mb_substr( $subject, 0, 255 ),
				'sent_by'    => get_current_user_id(),
				'sent_at'    => current_time( 'mysql' ),
			)
		);
	}

	public static function log_for( $gallery_id ) {
		global $wpdb;
		$t = CG_Install::tables();
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $t['emails'] . ' WHERE gallery_id = %d ORDER BY sent_at DESC LIMIT 20', $gallery_id ) ); // phpcs:ignore
	}

	/* ------------------------------------------------------------------ */
	/* Alerts to Cheri                                                     */
	/* ------------------------------------------------------------------ */

	public static function notify_new_guest( $user, $gallery_id ) {
		/* translators: 1: name, 2: gallery */
		$subject = sprintf( __( '%1$s signed up to %2$s', 'cheri-gallery' ), $user->display_name, get_the_title( $gallery_id ) );
		$text    = sprintf(
			/* translators: 1: name, 2: email, 3: gallery */
			__( '%1$s (%2$s) used the gallery password to sign up to %3$s and confirmed their email. They\'re a guest: they can view and make favourites. Make them a client on the gallery if they should have client downloads.', 'cheri-gallery' ),
			$user->display_name,
			$user->user_email,
			get_the_title( $gallery_id )
		);
		$html = cg_render(
			'email-layout.php',
			array(
				'settings' => CG_Settings::all(),
				'content'  => '<p style="margin:0;">' . esc_html( $text ) . '</p>',
			)
		);
		self::send( CG_Settings::get( 'notify_email' ), $subject, $html, $text );
	}

	public static function notify_favourites_sent( $list ) {
		if ( ! $list ) {
			return;
		}
		$user  = get_userdata( $list->user_id );
		$who   = $user ? $user->display_name : __( 'A client', 'cheri-gallery' );
		$count = count( CG_Favourites::photo_ids( $list->id ) );
		$link  = admin_url( 'admin.php?page=cg-clients&client=' . absint( $list->user_id ) . '#list-' . absint( $list->id ) );

		/* translators: 1: client name, 2: list name */
		$subject = sprintf( __( '%1$s sent "%2$s"', 'cheri-gallery' ), $who, $list->name );
		$text    = sprintf(
			/* translators: 1: client, 2: count, 3: list name, 4: gallery */
			__( '%1$s sent %2$d photos in "%3$s" from %4$s.', 'cheri-gallery' ),
			$who,
			$count,
			$list->name,
			get_the_title( $list->gallery_id )
		);
		if ( $list->note ) {
			$text .= "\n\n" . __( 'Their note:', 'cheri-gallery' ) . "\n" . $list->note;
		}
		$html_body = '<p style="margin:0 0 16px;">' . nl2br( esc_html( $text ) ) . '</p>'
			. '<p style="margin:0;"><a href="' . esc_url( $link ) . '" style="display:inline-block;background:#7d5f41;color:#ffffff;text-decoration:none;font:600 11px/16px Arial,sans-serif;letter-spacing:2px;text-transform:uppercase;padding:12px 24px;">' . esc_html__( 'See their picks', 'cheri-gallery' ) . '</a></p>';
		$text     .= "\n\n" . __( 'See their picks:', 'cheri-gallery' ) . ' ' . $link;

		$html = cg_render(
			'email-layout.php',
			array(
				'settings' => CG_Settings::all(),
				'content'  => $html_body,
			)
		);
		self::send( CG_Settings::get( 'notify_email' ), $subject, $html, $text );
	}
}
