<?php
/**
 * A public gallery's photos on another page ([cheri_gallery id="..."]).
 *
 * @var int    $gallery_id
 * @var array  $photos
 * @var int    $columns
 * @var int    $gap      Space between photos in px.
 * @var bool   $show_title
 * @var bool   $show_link
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="cg-page cg-embed<?php echo CG_Galleries::setting( $gallery_id, 'protect' ) ? ' cg-protect' : ''; ?>">
	<?php if ( $show_title ) : ?>
		<h2 class="ch-embed__title"><?php echo esc_html( get_the_title( $gallery_id ) ); ?></h2>
	<?php endif; ?>
	<div class="ch-grid ch-grid--embed"<?php echo CG_Galleries::setting( $gallery_id, 'even_bottom' ) ? '' : ' data-cg-even-off'; ?> style="--cg-cols: <?php echo esc_attr( $columns ); ?>; --cg-gap: <?php echo esc_attr( $gap ); ?>px">
		<?php foreach ( $photos as $p ) : ?>
			<figure class="ch-grid__item" data-cg-photo="<?php echo esc_attr( $p->id ); ?>" data-web="<?php echo esc_url( CG_Delivery::photo_url( $p, 'web' ) ); ?>" data-dl="" data-name="">
				<a href="<?php echo esc_url( CG_Delivery::photo_url( $p, 'web' ) ); ?>" class="ch-grid__open" aria-label="<?php esc_attr_e( 'Open photo', 'cheri-gallery' ); ?>">
					<img src="<?php echo esc_url( CG_Delivery::photo_url( $p, 'thumb' ) ); ?>" alt="" loading="lazy" decoding="async" <?php echo $p->width ? 'width="' . esc_attr( $p->width ) . '" height="' . esc_attr( $p->height ) . '"' : ''; ?>>
				</a>
			</figure>
		<?php endforeach; ?>
	</div>
	<?php if ( $show_link ) : ?>
		<p class="ch-embed__more"><a class="ch-btn ch-btn--outline" href="<?php echo esc_url( get_permalink( $gallery_id ) ); ?>"><?php esc_html_e( 'View the full gallery', 'cheri-gallery' ); ?></a></p>
	<?php endif; ?>
</div>
