<?php
/**
 * A password-protected gallery before it's unlocked: the full-screen cover photo with the names
 * and date, and the password box where "View gallery" normally sits. Only the cover photo is
 * shown; every other photo stays locked on the server until the password or a client sign-in.
 */

defined( 'ABSPATH' ) || exit;

$v     = $GLOBALS['cg_view'];
$gid   = $v['gallery_id'];
$s     = CG_Galleries::get( $gid );
$cid   = CG_Galleries::cover_photo( $gid );
$cover = $cid ? CG_Photos::get( $cid ) : null;

include cg_template( 'parts/page-start.php' );
?>
<?php if ( ! empty( $v['preview'] ) ) : ?>
	<p class="ch-adminbar">
		<?php esc_html_e( 'Preview: this is what visitors see before they enter the password.', 'cheri-gallery' ); ?>
		<a href="<?php echo esc_url( get_permalink( $gid ) ); ?>"><?php esc_html_e( 'Back to the full gallery', 'cheri-gallery' ); ?></a>
	</p>
<?php endif; ?>
<section class="ch-cover ch-cover--locked">
	<?php if ( $cover ) : ?>
		<div class="ch-cover__img"><img src="<?php echo esc_url( CG_Delivery::photo_url( $cover, 'web' ) ); ?>" alt="" fetchpriority="high" style="object-position: <?php echo esc_attr( CG_Galleries::cover_css_position( $gid ) ); ?>"></div>
	<?php else : ?>
		<div class="ch-cover__img"><span class="ch-ph ch-ph--tan"></span></div>
	<?php endif; ?>
	<div class="ch-cover__body">
		<h1 class="ch-cover__title"><?php echo esc_html( get_the_title( $gid ) ); ?></h1>
		<?php if ( $s['event_date'] ) : ?>
			<p class="ch-cover__date"><?php echo esc_html( cg_format_date( $s['event_date'] ) ); ?></p>
		<?php endif; ?>

		<form class="ch-unlock" method="post" action="<?php echo esc_url( get_permalink( $gid ) ); ?>">
			<?php wp_nonce_field( 'cg_pass_' . $gid, '_cgnonce' ); ?>
			<label class="ch-unlock__label" for="cg-pass"><?php esc_html_e( 'Enter the password from your email to see the photos', 'cheri-gallery' ); ?></label>
			<div class="ch-unlock__row">
				<input class="ch-unlock__input" id="cg-pass" type="password" name="cg_gallery_password" autocomplete="current-password" required placeholder="<?php esc_attr_e( 'Password', 'cheri-gallery' ); ?>" <?php echo $v['error'] ? 'aria-describedby="cg-pass-error" aria-invalid="true" autofocus' : ''; ?>>
				<button class="ch-btn ch-btn--photo" type="submit"><?php esc_html_e( 'Open gallery', 'cheri-gallery' ); ?></button>
			</div>
			<?php if ( $v['error'] ) : ?>
				<p class="ch-unlock__error" id="cg-pass-error" role="alert"><?php echo esc_html( $v['error'] ); ?></p>
			<?php endif; ?>
			<p class="ch-unlock__help">
				<?php if ( $s['clients'] ) : ?>
					<a href="<?php echo esc_url( CG_Auth::login_url( get_permalink( $gid ) ) ); ?>"><?php esc_html_e( 'Client? Sign in instead', 'cheri-gallery' ); ?></a>
					<span aria-hidden="true">·</span>
				<?php endif; ?>
				<a href="mailto:<?php echo esc_attr( CG_Settings::get( 'from_email' ) ); ?>"><?php esc_html_e( 'Lost the password?', 'cheri-gallery' ); ?></a>
			</p>
		</form>
	</div>
	<img class="ch-cover__mark ch-logo ch-logo--on-photo" src="<?php echo esc_url( CG_URL . 'assets/img/cheri-logo.png' ); ?>" alt="<?php echo esc_attr( CG_Settings::get( 'from_name' ) ); ?>">
</section>
<?php
include cg_template( 'parts/page-end.php' );
