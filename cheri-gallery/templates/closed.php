<?php
/**
 * Shown when a gallery has closed, is private without its link, or isn't published.
 */

defined( 'ABSPATH' ) || exit;

$v      = $GLOBALS['cg_view'];
$gid    = $v['gallery_id'];
$reason = $v['reason'];

$messages = array(
	'expired' => array( __( 'This gallery has closed', 'cheri-gallery' ), __( 'Get in touch if you\'d like it opened again.', 'cheri-gallery' ) ),
	'private' => array( __( 'This gallery is private', 'cheri-gallery' ), __( 'Use the link from your email, or sign in if it\'s your gallery.', 'cheri-gallery' ) ),
	'draft'   => array( __( 'Gallery not found', 'cheri-gallery' ), __( 'Check the link from your email.', 'cheri-gallery' ) ),
);
list( $heading, $text ) = isset( $messages[ $reason ] ) ? $messages[ $reason ] : $messages['draft'];

include cg_template( 'parts/page-start.php' );
?>
<main class="ch-gate">
	<div class="ch-gate__panel">
		<div class="ch-sh">
			<img class="ch-sh__mark" src="<?php echo esc_url( CG_URL . 'assets/img/cheri-mark.png' ); ?>" alt="">
			<hr class="ch-sh__rule">
			<h1 class="ch-sh__title"><?php echo esc_html( $heading ); ?></h1>
			<p class="ch-sh__intro"><?php echo esc_html( $text ); ?></p>
		</div>
		<div class="ch-gate__form">
			<?php if ( 'private' === $reason && ! is_user_logged_in() ) : ?>
				<a class="ch-btn" href="<?php echo esc_url( CG_Auth::login_url( get_permalink( $gid ) ) ); ?>"><?php esc_html_e( 'Sign in', 'cheri-gallery' ); ?></a>
			<?php endif; ?>
			<a class="ch-btn ch-btn--outline" href="mailto:<?php echo esc_attr( CG_Settings::get( 'from_email' ) ); ?>"><?php esc_html_e( 'Email Cheri', 'cheri-gallery' ); ?></a>
		</div>
	</div>
</main>
<?php
include cg_template( 'parts/page-end.php' );
