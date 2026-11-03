<?php
/**
 * Generated copies of photos: grid thumbnails, web-size copies, and watermarked versions.
 *
 * The watermark is drawn into the image file itself, so a screenshot or saved copy carries it.
 */

defined( 'ABSPATH' ) || exit;

class CG_Images {

	public static function watermark_styles() {
		return array(
			'off'    => __( 'Off', 'cheri-gallery' ),
			'corner' => __( 'Corner', 'cheri-gallery' ),
			'centre' => __( 'Centre', 'cheri-gallery' ),
			'tiled'  => __( 'Tiled', 'cheri-gallery' ),
		);
	}

	/**
	 * Opacity per style, matching the design system tokens watermark-corner / -centre / -tiled.
	 */
	public static function opacity( $style ) {
		$map = array(
			'corner' => 0.75,
			'centre' => 0.55,
			'tiled'  => 0.25,
		);
		return isset( $map[ $style ] ) ? $map[ $style ] : 0;
	}

	public static function max_edge( $size ) {
		return 'thumb' === $size ? (int) CG_Settings::get( 'thumb_size' ) : (int) CG_Settings::get( 'web_size' );
	}

	public static function cached_path( $photo, $size, $style ) {
		$base = pathinfo( $photo->file, PATHINFO_FILENAME );
		return cg_gallery_dir( $photo->gallery_id, 'cache' ) . '/' . $base . '-' . $size . '-' . $style . '.jpg';
	}

	/**
	 * Make sure a generated copy exists and return its path (or false).
	 *
	 * @param object $photo Photo row.
	 * @param string $size  'thumb' or 'web'.
	 * @param string $style Watermark style or 'off'.
	 */
	public static function ensure( $photo, $size, $style ) {
		$size  = ( 'thumb' === $size ) ? 'thumb' : 'web';
		$style = array_key_exists( $style, self::watermark_styles() ) ? $style : 'off';
		$path  = self::cached_path( $photo, $size, $style );
		$orig  = CG_Photos::original_path( $photo );

		if ( file_exists( $path ) && file_exists( $orig ) && filemtime( $path ) >= filemtime( $orig ) ) {
			return $path;
		}
		if ( ! file_exists( $orig ) || ! cg_ensure_dir( dirname( $path ) ) ) {
			return false;
		}
		wp_raise_memory_limit( 'image' );

		if ( 'off' === $style ) {
			return self::make_resized( $orig, $path, self::max_edge( $size ) ) ? $path : false;
		}

		$plain = self::ensure( $photo, $size, 'off' );
		if ( ! $plain ) {
			return false;
		}
		$img = @imagecreatefromjpeg( $plain ); // phpcs:ignore
		if ( ! $img ) {
			return false;
		}
		self::apply_watermark( $img, $style, self::mark_paths() );
		$ok = imagejpeg( $img, $path, (int) CG_Settings::get( 'jpeg_quality' ) );
		imagedestroy( $img );
		return $ok ? $path : false;
	}

