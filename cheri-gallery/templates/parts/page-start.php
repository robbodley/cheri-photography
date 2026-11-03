<?php
/**
 * Opening of the plugin's full pages (gallery, password, sign in, My galleries).
 *
 * Header: the gallery's slim Cheri logo bar (default) or the website's own header (get_header).
 * Settings → Pages.
 *
 * @var string $body_class Extra classes for the wrapper.
 */

defined( 'ABSPATH' ) || exit;
$body_class = isset( $body_class ) ? $body_class : '';
$cg_settings = CG_Settings::all();

if ( CG_Frontend::theme_header() ) {
	get_header();
} else {
	?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'cg-standalone' ); ?> style="margin:0">
<?php wp_body_open(); ?>
	<?php
}
?>
<div class="cg-page cg-themed <?php echo esc_attr( $body_class ); ?>" id="cg-root">
<?php if ( ! CG_Frontend::theme_header() ) : ?>
<header class="ch-sitebar">
	<a class="ch-sitebar__logo" href="<?php echo esc_url( $cg_settings['website_url'] ); ?>"><img src="<?php echo esc_url( CG_URL . 'assets/img/cheri-logo.png' ); ?>" alt="<?php echo esc_attr( $cg_settings['from_name'] ); ?>" width="120" height="60"></a>
	<nav class="ch-sitebar__links" aria-label="<?php esc_attr_e( 'Client', 'cheri-gallery' ); ?>">
		<?php if ( is_user_logged_in() ) : ?>
			<a href="<?php echo esc_url( CG_Auth::account_url() ); ?>"><?php esc_html_e( 'My galleries', 'cheri-gallery' ); ?></a>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'cheri-gallery' ); ?></a>
		<?php else : ?>
			<a href="<?php echo esc_url( CG_Auth::login_url() ); ?>"><?php esc_html_e( 'Client sign in', 'cheri-gallery' ); ?></a>
		<?php endif; ?>
	</nav>
</header>
<?php endif; ?>
