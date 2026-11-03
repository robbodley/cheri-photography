<?php
/**
 * Opening of the plugin's full pages (gallery, password, sign in, My galleries).
 *
 * By default the website's own header is used (get_header), so galleries sit inside the
 * Cheri Photography site. Settings → "Use the website's header and footer" switches to a
 * standalone page instead.
 *
 * @var string $body_class Extra classes for the wrapper.
 */

defined( 'ABSPATH' ) || exit;
$body_class = isset( $body_class ) ? $body_class : '';
$cg_themed  = CG_Frontend::use_theme_layout();

if ( $cg_themed ) {
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
<div class="cg-page<?php echo $cg_themed ? ' cg-themed' : ''; ?> <?php echo esc_attr( $body_class ); ?>" id="cg-root">
