<?php
/**
 * Opening of the plugin's full pages (gallery, password, login, my galleries).
 *
 * @var string $page_title
 * @var string $body_class
 */

defined( 'ABSPATH' ) || exit;
$body_class = isset( $body_class ) ? $body_class : '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'cg-page ' . $body_class ); ?>>
<?php wp_body_open(); ?>
