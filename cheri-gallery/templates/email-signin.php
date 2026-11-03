<?php
/**
 * Sign-in link email.
 *
 * @var string $name
 * @var string $link
 * @var int    $minutes
 */

defined( 'ABSPATH' ) || exit;

$content  = '<p style="margin:0 0 12px;">' . esc_html( sprintf( /* translators: %s: name */ __( 'Hi %s,', 'cheri-gallery' ), $name ) ) . '</p>';
$content .= '<p style="margin:0 0 20px;">' . esc_html__( 'Here\'s your link to sign in to your galleries, favourites and downloads.', 'cheri-gallery' ) . '</p>';
$content .= '<p style="margin:0 0 20px;text-align:center;"><a href="' . esc_url( $link ) . '" style="display:inline-block;background:#7d5f41;color:#ffffff;text-decoration:none;font:600 11px/16px \'Open Sans\',Arial,sans-serif;letter-spacing:2px;text-transform:uppercase;padding:12px 28px;">' . esc_html__( 'Sign in', 'cheri-gallery' ) . '</a></p>';
/* translators: %d: minutes */
$content .= '<p style="margin:0;font-size:12px;line-height:18px;color:#6b625a;">' . esc_html( sprintf( __( 'The link works once and lasts %d minutes. If you didn\'t ask for it, you can ignore this email.', 'cheri-gallery' ), $minutes ) ) . '</p>';

echo cg_render( // phpcs:ignore WordPress.Security.EscapeOutput
	'email-layout.php',
	array(
		'settings' => CG_Settings::all(),
		'content'  => $content,
	)
);
