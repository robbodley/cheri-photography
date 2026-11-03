<?php
/**
 * Password gate for a password-protected gallery.
 */

defined( 'ABSPATH' ) || exit;

$v   = $GLOBALS['cg_view'];
$gid = $v['gallery_id'];

include cg_template( 'parts/page-start.php' );
?>
<main class="ch-gate">
	<div class="ch-gate__panel">
		<div class="ch-sh">
			<img class="ch-sh__mark" src="<?php echo esc_url( CG_URL . 'assets/img/cheri-mark.png' ); ?>" alt="">
			<hr class="ch-sh__rule">
			<h1 class="ch-sh__title"><?php echo esc_html( get_the_title( $gid ) ); ?></h1>
			<p class="ch-sh__intro"><?php esc_html_e( 'This gallery is private. Enter the password from your welcome email.', 'cheri-gallery' ); ?></p>
		</div>
		<form class="ch-gate__form" method="post" action="<?php echo esc_url( get_permalink( $gid ) ); ?>">
			<?php wp_nonce_field( 'cg_pass_' . $gid, '_cgnonce' ); ?>
			<label class="ch-field<?php echo $v['error'] ? ' ch-field--error' : ''; ?>">
				<span class="ch-field__label"><?php esc_html_e( 'Password', 'cheri-gallery' ); ?></span>
				<input class="ch-field__input" type="password" name="cg_gallery_password" autocomplete="current-password" required autofocus <?php echo $v['error'] ? 'aria-describedby="cg-pass-error" aria-invalid="true"' : ''; ?>>
				<?php if ( $v['error'] ) : ?>
					<span class="ch-field__error" id="cg-pass-error"><?php echo esc_html( $v['error'] ); ?></span>
				<?php endif; ?>
			</label>
			<button class="ch-btn" type="submit"><?php esc_html_e( 'Open gallery', 'cheri-gallery' ); ?></button>
		</form>
		<p class="ch-gate__help">
			<?php
			printf(
				/* translators: %s: link */
				esc_html__( 'Are you the client? %s', 'cheri-gallery' ),
				'<a href="' . esc_url( CG_Auth::login_url( get_permalink( $gid ) ) ) . '">' . esc_html__( 'Sign in instead', 'cheri-gallery' ) . '</a>'
			);
			?>
			<br>
			<?php
			printf(
				/* translators: %s: link */
				esc_html__( 'Lost the password? %s', 'cheri-gallery' ),
				'<a href="mailto:' . esc_attr( CG_Settings::get( 'from_email' ) ) . '">' . esc_html__( 'Ask Cheri', 'cheri-gallery' ) . '</a>'
			);
			?>
		</p>
	</div>
</main>
<?php
include cg_template( 'parts/page-end.php' );
