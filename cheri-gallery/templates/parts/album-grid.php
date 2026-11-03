<?php
/**
 * Grid of album cards.
 *
 * @var WP_Post[] $galleries
 */

defined( 'ABSPATH' ) || exit;

if ( ! $galleries ) {
	return;
}
$badge = array(
	'public'   => array( 'globe', __( 'Public', 'cheri-gallery' ), '' ),
	'password' => array( 'lock', __( 'Password', 'cheri-gallery' ), ' ch-badge--password' ),
	'private'  => array( 'eyeoff', __( 'Private', 'cheri-gallery' ), ' ch-badge--private' ),
);
?>
<div class="ch-albums">
	<?php
	foreach ( $galleries as $g ) :
		$s     = CG_Galleries::get( $g->ID );
		$cover = CG_Frontend::card_cover_url( $g->ID );
		$count = CG_Photos::count( $g->ID );
		$b     = $badge[ $s['visibility'] ];
		$meta  = array_filter( array( cg_format_date( $s['event_date'], 'j M Y' ), $count ? sprintf( _n( '%d photo', '%d photos', $count, 'cheri-gallery' ), $count ) : '' ) );
		?>
		<a class="ch-album" href="<?php echo esc_url( CG_Galleries::link( $g->ID, CG_Access::is_client_of( $g->ID ) ) ); ?>">
			<div class="ch-album__media">
				<?php if ( $cover ) : ?>
					<img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy">
				<?php else : ?>
					<span class="ch-ph ch-ph--tan"></span>
				<?php endif; ?>
				<span class="ch-album__badge"><span class="ch-badge<?php echo esc_attr( $b[2] ); ?>"><?php echo cg_icon( $b[0] ); // phpcs:ignore ?> <?php echo esc_html( $b[1] ); ?></span></span>
			</div>
			<div class="ch-album__body">
				<p class="ch-album__type"><?php echo esc_html( cg_session_label( $s['session_type'] ) ); ?></p>
				<h3 class="ch-album__title"><?php echo esc_html( get_the_title( $g ) ); ?></h3>
				<?php if ( $meta ) : ?>
					<p class="ch-album__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
				<?php endif; ?>
			</div>
		</a>
	<?php endforeach; ?>
</div>
