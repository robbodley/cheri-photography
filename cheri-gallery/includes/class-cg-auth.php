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

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['cg_action'] ) ) { // phpcs:ignore
			$action = sanitize_key( $_POST['cg_action'] ); // phpcs:ignore
			if ( 'send_link' === $action && check_admin_referer( 'cg_send_link' ) ) {
				$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
				if ( ! is_email( $email ) ) {
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
