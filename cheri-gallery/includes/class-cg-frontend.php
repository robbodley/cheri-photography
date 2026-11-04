<?php
/**
 * Front end: the gallery page, password gate, the [cheri_galleries] shortcode, and assets.
 */

defined( 'ABSPATH' ) || exit;

class CG_Frontend {

	/** Set while rendering one of the plugin's own full pages. */
	private static $own_page = false;

	/** Error message for the password form. */
	private static $password_error = '';

	/** State of the "sign in with your email" form on a locked gallery. */
	private static $access = array();

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_gallery_request' ), 5 );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'init', array( __CLASS__, 'register_assets' ) ); // Early: the gallery page localizes the script before wp_head.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_own_page' ), 100 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'isolate_styles' ), 9999 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_shortcode( 'cheri_galleries', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'cheri_gallery', array( __CLASS__, 'embed_shortcode' ) );
	}

	/**
	 * Mark the current request as one of the plugin's full-page screens.
	 */
	public static function own_page() {
		self::$own_page = true;
	}

	/**
	 * Header and footer around the plugin's pages: the website's own (theme) or the gallery's.
	 * Settings → Pages; by default the gallery's slim logo header and the website's footer.
	 */
	/** Which kind of plugin page is rendering: 'gallery' or 'page' (password, sign in, My galleries…). */
	private static $page_kind = 'page';

	public static function set_page_kind( $kind ) {
		self::$page_kind = ( 'gallery' === $kind ) ? 'gallery' : 'page';
	}

	public static function page_kind() {
		return self::$page_kind;
	}

	/**
	 * Header above this page: 'none' (gallery default: the cover photo goes to the top),
	 * 'theme' (the website's header; default on other pages) or 'gallery' (slim Cheri logo bar).
	 */
	public static function header_style() {
		$key   = 'gallery' === self::$page_kind ? 'gallery_header' : 'pages_header';
		$style = CG_Settings::get( $key );
		$style = in_array( $style, array( 'none', 'theme', 'gallery' ), true ) ? $style : ( 'gallery' === self::$page_kind ? 'none' : 'theme' );
		return apply_filters( 'cg_header_style', $style, self::$page_kind );
	}

	public static function theme_header() {
		return 'theme' === self::header_style();
	}

	public static function theme_footer() {
		return (bool) apply_filters( 'cg_theme_footer', 'theme' === CG_Settings::get( 'site_footer' ) );
	}

	/**
	 * Load the gallery styles after the theme's, so they win where both style the same thing.
	 */
	public static function enqueue_own_page() {
		if ( self::$own_page ) {
			wp_enqueue_style( 'cg-gallery' );
		}
	}

	public static function register_assets() {
		if ( wp_script_is( 'cg-gallery', 'registered' ) ) {
			return;
		}
		wp_register_style( 'cg-fonts', 'https://fonts.googleapis.com/css2?family=Marcellus&family=Open+Sans:wght@400;600&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		// front.css = components.css + gallery.css, scoped under .cg-page (tools/build-front-css.py).
		wp_register_style( 'cg-gallery', CG_URL . 'assets/css/front.css', array( 'cg-fonts' ), CG_VERSION );
		wp_register_script( 'cg-gallery', CG_URL . 'assets/js/gallery.js', array(), CG_VERSION, true );
	}

	/**
	 * On the plugin's own pages, drop the theme's stylesheets so the gallery looks the same
	 * whatever the theme does. Scripts (analytics etc.) are left alone.
	 */
	public static function isolate_styles() {
		// Theme styles are only dropped when neither the theme's header nor footer is shown.
		if ( ! self::$own_page || self::theme_header() || self::theme_footer() ) {
			return;
		}
		$keep = apply_filters( 'cg_keep_styles', array( 'cg-fonts', 'cg-gallery', 'admin-bar', 'dashicons' ) );
		foreach ( wp_styles()->queue as $handle ) {
			if ( ! in_array( $handle, $keep, true ) ) {
				wp_dequeue_style( $handle );
			}
		}
	}

	/**
	 * Gallery pages other than public ones are kept out of search engines.
	 */
	public static function robots( $robots ) {
		if ( self::$own_page ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			if ( is_singular( CG_Galleries::POST_TYPE ) && 'public' === CG_Galleries::setting( get_queried_object_id(), 'visibility' ) ) {
				unset( $robots['noindex'], $robots['nofollow'] );
			}
		}
		return $robots;
	}

	/**
	 * Before the page renders: password form, private links.
	 */
	public static function handle_gallery_request() {
		if ( ! is_singular( CG_Galleries::POST_TYPE ) ) {
			return;
		}
		$gid = get_queried_object_id();
		self::own_page();
		nocache_headers();

		if ( isset( $_POST['cg_access_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::handle_access_form( $gid );
			return;
		}

		if ( isset( $_POST['cg_gallery_password'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! isset( $_POST['_cgnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_cgnonce'] ) ), 'cg_pass_' . $gid ) ) {
				self::$password_error = __( 'The page had been open a while. Please try again.', 'cheri-gallery' );
				return;
			}
			$result = CG_Access::try_password( $gid, wp_unslash( $_POST['cg_gallery_password'] ) ); // phpcs:ignore
			if ( true === $result ) {
				wp_safe_redirect( get_permalink( $gid ) );
				exit;
			}
			self::$password_error = $result;
			return;
		}

		if ( isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( CG_Access::try_key( $gid, sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) ) { // phpcs:ignore
				wp_safe_redirect( get_permalink( $gid ) );
				exit;
			}
		}
	}

	/**
	 * Email + gallery password on a locked gallery:
	 *  - a client already on this gallery goes straight in (if the setting allows),
	 *  - anyone else gets an email link to confirm their address and joins as a guest.
	 */
	private static function handle_access_form( $gid ) {
		$email    = sanitize_email( wp_unslash( $_POST['cg_access_email'] ) ); // phpcs:ignore
		$first    = isset( $_POST['cg_access_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cg_access_name'] ) ) : ''; // phpcs:ignore
		$password = isset( $_POST['cg_access_password'] ) ? (string) wp_unslash( $_POST['cg_access_password'] ) : ''; // phpcs:ignore
		self::$access = array(
			'open'  => true,
			'email' => $email,
			'name'  => $first,
		);
		if ( ! isset( $_POST['_cgnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_cgnonce'] ) ), 'cg_access_' . $gid ) ) {
			self::$access['error'] = __( 'The page had been open a while. Please try again.', 'cheri-gallery' );
			return;
		}
		if ( ! is_email( $email ) ) {
			self::$access['error'] = __( 'Enter your email address.', 'cheri-gallery' );
			return;
		}
		$user = get_user_by( 'email', $email );

		if ( '' === $password ) {
			// Only allowed at the "add your first name" step: a brand-new email, from a browser that
			// has just entered the right gallery password. Signing in to an existing account always
			// needs the password typed.
			if ( $user || ! CG_Access::has_password_cookie( $gid ) ) {
				self::$access['error'] = __( 'Enter the gallery password.', 'cheri-gallery' );
				return;
			}
		} else {
			$ok = CG_Access::try_password( $gid, $password );
			if ( true !== $ok ) {
				self::$access['error'] = $ok;
				return;
			}
		}
		$role = $user ? CG_Galleries::role_on( $gid, $user->ID ) : '';

		// Already on this gallery: straight in.
		if ( $user && '' !== $role && CG_Auth::can_password_signin( $user ) && CG_Settings::get( 'password_signin' ) ) {
			CG_Auth::sign_in( $user->ID );
			wp_safe_redirect( get_permalink( $gid ) );
			exit;
		}
		// Admin / editor accounts never sign in with a gallery password.
		if ( $user && ! CG_Auth::can_password_signin( $user ) ) {
			self::$access['error'] = __( 'Please use Client sign in for this email.', 'cheri-gallery' );
			return;
		}
		// On this gallery, but password sign-in is switched off: send their usual sign-in link.
		if ( $user && '' !== $role ) {
			CG_Auth::send_link( $user, get_permalink( $gid ) );
			wp_safe_redirect( add_query_arg( 'cg_notice', 'link', get_permalink( $gid ) ) );
			exit;
		}
		// New here: ask for a first name, then email a confirmation link.
		if ( ! $user && '' === $first ) {
			self::$access['need_name'] = true;
			return;
		}
		if ( ! cg_rate_limit( 'signup|' . strtolower( $email ), 3, HOUR_IN_SECONDS ) ) {
			self::$access['error'] = __( 'We\'ve already sent a few emails to that address. Check your inbox (and spam).', 'cheri-gallery' );
			return;
		}
		CG_Auth::start_signup( $email, $first ? $first : ( $user ? $user->first_name : '' ), $gid );
		wp_safe_redirect( add_query_arg( 'cg_notice', 'confirm', get_permalink( $gid ) ) );
		exit;
	}

	public static function access_state() {
		return self::$access;
	}

	public static function template( $template ) {
		if ( ! is_singular( CG_Galleries::POST_TYPE ) ) {
			return $template;
		}
		$gid   = get_queried_object_id();
		$block = CG_Access::view_block( $gid );

		// The photographer can preview the locked cover page (?cg_preview=locked).
		$preview = CG_Access::is_admin() && isset( $_GET['cg_preview'] ) && 'password' === CG_Galleries::setting( $gid, 'visibility' ); // phpcs:ignore WordPress.Security.NonceVerification

		$mid_form = ! empty( self::$access ) && ( ! empty( self::$access['need_name'] ) || ! empty( self::$access['error'] ) ) && 'password' === CG_Galleries::setting( $gid, 'visibility' );
		if ( 'password' === $block || $preview || $mid_form ) {
			self::set_page_kind( 'gallery' ); // Same layout as the gallery: cover photo to the top.
			$GLOBALS['cg_view'] = array(
				'gallery_id' => $gid,
				'error'      => self::$password_error,
				'preview'    => $preview,
			);
			return cg_template( 'password.php' );
		}
		if ( '' !== $block ) {
			if ( 'draft' === $block ) {
				status_header( 404 );
			}
			$GLOBALS['cg_view'] = array(
				'gallery_id' => $gid,
				'reason'     => $block,
			);
			return cg_template( 'closed.php' );
		}

		self::set_page_kind( 'gallery' );
		$GLOBALS['cg_view'] = self::gallery_view( $gid );
		self::enqueue_gallery_script( $GLOBALS['cg_view'] );
		return cg_template( 'gallery.php' );
	}

	/**
	 * Everything gallery.php needs.
	 */
	public static function gallery_view( $gid ) {
		$s      = CG_Galleries::get( $gid );
		$photos = CG_Photos::for_gallery( $gid, array( 'include_hidden' => false ) );
		$by_set = array();
		foreach ( $s['sets'] as $set ) {
			$by_set[ $set ] = array();
		}
		foreach ( $photos as $p ) {
			$set = isset( $by_set[ $p->set_name ] ) ? $p->set_name : $s['sets'][0];
			$by_set[ $set ][] = $p;
		}
		$by_set = array_filter( $by_set );

		$cover_id = CG_Galleries::cover_photo( $gid );
		return array(
			'gallery_id'      => $gid,
			'settings'        => $s,
			'photos'          => $photos,
			'by_set'          => $by_set,
			'cover'           => $cover_id ? CG_Photos::get( $cover_id ) : null,
			'can_album_dl'    => CG_Access::can_download( $gid ),
			'can_favourite'   => CG_Access::can_favourite( $gid ),
			'sign_in_for_dl'  => CG_Access::download_needs_sign_in( $gid ),
			'sign_in_for_fav' => ! is_user_logged_in() && $s['favourites'] && $s['clients'],
			'login_url'       => CG_Auth::login_url( get_permalink( $gid ) ),
		);
	}

	private static function enqueue_gallery_script( array $view ) {
		$gid = $view['gallery_id'];
		self::register_assets(); // Safe to repeat; makes sure the handle exists before localizing.
		wp_enqueue_script( 'cg-gallery' );
		wp_localize_script(
			'cg-gallery',
			'CG_GALLERY',
			array(
				'id'           => $gid,
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'cg_fav_' . $gid ),
				'canFavourite' => (bool) $view['can_favourite'],
				'signInForFav' => (bool) $view['sign_in_for_fav'],
				'loginUrl'     => $view['login_url'],
				'protect'      => (bool) $view['settings']['protect'],
				'shareUrl'     => get_permalink( $gid ),
				'state'        => $view['can_favourite'] ? CG_Favourites::state( $gid, get_current_user_id() ) : null,
				'i18n'         => array(
					'newList'     => __( 'Name your new list', 'cheri-gallery' ),
					'rename'      => __( 'Rename this list', 'cheri-gallery' ),
					'confirmDel'  => __( 'Delete this list? The photos stay in the gallery.', 'cheri-gallery' ),
					'sent'        => __( 'Sent to Cheri', 'cheri-gallery' ),
					'notSent'     => __( 'Not sent', 'cheri-gallery' ),
					'photos'      => __( 'photos', 'cheri-gallery' ),
					'photo'       => __( 'photo', 'cheri-gallery' ),
					'linkCopied'  => __( 'Link copied', 'cheri-gallery' ),
					'thanks'      => __( 'Sent. Cheri will take a look.', 'cheri-gallery' ),
					'error'       => __( 'Something went wrong. Please try again.', 'cheri-gallery' ),
					'of'          => __( 'of', 'cheri-gallery' ),
					'intro'       => __( 'Heart a photo to add it to the list that\'s selected. Send a list to Cheri when you\'re ready.', 'cheri-gallery' ),
					'new'         => __( 'New list', 'cheri-gallery' ),
					'renameLink'  => __( 'Rename', 'cheri-gallery' ),
					'delete'      => __( 'Delete', 'cheri-gallery' ),
					'filter'      => __( 'Show only this list', 'cheri-gallery' ),
					'note'        => __( 'Note for Cheri', 'cheri-gallery' ),
					'notePh'      => __( 'e.g. Can the one by the clock tower be black and white?', 'cheri-gallery' ),
					'send'        => __( 'Send to Cheri', 'cheri-gallery' ),
					'favs'        => __( 'My favourites', 'cheri-gallery' ),
					'close'       => __( 'Close', 'cheri-gallery' ),
					'favourite'   => __( 'Favourite', 'cheri-gallery' ),
					'download'    => __( 'Download', 'cheri-gallery' ),
					'slideshow'   => __( 'Slideshow', 'cheri-gallery' ),
					'previous'    => __( 'Previous photo', 'cheri-gallery' ),
					'next'        => __( 'Next photo', 'cheri-gallery' ),
					'prevShort'   => __( 'Previous', 'cheri-gallery' ),
					'nextShort'   => __( 'Next', 'cheri-gallery' ),
					'back'        => __( 'Back to gallery', 'cheri-gallery' ),
					'favourited'  => __( 'Favourited', 'cheri-gallery' ),
					'signInFav'   => __( 'Sign in to favourite', 'cheri-gallery' ),
					'pause'       => __( 'Pause', 'cheri-gallery' ),
				),
			)
		);
	}

	/**
	 * [cheri_galleries type="wedding" limit="12"] lists galleries marked "Show on galleries page".
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'  => '',
				'limit' => 24,
			),
			$atts,
			'cheri_galleries'
		);
		wp_enqueue_style( 'cg-gallery' );

		$posts = get_posts(
			array(
				'post_type'      => CG_Galleries::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$cards = array();
		foreach ( $posts as $post ) {
			$s = CG_Galleries::get( $post->ID );
			if ( empty( $s['list_publicly'] ) || 'private' === $s['visibility'] || CG_Galleries::is_expired( $post->ID ) ) {
				continue;
			}
			if ( $atts['type'] && $atts['type'] !== $s['session_type'] ) {
				continue;
			}
			$cards[] = $post;
			if ( count( $cards ) >= (int) $atts['limit'] ) {
				break;
			}
		}
		return '<div class="cg-page cg-embed">' . cg_render( 'parts/album-grid.php', array( 'galleries' => $cards ) ) . '</div>';
	}

	/** Set once the gallery script has been set up for embeds on this page. */
	private static $embed_script = false;

	/**
	 * [cheri_gallery id="42" set="Ceremony" limit="12" columns="3" title="yes" link="no"]
	 * Shows a public gallery's photos (grid + enlarged view) on any page. View only: no hearts or downloads.
	 */
	public static function embed_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'      => 0,
				'set'     => '',
				'limit'   => 0,
				'columns' => 4,
				'title'   => 'no',
				'link'    => 'yes',
				'gap'     => 8,
			),
			$atts,
			'cheri_gallery'
		);
		$gid = absint( $atts['id'] );
		if ( ! CG_Galleries::is_gallery( $gid ) || ! self::embeddable( $gid ) ) {
			return current_user_can( CG_CAP )
				? '<p style="padding:12px;border:1px dashed #a4443a;color:#a4443a;">' . esc_html__( 'Cheri Gallery: this shortcode only shows public, published galleries that are still open. (Only you can see this note.)', 'cheri-gallery' ) . '</p>'
				: '';
		}

		$args = array( 'include_hidden' => false );
		if ( '' !== $atts['set'] ) {
			$args['set'] = sanitize_text_field( $atts['set'] );
		}
		if ( absint( $atts['limit'] ) ) {
			$args['limit'] = absint( $atts['limit'] );
		}
		$photos = CG_Photos::for_gallery( $gid, $args );
		if ( ! $photos ) {
			return '';
		}

		self::register_assets();
		wp_enqueue_style( 'cg-gallery' );
		if ( ! self::$embed_script && ! wp_script_is( 'cg-gallery', 'enqueued' ) ) {
			self::$embed_script = true;
			self::enqueue_gallery_script(
				array(
					'gallery_id'      => $gid,
					'can_favourite'   => false,
					'sign_in_for_fav' => false,
					'login_url'       => '',
					'settings'        => array( 'protect' => (bool) CG_Galleries::setting( $gid, 'protect' ) ),
				)
			);
		}

		return cg_render(
			'parts/embed.php',
			array(
				'gallery_id' => $gid,
				'photos'     => $photos,
				'columns'    => min( 6, max( 2, absint( $atts['columns'] ) ) ),
				'gap'        => min( 30, absint( $atts['gap'] ) ),
				'show_title' => 'yes' === strtolower( $atts['title'] ),
				'show_link'  => 'no' !== strtolower( $atts['link'] ),
			)
		);
	}

	/**
	 * Only public, published, open galleries can be shown on other pages.
	 */
	public static function embeddable( $gallery_id ) {
		return 'publish' === get_post_status( $gallery_id )
			&& 'public' === CG_Galleries::setting( $gallery_id, 'visibility' )
			&& ! CG_Galleries::is_expired( $gallery_id );
	}

	/**
	 * Cover URL for album cards. Locked galleries show a plain placeholder rather than leaking a photo.
	 */
	public static function card_cover_url( $gallery_id ) {
		$cid = CG_Galleries::cover_photo( $gallery_id );
		if ( ! $cid ) {
			return '';
		}
		$photo = CG_Photos::get( $cid );
		if ( ! $photo ) {
			return '';
		}
		if ( CG_Access::can_view( $gallery_id ) ) {
			return CG_Delivery::photo_url( $photo, 'thumb', false, true );
		}
		// Password galleries listed publicly show their cover from the public email copy.
		return CG_Email::cover_url( $photo );
	}
}
