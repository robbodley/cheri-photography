<?php
/**
 * Clients: list, add, a client's page (details, galleries, favourites picks), sign-in links.
 */

defined( 'ABSPATH' ) || exit;

class CG_Admin_Clients {

	public static function init() {
		add_action( 'admin_post_cg_add_client', array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_cg_save_client', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_cg_send_link', array( __CLASS__, 'handle_send_link' ) );
	}

	public static function url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'edit.php?post_type=' . CG_Galleries::POST_TYPE . '&page=cg-clients' ) );
	}

	public static function page() {
		if ( ! current_user_can( CG_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		$client = isset( $_GET['client'] ) ? absint( $_GET['client'] ) : 0; // phpcs:ignore
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore

		echo '<div class="wrap cg-wrap">';
		CG_Admin::notice_from_query();
		if ( 'new' === $action ) {
			self::render_new();
		} elseif ( $client ) {
			self::render_client( $client );
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------ */
	/* List                                                                */
	/* ------------------------------------------------------------------ */

	private static function render_list() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
		$args   = array(
			'role'    => CG_ROLE,
			'orderby' => 'registered',
			'order'   => 'DESC',
			'number'  => 200,
		);
		if ( $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_email', 'display_name', 'user_login' );
		}
		$users = get_users( $args );
		?>
		<div class="cg-admin">
			<section class="ch-panel">
				<div class="ch-panel__head">
					<h1 class="ch-panel__title"><?php esc_html_e( 'Clients', 'cheri-gallery' ); ?></h1>
					<div class="ch-row">
						<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
							<input type="hidden" name="post_type" value="<?php echo esc_attr( CG_Galleries::POST_TYPE ); ?>">
							<input type="hidden" name="page" value="cg-clients">
							<input class="ch-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or email', 'cheri-gallery' ); ?>" aria-label="<?php esc_attr_e( 'Search clients', 'cheri-gallery' ); ?>">
						</form>
						<a class="ch-btn ch-btn--sm" href="<?php echo esc_url( self::url( array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Add client', 'cheri-gallery' ); ?></a>
					</div>
				</div>
				<?php if ( ! $users ) : ?>
					<div class="ch-panel__body"><p class="cg-hint"><?php echo $search ? esc_html__( 'No clients match that search.', 'cheri-gallery' ) : esc_html__( 'No clients yet. Add your first one.', 'cheri-gallery' ); ?></p></div>
				<?php else : ?>
					<table class="ch-table">
						<thead><tr>
							<th><?php esc_html_e( 'Client', 'cheri-gallery' ); ?></th>
							<th class="num"><?php esc_html_e( 'Galleries', 'cheri-gallery' ); ?></th>
							<th><?php esc_html_e( 'Favourites', 'cheri-gallery' ); ?></th>
							<th><?php esc_html_e( 'Last visit', 'cheri-gallery' ); ?></th>
						</tr></thead>
						<tbody>
							<?php
							foreach ( $users as $u ) :
								$galleries = CG_Galleries::for_client( $u->ID );
								$lists     = CG_Favourites::lists_for_user( $u->ID );
								$unseen    = CG_Favourites::unseen_count( $u->ID );
								$last      = get_user_meta( $u->ID, '_cg_last_visit', true );
								?>
								<tr>
									<td><div class="ch-who"><span class="ch-avatar"><?php echo esc_html( self::initials( $u ) ); ?></span><span><a class="ch-who__name" href="<?php echo esc_url( self::url( array( 'client' => $u->ID ) ) ); ?>"><?php echo esc_html( $u->display_name ); ?></a><span class="ch-who__mail"><?php echo esc_html( $u->user_email ); ?></span></span></div></td>
									<td class="num"><?php echo esc_html( count( $galleries ) ); ?></td>
									<td>
										<?php
										if ( $unseen ) {
											$first = null;
											foreach ( $lists as $l ) {
												if ( 'sent' === $l->status && ! $l->seen_at ) {
													$first = $l;
													break;
												}
											}
											/* translators: 1: list name, 2: count */
											echo '<span class="ch-dot ch-dot--new">' . esc_html( sprintf( __( 'New: %1$s · %2$d', 'cheri-gallery' ), $first->name, count( $first->photos ) ) ) . '</span>';
										} elseif ( $lists ) {
											echo '<span class="ch-dot">' . esc_html( $lists[0]->name . ' · ' . count( $lists[0]->photos ) ) . '</span>';
										} else {
											echo '<span class="ch-dot">' . esc_html__( 'None yet', 'cheri-gallery' ) . '</span>';
										}
										?>
									</td>
									<td><?php echo $last ? esc_html( human_time_diff( strtotime( $last ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'cheri-gallery' ) ) : esc_html__( 'Not visited', 'cheri-gallery' ); // phpcs:ignore ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	private static function initials( WP_User $u ) {
		$name  = $u->display_name ? $u->display_name : $u->user_email;
		$parts = preg_split( '/[\s&]+/', trim( $name ) );
		$out   = '';
		foreach ( $parts as $p ) {
			if ( '' !== $p ) {
				$out .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
			}
			if ( mb_strlen( $out ) >= 2 ) {
				break;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Add                                                                 */
	/* ------------------------------------------------------------------ */

	private static function render_new() {
		?>
		<div class="cg-admin">
			<p><a href="<?php echo esc_url( self::url() ); ?>">← <?php esc_html_e( 'All clients', 'cheri-gallery' ); ?></a></p>
			<section class="ch-panel cg-narrow-panel">
				<div class="ch-panel__head"><h1 class="ch-panel__title"><?php esc_html_e( 'Add client', 'cheri-gallery' ); ?></h1></div>
				<form class="ch-panel__body ch-set" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'cg_add_client' ); ?>
					<input type="hidden" name="action" value="cg_add_client">
					<?php self::client_fields(); ?>
					<?php CG_Admin::toggle( 'send_link', false, __( 'Email them a sign-in link now', 'cheri-gallery' ), __( 'Or wait and send the gallery email when their photos are ready', 'cheri-gallery' ) ); ?>
					<div><button class="ch-btn" type="submit"><?php esc_html_e( 'Add client', 'cheri-gallery' ); ?></button></div>
				</form>
			</section>
		</div>
		<?php
	}

	private static function client_fields( $u = null ) {
		$f = array(
			'first_name' => $u ? $u->first_name : '',
			'last_name'  => $u ? $u->last_name : '',
			'email'      => $u ? $u->user_email : '',
			'phone'      => $u ? get_user_meta( $u->ID, '_cg_phone', true ) : '',
			'notes'      => $u ? get_user_meta( $u->ID, '_cg_notes', true ) : '',
		);
		?>
		<div class="cg-grid2">
			<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Name', 'cheri-gallery' ); ?></span><input class="ch-field__input" name="first_name" value="<?php echo esc_attr( $f['first_name'] ); ?>" required placeholder="<?php esc_attr_e( 'e.g. Emma', 'cheri-gallery' ); ?>"></label>
			<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Surname (optional)', 'cheri-gallery' ); ?></span><input class="ch-field__input" name="last_name" value="<?php echo esc_attr( $f['last_name'] ); ?>"></label>
		</div>
		<div class="cg-grid2">
			<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Email', 'cheri-gallery' ); ?></span><input class="ch-field__input" type="email" name="email" value="<?php echo esc_attr( $f['email'] ); ?>" required></label>
			<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Phone (optional)', 'cheri-gallery' ); ?></span><input class="ch-field__input" type="tel" name="phone" value="<?php echo esc_attr( $f['phone'] ); ?>"></label>
		</div>
		<label class="ch-field"><span class="ch-field__label"><?php esc_html_e( 'Private notes (only you see these)', 'cheri-gallery' ); ?></span><textarea class="ch-field__input cg-textarea" name="notes" rows="3"><?php echo esc_textarea( $f['notes'] ); ?></textarea></label>
		<p class="cg-hint"><?php esc_html_e( 'For a couple, add each person as a client so both can sign in, then pick both on the gallery.', 'cheri-gallery' ); ?></p>
		<?php
	}

	public static function handle_add() {
		if ( ! current_user_can( CG_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		check_admin_referer( 'cg_add_client' );
		$uid = CG_Auth::create_client(
			isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '', // phpcs:ignore
			isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
			isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
			isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : ''
		);
		if ( is_wp_error( $uid ) ) {
			wp_safe_redirect( self::url( array( 'action' => 'new', 'cg_err' => rawurlencode( $uid->get_error_message() ) ) ) );
			exit;
		}
		$msg = 'client_added';
		if ( ! empty( $_POST['send_link'] ) ) {
			$msg = CG_Auth::send_link( get_userdata( $uid ) ) ? 'link_sent' : 'link_failed';
		}
		wp_safe_redirect( self::url( array( 'client' => $uid, 'cg_msg' => $msg ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* One client                                                          */
	/* ------------------------------------------------------------------ */

	private static function render_client( $uid ) {
		$u = get_userdata( $uid );
		if ( ! $u || ! CG_Auth::is_client( $u ) ) {
			echo '<p>' . esc_html__( 'Client not found.', 'cheri-gallery' ) . '</p>';
			return;
		}
		$galleries = CG_Galleries::for_client( $uid );
		$lists     = CG_Favourites::lists_for_user( $uid );
		?>
		<div class="cg-admin">
			<p><a href="<?php echo esc_url( self::url() ); ?>">← <?php esc_html_e( 'All clients', 'cheri-gallery' ); ?></a></p>
			<div class="cg-client">
				<div class="cg-client__main">
					<section class="ch-panel">
						<div class="ch-panel__head">
							<h1 class="ch-panel__title"><?php echo esc_html( $u->display_name ); ?> · <?php esc_html_e( 'Favourites', 'cheri-gallery' ); ?></h1>
						</div>
						<div class="ch-panel__body cg-picks-body">
							<?php
							if ( $lists ) {
								self::render_picks( $lists, false, true );
							} else {
								echo '<p class="cg-hint">' . esc_html__( 'No favourites yet. When they heart photos and send a list, it appears here.', 'cheri-gallery' ) . '</p>';
							}
							?>
						</div>
					</section>

					<section class="ch-panel">
						<div class="ch-panel__head">
							<h2 class="ch-panel__title"><?php esc_html_e( 'Galleries', 'cheri-gallery' ); ?></h2>
							<a class="ch-btn ch-btn--sm ch-btn--outline" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . CG_Galleries::POST_TYPE ) ); ?>"><?php esc_html_e( 'Add gallery', 'cheri-gallery' ); ?></a>
						</div>
						<?php if ( $galleries ) : ?>
							<table class="ch-table">
								<tbody>
								<?php foreach ( $galleries as $g ) : ?>
									<tr>
										<td><a class="ch-who__name" href="<?php echo esc_url( get_edit_post_link( $g->ID ) ); ?>"><?php echo esc_html( get_the_title( $g ) ); ?></a><span class="ch-who__mail"><?php echo esc_html( cg_session_label( CG_Galleries::setting( $g->ID, 'session_type' ) ) . ' · ' . cg_format_date( CG_Galleries::setting( $g->ID, 'event_date' ), 'j M Y' ) ); ?></span></td>
										<td class="num"><?php echo esc_html( CG_Photos::count( $g->ID ) ); ?> <?php esc_html_e( 'photos', 'cheri-gallery' ); ?></td>
										<td class="num"><a href="<?php echo esc_url( CG_Admin_Gallery::share_url( $g->ID ) ); ?>"><?php esc_html_e( 'Share', 'cheri-gallery' ); ?></a></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<div class="ch-panel__body"><p class="cg-hint"><?php esc_html_e( 'Not on any gallery yet. Open a gallery and pick them under "Client & shoot".', 'cheri-gallery' ); ?></p></div>
						<?php endif; ?>
					</section>
				</div>

				<aside class="cg-client__side">
					<section class="ch-panel">
						<div class="ch-panel__head"><h2 class="ch-panel__title"><?php esc_html_e( 'Details', 'cheri-gallery' ); ?></h2></div>
						<form class="ch-panel__body ch-set" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'cg_save_client_' . $uid ); ?>
							<input type="hidden" name="action" value="cg_save_client">
							<input type="hidden" name="client" value="<?php echo esc_attr( $uid ); ?>">
							<?php self::client_fields( $u ); ?>
							<div><button class="ch-btn ch-btn--sm" type="submit"><?php esc_html_e( 'Save', 'cheri-gallery' ); ?></button></div>
						</form>
					</section>
					<section class="ch-panel">
						<div class="ch-panel__body">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'cg_send_link_' . $uid ); ?>
								<input type="hidden" name="action" value="cg_send_link">
								<input type="hidden" name="client" value="<?php echo esc_attr( $uid ); ?>">
								<button class="ch-btn ch-btn--sm ch-btn--outline" type="submit"><?php esc_html_e( 'Email a sign-in link', 'cheri-gallery' ); ?></button>
							</form>
							<p class="cg-hint">
								<?php
								$last = get_user_meta( $uid, '_cg_last_visit', true );
								echo $last
									/* translators: %s: date */
									? esc_html( sprintf( __( 'Last visit: %s', 'cheri-gallery' ), mysql2date( 'j M Y, H:i', $last ) ) )
									: esc_html__( 'Hasn\'t signed in yet.', 'cheri-gallery' );
								?>
							</p>
						</div>
					</section>
				</aside>
			</div>
		</div>
		<?php
		// Opening the client's page counts as seeing their sent lists.
		foreach ( $lists as $l ) {
			if ( 'sent' === $l->status && ! $l->seen_at ) {
				CG_Favourites::mark_seen( $l->id );
			}
		}
	}

	/**
	 * Favourites lists as Cheri sees them: name, New flag, count, note, photos, Copy filenames.
	 *
	 * @param array $lists        Lists with ->photos.
	 * @param bool  $show_client  Show whose list it is (gallery screen).
	 * @param bool  $show_gallery Show which gallery it's from (client screen).
	 */
	public static function render_picks( array $lists, $show_client = false, $show_gallery = false ) {
		foreach ( $lists as $l ) {
			$count = count( $l->photos );
			$names = CG_Favourites::filenames( $l->id );
			$who   = $show_client ? get_userdata( $l->user_id ) : null;
			$bits  = array( sprintf( _n( '%d photo', '%d photos', $count, 'cheri-gallery' ), $count ) );
			if ( $show_gallery ) {
				$bits[] = get_the_title( $l->gallery_id );
			}
			if ( $who ) {
				$bits[] = $who->display_name;
			}
			/* translators: %s: date */
			$bits[] = 'sent' === $l->status ? sprintf( __( 'sent %s', 'cheri-gallery' ), mysql2date( 'j M Y, H:i', $l->sent_at ) ) : __( 'still being chosen', 'cheri-gallery' );
			?>
			<div class="ch-pick" id="list-<?php echo esc_attr( $l->id ); ?>">
				<div>
					<span class="ch-list__name"><?php echo esc_html( $l->name ); ?></span>
					<?php if ( 'sent' === $l->status && ! $l->seen_at ) : ?>
						<span class="ch-dot ch-dot--new"><?php esc_html_e( 'New', 'cheri-gallery' ); ?></span>
					<?php endif; ?>
					<span class="ch-list__meta" style="display:block"><?php echo esc_html( implode( ' · ', $bits ) ); ?></span>
				</div>
				<div class="ch-row">
					<?php if ( $names ) : ?>
						<button type="button" class="ch-btn ch-btn--sm ch-btn--outline" data-cg-copy="<?php echo esc_attr( implode( ', ', $names ) ); ?>" title="<?php esc_attr_e( 'Paste into Lightroom\'s filename filter', 'cheri-gallery' ); ?>"><?php esc_html_e( 'Copy filenames', 'cheri-gallery' ); ?></button>
					<?php endif; ?>
				</div>
				<?php if ( $l->note ) : ?>
					<p class="ch-note">"<?php echo esc_html( $l->note ); ?>"</p>
				<?php endif; ?>
				<?php if ( $count ) : ?>
					<div class="ch-pick__strip cg-strip">
						<?php
						foreach ( $l->photos as $pid ) :
							$p = CG_Photos::get( $pid );
							if ( ! $p ) {
								continue;
							}
							?>
							<a href="<?php echo esc_url( CG_Delivery::photo_url( $p, 'web', true ) ); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr( $p->original_name ); ?>"><img src="<?php echo esc_url( CG_Delivery::photo_url( $p, 'thumb', true ) ); ?>" alt="<?php echo esc_attr( $p->original_name ); ?>" loading="lazy"></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	public static function handle_save() {
		$uid = isset( $_POST['client'] ) ? absint( $_POST['client'] ) : 0;
		if ( ! current_user_can( CG_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		check_admin_referer( 'cg_save_client_' . $uid );
		$u = get_userdata( $uid );
		if ( ! $u || ! CG_Auth::is_client( $u ) ) {
			wp_die( esc_html__( 'Client not found.', 'cheri-gallery' ) );
		}
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_safe_redirect( self::url( array( 'client' => $uid, 'cg_err' => rawurlencode( __( 'That email address doesn\'t look right.', 'cheri-gallery' ) ) ) ) );
			exit;
		}
		$other = get_user_by( 'email', $email );
		if ( $other && (int) $other->ID !== $uid ) {
			wp_safe_redirect( self::url( array( 'client' => $uid, 'cg_err' => rawurlencode( __( 'Another account already uses that email.', 'cheri-gallery' ) ) ) ) );
			exit;
		}
		$display = trim( $first . ' ' . $last );
		$result  = wp_update_user(
			array(
				'ID'           => $uid,
				'user_email'   => $email,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => $display ? $display : $email,
			)
		);
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( self::url( array( 'client' => $uid, 'cg_err' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		update_user_meta( $uid, '_cg_phone', isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '' );
		update_user_meta( $uid, '_cg_notes', isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '' );
		wp_safe_redirect( self::url( array( 'client' => $uid, 'cg_msg' => 'client_saved' ) ) );
		exit;
	}

	public static function handle_send_link() {
		$uid = isset( $_POST['client'] ) ? absint( $_POST['client'] ) : 0;
		if ( ! current_user_can( CG_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'cheri-gallery' ) );
		}
		check_admin_referer( 'cg_send_link_' . $uid );
		$u  = get_userdata( $uid );
		$ok = $u && CG_Auth::is_client( $u ) && CG_Auth::send_link( $u );
		wp_safe_redirect( self::url( array( 'client' => $uid, 'cg_msg' => $ok ? 'link_sent' : 'link_failed' ) ) );
		exit;
	}
}