	/**
	 * Resize an original into a JPEG no larger than $max on its long edge, upright, on white.
	 */
	private static function make_resized( $orig, $dest, $max ) {
		$editor = wp_get_image_editor( $orig );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}
		$size = $editor->get_size();
		if ( $size['width'] > $max || $size['height'] > $max ) {
			$editor->resize( $max, $max, false );
		}
		$editor->set_quality( (int) CG_Settings::get( 'jpeg_quality' ) );
		$saved = $editor->save( $dest, 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			return false;
		}
		// Some editors pick their own file name; make sure the copy sits where we expect it.
		if ( ! empty( $saved['path'] ) && $saved['path'] !== $dest && file_exists( $saved['path'] ) ) {
			@rename( $saved['path'], $dest ); // phpcs:ignore
		}
		return file_exists( $dest );
	}

	/**
	 * The white mark and logo used for watermarks. A theme can swap them with the cg_watermark_images filter.
	 */
	public static function mark_paths() {
		return apply_filters(
			'cg_watermark_images',
			array(
				'mark' => CG_DIR . 'assets/img/cheri-mark-white.png',
				'logo' => CG_DIR . 'assets/img/cheri-logo-white.png',
			)
		);
	}

	/**
	 * Draw the watermark onto a GD image in place. Pure GD so it can be tested without WordPress.
	 *
	 * @param resource|GdImage $img   Target image.
	 * @param string           $style corner | centre | tiled.
	 * @param array            $paths 'mark' and 'logo' PNG paths (white on transparent).
	 */
	public static function apply_watermark( $img, $style, array $paths ) {
		$w     = imagesx( $img );
		$h     = imagesy( $img );
		$short = min( $w, $h );

		if ( 'centre' === $style ) {
			$src = @imagecreatefrompng( $paths['logo'] ); // phpcs:ignore
			if ( ! $src ) {
				return;
			}
			$mw   = (int) round( min( $w * 0.7, $short * 0.55 ) );
			$mark = self::prepare_mark( $src, $mw, self::opacity( 'centre' ) );
			imagealphablending( $img, true );
			imagecopy( $img, $mark, (int) ( ( $w - imagesx( $mark ) ) / 2 ), (int) ( ( $h - imagesy( $mark ) ) / 2 ), 0, 0, imagesx( $mark ), imagesy( $mark ) );
			imagedestroy( $mark );
			imagedestroy( $src );
			return;
		}

		$src = @imagecreatefrompng( $paths['mark'] ); // phpcs:ignore
		if ( ! $src ) {
			return;
		}

		if ( 'corner' === $style ) {
			$mw     = (int) round( $short * 0.24 );
			$mark   = self::prepare_mark( $src, $mw, self::opacity( 'corner' ) );
			$margin = (int) round( $short * 0.04 );
			imagealphablending( $img, true );
			imagecopy( $img, $mark, $w - imagesx( $mark ) - $margin, $h - imagesy( $mark ) - $margin, 0, 0, imagesx( $mark ), imagesy( $mark ) );
			imagedestroy( $mark );
			imagedestroy( $src );
			return;
		}

		// Tiled: the mark repeated diagonally across the whole photo.
		$mw   = max( 40, (int) round( $short * 0.2 ) );
		$mark = self::prepare_mark( $src, $mw, self::opacity( 'tiled' ) );
		$clear = imagecolorallocatealpha( $mark, 0, 0, 0, 127 );
		$rot   = imagerotate( $mark, 20, $clear );
		imagedestroy( $mark );
		if ( ! $rot ) {
			imagedestroy( $src );
			return;
		}
		imagealphablending( $rot, false );
		imagesavealpha( $rot, true );
		$tw   = imagesx( $rot );
		$th   = imagesy( $rot );
		$step_x = (int) round( $tw * 1.35 );
		$step_y = (int) round( $th * 1.4 );
		imagealphablending( $img, true );
		$row = 0;
		for ( $y = -$th / 2; $y < $h; $y += $step_y ) {
			$offset = ( $row % 2 ) ? $step_x / 2 : 0;
			for ( $x = -$tw / 2 + $offset; $x < $w; $x += $step_x ) {
				imagecopy( $img, $rot, (int) $x, (int) $y, 0, 0, $tw, $th );
			}
			++$row;
		}
		imagedestroy( $rot );
		imagedestroy( $src );
	}

	/**
	 * Scale a PNG to $width and multiply its alpha by $opacity.
	 */
	private static function prepare_mark( $src, $width, $opacity ) {
		$sw     = imagesx( $src );
		$sh     = imagesy( $src );
		$width  = max( 1, $width );
		$height = max( 1, (int) round( $sh * $width / $sw ) );

		$out = imagecreatetruecolor( $width, $height );
		imagealphablending( $out, false );
		imagesavealpha( $out, true );
		imagefill( $out, 0, 0, imagecolorallocatealpha( $out, 255, 255, 255, 127 ) );
		imagecopyresampled( $out, $src, 0, 0, 0, 0, $width, $height, $sw, $sh );

		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$rgba = imagecolorat( $out, $x, $y );
				$a    = ( $rgba >> 24 ) & 0x7F;
				if ( 127 === $a ) {
					continue;
				}
				$na = 127 - (int) round( ( 127 - $a ) * $opacity );
				imagesetpixel( $out, $x, $y, imagecolorallocatealpha( $out, ( $rgba >> 16 ) & 0xFF, ( $rgba >> 8 ) & 0xFF, $rgba & 0xFF, $na ) );
			}
		}
		return $out;
	}

	/**
	 * Remove a photo's generated copies.
	 */
	public static function delete_cached( $photo ) {
		$base = pathinfo( $photo->file, PATHINFO_FILENAME );
		foreach ( (array) glob( cg_gallery_dir( $photo->gallery_id, 'cache' ) . '/' . $base . '-*.jpg' ) as $f ) {
			@unlink( $f ); // phpcs:ignore
		}
	}

	/**
	 * Drop watermarked copies for a gallery (after its watermark style changes).
	 */
	public static function clear_watermarks( $gallery_id ) {
		foreach ( array( 'corner', 'centre', 'tiled' ) as $style ) {
			foreach ( (array) glob( cg_gallery_dir( $gallery_id, 'cache' ) . '/*-' . $style . '.jpg' ) as $f ) {
				@unlink( $f ); // phpcs:ignore
			}
		}
	}
}
