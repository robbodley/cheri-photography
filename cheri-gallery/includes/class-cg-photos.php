<?php
/**
 * Photos table: storing uploads, reading and updating photo rows.
 */

defined( 'ABSPATH' ) || exit;

class CG_Photos {

	const ALLOWED_MIME = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	);

	private static function table() {
		$t = CG_Install::tables();
		return $t['photos'];
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * Photos for a gallery in display order.
	 *
	 * @param array $args include_hidden (bool), set (string), limit (int).
	 */
	public static function for_gallery( $gallery_id, array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args( $args, array( 'include_hidden' => true, 'set' => null, 'limit' => 0 ) );
		$where = $wpdb->prepare( 'gallery_id = %d', $gallery_id );
		if ( ! $args['include_hidden'] ) {
			$where .= ' AND hidden = 0';
		}
		if ( null !== $args['set'] ) {
			$where .= $wpdb->prepare( ' AND set_name = %s', $args['set'] );
		}
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . " WHERE $where ORDER BY sort_order ASC, id ASC" ); // phpcs:ignore
		$rows = self::sort( (array) $rows, (string) CG_Galleries::setting( $gallery_id, 'photo_order' ) );
		return $args['limit'] ? array_slice( $rows, 0, (int) $args['limit'] ) : $rows;
	}

	/**
	 * Photo order choices for a gallery.
	 */
	public static function orders() {
		return array(
			'name'        => __( 'Name, A–Z (CP1, CP2 … CP10)', 'cheri-gallery' ),
			'name_desc'   => __( 'Name, Z–A', 'cheri-gallery' ),
			'upload'      => __( 'Upload order (first uploaded first)', 'cheri-gallery' ),
			'upload_desc' => __( 'Upload order (newest first)', 'cheri-gallery' ),
		);
	}

	/**
	 * Sort photos: pinned ("Top") photos first, then by the gallery's order. Name order is
	 * "natural", so CP2 comes before CP10.
	 */
	public static function sort( array $rows, $order ) {
		$order = array_key_exists( $order, self::orders() ) ? $order : 'name';
		$desc  = '_desc' === substr( $order, -5 );
		$by    = $desc ? substr( $order, 0, -5 ) : $order;
		usort(
			$rows,
			function ( $a, $b ) use ( $by, $desc ) {
				$pa = ! empty( $a->pinned );
				$pb = ! empty( $b->pinned );
				if ( $pa !== $pb ) {
					return $pa ? -1 : 1;
				}
				if ( 'name' === $by ) {
					$cmp = strnatcasecmp( (string) $a->original_name, (string) $b->original_name );
				} else {
					$cmp = (int) $a->sort_order - (int) $b->sort_order;
				}
				if ( 0 === $cmp ) {
					$cmp = (int) $a->id - (int) $b->id;
				}
				return $desc ? -$cmp : $cmp;
			}
		);
		return $rows;
	}

	public static function count( $gallery_id, $include_hidden = false ) {
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE gallery_id = %d' . ( $include_hidden ? '' : ' AND hidden = 0' );
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $gallery_id ) ); // phpcs:ignore
	}

	/**
	 * Absolute path of a photo's original file.
	 */
	public static function original_path( $photo ) {
		return cg_gallery_dir( $photo->gallery_id, 'originals' ) . '/' . basename( $photo->file );
	}

	/**
	 * Store an uploaded file as a new photo.
	 *
	 * @param int    $gallery_id Gallery.
	 * @param array  $file       One entry from $_FILES.
	 * @param string $set_name   Set to put it in.
	 * @return object|WP_Error   The new photo row.
	 */
	public static function add_from_upload( $gallery_id, array $file, $set_name ) {
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'cg_upload', self::upload_error_message( (int) $file['error'] ) );
		}
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'cg_upload', __( 'The upload did not arrive.', 'cheri-gallery' ) );
		}

		$check = wp_check_filetype_and_ext(
			$file['tmp_name'],
			$file['name'],
			array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'webp'         => 'image/webp',
			)
		);
		$info  = @getimagesize( $file['tmp_name'] ); // phpcs:ignore
		$mime  = $info ? $info['mime'] : '';
		if ( ! $info || ! isset( self::ALLOWED_MIME[ $mime ] ) || ( $check['type'] && ! isset( self::ALLOWED_MIME[ $check['type'] ] ) ) ) {
			return new WP_Error( 'cg_upload', __( 'Only JPEG, PNG and WebP photos can be uploaded.', 'cheri-gallery' ) );
		}

		$dir = cg_gallery_dir( $gallery_id, 'originals' );
		if ( ! cg_ensure_dir( $dir ) ) {
			return new WP_Error( 'cg_upload', __( 'Could not create the gallery folder. Check the uploads folder is writable.', 'cheri-gallery' ) );
		}

		// Random file name so originals can never be guessed.
		$stored = strtolower( wp_generate_password( 24, false, false ) ) . '.' . self::ALLOWED_MIME[ $mime ];
		$dest   = $dir . '/' . $stored;
		if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) { // phpcs:ignore
			return new WP_Error( 'cg_upload', __( 'Could not save the photo.', 'cheri-gallery' ) );
		}
		@chmod( $dest, 0640 ); // phpcs:ignore

		// Respect camera rotation so width/height match what people see.
		$width  = (int) $info[0];
		$height = (int) $info[1];
		if ( 'image/jpeg' === $mime && function_exists( 'exif_read_data' ) ) {
			$exif = @exif_read_data( $dest ); // phpcs:ignore
			if ( ! empty( $exif['Orientation'] ) && in_array( (int) $exif['Orientation'], array( 5, 6, 7, 8 ), true ) ) {
				list( $width, $height ) = array( $height, $width );
			}
		}

		global $wpdb;
		$max_order = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(sort_order) FROM ' . self::table() . ' WHERE gallery_id = %d', $gallery_id ) ); // phpcs:ignore
		$wpdb->insert(
			self::table(),
			array(
				'gallery_id'    => $gallery_id,
				'set_name'      => $set_name,
				'file'          => $stored,
				'original_name' => sanitize_file_name( $file['name'] ),
				'width'         => $width,
				'height'        => $height,
				'filesize'      => (int) filesize( $dest ),
				'hidden'        => 0,
				'no_download'   => 0,
				'sort_order'    => $max_order + 1,
				'created_at'    => current_time( 'mysql' ),
			)
		);
		$photo = self::get( $wpdb->insert_id );

		// Make the grid and web copies now so the gallery is quick for clients.
		CG_Images::ensure( $photo, 'thumb', 'off' );
		CG_Images::ensure( $photo, 'web', 'off' );
		$style = CG_Galleries::setting( $gallery_id, 'watermark' );
		if ( 'off' !== $style ) {
			CG_Images::ensure( $photo, 'thumb', $style );
			CG_Images::ensure( $photo, 'web', $style );
		}
		return $photo;
	}

	private static function upload_error_message( $code ) {
		switch ( $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'This photo is bigger than your web host allows. Ask them to raise upload_max_filesize, or export at a smaller size.', 'cheri-gallery' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The upload was interrupted. Try again.', 'cheri-gallery' );
			default:
				return __( 'The upload failed.', 'cheri-gallery' );
		}
	}

	/**
	 * Update simple fields on one or more photos in a gallery.
	 */
	public static function update( $gallery_id, array $photo_ids, array $fields ) {
		global $wpdb;
		$data = array();
		if ( array_key_exists( 'hidden', $fields ) ) {
			$data['hidden'] = $fields['hidden'] ? 1 : 0;
		}
		if ( array_key_exists( 'no_download', $fields ) ) {
			$data['no_download'] = $fields['no_download'] ? 1 : 0;
		}
		if ( array_key_exists( 'pinned', $fields ) ) {
			$data['pinned'] = $fields['pinned'] ? 1 : 0;
		}
		if ( array_key_exists( 'set_name', $fields ) ) {
			$data['set_name'] = mb_substr( sanitize_text_field( $fields['set_name'] ), 0, 100 );
		}
		if ( ! $data ) {
			return 0;
		}
		$n = 0;
		foreach ( array_map( 'absint', $photo_ids ) as $pid ) {
			$n += (int) $wpdb->update( self::table(), $data, array( 'id' => $pid, 'gallery_id' => $gallery_id ) );
		}
		CG_Delivery::clear_zip_cache( $gallery_id );
		return $n;
	}

	/**
	 * Move every photo from one set name to another (used when a set is renamed or removed).
	 */
	public static function rename_set( $gallery_id, $from, $to ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'set_name' => $to ), array( 'gallery_id' => $gallery_id, 'set_name' => $from ) );
	}

	/**
	 * Save a new order: photo IDs in the order they should appear.
	 */
	public static function reorder( $gallery_id, array $photo_ids ) {
		global $wpdb;
		$i = 1;
		foreach ( array_map( 'absint', $photo_ids ) as $pid ) {
			$wpdb->update( self::table(), array( 'sort_order' => $i++ ), array( 'id' => $pid, 'gallery_id' => $gallery_id ) );
		}
	}

	public static function delete( $gallery_id, array $photo_ids ) {
		global $wpdb;
		$n = 0;
		foreach ( array_map( 'absint', $photo_ids ) as $pid ) {
			$photo = self::get( $pid );
			if ( ! $photo || (int) $photo->gallery_id !== (int) $gallery_id ) {
				continue;
			}
			@unlink( self::original_path( $photo ) ); // phpcs:ignore
			CG_Images::delete_cached( $photo );
			$wpdb->delete( self::table(), array( 'id' => $pid ) );
			CG_Favourites::forget_photo( $pid );
			++$n;
		}
		CG_Delivery::clear_zip_cache( $gallery_id );
		return $n;
	}

	public static function delete_all_for_gallery( $gallery_id ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE gallery_id = %d', $gallery_id ) ); // phpcs:ignore
		self::delete( $gallery_id, $ids );
		foreach ( array( 'originals', 'cache' ) as $kind ) {
			$dir = cg_gallery_dir( $gallery_id, $kind );
			if ( is_dir( $dir ) ) {
				foreach ( (array) glob( $dir . '/*' ) as $f ) {
					@unlink( $f ); // phpcs:ignore
				}
				@rmdir( $dir ); // phpcs:ignore
			}
		}
	}

	/**
	 * File name the client sees when downloading, e.g. "Emma-Rob-IMG_0412.jpg".
	 */
	public static function download_name( $photo, $ext = null ) {
		$base = pathinfo( $photo->original_name, PATHINFO_FILENAME );
		$ext  = $ext ? $ext : pathinfo( $photo->file, PATHINFO_EXTENSION );
		$name = sanitize_file_name( get_the_title( $photo->gallery_id ) . '-' . ( $base ? $base : $photo->id ) );
		return $name . '.' . $ext;
	}
}
