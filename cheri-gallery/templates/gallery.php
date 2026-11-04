<?php
/**
 * A client gallery: cover, sticky header with sets and actions, photo grid.
 * The lightbox and favourites drawer are added by assets/js/gallery.js.
 */

defined( 'ABSPATH' ) || exit;

$v     = $GLOBALS['cg_view'];
$gid   = $v['gallery_id'];
$s     = $v['settings'];
$title = get_the_title( $gid );
$sets  = array_keys( $v['by_set'] );

$body_class = $s['protect'] ? 'cg-protect' : '';
include cg_template( 'parts/page-start.php' );
?>
<?php if ( CG_Access::is_admin() && 'public' !== $s['visibility'] ) : ?>
	<p class="ch-adminbar">
		<?php esc_html_e( 'You\'re seeing every photo because you\'re signed in as the photographer.', 'cheri-gallery' ); ?>
		<?php if ( 'password' === $s['visibility'] ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'cg_preview', 'locked', get_permalink( $gid ) ) ); ?>"><?php esc_html_e( 'Preview the locked page visitors see', 'cheri-gallery' ); ?></a>
		<?php endif; ?>
	</p>
<?php endif; ?>
<?php
$cg_notice = isset( $_GET['cg_notice'] ) ? sanitize_key( $_GET['cg_notice'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
if ( 'confirm' === $cg_notice || 'link' === $cg_notice ) :
	?>
	<p class="ch-adminbar ch-adminbar--tan" role="status">
		<?php
		echo 'confirm' === $cg_notice
			? esc_html__( 'Nearly there: we\'ve emailed you a link to confirm your address. Once you click it you can save favourites.', 'cheri-gallery' )
			: esc_html__( 'We\'ve emailed you a sign-in link. Click it to save favourites and download.', 'cheri-gallery' );
		?>
	</p>
<?php endif; ?>
<section class="ch-cover" id="cg-cover">
	<?php if ( $v['cover'] ) : ?>
		<div class="ch-cover__img"><img src="<?php echo esc_url( CG_Delivery::photo_url( $v['cover'], 'web', false, true ) ); ?>" alt="" fetchpriority="high" style="object-position: <?php echo esc_attr( CG_Galleries::cover_css_position( $gid ) ); ?>"></div>
	<?php else : ?>
		<div class="ch-cover__img"><span class="ch-ph ch-ph--tan"></span></div>
	<?php endif; ?>
	<div class="ch-cover__body">
		<h1 class="ch-cover__title"><?php echo esc_html( $title ); ?></h1>
		<?php if ( $s['event_date'] ) : ?>
			<p class="ch-cover__date"><?php echo esc_html( cg_format_date( $s['event_date'] ) ); ?></p>
		<?php endif; ?>
		<a class="ch-btn ch-btn--photo" href="#cg-photos"><?php esc_html_e( 'View gallery', 'cheri-gallery' ); ?></a>
	</div>
	<img class="ch-cover__mark ch-logo ch-logo--on-photo" src="<?php echo esc_url( CG_URL . 'assets/img/cheri-logo.png' ); ?>" alt="<?php echo esc_attr( CG_Settings::get( 'from_name' ) ); ?>">
</section>

<header class="ch-gh-wrap" id="cg-photos">
	<div class="ch-gh">
		<div>
			<p class="ch-gh__title"><?php echo esc_html( $title ); ?></p>
			<p class="ch-gh__sub"><?php echo esc_html( CG_Settings::get( 'from_name' ) ); ?></p>
		</div>
		<nav class="ch-gh__actions" aria-label="<?php esc_attr_e( 'Gallery actions', 'cheri-gallery' ); ?>">
			<?php if ( is_user_logged_in() ) : ?>
				<a class="ch-gh__link ch-gh__link--wide" href="<?php echo esc_url( CG_Auth::account_url() ); ?>"><?php esc_html_e( 'My galleries', 'cheri-gallery' ); ?></a>
				<a class="ch-gh__link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'cheri-gallery' ); ?></a>
			<?php elseif ( $s['clients'] ) : ?>
				<a class="ch-gh__link" href="<?php echo esc_url( $v['login_url'] ); ?>"><?php esc_html_e( 'Client sign in', 'cheri-gallery' ); ?></a>
			<?php endif; ?>
			<?php if ( $v['can_favourite'] ) : ?>
				<button class="ch-btn ch-btn--icon ch-gh__fav" type="button" data-cg-open-favs aria-label="<?php esc_attr_e( 'My favourites', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'heart' ); // phpcs:ignore ?><span class="ch-count" data-cg-fav-count hidden></span></button>
			<?php elseif ( $v['sign_in_for_fav'] ) : ?>
				<a class="ch-btn ch-btn--icon" href="<?php echo esc_url( $v['login_url'] ); ?>" aria-label="<?php esc_attr_e( 'Sign in to save favourites', 'cheri-gallery' ); ?>" title="<?php esc_attr_e( 'Sign in to save favourites', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'heart' ); // phpcs:ignore ?></a>
			<?php endif; ?>
			<?php if ( $v['can_album_dl'] ) : ?>
				<a class="ch-btn ch-btn--icon" href="<?php echo esc_url( CG_Delivery::album_download_url( $gid ) ); ?>" aria-label="<?php esc_attr_e( 'Download all', 'cheri-gallery' ); ?>" title="<?php esc_attr_e( 'Download all', 'cheri-gallery' ); ?>" data-cg-album-dl><?php echo cg_icon( 'download' ); // phpcs:ignore ?></a>
			<?php elseif ( $v['sign_in_for_dl'] ) : ?>
				<a class="ch-btn ch-btn--icon" href="<?php echo esc_url( $v['login_url'] ); ?>" aria-label="<?php esc_attr_e( 'Sign in to download', 'cheri-gallery' ); ?>" title="<?php esc_attr_e( 'Sign in to download', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'download' ); // phpcs:ignore ?></a>
			<?php endif; ?>
			<button class="ch-btn ch-btn--icon" type="button" data-cg-share aria-label="<?php esc_attr_e( 'Share', 'cheri-gallery' ); ?>" title="<?php esc_attr_e( 'Share', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'share' ); // phpcs:ignore ?></button>
			<?php if ( $v['photos'] ) : ?>
				<button class="ch-btn ch-btn--icon" type="button" data-cg-slideshow aria-label="<?php esc_attr_e( 'Slideshow', 'cheri-gallery' ); ?>" title="<?php esc_attr_e( 'Slideshow', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'play' ); // phpcs:ignore ?></button>
			<?php endif; ?>
		</nav>
	</div>
	<?php if ( count( $sets ) > 1 ) : ?>
		<nav class="ch-gh__tabs" aria-label="<?php esc_attr_e( 'Sets', 'cheri-gallery' ); ?>">
			<?php foreach ( $sets as $i => $set ) : ?>
				<a class="ch-gh__tab" href="#set-<?php echo esc_attr( $i ); ?>" data-cg-set="<?php echo esc_attr( $i ); ?>" <?php echo 0 === $i ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $set ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</header>

<main class="ch-main">
	<?php if ( ! $v['photos'] ) : ?>
		<p class="ch-empty"><?php esc_html_e( 'Photos are on their way. Check back soon.', 'cheri-gallery' ); ?></p>
	<?php endif; ?>

	<?php foreach ( array_values( $v['by_set'] ) as $i => $photos ) : ?>
		<section class="ch-set" id="set-<?php echo esc_attr( $i ); ?>" data-cg-set-panel="<?php echo esc_attr( $i ); ?>">
			<?php if ( count( $sets ) > 1 ) : ?>
				<h2 class="ch-set__title"><?php echo esc_html( $sets[ $i ] ); ?></h2>
			<?php endif; ?>
			<div class="ch-grid">
				<?php
				foreach ( $photos as $p ) :
					$can_dl = CG_Access::can_download( $gid, $p );
					?>
					<figure class="ch-grid__item" data-cg-photo="<?php echo esc_attr( $p->id ); ?>" data-web="<?php echo esc_url( CG_Delivery::photo_url( $p, 'web' ) ); ?>" data-dl="<?php echo $can_dl ? esc_url( CG_Delivery::photo_download_url( $p ) ) : ''; ?>" data-name="<?php echo esc_attr( pathinfo( $p->original_name, PATHINFO_FILENAME ) ); ?>">
						<a href="<?php echo esc_url( CG_Delivery::photo_url( $p, 'web' ) ); ?>" class="ch-grid__open" aria-label="<?php esc_attr_e( 'Open photo', 'cheri-gallery' ); ?>">
							<img src="<?php echo esc_url( CG_Delivery::photo_url( $p, 'thumb' ) ); ?>" alt="" loading="lazy" decoding="async" <?php echo $p->width ? 'width="' . esc_attr( $p->width ) . '" height="' . esc_attr( $p->height ) . '"' : ''; ?>>
						</a>
						<div class="ch-grid__tools">
							<?php if ( $v['can_favourite'] ) : ?>
								<button class="ch-grid__tool" type="button" data-cg-heart aria-pressed="false" aria-label="<?php esc_attr_e( 'Favourite', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'heart' ); // phpcs:ignore ?></button>
							<?php elseif ( $v['sign_in_for_fav'] ) : ?>
								<a class="ch-grid__tool" href="<?php echo esc_url( $v['login_url'] ); ?>" aria-label="<?php esc_attr_e( 'Sign in to save favourites', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'heart' ); // phpcs:ignore ?></a>
							<?php endif; ?>
							<?php if ( $can_dl ) : ?>
								<a class="ch-grid__tool" href="<?php echo esc_url( CG_Delivery::photo_download_url( $p ) ); ?>" aria-label="<?php esc_attr_e( 'Download', 'cheri-gallery' ); ?>"><?php echo cg_icon( 'download' ); // phpcs:ignore ?></a>
							<?php endif; ?>
						</div>
					</figure>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
</main>

<div class="ch-toast" role="status" aria-live="polite" data-cg-toast hidden></div>
<?php
include cg_template( 'parts/page-end.php' );
