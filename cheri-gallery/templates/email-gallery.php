<?php
/**
 * The "your photos are ready" email. Built with tables and inline styles so it looks right in
 * Gmail, Apple Mail and Outlook. Marcellus/Open Sans fall back to Georgia/Arial where web fonts are blocked.
 *
 * @var string $names
 * @var string $date
 * @var string $cover_url
 * @var string $message
 * @var string $password
 * @var string $gallery_url
 * @var string $signin_url
 * @var string $client_names
 * @var array  $settings
 */

defined( 'ABSPATH' ) || exit;

$serif = "Marcellus,Georgia,'Times New Roman',serif";
$sans  = "'Open Sans',Arial,Helvetica,sans-serif";
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<title><?php echo esc_html( $names ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#efe7dd;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#efe7dd;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;">

	<tr><td align="center" style="padding:28px 24px 14px;">
		<img src="<?php echo esc_url( CG_URL . 'assets/img/cheri-logo.png' ); ?>" width="130" alt="<?php echo esc_attr( $settings['from_name'] ); ?>" style="display:block;width:130px;height:auto;border:0;margin:0 auto 14px;">
		<div style="font:400 28px/34px <?php echo esc_attr( $serif ); ?>;letter-spacing:3px;text-transform:uppercase;color:#2e2a27;"><?php echo esc_html( $names ); ?></div>
		<?php if ( $date ) : ?>
			<div style="font:600 10px/14px <?php echo esc_attr( $sans ); ?>;letter-spacing:2px;text-transform:uppercase;color:#6b625a;padding-top:6px;"><?php echo esc_html( $date ); ?></div>
		<?php endif; ?>
	</td></tr>

	<?php if ( $cover_url ) : ?>
	<tr><td style="padding:0;">
		<a href="<?php echo esc_url( $gallery_url ); ?>"><img src="<?php echo esc_url( $cover_url ); ?>" width="600" alt="" style="display:block;width:100%;max-width:600px;height:auto;border:0;"></a>
	</td></tr>
	<?php endif; ?>

	<?php if ( '' !== trim( $message ) ) : ?>
	<tr><td style="padding:28px 36px 8px;font:400 15px/24px <?php echo esc_attr( $sans ); ?>;color:#2e2a27;">
		<?php echo wp_kses_post( wpautop( esc_html( $message ) ) ); ?>
	</td></tr>
	<?php endif; ?>

	<?php if ( $password ) : ?>
	<tr><td style="padding:8px 36px 4px;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fbf8f2;border-top:1px solid #cfaf8b;border-bottom:1px solid #cfaf8b;">
			<tr><td align="center" style="padding:14px 16px;">
				<div style="font:400 12px/18px <?php echo esc_attr( $sans ); ?>;color:#6b625a;"><?php esc_html_e( 'To open the gallery, use this password (it\'s case-sensitive):', 'cheri-gallery' ); ?></div>
				<div style="font:600 22px/30px <?php echo esc_attr( $sans ); ?>;letter-spacing:3px;color:#2e2a27;padding-top:2px;"><?php echo esc_html( $password ); ?></div>
			</td></tr>
		</table>
	</td></tr>
	<?php endif; ?>

	<tr><td align="center" style="padding:20px 24px 8px;">
		<a href="<?php echo esc_url( $gallery_url ); ?>" style="display:inline-block;background:#7d5f41;color:#ffffff;text-decoration:none;font:600 11px/16px <?php echo esc_attr( $sans ); ?>;letter-spacing:2px;text-transform:uppercase;padding:13px 30px;"><?php esc_html_e( 'View gallery', 'cheri-gallery' ); ?></a>
	</td></tr>

	<?php if ( $signin_url ) : ?>
	<tr><td align="center" style="padding:8px 36px 0;font:400 12px/18px <?php echo esc_attr( $sans ); ?>;color:#6b625a;">
		<?php
		printf(
			/* translators: 1: client names, 2: sign-in link */
			esc_html__( '%1$s: %2$s to make favourites and download.', 'cheri-gallery' ),
			esc_html( $client_names ),
			'<a href="' . esc_url( $signin_url ) . '" style="color:#7d5f41;">' . esc_html__( 'sign in to your account', 'cheri-gallery' ) . '</a>'
		);
		?>
	</td></tr>
	<?php endif; ?>

	<tr><td align="center" style="padding:28px 24px 26px;background:#fbf8f2;font:400 12px/19px <?php echo esc_attr( $sans ); ?>;color:#6b625a;" bgcolor="#fbf8f2">
		<img src="<?php echo esc_url( CG_URL . 'assets/img/cheri-mark.png' ); ?>" width="56" alt="" style="display:block;width:56px;height:auto;border:0;margin:0 auto 6px;">
		<?php echo esc_html( $settings['from_name'] . ( $settings['location'] ? ' · ' . $settings['location'] : '' ) ); ?><br>
		<a href="<?php echo esc_url( $settings['website_url'] ); ?>" style="color:#7d5f41;"><?php echo esc_html( wp_parse_url( $settings['website_url'], PHP_URL_HOST ) ); ?></a>
		<?php if ( $settings['instagram_url'] ) : ?>
			· <a href="<?php echo esc_url( $settings['instagram_url'] ); ?>" style="color:#7d5f41;">Instagram</a>
		<?php endif; ?>
		<br><?php esc_html_e( 'Questions? Just reply to this email.', 'cheri-gallery' ); ?>
	</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
