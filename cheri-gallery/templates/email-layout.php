<?php
/**
 * Simple branded email wrapper (sign-in links, alerts). Tables and inline styles for email programs.
 *
 * @var array  $settings
 * @var string $content  Already-escaped HTML.
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#efe7dd;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#efe7dd;">
<tr><td align="center" style="padding:24px 12px;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;">
		<tr><td align="center" style="padding:28px 24px 8px;">
			<img src="<?php echo esc_url( CG_URL . 'assets/img/cheri-logo.png' ); ?>" width="120" alt="<?php echo esc_attr( $settings['from_name'] ); ?>" style="display:block;width:120px;height:auto;border:0;">
		</td></tr>
		<tr><td style="padding:16px 32px 28px;font:14px/22px 'Open Sans',Arial,sans-serif;color:#2e2a27;">
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</td></tr>
		<tr><td align="center" style="padding:18px 24px;background:#fbf8f2;font:12px/18px 'Open Sans',Arial,sans-serif;color:#6b625a;">
			<?php echo esc_html( $settings['from_name'] ); ?> · <a href="<?php echo esc_url( $settings['website_url'] ); ?>" style="color:#7d5f41;"><?php echo esc_html( wp_parse_url( $settings['website_url'], PHP_URL_HOST ) ); ?></a>
		</td></tr>
	</table>
</td></tr>
</table>
</body>
</html>
