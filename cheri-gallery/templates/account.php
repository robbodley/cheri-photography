<?php
/**
 * My galleries: everything a signed-in client has, plus their favourites lists.
 *
 * @var WP_User   $user
 * @var WP_Post[] $galleries
 */

defined( 'ABSPATH' ) || exit;

$name  = $user->first_name ? $user->first_name : $user->display_name;
$lists = CG_Favourites::lists_for_user( $user->ID );

include cg_template( 'parts/page-start.php' );
?>

<main class="ch-band">
	<div class="ch-sh">
		<img class="ch-sh__mark" src="<?php echo esc_url( CG_URL . 'assets/img/cheri-mark.png' ); ?>" alt="">
		<hr class="ch-sh__rule">
		<?php /* translators: %s: first name */ ?>
		<h1 class="ch-sh__title"><?php echo esc_html( sprintf( __( 'Hello %s', 'cheri-gallery' ), $name ) ); ?></h1>
		<p class="ch-sh__intro"><?php esc_html_e( 'Your galleries, all in one place.', 'cheri-gallery' ); ?></p>
	</div>

	<div class="ch-band__inner">
		<?php if ( $galleries ) : ?>
			<?php echo cg_render( 'parts/album-grid.php', array( 'galleries' => $galleries ) ); // phpcs:ignore ?>
		<?php else : ?>
			<p class="ch-empty"><?php esc_html_e( 'Your galleries will appear here as soon as they\'re ready.', 'cheri-gallery' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $lists ) : ?>
		<div class="ch-band__inner ch-account-lists">
			<h2 class="ch-sh__title ch-sh__title--sm"><?php esc_html_e( 'My favourites', 'cheri-gallery' ); ?></h2>
			<div class="ch-lists">
				<?php
				foreach ( $lists as $list ) :
					$count = count( $list->photos );
					$thumb = array_slice( $list->photos, 0, 4 );
					?>
					<a class="ch-list" href="<?php echo esc_url( add_query_arg( 'favs', $list->id, get_permalink( $list->gallery_id ) ) ); ?>">
						<span class="ch-list__thumbs">
							<?php
							foreach ( $thumb as $pid ) :
								$p = CG_Photos::get( $pid );
								if ( $p ) :
									?>
									<img src="<?php echo esc_url( CG_Delivery::photo_url( $p, 'thumb' ) ); ?>" alt="" loading="lazy">
									<?php
								endif;
							endforeach;
							?>
						</span>
						<span class="ch-list__main">
							<span class="ch-list__name"><?php echo esc_html( $list->name ); ?></span>
							<span class="ch-list__meta">
								<?php
								echo esc_html( get_the_title( $list->gallery_id ) . ' · ' . sprintf( _n( '%d photo', '%d photos', $count, 'cheri-gallery' ), $count ) . ' · ' );
								echo 'sent' === $list->status
									/* translators: %s: date */
									? esc_html( sprintf( __( 'Sent to Cheri %s', 'cheri-gallery' ), mysql2date( 'j M', $list->sent_at ) ) )
									: esc_html__( 'Not sent', 'cheri-gallery' );
								?>
							</span>
						</span>
						<span class="ch-heart"><?php echo cg_icon( 'heart' ); // phpcs:ignore ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</main>
<?php
include cg_template( 'parts/page-end.php' );
