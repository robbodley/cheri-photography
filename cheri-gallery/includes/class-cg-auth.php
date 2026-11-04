<?php
/**
 * Client accounts: sign in by emailed link, the "My galleries" page, and keeping clients out of wp-admin.
 *
 *   /client-login/   ask for an email, or confirm a sign-in link
 *   /my-galleries/   a signed-in client's galleries
 */

defined( 'ABSPATH' ) || exit;

class CG_Auth {

	const LINK_MINUTES = 60;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 2 );
		add_action( 'admin_init', array( __CLASS__, 'keep_clients_out_of_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	public static function add_rewrites() {
		add_rewrite_rule( '^client-login/?$', 'index.php?cg_page=login', 'top' );
		add_rewrite_rule( '^my-galleries/?$', 'index.php?cg_page=account', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'cg_page';
		return $vars;
	}

	public static function login_url( $redirect = '' ) {
		$url = home_url( 'client-login/' );
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url ) : $url;
	}

	public static function account_url() {
		return home_url( 'my-galleries/' );
	}

	public static function is_client( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		return $user && $user->exists() && in_array( CG_ROLE, (array) $user->roles, true );
	}

	/* ------------------------------------------------------------------ */
	/* Pages                                                               */
	/* ------------------------------------------------------------------ */

	public static function route() {
		$page = get_query_var( 'cg_page' );
		if ( 'login' !== $page && 'account' !== $page ) {
			return;
		}
		global $wp_query;
		$wp_query->is_404 = false;
		status_header( 200 );
		$title = 'login' === $page ? __( 'Client sign in', 'cheri-gallery' ) : __( 'My galleries', 'cheri-gallery' );
		add_filter(
			'pre_get_document_title',
			function () use ( $title ) {
				return $title . ' – ' . CG_Settings::get( 'from_name' );
			}
		);
		if ( 'login' === $page ) {
			self::login_page();
		} elseif ( 'account' === $page ) {
			self::account_page();
		}
	}

	private static function safe_redirect_target() {
		$raw = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return wp_validate_redirect( $raw, self::account_url() );
	}

	private static function login_page() {
		nocache_headers();
		CG_Frontend::own_page();
		$redirect = self::safe_redirect_target();
		$state    = 'ask';
		$message  = '';
		$email    = '';

		// Step 2: the link from the email. Show a button so link scanners can't use it up.
		$uid   = isset( $_GET['u'] ) ? absint( $_GET['u'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$need_name = '';
		$signup = isset( $_REQUEST['cg_confirm'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cg_confirm'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['cg_action'] ) && 'confirm_signup' === $_POST['cg_action'] ) { // phpcs:ignore
			check_admin_referer( 'cg_confirm_signup' );
			$gid = self::finish_signup( $signup );
			if ( ! is_wp_error( $gid ) ) {
				wp_safe_redirect( $gid ? get_permalink( $gid ) : self::account_url() );
				exit;
			}
			$state   = 'ask';
			$message = $gid->get_error_message();
		} elseif ( $signup && 'GET' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore
			// Show a button first, so email link scanners can't use the link up.
			$data = get_transient( self::signup_key( $signup ) );
			if ( is_array( $data ) ) {
				$state = 'confirm_signup';
				$email = $data['email'];
			} else {
				$message = __( 'That link has expired or has already been used. Open the gallery and sign up again.', 'cheri-gallery' );
			}
		} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['cg_action'] ) ) { // phpcs:ignore
			$action = sanitize_key( $_POST['cg_action'] ); // phpcs:ignore
			if ( 'send_link' === $action && check_admin_referer( 'cg_send_link' ) ) {
				$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
				$password = isset( $_POST['gallery_password'] ) ? (string) wp_unslash( $_POST['gallery_password'] ) : '';
				$pw_ok    = isset( $_POST['cg_pw_ok'] ) ? sanitize_text_field( wp_unslash( $_POST['cg_pw_ok'] ) ) : '';
				$first    = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
				if ( is_email( $email ) && ( '' !== $password || '' !== $pw_ok ) ) {
					$result = self::password_route( $email, $password, $pw_ok, $first, $redirect );
					$state     = $result['state'];
					$message   = $result['message'] ?? '';
					$need_name = $result['need_name'] ?? '';
				} elseif ( ! is_email( $email ) ) {
					$state   = 'ask';
					$message = __( 'Enter the email address Cheri has for you.', 'cheri-gallery' );
				} else {
					if ( cg_rate_limit( 'link|' . strtolower( $email ), 3, 15 * MINUTE_IN_SECONDS ) && cg_rate_limit( 'linkip|' . cg_client_ip(), 10, HOUR_IN_SECONDS ) ) {
						$user = get_user_by( 'email', $email );
						if ( $user && ( self::is_client( $user ) || user_can( $user, CG_CAP ) ) ) {
							self::send_link( $user, $redirect );
						}
					}
					// Same answer either way, so the form can't be used to find out who is a client.
					$state = 'sent';
				}
			} elseif ( 'use_link' === $action && check_admin_referer( 'cg_use_link' ) ) {
				$uid   = isset( $_POST['u'] ) ? absint( $_POST['u'] ) : 0;
				$token = isset( $_POST['t'] ) ? sanitize_text_field( wp_unslash( $_POST['t'] ) ) : '';
				if ( self::consume_token( $uid, $token ) ) {
					wp_set_current_user( $uid );
					wp_set_auth_cookie( $uid, true, is_ssl() );
					update_user_meta( $uid, '_cg_last_visit', current_time( 'mysql' ) );
					wp_safe_redirect( $redirect );
					exit;
				}
				$state   = 'ask';
				$message = __( 'That sign-in link has expired or has already been used. Enter your email for a new one.', 'cheri-gallery' );
			}
		} elseif ( $uid && $token ) {
			$state = self::token_valid( $uid, $token ) ? 'confirm' : 'ask';
			if ( 'ask' === $state ) {
				$message = __( 'That sign-in link has expired or has already been used. Enter your email for a new one.', 'cheri-gallery' );
			}
		} elseif ( is_user_logged_in() ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		echo cg_render( // phpcs:ignore WordPress.Security.EscapeOutput
			'login.php',
			array(
				'state'    => $state,
				'message'  => $message,
				'email'    => $email,
				'redirect' => $redirect,
				'uid'      => $uid,
				'token'    => $token,
				'signup'   => $signup,
				'need_name' => $need_name,
			)
		);
		exit;
	}

	private static function account_page() {
		nocache_headers();
		CG_Frontend::own_page();
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::login_url( self::account_url() ) );
			exit;
		}
		$user      = wp_get_current_user();
		// Cheri (or another admin) sees every gallery here, to check how they look to clients.
		$galleries = current_user_can( CG_CAP ) ? CG_Galleries::all_published() : CG_Galleries::for_client( $user->ID );
		update_user_meta( $user->ID, '_cg_last_visit', current_time( 'mysql' ) );
		echo cg_render( 'account.php', array( 'user' => $user, 'galleries' => $galleries ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Sign-in links                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Create a one-time sign-in link for a user.
	 */
	public static function create_link( WP_User $user, $redirect = '' ) {
		$token = wp_generate_password( 32, false, false );
		update_user_meta(
			$user->ID,
			'_cg_login_token',
			array(
				'hash'    => hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ),
				'expires' => time() + self::LINK_MINUTES * MINUTE_IN_SECONDS,
			)
		);
		$args = array(
			'u' => $user->ID,
			't' => $token,
		);
		if ( $redirect ) {
			$args['redirect_to'] = rawurlencode( $redirect );
		}
		return add_query_arg( $args, home_url( 'client-login/' ) );
	}

	public static function send_link( WP_User $user, $redirect = '' ) {
		$link = self::create_link( $user, $redirect );
		$name = $user->first_name ? $user->first_name : $user->display_name;
		$html = cg_render(
			'email-signin.php',
			array(
				'name'    => $name,
				'link'    => $link,
				'minutes' => self::LINK_MINUTES,
			)
		);
		/* translators: %s: studio name */
		$subject = sprintf( __( 'Your sign-in link for %s', 'cheri-gallery' ), CG_Settings::get( 'from_name' ) );
		$text    = sprintf(
			/* translators: 1: name, 2: link, 3: minutes */
			__( "Hi %1\$s,\n\nUse this link to sign in to your galleries:\n%2\$s\n\nIt works once and lasts %3\$d minutes. If you didn't ask for it, you can ignore this email.", 'cheri-gallery' ),
			$name,
			$link,
			self::LINK_MINUTES
		);
		return CG_Email::send( $user->user_email, $subject, $html, $text );
	}

	private static function token_valid( $uid, $token ) {
		$saved = get_user_meta( $uid, '_cg_login_token', true );
		if ( ! is_array( $saved ) || empty( $saved['hash'] ) || empty( $saved['expires'] ) || time() > (int) $saved['expires'] ) {
			return false;
		}
		return hash_equals( $saved['hash'], hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) ) );
	}

	private static function consume_token( $uid, $token ) {
		if ( ! $uid || ! self::token_valid( $uid, $token ) ) {
			return false;
		}
		delete_user_meta( $uid, '_cg_login_token' );
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Keep clients on the front end                                       */
	/* ------------------------------------------------------------------ */

	public static function keep_clients_out_of_admin() {
		if ( wp_doing_ajax() || ! self::is_client() || current_user_can( CG_CAP ) ) {
			return;
		}
		wp_safe_redirect( self::account_url() );
		exit;
	}

	public static function admin_bar( $show ) {
		return self::is_client() && ! current_user_can( CG_CAP ) ? false : $show;
	}

	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( $user instanceof WP_User && self::is_client( $user ) && ! user_can( $user, CG_CAP ) ) {
			return self::account_url();
		}
		return $redirect_to;
	}

	/**
	 * Accounts that may use the gallery password: client accounts only, never anyone who can edit the site.
	 */
	public static function can_password_signin( $user ) {
		return $user instanceof WP_User && self::is_client( $user ) && ! user_can( $user, 'edit_posts' ) && ! user_can( $user, CG_CAP );
	}

	public static function sign_in( $user_id ) {
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		update_user_meta( $user_id, '_cg_last_visit', current_time( 'mysql' ) );
	}

	/**
	 * A new person (or an existing client not yet on this gallery) knows the gallery password:
	 * email them a link to confirm their address. The account is created only when they confirm.
	 */
	public static function start_signup( $email, $first_name, $gallery_id ) {
		$token = wp_generate_password( 32, false, false );
		set_transient(
			'cg_su_' . hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ),
			array(
				'email'      => $email,
				'first_name' => $first_name,
				'gallery'    => absint( $gallery_id ),
			),
			DAY_IN_SECONDS
		);
		$link    = add_query_arg( 'cg_confirm', $token, home_url( 'client-login/' ) );
		$name    = $first_name ? $first_name : __( 'there', 'cheri-gallery' );
		$gallery = get_the_title( $gallery_id );
		$content  = '<p style="margin:0 0 12px;">' . esc_html( sprintf( /* translators: %s: name */ __( 'Hi %s,', 'cheri-gallery' ), $name ) ) . '</p>';
		$content .= '<p style="margin:0 0 20px;">' . esc_html( sprintf( /* translators: %s: gallery */ __( 'Confirm your email to open %s and save your favourites.', 'cheri-gallery' ), $gallery ) ) . '</p>';
		$content .= '<p style="margin:0 0 20px;text-align:center;"><a href="' . esc_url( $link ) . '" style="display:inline-block;background:#7d5f41;color:#ffffff;text-decoration:none;font:600 11px/16px \'Open Sans\',Arial,sans-serif;letter-spacing:2px;text-transform:uppercase;padding:12px 28px;">' . esc_html__( 'Confirm my email', 'cheri-gallery' ) . '</a></p>';
		$content .= '<p style="margin:0;font-size:12px;line-height:18px;color:#6b625a;">' . esc_html__( 'The link lasts 24 hours. If you didn\'t ask for it, you can ignore this email.', 'cheri-gallery' ) . '</p>';
		$html = cg_render( 'email-layout.php', array( 'settings' => CG_Settings::all(), 'content' => $content ) );
		/* translators: %s: gallery */
		$subject = sprintf( __( 'Confirm your email for %s', 'cheri-gallery' ), $gallery );
		$text    = sprintf( /* translators: 1: gallery, 2: link */ __( "Confirm your email to open %1\$s and save your favourites:\n%2\$s\n\nThe link lasts 24 hours.", 'cheri-gallery' ), $gallery, $link );
		return CG_Email::send( $email, $subject, $html, $text );
	}

	/**
	 * Sign-in page with a gallery password: a client of that gallery goes straight in; someone new
	 * (or not yet on that gallery) confirms their email first and joins as a guest.
	 *
	 * @return array state ('ask' | 'sent' | 'signup_sent'), message, need_name (token for the name step).
	 */
	private static function password_route( $email, $password, $pw_ok, $first, $redirect ) {
		$user = get_user_by( 'email', $email );

		// Name step: the password was checked a moment ago; carry on without asking again.
		if ( '' !== $pw_ok ) {
			$key  = 'cg_pwok_' . hash_hmac( 'sha256', $pw_ok, wp_salt( 'auth' ) );
			$data = get_transient( $key );
			if ( ! is_array( $data ) || strtolower( $data['email'] ) !== strtolower( $email ) || $user ) {
				return array( 'state' => 'ask', 'message' => __( 'Please enter the gallery password again.', 'cheri-gallery' ) );
			}
			if ( '' === $first ) {
				return array( 'state' => 'ask', 'need_name' => $pw_ok, 'message' => __( 'Add your first name.', 'cheri-gallery' ) );
			}
			delete_transient( $key );
			self::start_signup( $email, $first, $data['gallery'] );
			return array( 'state' => 'signup_sent' );
		}

		if ( ! cg_rate_limit( 'pwlogin|' . cg_client_ip(), 10, 10 * MINUTE_IN_SECONDS ) ) {
			return array( 'state' => 'ask', 'message' => __( 'Too many tries. Wait ten minutes and try again.', 'cheri-gallery' ) );
		}
		$gid = self::gallery_for_password( $password, $redirect, $user );
		if ( ! $gid ) {
			return array( 'state' => 'ask', 'message' => __( 'That gallery password doesn\'t match. Check your email from Cheri (it\'s case-sensitive), or leave it empty and we\'ll email you a link.', 'cheri-gallery' ) );
		}
		CG_Access::grant( $gid, 'pass' );
		$target = ( ! $redirect || self::account_url() === $redirect ) ? get_permalink( $gid ) : $redirect;

		if ( $user && ! self::can_password_signin( $user ) ) {
			return array( 'state' => 'ask', 'message' => __( 'For this email, leave the gallery password empty and we\'ll email you a link.', 'cheri-gallery' ) );
		}
		$role = $user ? CG_Galleries::role_on( $gid, $user->ID ) : '';
		if ( $user && '' !== $role ) {
			if ( CG_Settings::get( 'password_signin' ) ) {
				self::sign_in( $user->ID );
				wp_safe_redirect( $target );
				exit;
			}
			self::send_link( $user, $target );
			return array( 'state' => 'sent' );
		}
		if ( $user ) { // Has an account, but isn't on this gallery yet: confirm by email, then join as a guest.
			self::start_signup( $email, $user->first_name, $gid );
			return array( 'state' => 'signup_sent' );
		}
		// Brand new: ask for a first name next, without asking for the password again.
		$token = wp_generate_password( 24, false, false );
		set_transient( 'cg_pwok_' . hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ), array( 'email' => $email, 'gallery' => $gid ), 15 * MINUTE_IN_SECONDS );
		return array( 'state' => 'ask', 'need_name' => $token );
	}

	/**
	 * Which gallery a typed password opens: the one being signed in to (from redirect_to) if it
	 * matches, otherwise a password gallery the person belongs to, otherwise any with that password.
	 */
	private static function gallery_for_password( $password, $redirect, $user ) {
		$matches = function ( $gid ) use ( $password ) {
			$real = (string) CG_Galleries::setting( $gid, 'password' );
			return 'password' === CG_Galleries::setting( $gid, 'visibility' ) && '' !== $real && hash_equals( $real, $password );
		};
		$from = $redirect ? url_to_postid( $redirect ) : 0;
		if ( $from && CG_Galleries::is_gallery( $from ) && $matches( $from ) ) {
			return $from;
		}
		$found = 0;
		foreach ( CG_Galleries::all_published() as $g ) {
			if ( ! $matches( $g->ID ) ) {
				continue;
			}
			if ( $user && '' !== CG_Galleries::role_on( $g->ID, $user->ID ) ) {
				return $g->ID;
			}
			$found = $found ? $found : $g->ID;
		}
		return $found;
	}

	private static function signup_key( $token ) {
		return 'cg_su_' . hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	/**
	 * Finish a sign-up: create (or find) the account, add them to the gallery as a guest, sign in.
	 *
	 * @return int|WP_Error Gallery ID.
	 */
	private static function finish_signup( $token ) {
		$data = get_transient( self::signup_key( $token ) );
		if ( ! is_array( $data ) || empty( $data['email'] ) ) {
			return new WP_Error( 'cg_signup', __( 'That link has expired or has already been used. Open the gallery and sign up again.', 'cheri-gallery' ) );
		}
		delete_transient( self::signup_key( $token ) );
		$user = get_user_by( 'email', $data['email'] );
		if ( $user && ! self::can_password_signin( $user ) ) {
			return new WP_Error( 'cg_signup', __( 'This email belongs to an account that signs in another way. Use Client sign in instead.', 'cheri-gallery' ) );
		}
		$new = false;
		if ( ! $user ) {
			$uid = self::create_client( $data['email'], $data['first_name'] );
			if ( is_wp_error( $uid ) ) {
				return $uid;
			}
			$user = get_userdata( $uid );
			$new  = true;
		}
		$gid = absint( $data['gallery'] );
		if ( CG_Galleries::is_gallery( $gid ) ) {
			CG_Galleries::add_guest( $gid, $user->ID );
		}
		self::sign_in( $user->ID );
		if ( $new ) {
			CG_Email::notify_new_guest( $user, $gid );
		}
		return $gid;
	}

	/**
	 * Create (or find) a client account.
	 *
	 * @return int|WP_Error User ID.
	 */
	public static function create_client( $email, $first_name, $last_name = '', $phone = '', $notes = '' ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'cg_email', __( 'That email address doesn\'t look right.', 'cheri-gallery' ) );
		}
		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			return new WP_Error( 'cg_exists', __( 'There is already an account with that email.', 'cheri-gallery' ) );
		}
		$base     = sanitize_user( strtolower( strtok( $email, '@' ) ), true );
		$login    = $base ? $base : 'client';
		$i        = 1;
		while ( username_exists( $login ) ) {
			$login = $base . ++$i;
		}
		$display = trim( $first_name . ' ' . $last_name );
		$uid     = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'first_name'   => sanitize_text_field( $first_name ),
				'last_name'    => sanitize_text_field( $last_name ),
				'display_name' => $display ? sanitize_text_field( $display ) : $email,
				'role'         => CG_ROLE,
			)
		);
		if ( ! is_wp_error( $uid ) ) {
			update_user_meta( $uid, '_cg_phone', sanitize_text_field( $phone ) );
			update_user_meta( $uid, '_cg_notes', sanitize_textarea_field( $notes ) );
		}
		return $uid;
	}
}
