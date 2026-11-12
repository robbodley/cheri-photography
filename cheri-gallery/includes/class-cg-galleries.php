<?php
/**
 * The gallery post type and its settings.
 */

defined( 'ABSPATH' ) || exit;

class CG_Galleries {

	const POST_TYPE = 'cheri_gallery';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
		// Never list gallery addresses in sitemaps (WordPress core and Yoast).
		add_filter(
			'wp_sitemaps_post_types',
			function ( $types ) {
				unset( $types[ self::POST_TYPE ] );
				return $types;
			}
		);
		add_filter(
			'wpseo_sitemap_exclude_post_type',
			function ( $exclude, $type ) {
				return self::POST_TYPE === $type ? true : $exclude;
			},
			10,
			2
		);
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Galleries', 'cheri-gallery' ),
					'menu_name'     => __( 'Cheri Gallery', 'cheri-gallery' ),
					'singular_name' => __( 'Gallery', 'cheri-gallery' ),
					'add_new'       => __( 'Add gallery', 'cheri-gallery' ),
					'add_new_item'  => __( 'Add gallery', 'cheri-gallery' ),
					'edit_item'     => __( 'Edit gallery', 'cheri-gallery' ),
					'all_items'     => __( 'Galleries', 'cheri-gallery' ),
					'search_items'  => __( 'Search galleries', 'cheri-gallery' ),
					'not_found'     => __( 'No galleries yet.', 'cheri-gallery' ),
				),
				'public'              => true,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-format-gallery',
				'menu_position'       => 26,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => sanitize_title( CG_Settings::get( 'gallery_base' ) ),
					'with_front' => false,
				),
				'capability_type'     => 'post',
				'capabilities'        => array(
					'edit_post'          => CG_CAP,
					'read_post'          => 'read',
					'delete_post'        => CG_CAP,
					'edit_posts'         => CG_CAP,
					'edit_others_posts'  => CG_CAP,
					'publish_posts'      => CG_CAP,
					'read_private_posts' => CG_CAP,
					'delete_posts'       => CG_CAP,
					'create_posts'       => CG_CAP,
				),
				'map_meta_cap'        => false,
			)
		);
	}

	/**
	 * Every per-gallery setting with its default.
	 */
	public static function setting_defaults() {
		return array(
			'clients'          => array(),
			'guests'           => array(), // Signed themselves up with the gallery password.
			'session_type'     => array_key_exists( 'wedding', cg_session_types() ) ? 'wedding' : (string) key( cg_session_types() ),
			'event_date'       => '',
			'cover_photo'      => 0,
			'cover_position'   => 'center',
			'photo_order'      => 'name', // name | name_desc | upload | upload_desc
			'visibility'       => 'password',
			'password'         => '',
			'private_key'      => '',
			'list_publicly'    => 0,
			'featured'         => 0, // Home-page position 1–6; 0 = not featured.
			'album_download'   => 1,
			'photo_download'   => 1,
			'download_size'    => 'full',
			'download_who'     => 'client',
			'watermark'        => CG_Settings::get( 'default_watermark' ),
			'protect'          => 1,
			'cover_watermark'  => 0,
			'even_bottom'      => 1, // Line up the bottom edge of the photo grid.
			'favourites'       => 1,
			'expiry'           => '',
			'sets'             => array( __( 'Highlights', 'cheri-gallery' ) ),
		);
	}

	/**
	 * All settings for a gallery, merged with defaults.
	 */
	public static function get( $gallery_id ) {
		$saved = get_post_meta( $gallery_id, '_cg_settings', true );
		$s     = wp_parse_args( is_array( $saved ) ? $saved : array(), self::setting_defaults() );
		$s['clients'] = array_values( array_filter( array_map( 'absint', (array) $s['clients'] ) ) );
		$s['guests']  = array_values( array_diff( array_filter( array_map( 'absint', (array) $s['guests'] ) ), $s['clients'] ) );
		$s['sets']    = array_values( array_filter( array_map( 'strval', (array) $s['sets'] ), 'strlen' ) );
		if ( empty( $s['sets'] ) ) {
			$s['sets'] = array( __( 'Highlights', 'cheri-gallery' ) );
		}
		return $s;
	}

	public static function setting( $gallery_id, $key ) {
		$s = self::get( $gallery_id );
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	/**
	 * Save settings after validating every field.
	 */
	public static function save( $gallery_id, array $input ) {
		$s = self::get( $gallery_id );

		if ( isset( $input['clients'] ) ) {
			$s['clients'] = array_values( array_unique( array_filter( array_map( 'absint', (array) $input['clients'] ) ) ) );
		}
		if ( isset( $input['guests'] ) ) {
			$s['guests'] = array_values( array_unique( array_filter( array_map( 'absint', (array) $input['guests'] ) ) ) );
		}
		$s['guests'] = array_values( array_diff( (array) $s['guests'], $s['clients'] ) ); // A client is never also a guest.
		if ( isset( $input['session_type'] ) && array_key_exists( $input['session_type'], cg_session_types() ) ) {
			$s['session_type'] = $input['session_type'];
		}
		if ( isset( $input['event_date'] ) ) {
			$s['event_date'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input['event_date'] ) ? $input['event_date'] : '';
		}
		if ( isset( $input['expiry'] ) ) {
			$s['expiry'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input['expiry'] ) ? $input['expiry'] : '';
		}
		if ( isset( $input['featured'] ) ) {
			$s['featured'] = min( 6, absint( $input['featured'] ) );
		}
		if ( isset( $input['photo_order'] ) && array_key_exists( $input['photo_order'], CG_Photos::orders() ) ) {
			$s['photo_order'] = $input['photo_order'];
		}
		if ( isset( $input['cover_position'] ) && in_array( $input['cover_position'], array( 'top', 'center', 'bottom' ), true ) ) {
			$s['cover_position'] = $input['cover_position'];
		}
		if ( isset( $input['cover_photo'] ) ) {
			$s['cover_photo'] = absint( $input['cover_photo'] );
		}
		if ( isset( $input['visibility'] ) && in_array( $input['visibility'], array( 'public', 'password', 'private' ), true ) ) {
			$s['visibility'] = $input['visibility'];
		}
		if ( isset( $input['password'] ) ) {
			// Gallery passwords are shared by email, so they are kept readable for the share email.
			$s['password'] = substr( sanitize_text_field( $input['password'] ), 0, 64 );
		}
		if ( isset( $input['download_size'] ) && in_array( $input['download_size'], array( 'full', 'web' ), true ) ) {
			$s['download_size'] = $input['download_size'];
		}
		if ( isset( $input['download_who'] ) && in_array( $input['download_who'], array( 'client', 'anyone' ), true ) ) {
			$s['download_who'] = $input['download_who'];
		}
		if ( isset( $input['watermark'] ) && array_key_exists( $input['watermark'], CG_Images::watermark_styles() ) ) {
			$s['watermark'] = $input['watermark'];
		}
		foreach ( array( 'album_download', 'photo_download', 'favourites', 'protect', 'list_publicly', 'cover_watermark', 'even_bottom' ) as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$s[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
			}
		}
		if ( isset( $input['sets'] ) ) {
			$sets = array();
			foreach ( (array) $input['sets'] as $name ) {
				$name = trim( sanitize_text_field( $name ) );
				if ( '' !== $name && ! in_array( $name, $sets, true ) ) {
					$sets[] = mb_substr( $name, 0, 100 );
				}
			}
			$s['sets'] = $sets ? $sets : array( __( 'Highlights', 'cheri-gallery' ) );
		}

		if ( 'password' === $s['visibility'] && '' === $s['password'] ) {
			$s['password'] = self::suggest_password( $gallery_id );
		}
		if ( '' === $s['private_key'] ) {
			$s['private_key'] = wp_generate_password( 20, false, false );
		}

		update_post_meta( $gallery_id, '_cg_settings', $s );
		return $s;
	}

	/**
	 * A friendly password like the ones used on Pixieset: initials + day/month of the event.
	 */
	public static function suggest_password( $gallery_id ) {
		$title    = get_the_title( $gallery_id );
		$initials = '';
		foreach ( preg_split( '/[\s&,+]+/', strtolower( $title ) ) as $word ) {
			if ( preg_match( '/^[a-z]/', $word ) && ! in_array( $word, array( 'and', 'the' ), true ) ) {
				$initials .= $word[0];
			}
		}
		$initials = $initials ? substr( $initials, 0, 3 ) : 'cg';
		$date     = self::setting( $gallery_id, 'event_date' );
		$suffix   = $date ? gmdate( 'dm', strtotime( $date ) ) : (string) wp_rand( 1000, 9999 );
		return $initials . $suffix;
	}

	public static function is_gallery( $post ) {
		$post = get_post( $post );
		return $post && self::POST_TYPE === $post->post_type;
	}

	public static function is_expired( $gallery_id ) {
		$expiry = self::setting( $gallery_id, 'expiry' );
		if ( ! $expiry ) {
			return false;
		}
		return current_time( 'Y-m-d' ) > $expiry;
	}

	/**
	 * Link to the gallery. Private galleries carry their secret key.
	 */
	public static function link( $gallery_id, $with_key = true ) {
		$url = get_permalink( $gallery_id );
		if ( $with_key && 'private' === self::setting( $gallery_id, 'visibility' ) ) {
			$url = add_query_arg( 'key', rawurlencode( self::setting( $gallery_id, 'private_key' ) ), $url );
		}
		return $url;
	}

	/**
	 * Galleries a client account belongs to.
	 */
	public static function for_client( $user_id, $include_unpublished = false ) {
		$user_id = absint( $user_id );
		$all     = self::all_published( $include_unpublished );
		return array_values(
			array_filter(
				$all,
				function ( $post ) use ( $user_id ) {
					return '' !== self::role_on( $post->ID, $user_id );
				}
			)
		);
	}

	/**
	 * Every published gallery, newest shoot first.
	 */
	public static function all_published( $include_unpublished = false ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => $include_unpublished ? array( 'publish', 'draft', 'pending', 'future', 'private' ) : 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		usort(
			$posts,
			function ( $a, $b ) {
				return strcmp( (string) self::setting( $b->ID, 'event_date' ), (string) self::setting( $a->ID, 'event_date' ) );
			}
		);
		return $posts;
	}

	/**
	 * CSS object-position for the cover photo ("center top" etc.).
	 */
	public static function cover_css_position( $gallery_id ) {
		$pos = self::setting( $gallery_id, 'cover_position' );
		return 'center ' . ( in_array( $pos, array( 'top', 'bottom' ), true ) ? $pos : 'center' );
	}

	/**
	 * How a person belongs to a gallery: 'client' (added by Cheri), 'guest' (signed up with the
	 * gallery password and confirmed their email) or '' (not at all).
	 */
	public static function role_on( $gallery_id, $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return '';
		}
		if ( in_array( $user_id, self::setting( $gallery_id, 'clients' ), true ) ) {
			return 'client';
		}
		return in_array( $user_id, self::setting( $gallery_id, 'guests' ), true ) ? 'guest' : '';
	}

	/**
	 * Add someone as a guest (no-op if they're already a client).
	 */
	public static function add_guest( $gallery_id, $user_id ) {
		if ( '' !== self::role_on( $gallery_id, $user_id ) ) {
			return;
		}
		$guests   = self::setting( $gallery_id, 'guests' );
		$guests[] = absint( $user_id );
		self::save( $gallery_id, array( 'guests' => $guests ) );
	}

	/**
	 * Add or remove one client on a gallery.
	 */
	public static function link_client( $gallery_id, $user_id, $add = true ) {
		$clients = self::setting( $gallery_id, 'clients' );
		$clients = $add ? array_merge( $clients, array( absint( $user_id ) ) ) : array_diff( $clients, array( absint( $user_id ) ) );
		$guests  = array_diff( self::setting( $gallery_id, 'guests' ), array( absint( $user_id ) ) );
		self::save( $gallery_id, array( 'clients' => array_values( array_unique( $clients ) ), 'guests' => array_values( $guests ) ) );
		self::sync_client_index( $gallery_id );
	}

	/**
	 * Keep a flat _cg_client meta per client so galleries can be looked up by client.
	 */
	public static function sync_client_index( $gallery_id ) {
		delete_post_meta( $gallery_id, '_cg_client' );
		foreach ( self::setting( $gallery_id, 'clients' ) as $uid ) {
			add_post_meta( $gallery_id, '_cg_client', $uid );
		}
	}

	public static function on_delete( $post_id ) {
		if ( ! self::is_gallery( $post_id ) ) {
			return;
		}
		CG_Photos::delete_all_for_gallery( $post_id );
		CG_Favourites::delete_for_gallery( $post_id );
	}

	/**
	 * Cover photo ID: the chosen one, else the first visible photo.
	 */
	public static function cover_photo( $gallery_id ) {
		$id = (int) self::setting( $gallery_id, 'cover_photo' );
		if ( $id ) {
			$p = CG_Photos::get( $id );
			if ( $p && (int) $p->gallery_id === (int) $gallery_id ) {
				return $id;
			}
		}
		$photos = CG_Photos::for_gallery( $gallery_id, array( 'include_hidden' => false, 'limit' => 1 ) );
		return $photos ? (int) $photos[0]->id : 0;
	}
}
