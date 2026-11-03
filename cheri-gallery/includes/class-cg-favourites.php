<?php
/**
 * Favourites lists: clients make named lists of photos, add a note, and send them to Cheri.
 */

defined( 'ABSPATH' ) || exit;

class CG_Favourites {

	public static function init() {
		add_action( 'wp_ajax_cg_fav', array( __CLASS__, 'ajax' ) );
	}

	private static function t( $key ) {
		$t = CG_Install::tables();
		return $t[ $key ];
	}

	/* ------------------------------------------------------------------ */
	/* Reading                                                             */
	/* ------------------------------------------------------------------ */

	public static function get_list( $list_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'lists' ) . ' WHERE id = %d', $list_id ) ); // phpcs:ignore
	}

	public static function photo_ids( $list_id ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT i.photo_id FROM ' . self::t( 'items' ) . ' i JOIN ' . self::t( 'photos' ) . ' p ON p.id = i.photo_id WHERE i.list_id = %d ORDER BY p.sort_order, p.id', $list_id ) ); // phpcs:ignore
		return array_map( 'intval', $ids );
	}

	/**
	 * A client's lists for one gallery, with their photo IDs.
	 */
	public static function lists_for( $gallery_id, $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'lists' ) . ' WHERE gallery_id = %d AND user_id = %d ORDER BY id ASC', $gallery_id, $user_id ) ); // phpcs:ignore
		foreach ( $rows as $row ) {
			$row->photos = self::photo_ids( $row->id );
		}
		return $rows;
	}

	/**
	 * Every list in a gallery (for Cheri), newest activity first.
	 */
	public static function lists_for_gallery( $gallery_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'lists' ) . ' WHERE gallery_id = %d ORDER BY (status = \'sent\') DESC, COALESCE(sent_at, updated_at) DESC', $gallery_id ) ); // phpcs:ignore
		foreach ( $rows as $row ) {
			$row->photos = self::photo_ids( $row->id );
		}
		return $rows;
	}

	/**
	 * Every list a client has made, across galleries.
	 */
	public static function lists_for_user( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'lists' ) . ' WHERE user_id = %d ORDER BY (status = \'sent\') DESC, COALESCE(sent_at, updated_at) DESC', $user_id ) ); // phpcs:ignore
		foreach ( $rows as $row ) {
			$row->photos = self::photo_ids( $row->id );
		}
		return $rows;
	}

	/**
	 * Lists sent but not yet opened by Cheri, for one client (or all when $user_id is 0).
	 */
	public static function unseen_count( $user_id = 0 ) {
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . self::t( 'lists' ) . " WHERE status = 'sent' AND seen_at IS NULL";
		if ( $user_id ) {
			$sql .= $wpdb->prepare( ' AND user_id = %d', $user_id );
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore
	}

	public static function mark_seen( $list_id ) {
		global $wpdb;
		$wpdb->update( self::t( 'lists' ), array( 'seen_at' => current_time( 'mysql' ) ), array( 'id' => $list_id ) );
	}

	/**
	 * Original file names of a list's photos, ready for a Lightroom filter.
	 */
	public static function filenames( $list_id ) {
		$names = array();
		foreach ( self::photo_ids( $list_id ) as $pid ) {
			$p = CG_Photos::get( $pid );
			if ( $p ) {
				$names[] = pathinfo( $p->original_name, PATHINFO_FILENAME );
			}
		}
		return $names;
	}

	/* ------------------------------------------------------------------ */
	/* Writing                                                             */
	/* ------------------------------------------------------------------ */

	public static function create( $gallery_id, $user_id, $name ) {
		global $wpdb;
		$now  = current_time( 'mysql' );
		$name = mb_substr( trim( sanitize_text_field( $name ) ), 0, 100 );
		$wpdb->insert(
			self::t( 'lists' ),
			array(
				'gallery_id' => $gallery_id,
				'user_id'    => $user_id,
				'name'       => $name ? $name : __( 'My favourites', 'cheri-gallery' ),
				'note'       => '',
				'status'     => 'open',
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	private static function touch( $list_id ) {
		global $wpdb;
		$wpdb->update( self::t( 'lists' ), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $list_id ) );
	}

	/**
	 * Add or remove a photo. Returns true when the photo is now in the list.
	 */
	public static function toggle( $list_id, $photo_id ) {
		global $wpdb;
		$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'items' ) . ' WHERE list_id = %d AND photo_id = %d', $list_id, $photo_id ) ); // phpcs:ignore
		if ( $exists ) {
			$wpdb->delete( self::t( 'items' ), array( 'list_id' => $list_id, 'photo_id' => $photo_id ) );
		} else {
			$wpdb->insert( self::t( 'items' ), array( 'list_id' => $list_id, 'photo_id' => $photo_id, 'created_at' => current_time( 'mysql' ) ) );
		}
		self::touch( $list_id );
		return ! $exists;
	}

	public static function update_fields( $list_id, array $fields ) {
		global $wpdb;
		$data = array( 'updated_at' => current_time( 'mysql' ) );
		if ( isset( $fields['name'] ) ) {
			$name = mb_substr( trim( sanitize_text_field( $fields['name'] ) ), 0, 100 );
			if ( '' !== $name ) {
				$data['name'] = $name;
			}
		}
		if ( isset( $fields['note'] ) ) {
			$data['note'] = mb_substr( sanitize_textarea_field( $fields['note'] ), 0, 2000 );
		}
		$wpdb->update( self::t( 'lists' ), $data, array( 'id' => $list_id ) );
	}

	public static function delete_list( $list_id ) {
		global $wpdb;
		$wpdb->delete( self::t( 'items' ), array( 'list_id' => $list_id ) );
		$wpdb->delete( self::t( 'lists' ), array( 'id' => $list_id ) );
	}

	/**
	 * Mark a list as sent (again) and tell Cheri.
	 */
	public static function send( $list_id ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->update(
			self::t( 'lists' ),
			array(
				'status'     => 'sent',
				'sent_at'    => $now,
				'seen_at'    => null,
				'updated_at' => $now,
			),
			array( 'id' => $list_id )
		);
		$list = self::get_list( $list_id );
		CG_Email::notify_favourites_sent( $list );
	}

	public static function forget_photo( $photo_id ) {
		global $wpdb;
		$wpdb->delete( self::t( 'items' ), array( 'photo_id' => $photo_id ) );
	}

	public static function delete_for_gallery( $gallery_id ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::t( 'lists' ) . ' WHERE gallery_id = %d', $gallery_id ) ); // phpcs:ignore
		foreach ( $ids as $id ) {
			self::delete_list( (int) $id );
		}
	}

	/* ------------------------------------------------------------------ */
	/* AJAX from the gallery page                                          */
	/* ------------------------------------------------------------------ */

	public static function ajax() {
		$gallery_id = isset( $_POST['gallery'] ) ? absint( $_POST['gallery'] ) : 0;
		if ( ! $gallery_id || ! check_ajax_referer( 'cg_fav_' . $gallery_id, 'nonce', false ) ) {
			cg_json_fail( __( 'Your session has expired. Refresh the page and try again.', 'cheri-gallery' ), 403 );
		}
		if ( ! CG_Access::can_favourite( $gallery_id ) ) {
			cg_json_fail( __( 'Sign in to save favourites.', 'cheri-gallery' ), 403 );
		}
		$uid = get_current_user_id();
		$op  = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';

		$list_id = isset( $_POST['list'] ) ? absint( $_POST['list'] ) : 0;
		$list    = $list_id ? self::get_list( $list_id ) : null;
		if ( $list && ( (int) $list->user_id !== $uid || (int) $list->gallery_id !== $gallery_id ) ) {
			cg_json_fail( __( 'That list isn\'t yours.', 'cheri-gallery' ), 403 );
		}

		switch ( $op ) {
			case 'create':
				$name    = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : ''; // phpcs:ignore
				$list_id = self::create( $gallery_id, $uid, $name );
				break;

			case 'toggle':
				$photo_id = isset( $_POST['photo'] ) ? absint( $_POST['photo'] ) : 0;
				$photo    = CG_Photos::get( $photo_id );
				if ( ! $photo || (int) $photo->gallery_id !== $gallery_id || $photo->hidden ) {
					cg_json_fail( __( 'Photo not found.', 'cheri-gallery' ), 404 );
				}
				if ( ! $list ) {
					$lists   = self::lists_for( $gallery_id, $uid );
					$list_id = $lists ? (int) $lists[0]->id : self::create( $gallery_id, $uid, __( 'My favourites', 'cheri-gallery' ) );
				}
				self::toggle( $list_id, $photo_id );
				break;

			case 'update':
				if ( ! $list ) {
					cg_json_fail( __( 'List not found.', 'cheri-gallery' ), 404 );
				}
				$fields = array();
				foreach ( array( 'name', 'note' ) as $f ) {
					if ( isset( $_POST[ $f ] ) ) {
						$fields[ $f ] = wp_unslash( $_POST[ $f ] ); // phpcs:ignore
					}
				}
				self::update_fields( $list->id, $fields );
				break;

			case 'delete':
				if ( ! $list ) {
					cg_json_fail( __( 'List not found.', 'cheri-gallery' ), 404 );
				}
				self::delete_list( $list->id );
				$list_id = 0;
				break;

			case 'send':
				if ( ! $list ) {
					cg_json_fail( __( 'List not found.', 'cheri-gallery' ), 404 );
				}
				if ( ! self::photo_ids( $list->id ) ) {
					cg_json_fail( __( 'Heart some photos first.', 'cheri-gallery' ) );
				}
				if ( ! cg_rate_limit( 'send|' . $list->id, 5, HOUR_IN_SECONDS ) ) {
					cg_json_fail( __( 'This list has been sent several times already. Try again later.', 'cheri-gallery' ), 429 );
				}
				self::send( $list->id );
				break;

			case 'state':
				break;

			default:
				cg_json_fail( __( 'Unknown action.', 'cheri-gallery' ) );
		}

		cg_json_ok( self::state( $gallery_id, $uid, $list_id ) );
	}

	/**
	 * What the gallery page needs to draw hearts and the favourites drawer.
	 */
	public static function state( $gallery_id, $user_id, $current = 0 ) {
		$out = array();
		foreach ( self::lists_for( $gallery_id, $user_id ) as $l ) {
			$out[] = array(
				'id'     => (int) $l->id,
				'name'   => $l->name,
				'note'   => $l->note,
				'status' => $l->status,
				'sent'   => $l->sent_at ? mysql2date( 'j M', $l->sent_at ) : '',
				'photos' => $l->photos,
			);
		}
		return array(
			'lists'   => $out,
			'current' => (int) $current,
		);
	}
}
