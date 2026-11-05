<?php
/**
 * Sending email: the branded gallery email, sign-in links and favourites alerts.
 */

defined( 'ABSPATH' ) || exit;

class CG_Email {

	/** Plain-text alternative for the message currently being sent. */
	private static $alt_body = '';

	/** Images to attach inline for the message being sent: [ [ 'path' => ..., 'cid' => ..., 'name' => ... ], ... ]. */
	private static $embeds = array();

	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'add_alt_body' ) );
	}

	/**
	 * Send an HTML email with a plain-text version, from and replying to the studio address.
	 *
	 * @param string|array $to Recipients.
	 */
	public static function send( $to, $subject, $html, $text = '', array $embeds = array() ) {
		$from_email = CG_Settings::get( 'from_email' );
		$from_name  = CG_Settings::get( 'from_name' );
		$headers    = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', self::header_safe( $from_name ), $from_email ),
			sprintf( 'Reply-To: %s <%s>', self::header_safe( $from_name ), $from_email ),
		);
		self::$alt_body = $text ? $text : wp_strip_all_tags( $html );
		self::$embeds   = $embeds;
		$ok             = wp_mail( $to, $subject, $html, $headers );
		self::$alt_body = '';
		self::$embeds   = array();
		return $ok;
	}

	private static function header_safe( $s ) {
		return trim( str_replace( array( "\r", "\n", '<', '>', '"' ), '', (string) $s ) );
	}

	public static function add_alt_body( $phpmailer ) {
		if ( self::$alt_body ) {
			$phpmailer->AltBody = self::$alt_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		}
		// Thumbnails shown in the email body (src="cid:..."), carried inside the email itself.
		foreach ( self::$embeds as $img ) {
			try {
				$phpmailer->addEmbeddedImage( $img['path'], $img['cid'], $img['name'], 'base64', 'image/jpeg' );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				// A missing thumbnail shouldn't stop the email.
			}
		}
	}

	/**
	 * Small, clean JPEG thumbnails of photos for an email, written to the temp folder.
	 *
	 * @return array List of [ path, cid, name, photo ].
	 */
	private static function email_thumbs( array $photo_ids, $max = 60, $width = 240 ) {
		$out = array();
		$dir = trailingslashit( get_temp_dir() );
		foreach ( array_slice( $photo_ids, 0, $max ) as $i => $pid ) {
			$photo = CG_Photos::get( $pid );
			if ( ! $photo ) {
				continue;
			}
			$src = CG_Images::ensure( $photo, 'thumb', 'off' );
			if ( ! $src ) {
				continue;
			}
			$editor = wp_get_image_editor( $src );
			if ( is_wp_error( $editor ) ) {
				continue;
			}
			$editor->resize( $width, $width * 2, false );
			$editor->set_quality( 72 );
			$path  = $dir . 'cg-fav-' . absint( $pid ) . '-' . wp_generate_password( 6, false, false ) . '.jpg';
			$saved = $editor->save( $path, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				continue;
			}
			$out[] = array(
				'path'  => ! empty( $saved['path'] ) ? $saved['path'] : $path,
				'cid'   => 'cgfav' . absint( $pid ) . '_' . $i,
				'name'  => sanitize_file_name( $photo->original_name ),
				'photo' => $photo,
			);
		}
		return $out;
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
		$user    = get_userdata( $list->user_id );
		$who     = $user ? $user->display_name : __( 'A client', 'cheri-gallery' );
		$ids     = CG_Favourites::photo_ids( $list->id );
		$count   = count( $ids );
		$names   = CG_Favourites::filenames( $list->id );
		$gallery = get_the_title( $list->gallery_id );

		/* translators: 1: client name, 2: list name */
		$subject = sprintf( __( '%1$s sent "%2$s"', 'cheri-gallery' ), $who, $list->name );
		/* translators: 1: client, 2: count, 3: list name, 4: gallery */
		$intro = sprintf( _n( '%1$s sent %2$d photo in "%3$s" from %4$s.', '%1$s sent %2$d photos in "%3$s" from %4$s.', $count, 'cheri-gallery' ), $who, $count, $list->name, $gallery );

		$thumbs = self::email_thumbs( $ids );

		// HTML: intro, note, thumbnail grid (3 across) with file names, then every file name in one line.
		$h  = '<p style="margin:0 0 16px;">' . esc_html( $intro ) . '</p>';
		if ( $list->note ) {
			$h .= '<p style="margin:0 0 6px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#6b625a;">' . esc_html__( 'Their note', 'cheri-gallery' ) . '</p>';
			$h .= '<p style="margin:0 0 20px;padding:10px 14px;background:#fbf8f2;font-style:italic;">' . nl2br( esc_html( $list->note ) ) . '</p>';
		}
		if ( $thumbs ) {
			$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">';
			foreach ( array_chunk( $thumbs, 3 ) as $row ) {
				$h .= '<tr>';
				foreach ( $row as $t ) {
					$label = pathinfo( $t['photo']->original_name, PATHINFO_FILENAME );
					$h    .= '<td width="33%" valign="top" style="padding:4px;">'
						. '<img src="cid:' . esc_attr( $t['cid'] ) . '" width="160" alt="' . esc_attr( $label ) . '" style="display:block;width:100%;max-width:160px;height:auto;border:0;">'
						. '<div style="font:12px/16px Arial,sans-serif;color:#2e2a27;padding-top:4px;word-break:break-all;">' . esc_html( $label ) . '</div>'
						. '</td>';
				}
				for ( $pad = count( $row ); $pad < 3; $pad++ ) {
					$h .= '<td width="33%" style="padding:4px;"></td>';
				}
				$h .= '</tr>';
			}
			$h .= '</table>';
			if ( $count > count( $thumbs ) ) {
				/* translators: %d: number of photos shown */
				$h .= '<p style="margin:0 0 16px;font-size:12px;color:#6b625a;">' . esc_html( sprintf( __( 'Showing the first %d photos; every file name is listed below.', 'cheri-gallery' ), count( $thumbs ) ) ) . '</p>';
			}
		}
		if ( $names ) {
			$h .= '<p style="margin:0 0 6px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#6b625a;">' . esc_html__( 'File names (paste into Lightroom\'s search)', 'cheri-gallery' ) . '</p>';
			$h .= '<p style="margin:0;padding:10px 14px;background:#fbf8f2;font:13px/20px Menlo,Consolas,monospace;word-break:break-word;">' . esc_html( implode( ', ', $names ) ) . '</p>';
		}

		// Plain-text version.
		$text = $intro;
		if ( $list->note ) {
			$text .= "\n\n" . __( 'Their note:', 'cheri-gallery' ) . "\n" . $list->note;
		}
		if ( $names ) {
			$text .= "\n\n" . __( 'File names:', 'cheri-gallery' ) . "\n" . implode( ', ', $names );
		}

		$html = cg_render(
			'email-layout.php',
			array(
				'settings' => CG_Settings::all(),
				'content'  => $h,
			)
		);
		self::send( CG_Settings::get( 'notify_email' ), $subject, $html, $text, $thumbs );

		foreach ( $thumbs as $t ) {
			@unlink( $t['path'] ); // phpcs:ignore
		}
	}
}
