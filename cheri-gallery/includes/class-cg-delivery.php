<?php
/**
 * Serves photos and downloads through WordPress, after checking access.
 *
 *   /cg-photo/{id}/{thumb|web}/      grid and lightbox images
 *   /cg-download/photo/{id}/         one photo
 *   /cg-download/album/{gallery}/    the whole album as a zip
 */

defined( 'ABSPATH' ) || exit;

class CG_Delivery {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 1 );
	}

	public static function add_rewrites() {
		add_rewrite_rule( '^cg-photo/([0-9]+)/(thumb|web)/?$', 'index.php?cg_photo=$matches[1]&cg_size=$matches[2]', 'top' );
		add_rewrite_rule( '^cg-download/photo/([0-9]+)/?$', 'index.php?cg_dl=photo&cg_id=$matches[1]', 'top' );
		add_rewrite_rule( '^cg-download/album/([0-9]+)/?$', 'index.php?cg_dl=album&cg_id=$matches[1]', 'top' );
	}

	public static function query_vars( $vars ) {
		return array_merge( $vars, array( 'cg_photo', 'cg_size', 'cg_dl', 'cg_id' ) );
	}

	/* ------------------------------------------------------------------ */
	/* URLs                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * URL of a photo copy. The version string changes when the watermark changes,
	 * so browsers never show a stale copy.
	 */
	public static function photo_url( $photo, $size = 'thumb', $plain = false, $as_cover = false ) {
		$style = $plain ? 'off' : self::style_for( $photo, $as_cover );
		$url   = home_url( 'cg-photo/' . absint( $photo->id ) . '/' . ( 'web' === $size ? 'web' : 'thumb' ) . '/' );
		$args  = array( 'v' => substr( md5( $style . '|' . $photo->filesize . '|' . CG_Settings::get( 'web_size' ) ), 0, 8 ) );
		if ( $plain ) {
			$args['plain'] = 1;
		}
		if ( $as_cover ) {
			$args['cover'] = 1;
		}
		return add_query_arg( $args, $url );
	}

	/**
	 * Watermark style for a photo. Shown as the cover (cover=1), the gallery's cover photo can be
	 * clean when "Watermark the cover photo" is off; only the actual cover photo, never others.
	 */
	public static function style_for( $photo, $as_cover = false ) {
		$gid   = (int) $photo->gallery_id;
		$style = CG_Galleries::setting( $gid, 'watermark' );
		if ( $as_cover && ! CG_Galleries::setting( $gid, 'cover_watermark' ) && (int) CG_Galleries::cover_photo( $gid ) === (int) $photo->id ) {
			return 'off';
		}
		return $style;
	}

	public static function photo_download_url( $photo ) {
		return home_url( 'cg-download/photo/' . absint( $photo->id ) . '/' );
	}

	public static function album_download_url( $gallery_id ) {
		return home_url( 'cg-download/album/' . absint( $gallery_id ) . '/' );
	}

	/* ------------------------------------------------------------------ */
	/* Routing                                                             */
	/* ------------------------------------------------------------------ */

	public static function route() {
		$photo_id = absint( get_query_var( 'cg_photo' ) );
		if ( $photo_id ) {
			self::serve_photo( $photo_id, get_query_var( 'cg_size' ) );
		}
		$dl = get_query_var( 'cg_dl' );
		if ( 'photo' === $dl ) {
			self::download_photo( absint( get_query_var( 'cg_id' ) ) );
		} elseif ( 'album' === $dl ) {
			self::download_album( absint( get_query_var( 'cg_id' ) ) );
		}
	}

	private static function deny( $status = 403 ) {
		status_header( $status );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( 404 === $status ? __( 'Not found.', 'cheri-gallery' ) : __( 'You don\'t have access to this.', 'cheri-gallery' ) );
		exit;
	}

	private static function serve_photo( $photo_id, $size ) {
		$photo = CG_Photos::get( $photo_id );
		if ( ! $photo ) {
			self::deny( 404 );
		}
		$gid = (int) $photo->gallery_id;
		$locked_cover = 'web' === $size && CG_Access::is_locked_cover( $photo );
		if ( ( ! CG_Access::can_view( $gid ) && ! $locked_cover ) || ( $photo->hidden && ! CG_Access::is_admin() ) ) {
			self::deny();
		}
		$plain = ! empty( $_GET['plain'] ) && CG_Access::is_admin(); // phpcs:ignore WordPress.Security.NonceVerification
		$cover = ! empty( $_GET['cover'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$style = $plain ? 'off' : self::style_for( $photo, $cover );
		$path  = CG_Images::ensure( $photo, 'web' === $size ? 'web' : 'thumb', $style );
		if ( ! $path ) {
			self::deny( 404 );
		}
		self::send_file( $path, 'image/jpeg', null, true );
	}

	private static function download_photo( $photo_id ) {
		$photo = CG_Photos::get( $photo_id );
		if ( ! $photo ) {
			self::deny( 404 );
		}
		if ( ! CG_Access::can_download( (int) $photo->gallery_id, $photo ) ) {
			self::redirect_to_sign_in_or_deny( (int) $photo->gallery_id );
		}
		list( $path, $ext ) = self::download_source( $photo );
		if ( ! $path ) {
			self::deny( 404 );
		}
		self::send_file( $path, 'jpg' === $ext ? 'image/jpeg' : wp_check_filetype( $path )['type'], CG_Photos::download_name( $photo, $ext ) );
	}

	/**
	 * Which file a download gets: a watermarked web copy while a watermark is on,
	 * otherwise the original or the web copy as the gallery says.
	 *
	 * @return array [ path|false, extension ]
	 */
	private static function download_source( $photo ) {
		$s = CG_Galleries::get( $photo->gallery_id );
		if ( 'off' !== $s['watermark'] && ! CG_Access::is_admin() ) {
			return array( CG_Images::ensure( $photo, 'web', $s['watermark'] ), 'jpg' );
		}
		if ( 'full' === $s['download_size'] ) {
			$orig = CG_Photos::original_path( $photo );
			return array( file_exists( $orig ) ? $orig : false, pathinfo( $photo->file, PATHINFO_EXTENSION ) );
		}
		return array( CG_Images::ensure( $photo, 'web', 'off' ), 'jpg' );
	}

	private static function download_album( $gallery_id ) {
		if ( ! CG_Galleries::is_gallery( $gallery_id ) ) {
			self::deny( 404 );
		}
		if ( ! CG_Access::can_download( $gallery_id ) ) {
			self::redirect_to_sign_in_or_deny( $gallery_id );
		}
		$photos = array_filter(
			CG_Photos::for_gallery( $gallery_id, array( 'include_hidden' => false ) ),
			function ( $p ) {
				return ! $p->no_download;
			}
		);
		if ( ! $photos ) {
			self::deny( 404 );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Your web host does not support zip files (the PHP zip extension is missing).', 'cheri-gallery' ) );
		}

		$s = CG_Galleries::get( $gallery_id );
		$admin = CG_Access::is_admin();
		$key  = md5( wp_json_encode( array( wp_list_pluck( $photos, 'id' ), wp_list_pluck( $photos, 'filesize' ), $admin ? 'off' : $s['watermark'], $s['download_size'], CG_Settings::get( 'web_size' ) ) ) );
		$dir  = cg_gallery_dir( $gallery_id, 'cache' );
		cg_ensure_dir( $dir );
		$zip_path = $dir . '/album-' . $key . '.zip';

		if ( ! file_exists( $zip_path ) ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 0 ); // phpcs:ignore
			}
			self::clear_zip_cache( $gallery_id );
			$tmp = $zip_path . '.tmp';
			$zip = new ZipArchive();
			if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				self::deny( 500 );
			}
			$used = array();
			foreach ( $photos as $photo ) {
				list( $path, $ext ) = self::download_source( $photo );
				if ( ! $path ) {
					continue;
				}
				$set  = $photo->set_name ? sanitize_file_name( $photo->set_name ) . '/' : '';
				$name = $set . CG_Photos::download_name( $photo, $ext );
				if ( isset( $used[ $name ] ) ) {
					$name = $set . pathinfo( $name, PATHINFO_FILENAME ) . '-' . $photo->id . '.' . $ext;
				}
				$used[ $name ] = true;
				$zip->addFile( $path, $name );
				if ( method_exists( $zip, 'setCompressionName' ) ) {
					$zip->setCompressionName( $name, ZipArchive::CM_STORE ); // Photos are already compressed.
				}
			}
			$zip->close();
			@rename( $tmp, $zip_path ); // phpcs:ignore
		}

		self::send_file( $zip_path, 'application/zip', sanitize_file_name( get_the_title( $gallery_id ) ) . '.zip' );
	}

	private static function redirect_to_sign_in_or_deny( $gallery_id ) {
		if ( ! is_user_logged_in() && CG_Access::can_view( $gallery_id ) ) {
			wp_safe_redirect( CG_Auth::login_url( CG_Galleries::link( $gallery_id ) ) );
			exit;
		}
		self::deny();
	}

	/**
	 * Stream a file with sensible caching. Images get 304s when unchanged.
	 */
	private static function send_file( $path, $mime, $download_name = null, $cacheable = false ) {
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			self::deny( 404 );
		}
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$mtime = filemtime( $path );
		$size  = filesize( $path );
		$etag  = '"' . md5( $path . $mtime . $size ) . '"';

		status_header( 200 );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Content-Type-Options: nosniff' );
		if ( $cacheable ) {
			header( 'Cache-Control: private, max-age=604800' );
			header( 'ETag: ' . $etag );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT' );
			$inm = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : ''; // phpcs:ignore
			if ( $inm === $etag ) {
				status_header( 304 );
				exit;
			}
		} else {
			nocache_headers();
		}
		header( 'Content-Type: ' . ( $mime ? $mime : 'application/octet-stream' ) );
		header( 'Content-Length: ' . $size );
		if ( $download_name ) {
			header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $download_name ) . '"' );
		}
		readfile( $path ); // phpcs:ignore
		exit;
	}

	public static function clear_zip_cache( $gallery_id ) {
		foreach ( (array) glob( cg_gallery_dir( $gallery_id, 'cache' ) . '/album-*.zip' ) as $f ) {
			@unlink( $f ); // phpcs:ignore
		}
	}
}
