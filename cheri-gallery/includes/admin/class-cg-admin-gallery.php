<?php
/**
 * The gallery edit screen: details, settings, photos, favourites, share.
 */

defined( 'ABSPATH' ) || exit;

class CG_Admin_Gallery {

	public static function init() {
		add_action( 'add_meta_boxes_' . CG_Galleries::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . CG_Galleries::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_cg_admin_photos', array( __CLASS__, 'ajax' ) );
		add_filter( 'manage_' . CG_Galleries::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . CG_Galleries::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'after_title' ) );
		add_filter( 'post_updated_messages', array( __CLASS__, 'messages' ) );
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || CG_Galleries::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		global $post;
		wp_enqueue_script( 'cg-admin-gallery', CG_URL . 'assets/js/admin-gallery.js', array(), CG_VERSION, true );
		wp_localize_script(
			'cg-admin-gallery',
			'CG_ADMIN',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'gallery'  => $post ? $post->ID : 0,
				'nonce'    => $post ? wp_create_nonce( 'cg_admin_' . $post->ID ) : '',
				'maxBytes' => wp_max_upload_size(),
				'i18n'     => array(
					'uploading'   => __( 'Uploading %1$d of %2$d…', 'cheri-gallery' ),
					'done'        => __( 'Upload finished.', 'cheri-gallery' ),
					'failed'      => __( '%d could not be uploaded:', 'cheri-gallery' ),
					'tooBig'      => __( 'too big for your web host (limit %s)', 'cheri-gallery' ),
					'confirmDel'  => __( 'Delete the selected photos? This can\'t be undone.', 'cheri-gallery' ),
					'newSet'      => __( 'Name the new set (e.g. Ceremony)', 'cheri-gallery' ),
					'renameSet'   => __( 'Rename this set', 'cheri-gallery' ),
					'deleteSet'   => __( 'Remove this set? Its photos move to the first set.', 'cheri-gallery' ),
					'selected'    => __( '%d selected', 'cheri-gallery' ),
					'copied'      => __( 'Copied', 'cheri-gallery' ),
					'leaveUpload' => __( 'Photos are still uploading. Leave anyway?', 'cheri-gallery' ),
					'remove'      => __( 'Remove', 'cheri-gallery' ),
					/* translators: 1: number of photos, 2: set name */
					'emptySet'    => __( 'Delete all %1$d photos in "%2$s"? They\'re removed from the gallery, from clients\' favourites and from your server, and this can\'t be undone.\n\nType DELETE to confirm.', 'cheri-gallery' ),
					'emptyNone'   => __( 'There are no photos in this set.', 'cheri-gallery' ),
					/* translators: 1: done, 2: total */
					'deleting'    => __( 'Deleting %1$d of %2$d…', 'cheri-gallery' ),
					/* translators: %d: number of photos */
					'deleted'     => __( '%d photos deleted.', 'cheri-gallery' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* List screen                                                         */
	/* ------------------------------------------------------------------ */

	public static function columns( $cols ) {
		return array(
			'cb'            => $cols['cb'],
			'title'         => __( 'Gallery', 'cheri-gallery' ),
			'cg_client'     => __( 'Client', 'cheri-gallery' ),
			'cg_type'       => __( 'Type', 'cheri-gallery' ),
			'cg_visibility' => __( 'Who can see it', 'cheri-gallery' ),
			'cg_featured'   => __( 'Featured', 'cheri-gallery' ),
			'cg_photos'     => __( 'Photos', 'cheri-gallery' ),
			'cg_date'       => __( 'Shoot date', 'cheri-gallery' ),
		);
	}

	public static function column( $col, $post_id ) {
		$s = CG_Galleries::get( $post_id );
		switch ( $col ) {
			case 'cg_client':
				$out = array();
				foreach ( $s['clients'] as $uid ) {
					$u = get_userdata( $uid );
					if ( $u ) {
						$out[] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . CG_Galleries::POST_TYPE . '&page=cg-clients&client=' . $uid ) ) . '">' . esc_html( $u->display_name ) . '</a>';
					}
				}
				echo $out ? implode( ', ', $out ) : '—'; // phpcs:ignore
				break;
			case 'cg_type':
				echo esc_html( cg_session_label( $s['session_type'] ) );
				break;
			case 'cg_featured':
				echo $s['featured'] ? '★ ' . esc_html( $s['featured'] ) : '—';
				break;
			case 'cg_visibility':
				$labels = array(
					'public'   => __( 'Public', 'cheri-gallery' ),
					'password' => __( 'Password', 'cheri-gallery' ),
					'private'  => __( 'Private', 'cheri-gallery' ),
				);
				echo esc_html( $labels[ $s['visibility'] ] );
				if ( CG_Galleries::is_expired( $post_id ) ) {
					echo ' · <em>' . esc_html__( 'closed', 'cheri-gallery' ) . '</em>';
				}
				break;
			case 'cg_photos':
				echo esc_html( number_format_i18n( CG_Photos::count( $post_id, true ) ) );
				break;
			case 'cg_date':
				echo esc_html( cg_format_date( $s['event_date'], 'j M Y' ) );
				break;
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( CG_Galleries::POST_TYPE === $post->post_type && current_user_can( CG_CAP ) ) {
			$actions['cg_share'] = '<a href="' . esc_url( self::share_url( $post->ID ) ) . '">' . esc_html__( 'Share', 'cheri-gallery' ) . '</a>';
		}
		return $actions;
	}

	public static function share_url( $gallery_id ) {
		return admin_url( 'admin.php?page=cg-share&gallery=' . absint( $gallery_id ) );
	}

	public static function title_placeholder( $text, $post ) {
		return CG_Galleries::POST_TYPE === $post->post_type ? __( 'Client names, e.g. Emma & Rob', 'cheri-gallery' ) : $text;
	}

	public static function after_title( $post ) {
		if ( CG_Galleries::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		$link = CG_Galleries::link( $post->ID );
		echo '<p class="cg-gallery-link">' . esc_html__( 'Gallery link:', 'cheri-gallery' ) . ' <a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( $link ) . '</a> <button type="button" class="button button-small" data-cg-copy="' . esc_attr( $link ) . '">' . esc_html__( 'Copy', 'cheri-gallery' ) . '</button></p>';
	}

	public static function messages( $messages ) {
		$messages[ CG_Galleries::POST_TYPE ] = array(
			1 => __( 'Gallery saved.', 'cheri-gallery' ),
			4 => __( 'Gallery saved.', 'cheri-gallery' ),
			6 => __( 'Gallery published. Press Share when you\'re ready to send it.', 'cheri-gallery' ),
			7 => __( 'Gallery saved.', 'cheri-gallery' ),
			8 => __( 'Gallery saved.', 'cheri-gallery' ),
			10 => __( 'Draft saved. Clients can\'t see it until you publish.', 'cheri-gallery' ),
		);
		return $messages;
	}

	/* ------------------------------------------------------------------ */
	/* Meta boxes                                                          */
	/* ------------------------------------------------------------------ */

	public static function meta_boxes( $post ) {
		add_meta_box( 'cg-photos', __( 'Photos', 'cheri-gallery' ), array( __CLASS__, 'box_photos' ), null, 'normal', 'high' );
		add_meta_box( 'cg-settings', __( 'Settings', 'cheri-gallery' ), array( __CLASS__, 'box_settings' ), null, 'normal', 'default' );
		add_meta_box( 'cg-favourites', __( 'Favourites', 'cheri-gallery' ), array( __CLASS__, 'box_favourites' ), null, 'normal', 'default' );
		add_meta_box( 'cg-details', __( 'Client & shoot', 'cheri-gallery' ), array( __CLASS__, 'box_details' ), null, 'side', 'high' );
		add_meta_box( 'cg-share', __( 'Share', 'cheri-gallery' ), array( __CLASS__, 'box_share' ), null, 'side', 'default' );
	}

	public static function box_details( $post ) {
		$s       = CG_Galleries::get( $post->ID );
		$clients = get_users(
			array(
				'role'    => CG_ROLE,
				'orderby' => 'display_name',
				'number'  => 500,
			)
		);
		wp_nonce_field( 'cg_save_gallery', '_cg_gallery_nonce' );
		?>
		<div class="cg-admin cg-side">
			<span class="cg-label"><?php esc_html_e( 'Client(s)', 'cheri-gallery' ); ?></span>
			<input type="hidden" name="cg[clients][]" value="0">
			<ul class="cg-chips" data-cg-chips>
				<?php
				foreach ( $s['clients'] as $cid ) :
					$cu = get_userdata( $cid );
					if ( ! $cu ) {
						continue;
					}
					?>
					<li class="cg-chip" data-id="<?php echo esc_attr( $cid ); ?>">
						<input type="hidden" name="cg[clients][]" value="<?php echo esc_attr( $cid ); ?>">
						<span class="cg-chip__name"><?php echo esc_html( $cu->display_name ); ?><small><?php echo esc_html( $cu->user_email ); ?></small></span>
						<button type="button" class="cg-chip__remove" data-cg-chip-remove aria-label="<?php echo esc_attr( sprintf( /* translators: %s: name */ __( 'Remove %s from this gallery', 'cheri-gallery' ), $cu->display_name ) ); ?>">✕ <?php esc_html_e( 'Remove', 'cheri-gallery' ); ?></button>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $s['guests'] ) : ?>
				<span class="cg-label"><?php esc_html_e( 'Guests (signed up themselves)', 'cheri-gallery' ); ?></span>
				<ul class="cg-chips cg-chips--guests" data-cg-guests>
					<?php
					foreach ( $s['guests'] as $gid_user ) :
						$gu = get_userdata( $gid_user );
						if ( ! $gu ) {
							continue;
						}
						?>
						<li class="cg-chip cg-chip--guest" data-id="<?php echo esc_attr( $gid_user ); ?>">
							<input type="hidden" name="cg[guests][]" value="<?php echo esc_attr( $gid_user ); ?>">
							<span class="cg-chip__name"><?php echo esc_html( $gu->display_name ); ?><small><?php echo esc_html( $gu->user_email ); ?></small></span>
							<span class="cg-chip__btns">
								<button type="button" class="cg-chip__promote" data-cg-guest-promote><?php esc_html_e( 'Make client', 'cheri-gallery' ); ?></button>
								<button type="button" class="cg-chip__remove" data-cg-guest-remove>✕</button>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="cg-hint"><?php esc_html_e( 'Guests can view and make favourites. Make them a client for client downloads.', 'cheri-gallery' ); ?></p>
			<?php endif; ?>
			<input type="hidden" name="cg[guests][]" value="0">
			<p class="cg-hint" data-cg-chips-empty <?php echo $s['clients'] ? 'hidden' : ''; ?>><?php esc_html_e( 'No client yet. Add one below so they can sign in, favourite and download.', 'cheri-gallery' ); ?></p>
			<select class="ch-select cg-add-client" name="cg[clients][]" data-cg-add-client aria-label="<?php esc_attr_e( 'Add a client', 'cheri-gallery' ); ?>">
				<option value=""><?php esc_html_e( '+ Add a client…', 'cheri-gallery' ); ?></option>
				<?php foreach ( $clients as $c ) : ?>
					<option value="<?php echo esc_attr( $c->ID ); ?>" data-email="<?php echo esc_attr( $c->user_email ); ?>" <?php echo in_array( $c->ID, $s['clients'], true ) ? 'hidden disabled' : ''; ?>><?php echo esc_html( $c->display_name . ' (' . $c->user_email . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="cg-hint">
				<?php esc_html_e( 'For a couple, add both. Changes save when you press Update.', 'cheri-gallery' ); ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CG_Galleries::POST_TYPE . '&page=cg-clients&action=new' ) ); ?>" target="_blank"><?php esc_html_e( 'New client', 'cheri-gallery' ); ?></a>
			</p>

			<label class="cg-label" for="cg-type"><?php esc_html_e( 'Session type', 'cheri-gallery' ); ?></label>
			<select id="cg-type" name="cg[session_type]" class="ch-select">
				<?php foreach ( cg_session_types() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['session_type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label class="cg-label" for="cg-date"><?php esc_html_e( 'Shoot date', 'cheri-gallery' ); ?></label>
			<input id="cg-date" type="date" name="cg[event_date]" value="<?php echo esc_attr( $s['event_date'] ); ?>" class="ch-select">

			<label class="cg-label" for="cg-cover-pos"><?php esc_html_e( 'Cover photo position', 'cheri-gallery' ); ?></label>
			<select id="cg-cover-pos" name="cg[cover_position]" class="ch-select">
				<option value="top" <?php selected( $s['cover_position'], 'top' ); ?>><?php esc_html_e( 'Top (keep heads in view)', 'cheri-gallery' ); ?></option>
				<option value="center" <?php selected( $s['cover_position'], 'center' ); ?>><?php esc_html_e( 'Centre', 'cheri-gallery' ); ?></option>
				<option value="bottom" <?php selected( $s['cover_position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'cheri-gallery' ); ?></option>
			</select>
			<p class="cg-hint"><?php esc_html_e( 'Which part of the cover photo stays in view when it\'s cropped to fill the screen, the gallery cards and the share email. Pick the cover itself with ★ in Photos.', 'cheri-gallery' ); ?></p>
		</div>
		<?php
	}

	public static function box_settings( $post ) {
		$s    = CG_Galleries::get( $post->ID );
		$vis  = array(
			'public'   => array( 'globe', __( 'Public', 'cheri-gallery' ) ),
			'password' => array( 'lock', __( 'Password', 'cheri-gallery' ) ),
			'private'  => array( 'eyeoff', __( 'Private', 'cheri-gallery' ) ),
		);
		$private_link = $s['private_key'] ? add_query_arg( 'key', $s['private_key'], get_permalink( $post->ID ) ) : '';
		?>
		<div class="cg-admin ch-set">
			<div class="ch-set__group">
				<p class="ch-set__h"><?php esc_html_e( 'Who can see it', 'cheri-gallery' ); ?></p>
				<div class="ch-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Visibility', 'cheri-gallery' ); ?>">
					<?php foreach ( $vis as $key => $v ) : ?>
						<label><input type="radio" name="cg[visibility]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['visibility'], $key ); ?> data-cg-vis><span><?php echo cg_icon( $v[0] ); // phpcs:ignore ?> <?php echo esc_html( $v[1] ); ?></span></label>
					<?php endforeach; ?>
				</div>
				<p class="cg-hint" data-cg-vis-hint="public" <?php echo 'public' === $s['visibility'] ? '' : 'hidden'; ?>><?php esc_html_e( 'Anyone with the link can view. Good for portfolio galleries.', 'cheri-gallery' ); ?></p>
				<div data-cg-vis-hint="password" <?php echo 'password' === $s['visibility'] ? '' : 'hidden'; ?>>
					<label class="ch-field cg-narrow">
						<span class="ch-field__label"><?php esc_html_e( 'Gallery password', 'cheri-gallery' ); ?></span>
						<input class="ch-field__input" type="text" name="cg[password]" value="<?php echo esc_attr( $s['password'] ); ?>" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'Made for you when you save', 'cheri-gallery' ); ?>">
					</label>
					<p class="cg-hint"><?php esc_html_e( 'Case-sensitive. Anyone with the link and password can view; the client signs in to favourite and download. Changing it locks out everyone using the old one.', 'cheri-gallery' ); ?></p>
				</div>
				<div data-cg-vis-hint="private" <?php echo 'private' === $s['visibility'] ? '' : 'hidden'; ?>>
					<p class="cg-hint"><?php esc_html_e( 'Never listed. Opens only from the private link below, or for the signed-in client.', 'cheri-gallery' ); ?></p>
					<?php if ( $private_link && 'publish' === $post->post_status ) : ?>
						<p class="cg-copyline"><code><?php echo esc_html( $private_link ); ?></code> <button type="button" class="button button-small" data-cg-copy="<?php echo esc_attr( $private_link ); ?>"><?php esc_html_e( 'Copy', 'cheri-gallery' ); ?></button></p>
					<?php endif; ?>
				</div>
				<?php CG_Admin::toggle( 'cg[list_publicly]', $s['list_publicly'], __( 'Show on the galleries page', 'cheri-gallery' ), __( 'Lists it in the [cheri_galleries] shortcode. Never for private galleries.', 'cheri-gallery' ) ); ?>
				<label class="ch-toggle">
					<span class="ch-toggle__text">
						<span class="ch-toggle__label"><?php esc_html_e( 'Featured on the home page', 'cheri-gallery' ); ?></span>
						<span class="ch-toggle__hint"><?php esc_html_e( 'Shown by [cheri_galleries featured="yes"] in this position (up to 6). Never for private or closed galleries.', 'cheri-gallery' ); ?></span>
					</span>
					<select class="ch-select" name="cg[featured]">
						<option value="0" <?php selected( (int) $s['featured'], 0 ); ?>><?php esc_html_e( 'Not featured', 'cheri-gallery' ); ?></option>
						<?php
						$cg_taken = array();
						foreach ( CG_Galleries::all_published( true ) as $cg_other ) {
							$pos = (int) CG_Galleries::setting( $cg_other->ID, 'featured' );
							if ( $pos && (int) $cg_other->ID !== (int) $post->ID ) {
								$cg_taken[ $pos ] = get_the_title( $cg_other );
							}
						}
						for ( $i = 1; $i <= 6; $i++ ) :
							/* translators: %d: position */
							$label = sprintf( __( 'Position %d', 'cheri-gallery' ), $i ) . ( isset( $cg_taken[ $i ] ) ? ' (' . sprintf( /* translators: %s: gallery */ __( 'now: %s', 'cheri-gallery' ), $cg_taken[ $i ] ) . ')' : '' );
							?>
							<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (int) $s['featured'], $i ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endfor; ?>
					</select>
				</label>
			</div>

			<div class="ch-set__group">
				<p class="ch-set__h"><?php esc_html_e( 'Downloads', 'cheri-gallery' ); ?></p>
				<?php CG_Admin::toggle( 'cg[album_download]', $s['album_download'], __( 'Download whole album', 'cheri-gallery' ), __( 'One zip of every photo', 'cheri-gallery' ) ); ?>
				<?php CG_Admin::toggle( 'cg[photo_download]', $s['photo_download'], __( 'Download single photos', 'cheri-gallery' ), __( 'A download button on each photo. Block individual photos in Photos.', 'cheri-gallery' ) ); ?>
				<label class="ch-toggle">
					<span class="ch-toggle__text"><span class="ch-toggle__label"><?php esc_html_e( 'Download size', 'cheri-gallery' ); ?></span></span>
					<select class="ch-select" name="cg[download_size]">
						<option value="full" <?php selected( $s['download_size'], 'full' ); ?>><?php esc_html_e( 'Full resolution', 'cheri-gallery' ); ?></option>
						<option value="web" <?php selected( $s['download_size'], 'web' ); ?>>
							<?php
							/* translators: %d: pixels */
							echo esc_html( sprintf( __( 'Web size (%dpx)', 'cheri-gallery' ), CG_Settings::get( 'web_size' ) ) );
							?>
						</option>
					</select>
				</label>
				<label class="ch-toggle">
					<span class="ch-toggle__text"><span class="ch-toggle__label"><?php esc_html_e( 'Who can download', 'cheri-gallery' ); ?></span></span>
					<select class="ch-select" name="cg[download_who]">
						<option value="client" <?php selected( $s['download_who'], 'client' ); ?>><?php esc_html_e( 'Signed-in client only', 'cheri-gallery' ); ?></option>
						<option value="anyone" <?php selected( $s['download_who'], 'anyone' ); ?>><?php esc_html_e( 'Anyone who can view', 'cheri-gallery' ); ?></option>
					</select>
				</label>
			</div>

			<div class="ch-set__group">
				<p class="ch-set__h"><?php esc_html_e( 'Protection', 'cheri-gallery' ); ?></p>
				<label class="ch-toggle">
					<span class="ch-toggle__text">
						<span class="ch-toggle__label"><?php esc_html_e( 'Watermark', 'cheri-gallery' ); ?></span>
						<span class="ch-toggle__hint"><?php esc_html_e( 'Drawn into the photos, so screenshots carry it. Downloads get the watermarked copy while it\'s on.', 'cheri-gallery' ); ?></span>
					</span>
					<select class="ch-select" name="cg[watermark]">
						<?php foreach ( CG_Images::watermark_styles() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['watermark'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php CG_Admin::toggle( 'cg[cover_watermark]', $s['cover_watermark'], __( 'Watermark the cover photo', 'cheri-gallery' ), __( 'Off: the big cover photo and the gallery card stay clean while the album photos keep the watermark.', 'cheri-gallery' ) ); ?>
				<?php CG_Admin::toggle( 'cg[protect]', $s['protect'], __( 'Discourage saving', 'cheri-gallery' ), __( 'Turns off right-click and drag-to-save on photos', 'cheri-gallery' ) ); ?>
				<?php CG_Admin::toggle( 'cg[favourites]', $s['favourites'], __( 'Favourites', 'cheri-gallery' ), __( 'Client can make favourite lists and send them to you', 'cheri-gallery' ) ); ?>
				<label class="ch-toggle">
					<span class="ch-toggle__text">
						<span class="ch-toggle__label"><?php esc_html_e( 'Close gallery on', 'cheri-gallery' ); ?></span>
						<span class="ch-toggle__hint"><?php esc_html_e( 'Leave empty to keep it open', 'cheri-gallery' ); ?></span>
					</span>
					<input class="ch-select" type="date" name="cg[expiry]" value="<?php echo esc_attr( $s['expiry'] ); ?>">
				</label>
			</div>
		</div>
		<?php
	}

	public static function box_photos( $post ) {
		$s      = CG_Galleries::get( $post->ID );
		$photos = CG_Photos::for_gallery( $post->ID );
		$cover  = CG_Galleries::cover_photo( $post->ID );
		$counts = array_fill_keys( $s['sets'], 0 );
		foreach ( $photos as $p ) {
			if ( isset( $counts[ $p->set_name ] ) ) {
				++$counts[ $p->set_name ];
			}
		}
		?>
		<div class="cg-admin cg-pm" data-cg-pm>
			<nav class="ch-gh__tabs cg-pm__tabs" data-cg-sets>
				<?php foreach ( $s['sets'] as $i => $set ) : ?>
					<a class="ch-gh__tab" href="#" data-cg-set="<?php echo esc_attr( $set ); ?>" <?php echo 0 === $i ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $set ); ?> · <span data-cg-set-count><?php echo esc_html( $counts[ $set ] ); ?></span></a>
				<?php endforeach; ?>
				<a class="ch-gh__tab" href="#" data-cg-set-add>+ <?php esc_html_e( 'New set', 'cheri-gallery' ); ?></a>
				<span class="cg-pm__setlinks">
					<button type="button" class="ch-textlink" data-cg-set-rename><?php esc_html_e( 'Rename set', 'cheri-gallery' ); ?></button>
					<button type="button" class="ch-textlink" data-cg-set-delete><?php esc_html_e( 'Remove set', 'cheri-gallery' ); ?></button>
					<button type="button" class="ch-textlink cg-danger-link" data-cg-set-empty><?php esc_html_e( 'Delete all photos in this set', 'cheri-gallery' ); ?></button>
				</span>
			</nav>

			<div class="cg-pm__order">
				<label for="cg-photo-order"><?php esc_html_e( 'Order', 'cheri-gallery' ); ?></label>
				<select id="cg-photo-order" class="ch-select" data-cg-order>
					<?php foreach ( CG_Photos::orders() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['photo_order'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="cg-hint"><?php esc_html_e( 'Photos marked ⤒ Top always come first. Applies to the gallery, slideshow, embeds and downloads.', 'cheri-gallery' ); ?></span>
			</div>

			<label class="ch-drop" data-cg-drop>
				<input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-cg-file>
				<?php
				printf(
					/* translators: 1: "choose files" link, 2: max size */
					esc_html__( 'Drag photos here or %1$s · JPEG, PNG or WebP up to %2$s each · web-size and watermarked copies are made automatically', 'cheri-gallery' ),
					'<strong>' . esc_html__( 'choose files', 'cheri-gallery' ) . '</strong>',
					esc_html( size_format( wp_max_upload_size() ) )
				);
				?>
			</label>
			<p class="cg-pm__status" data-cg-status role="status" aria-live="polite"></p>

			<div class="cg-pm__bulk" data-cg-bulk hidden>
				<span data-cg-bulk-count></span>
				<button type="button" class="button" data-cg-bulk-op="hide"><?php esc_html_e( 'Hide', 'cheri-gallery' ); ?></button>
				<button type="button" class="button" data-cg-bulk-op="show"><?php esc_html_e( 'Unhide', 'cheri-gallery' ); ?></button>
				<button type="button" class="button" data-cg-bulk-op="block"><?php esc_html_e( 'Block download', 'cheri-gallery' ); ?></button>
				<button type="button" class="button" data-cg-bulk-op="allow"><?php esc_html_e( 'Allow download', 'cheri-gallery' ); ?></button>
				<button type="button" class="button" data-cg-bulk-op="pin"><?php esc_html_e( 'Move to top', 'cheri-gallery' ); ?></button>
				<button type="button" class="button" data-cg-bulk-op="unpin"><?php esc_html_e( 'Unpin', 'cheri-gallery' ); ?></button>
				<select data-cg-bulk-move>
					<option value=""><?php esc_html_e( 'Move to set…', 'cheri-gallery' ); ?></option>
					<?php foreach ( $s['sets'] as $set ) : ?>
						<option value="<?php echo esc_attr( $set ); ?>"><?php echo esc_html( $set ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button cg-danger" data-cg-bulk-op="delete"><?php esc_html_e( 'Delete', 'cheri-gallery' ); ?></button>
				<button type="button" class="button-link" data-cg-bulk-clear><?php esc_html_e( 'Clear selection', 'cheri-gallery' ); ?></button>
			</div>

			<div class="ch-pm" data-cg-grid>
				<?php
				foreach ( $photos as $p ) {
					echo self::tile( $p, $cover, $s['sets'] ); // phpcs:ignore
				}
				?>
			</div>
			<input type="hidden" name="cg[cover_photo]" value="<?php echo esc_attr( $cover ); ?>" data-cg-cover-input>
		</div>
		<?php
	}

	/**
	 * One photo tile in the manager.
	 */
	public static function tile( $p, $cover_id, array $sets ) {
		$set = in_array( $p->set_name, $sets, true ) ? $p->set_name : $sets[0];
		ob_start();
		?>
		<div class="ch-pm__item<?php echo $p->hidden ? ' is-hidden' : ''; ?>" data-cg-tile="<?php echo esc_attr( $p->id ); ?>" data-set="<?php echo esc_attr( $set ); ?>">
			<label class="cg-pm__check"><input type="checkbox" data-cg-select aria-label="<?php esc_attr_e( 'Select photo', 'cheri-gallery' ); ?>"></label>
			<img src="<?php echo esc_url( CG_Delivery::photo_url( $p, 'thumb', true ) ); ?>" alt="" loading="lazy">
			<span class="ch-pm__flag">
				<?php if ( (int) $p->id === (int) $cover_id ) : ?>
					<span class="ch-badge ch-badge--password"><?php esc_html_e( 'Cover', 'cheri-gallery' ); ?></span>
				<?php endif; ?>
				<?php if ( $p->hidden ) : ?>
					<span class="ch-badge ch-badge--private"><?php echo cg_icon( 'eyeoff' ); // phpcs:ignore ?> <?php esc_html_e( 'Hidden', 'cheri-gallery' ); ?></span>
				<?php endif; ?>
				<?php if ( $p->no_download ) : ?>
					<span class="ch-badge ch-badge--private"><?php echo cg_icon( 'nodl' ); // phpcs:ignore ?> <?php esc_html_e( 'No download', 'cheri-gallery' ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $p->pinned ) ) : ?>
					<span class="ch-badge ch-badge--password">⤒ <?php esc_html_e( 'Top', 'cheri-gallery' ); ?></span>
				<?php endif; ?>
			</span>
			<div class="ch-pm__bar">
				<span class="cg-pm__name" title="<?php echo esc_attr( $p->original_name ); ?>"><?php echo esc_html( $p->original_name ); ?></span>
				<span class="cg-pm__btns">
					<button type="button" class="ch-iconbtn" data-cg-op="cover" title="<?php esc_attr_e( 'Use as cover', 'cheri-gallery' ); ?>" aria-label="<?php esc_attr_e( 'Use as cover', 'cheri-gallery' ); ?>">★</button>
					<button type="button" class="ch-iconbtn" data-cg-op="hidden" aria-pressed="<?php echo $p->hidden ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Hide from client', 'cheri-gallery' ); ?>" aria-label="<?php esc_attr_e( 'Hide from client', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'eyeoff' ); // phpcs:ignore ?></button>
					<button type="button" class="ch-iconbtn" data-cg-op="pinned" aria-pressed="<?php echo ! empty( $p->pinned ) ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Move to the top', 'cheri-gallery' ); ?>" aria-label="<?php esc_attr_e( 'Move to the top', 'cheri-gallery' ); ?>">⤒</button>
					<button type="button" class="ch-iconbtn" data-cg-op="no_download" aria-pressed="<?php echo $p->no_download ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Block download of this photo', 'cheri-gallery' ); ?>" aria-label="<?php esc_attr_e( 'Block download of this photo', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'nodl' ); // phpcs:ignore ?></button>
				</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function box_favourites( $post ) {
		$lists = CG_Favourites::lists_for_gallery( $post->ID );
		echo '<div class="cg-admin">';
		if ( ! $lists ) {
			echo '<p class="cg-hint">' . esc_html__( 'When the client hearts photos and sends a list, it appears here.', 'cheri-gallery' ) . '</p>';
		} else {
			CG_Admin_Clients::render_picks( $lists, true );
		}
		echo '</div>';
	}

	public static function box_share( $post ) {
		$log = CG_Email::log_for( $post->ID );
		echo '<div class="cg-admin cg-side">';
		if ( 'publish' !== $post->post_status ) {
			echo '<p class="cg-hint">' . esc_html__( 'Publish the gallery first, then share it from here.', 'cheri-gallery' ) . '</p>';
		} else {
			echo '<p><a class="ch-btn ch-btn--sm" href="' . esc_url( self::share_url( $post->ID ) ) . '">' . esc_html__( 'Share gallery', 'cheri-gallery' ) . '</a></p>';
		}
		// Shortcode to show this gallery on another page (public galleries only).
		echo '<p class="ch-set__h" style="margin-top:16px">' . esc_html__( 'Show on a web page', 'cheri-gallery' ) . '</p>';
		if ( CG_Frontend::embeddable( $post->ID ) ) {
			$code = '[cheri_gallery id="' . absint( $post->ID ) . '"]';
			echo '<p class="cg-copyline"><code>' . esc_html( $code ) . '</code> <button type="button" class="button button-small" data-cg-copy="' . esc_attr( $code ) . '">' . esc_html__( 'Copy', 'cheri-gallery' ) . '</button></p>';
			echo '<p class="cg-hint">' . esc_html__( 'Paste into any page or an Elementor Shortcode widget. Options: set="Ceremony" limit="12" columns="3" gap="12" title="yes" link="no".', 'cheri-gallery' ) . '</p>';
		} else {
			echo '<p class="cg-hint">' . esc_html__( 'Only public, published galleries can be shown on other pages. Set "Who can see it" to Public to get a shortcode.', 'cheri-gallery' ) . '</p>';
		}
		if ( $log ) {
			echo '<p class="ch-set__h">' . esc_html__( 'Sent', 'cheri-gallery' ) . '</p><ul class="cg-log">';
			foreach ( array_slice( $log, 0, 5 ) as $row ) {
				echo '<li>' . esc_html( mysql2date( 'j M Y, H:i', $row->sent_at ) ) . '<br><span class="cg-hint">' . esc_html( $row->recipients ) . '</span></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------ */
	/* Saving                                                              */
	/* ------------------------------------------------------------------ */

	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['_cg_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_cg_gallery_nonce'] ) ), 'cg_save_gallery' ) ) {
			return;
		}
		if ( ! current_user_can( CG_CAP ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$input  = isset( $_POST['cg'] ) && is_array( $_POST['cg'] ) ? wp_unslash( $_POST['cg'] ) : array(); // phpcs:ignore
		$before = CG_Galleries::get( $post_id );
		$after  = CG_Galleries::save( $post_id, $input );
		if ( $after['featured'] && $after['featured'] !== (int) $before['featured'] ) {
			foreach ( CG_Galleries::all_published( true ) as $other ) {
				if ( (int) $other->ID !== (int) $post_id && (int) CG_Galleries::setting( $other->ID, 'featured' ) === (int) $after['featured'] ) {
					CG_Galleries::save( $other->ID, array( 'featured' => 0 ) );
				}
			}
		}
		CG_Galleries::sync_client_index( $post_id );

		if ( $before['watermark'] !== $after['watermark'] || $before['download_size'] !== $after['download_size'] ) {
			CG_Images::clear_watermarks( $post_id );
			CG_Delivery::clear_zip_cache( $post_id );
		}
	}

	/* ------------------------------------------------------------------ */
	/* AJAX: upload and manage photos                                      */
	/* ------------------------------------------------------------------ */

	public static function ajax() {
		$gid = isset( $_POST['gallery'] ) ? absint( $_POST['gallery'] ) : 0;
		if ( ! $gid || ! check_ajax_referer( 'cg_admin_' . $gid, 'nonce', false ) || ! current_user_can( CG_CAP ) || ! CG_Galleries::is_gallery( $gid ) ) {
			cg_json_fail( __( 'Your session has expired. Reload the page.', 'cheri-gallery' ), 403 );
		}
		$op  = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore
		$s   = CG_Galleries::get( $gid );

		switch ( $op ) {
			case 'upload':
				if ( empty( $_FILES['file'] ) ) {
					cg_json_fail( __( 'No file received.', 'cheri-gallery' ) );
				}
				$set = isset( $_POST['set'] ) ? sanitize_text_field( wp_unslash( $_POST['set'] ) ) : '';
				if ( ! in_array( $set, $s['sets'], true ) ) {
					$set = $s['sets'][0];
				}
				if ( function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 120 ); // phpcs:ignore
				}
				$photo = CG_Photos::add_from_upload( $gid, $_FILES['file'], $set ); // phpcs:ignore
				if ( is_wp_error( $photo ) ) {
					cg_json_fail( $photo->get_error_message() );
				}
				cg_json_ok( array( 'html' => self::tile( $photo, CG_Galleries::cover_photo( $gid ), $s['sets'] ) ) );
				break;

			case 'set_flag':
				$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
				if ( ! in_array( $field, array( 'hidden', 'no_download', 'pinned' ), true ) ) {
					cg_json_fail( 'Bad field' );
				}
				CG_Photos::update( $gid, $ids, array( $field => ! empty( $_POST['value'] ) ) );
				break;

			case 'move':
				$set = isset( $_POST['set'] ) ? sanitize_text_field( wp_unslash( $_POST['set'] ) ) : '';
				if ( ! in_array( $set, $s['sets'], true ) ) {
					cg_json_fail( __( 'That set doesn\'t exist.', 'cheri-gallery' ) );
				}
				CG_Photos::update( $gid, $ids, array( 'set_name' => $set ) );
				break;

			case 'delete':
				CG_Photos::delete( $gid, $ids );
				break;

			case 'order':
				$order = isset( $_POST['order'] ) ? sanitize_key( $_POST['order'] ) : '';
				CG_Galleries::save( $gid, array( 'photo_order' => $order ) );
				CG_Delivery::clear_zip_cache( $gid );
				break;

			case 'refresh':
				break;

			case 'cover':
				CG_Galleries::save( $gid, array( 'cover_photo' => $ids ? $ids[0] : 0 ) );
				break;

			case 'reorder':
				CG_Photos::reorder( $gid, $ids );
				break;

			case 'sets':
				// New list of set names plus any renames ({"Old": "New"}).
				$sets    = isset( $_POST['sets'] ) ? (array) wp_unslash( $_POST['sets'] ) : array(); // phpcs:ignore
				$renames = isset( $_POST['renames'] ) ? json_decode( wp_unslash( $_POST['renames'] ), true ) : array(); // phpcs:ignore
				$saved   = CG_Galleries::save( $gid, array( 'sets' => $sets ) );
				foreach ( (array) $renames as $from => $to ) {
					$to = sanitize_text_field( $to );
					if ( in_array( $to, $saved['sets'], true ) ) {
						CG_Photos::rename_set( $gid, sanitize_text_field( $from ), $to );
					}
				}
				// Photos in a removed set go to the first set.
				foreach ( CG_Photos::for_gallery( $gid ) as $p ) {
					if ( ! in_array( $p->set_name, $saved['sets'], true ) ) {
						CG_Photos::update( $gid, array( $p->id ), array( 'set_name' => $saved['sets'][0] ) );
					}
				}
				cg_json_ok( array( 'sets' => $saved['sets'] ) );
				break;

			default:
				cg_json_fail( 'Unknown action' );
		}

		// After any change, send back fresh tiles for the affected photos.
		$cover = CG_Galleries::cover_photo( $gid );
		$tiles = array();
		$fresh = CG_Galleries::get( $gid );
		foreach ( CG_Photos::for_gallery( $gid ) as $p ) {
			if ( 'cover' === $op || in_array( (int) $p->id, $ids, true ) ) {
				$tiles[ $p->id ] = self::tile( $p, $cover, $fresh['sets'] );
			}
		}
		cg_json_ok(
			array(
				'tiles' => $tiles,
				'order' => array_map( 'intval', wp_list_pluck( CG_Photos::for_gallery( $gid ), 'id' ) ),
			)
		);
	}
}
