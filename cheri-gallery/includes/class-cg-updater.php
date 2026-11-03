<?php
/**
 * Self-updates from GitHub Releases.
 *
 * Each release on github.com/robbodley/cheri-photography carries a cheri-gallery.zip. WordPress
 * checks the latest release twice a day (or on demand from Settings) and, when it's newer than the
 * installed version, shows the normal "Update now" link on the Plugins page. Auto-updates work too.
 *
 * Photos, clients and favourites live in the uploads folder and the database, so updating never touches them.
 */

defined( 'ABSPATH' ) || exit;

class CG_Updater {

	const REPO      = 'robbodley/cheri-photography';
	const ASSET     = 'cheri-gallery.zip';
	const CACHE     = 'cg_update_release';
	const TOKEN_OPT = 'cg_github_token';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'download' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder_name' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_update' ), 10, 2 );
	}

	public static function basename() {
		return plugin_basename( CG_FILE );
	}

	private static function headers( $accept = 'application/vnd.github+json' ) {
		$h     = array(
			'Accept'     => $accept,
			'User-Agent'    => 'Cheri-Gallery-Updater',
			'Cache-Control' => 'no-cache',
		);
		$token = trim( (string) get_option( self::TOKEN_OPT, '' ) );
		if ( $token ) {
			$h['Authorization'] = 'Bearer ' . $token;
		}
		return $h;
	}

	/**
	 * The latest release, cached for 6 hours.
	 *
	 * Without a token it uses GitHub's ordinary download links, which aren't rate-limited like the
	 * API (shared web hosts often use up the API's 60-an-hour allowance, giving "403").
	 *
	 * @return array|WP_Error version, notes, published, package (zip URL), url.
	 */
	public static function latest( $force = false ) {
		$cached = get_site_transient( self::CACHE );
		if ( ! $force && is_array( $cached ) ) {
			return $cached;
		}
		$token   = trim( (string) get_option( self::TOKEN_OPT, '' ) );
		$release = $token ? self::from_api() : self::from_web();
		if ( is_wp_error( $release ) ) {
			$other = $token ? self::from_web() : self::from_api();
			if ( ! is_wp_error( $other ) ) {
				$release = $other;
			}
		}
		if ( is_wp_error( $release ) ) {
			return $release;
		}
		$release['checked'] = time();
		set_site_transient( self::CACHE, $release, 6 * HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * From the release's release.json (a stable "latest" download link), or failing that the
	 * address GitHub's "latest release" page redirects to.
	 */
	private static function from_web() {
		$base = 'https://github.com/' . self::REPO . '/releases';
		$args = array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent'    => 'Cheri-Gallery-Updater',
				'Cache-Control' => 'no-cache',
			),
		);

		$res = wp_remote_get( $base . '/latest/download/release.json?nocache=' . time(), $args );
		if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
			$data = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( is_array( $data ) && ! empty( $data['version'] ) ) {
				$version = ltrim( (string) $data['version'], 'vV' );
				return array(
					'version'   => $version,
					'notes'     => (string) ( $data['notes'] ?? '' ),
					'published' => (string) ( $data['published'] ?? '' ),
					'package'   => $base . '/download/v' . rawurlencode( $version ) . '/' . self::ASSET,
					'url'       => $base . '/tag/v' . rawurlencode( $version ),
				);
			}
		}

		// Older releases have no release.json: read the version from where /releases/latest points.
		$res = wp_remote_get( $base . '/latest', array_merge( $args, array( 'redirection' => 0 ) ) );
		if ( is_wp_error( $res ) ) {
			/* translators: %s: error */
			return new WP_Error( 'cg_github', sprintf( __( 'Your website couldn\'t reach GitHub: %s', 'cheri-gallery' ), $res->get_error_message() ) );
		}
		$location = (string) wp_remote_retrieve_header( $res, 'location' );
		if ( ! preg_match( '#/releases/tag/v?([0-9][0-9A-Za-z.\-]*)$#', $location, $m ) ) {
			return new WP_Error( 'cg_no_release', __( 'Couldn\'t find a published release on GitHub.', 'cheri-gallery' ) );
		}
		return array(
			'version'   => $m[1],
			'notes'     => '',
			'published' => '',
			'package'   => $base . '/download/v' . rawurlencode( $m[1] ) . '/' . self::ASSET,
			'url'       => $location,
		);
	}

	/**
	 * From the GitHub API (used with a token, or when the plain links fail).
	 */
	private static function from_api() {
		$res = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest?nocache=' . time(),
			array(
				'timeout' => 15,
				'headers' => self::headers(),
			)
		);
		if ( is_wp_error( $res ) ) {
			/* translators: %s: error */
			return new WP_Error( 'cg_github', sprintf( __( 'Your website couldn\'t reach GitHub: %s', 'cheri-gallery' ), $res->get_error_message() ) );
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( 404 === $code ) {
			return new WP_Error( 'cg_no_release', __( 'No releases published on GitHub yet.', 'cheri-gallery' ) );
		}
		if ( 200 !== $code ) {
			if ( 403 === $code || 429 === $code ) {
				return new WP_Error( 'cg_github', __( 'GitHub is limiting requests from your web host right now. Try again in an hour, or add a GitHub token below.', 'cheri-gallery' ) );
			}
			/* translators: %d: HTTP status */
			return new WP_Error( 'cg_github', sprintf( __( 'GitHub answered with status %d. If the repository is private, add a token in Settings.', 'cheri-gallery' ), $code ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['tag_name'] ) ) {
			return new WP_Error( 'cg_github', __( 'Could not read the latest release.', 'cheri-gallery' ) );
		}
		$asset = null;
		foreach ( (array) ( $data['assets'] ?? array() ) as $a ) {
			if ( self::ASSET === ( $a['name'] ?? '' ) ) {
				$asset = $a;
				break;
			}
		}
		if ( ! $asset ) {
			return new WP_Error( 'cg_github', __( 'The latest release has no cheri-gallery.zip attached.', 'cheri-gallery' ) );
		}
		$release = array(
			'version'   => ltrim( $data['tag_name'], 'vV' ),
			'notes'     => (string) ( $data['body'] ?? '' ),
			'published' => (string) ( $data['published_at'] ?? '' ),
			'package'   => (string) $asset['url'],
			'url'       => (string) ( $data['html_url'] ?? 'https://github.com/' . self::REPO . '/releases' ),
		);
		// Without a token the public download link avoids the API entirely.
		if ( ! trim( (string) get_option( self::TOKEN_OPT, '' ) ) ) {
			$release['package'] = (string) $asset['browser_download_url'];
		}
		return $release;
	}

	/**
	 * One-click link that installs the update (WordPress's own updater, with its security check).
	 */
	public static function update_url() {
		$file = self::basename();
		return wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $file ) ), 'upgrade-plugin_' . $file );
	}

	public static function has_update( $release = null ) {
		$release = $release ? $release : self::latest();
		return ! is_wp_error( $release ) && version_compare( $release['version'], CG_VERSION, '>' );
	}

	/**
	 * Tell WordPress about the update (or that there isn't one, so the auto-update toggle shows).
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$release = self::latest();
		if ( is_wp_error( $release ) ) {
			return $transient;
		}
		$item = (object) array(
			'id'            => 'github.com/' . self::REPO,
			'slug'          => 'cheri-gallery',
			'plugin'        => self::basename(),
			'new_version'   => $release['version'],
			'url'           => $release['url'],
			'package'       => $release['package'],
			'icons'         => array( '1x' => CG_URL . 'assets/img/cheri-mark.png' ),
			'tested'        => get_bloginfo( 'version' ),
			'requires_php'  => '7.4',
			'compatibility' => new stdClass(),
		);
		if ( self::has_update( $release ) ) {
			$transient->response[ self::basename() ] = $item;
			unset( $transient->no_update[ self::basename() ] );
		} else {
			$item->new_version = CG_VERSION;
			$transient->no_update[ self::basename() ] = $item;
			unset( $transient->response[ self::basename() ] );
		}
		return $transient;
	}

	/**
	 * "View details" popup on the Plugins page.
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'cheri-gallery' !== $args->slug ) {
			return $result;
		}
		$release = self::latest();
		if ( is_wp_error( $release ) ) {
			return $result;
		}
		$notes = $release['notes'] ? $release['notes'] : __( 'No release notes.', 'cheri-gallery' );
		return (object) array(
			'name'          => 'Cheri Gallery',
			'slug'          => 'cheri-gallery',
			'version'       => $release['version'],
			'author'        => 'Cheri Photography',
			'homepage'      => $release['url'],
			'requires_php'  => '7.4',
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'changelog' => wpautop( esc_html( $notes ) ),
			),
		);
	}

	/**
	 * Download our package ourselves: GitHub's asset API redirects to a signed storage URL that
	 * must be fetched without the GitHub headers.
	 */
	public static function download( $reply, $package, $upgrader ) {
		if ( false !== $reply || ! is_string( $package ) || 0 !== strpos( $package, 'https://api.github.com/repos/' . self::REPO . '/releases/assets/' ) ) {
			return $reply;
		}
		$res = wp_remote_get(
			$package,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => self::headers( 'application/octet-stream' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$location = wp_remote_retrieve_header( $res, 'location' );
		if ( ! $location ) {
			return new WP_Error( 'cg_download', __( 'GitHub did not provide the update file.', 'cheri-gallery' ) );
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return download_url( $location, 300 );
	}

	/**
	 * Make sure the unzipped folder is called cheri-gallery so the update replaces the right plugin.
	 */
	public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || self::basename() !== $hook_extra['plugin'] ) {
			return $source;
		}
		$want = trailingslashit( $remote_source ) . 'cheri-gallery/';
		if ( trailingslashit( $source ) === $want ) {
			return $source;
		}
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->move( $source, $want ) ) {
			return $want;
		}
		return $source;
	}

	public static function after_update( $upgrader, $options ) {
		if ( isset( $options['type'], $options['plugins'] ) && 'plugin' === $options['type'] && in_array( self::basename(), (array) $options['plugins'], true ) ) {
			delete_site_transient( self::CACHE );
		}
	}

	/**
	 * Force a fresh check (Settings → Check for updates).
	 */
	public static function check_now() {
		delete_site_transient( self::CACHE );
		$release = self::latest( true );
		delete_site_transient( 'update_plugins' );
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
		return $release;
	}
}
