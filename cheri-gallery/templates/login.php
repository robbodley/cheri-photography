<?php
/**
 * Client sign-in: ask for an email, say the link is on its way, or confirm the link.
 *
 * @var string $state    ask | sent | confirm
 * @var string $message
 * @var string $email
 * @var string $redirect
 * @var int    $uid
 * @var string $token
 * @var string $signup
 * @var string $need_name Token for the first-name step, or ''.
 */

defined( 'ABSPATH' ) || exit;

include cg_template( 'parts/page-start.php' );
?>
<main class="ch-gate">
	<div class="ch-gate__panel">
		<div class="ch-sh">
			<img class="ch-sh__mark" src="<?php echo esc_url( CG_URL . 'assets/img/cheri-mark.png' ); ?>" alt="">
			<hr class="ch-sh__rule">
			<?php if ( 'signup_sent' === $state ) : ?>
				<h1 class="ch-sh__title"><?php esc_html_e( 'Check your email', 'cheri-gallery' ); ?></h1>
				<p class="ch-sh__intro"><?php esc_html_e( 'We\'ve emailed you a link to confirm your address. Click it and you\'ll go straight into the gallery.', 'cheri-gallery' ); ?></p>
			<?php elseif ( 'sent' === $state ) : ?>
				<h1 class="ch-sh__title"><?php esc_html_e( 'Check your email', 'cheri-gallery' ); ?></h1>
				<p class="ch-sh__intro">
					<?php
					printf(
						/* translators: %s: email address */
						esc_html__( 'If %s belongs to one of Cheri\'s clients, a sign-in link is on its way. It works once and lasts an hour.', 'cheri-gallery' ),
						'<strong>' . esc_html( $email ) . '</strong>'
					);
					?>
				</p>
			<?php elseif ( 'confirm_signup' === $state ) : ?>
				<h1 class="ch-sh__title"><?php esc_html_e( 'Confirm your email', 'cheri-gallery' ); ?></h1>
				<p class="ch-sh__intro"><?php echo esc_html( sprintf( /* translators: %s: email */ __( 'Press the button to confirm %s and open the gallery.', 'cheri-gallery' ), $email ) ); ?></p>
			<?php elseif ( 'confirm' === $state ) : ?>
				<h1 class="ch-sh__title"><?php esc_html_e( 'Welcome back', 'cheri-gallery' ); ?></h1>
				<p class="ch-sh__intro"><?php esc_html_e( 'Press the button to sign in to your galleries.', 'cheri-gallery' ); ?></p>
			<?php else : ?>
				<h1 class="ch-sh__title"><?php esc_html_e( 'Client sign in', 'cheri-gallery' ); ?></h1>
				<p class="ch-sh__intro"><?php esc_html_e( 'Enter your email and the gallery password from Cheri\'s email to go straight in. No gallery password? Leave it empty and we\'ll email you a sign-in link.', 'cheri-gallery' ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( 'confirm_signup' === $state ) : ?>
			<form class="ch-gate__form" method="post" action="<?php echo esc_url( home_url( 'client-login/' ) ); ?>">
				<?php wp_nonce_field( 'cg_confirm_signup' ); ?>
				<input type="hidden" name="cg_action" value="confirm_signup">
				<input type="hidden" name="cg_confirm" value="<?php echo esc_attr( $signup ); ?>">
				<button class="ch-btn" type="submit"><?php esc_html_e( 'Confirm and open the gallery', 'cheri-gallery' ); ?></button>
			</form>
		<?php elseif ( 'confirm' === $state ) : ?>
			<form class="ch-gate__form" method="post" action="<?php echo esc_url( home_url( 'client-login/' ) ); ?>">
				<?php wp_nonce_field( 'cg_use_link' ); ?>
				<input type="hidden" name="cg_action" value="use_link">
				<input type="hidden" name="u" value="<?php echo esc_attr( $uid ); ?>">
				<input type="hidden" name="t" value="<?php echo esc_attr( $token ); ?>">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>">
				<button class="ch-btn" type="submit"><?php esc_html_e( 'Sign in', 'cheri-gallery' ); ?></button>
			</form>
		<?php elseif ( 'ask' === $state ) : ?>
			<form class="ch-gate__form" method="post" action="<?php echo esc_url( home_url( 'client-login/' ) ); ?>">
				<?php wp_nonce_field( 'cg_send_link' ); ?>
				<input type="hidden" name="cg_action" value="send_link">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>">
				<label class="ch-field<?php echo $message ? ' ch-field--error' : ''; ?>">
					<span class="ch-field__label"><?php esc_html_e( 'Email', 'cheri-gallery' ); ?></span>
					<input class="ch-field__input" type="email" name="email" value="<?php echo esc_attr( $email ); ?>" autocomplete="email" required <?php echo $need_name ? 'readonly' : 'autofocus'; ?>>
				</label>
				<?php if ( $need_name ) : ?>
					<input type="hidden" name="cg_pw_ok" value="<?php echo esc_attr( $need_name ); ?>">
					<p class="ch-gate__help" style="margin:0;text-align:left"><?php esc_html_e( 'You\'re new here. Add your first name and we\'ll email you a link to confirm your address.', 'cheri-gallery' ); ?></p>
					<label class="ch-field">
						<span class="ch-field__label"><?php esc_html_e( 'First name', 'cheri-gallery' ); ?></span>
						<input class="ch-field__input" type="text" name="first_name" autocomplete="given-name" required autofocus>
					</label>
				<?php else : ?>
					<label class="ch-field">
						<span class="ch-field__label"><?php esc_html_e( 'Gallery password (optional)', 'cheri-gallery' ); ?></span>
						<input class="ch-field__input" type="password" name="gallery_password" autocomplete="current-password">
					</label>
				<?php endif; ?>
				<?php if ( $message ) : ?>
					<p class="ch-field__error" role="alert" style="margin:0"><?php echo esc_html( $message ); ?></p>
				<?php endif; ?>
				<button class="ch-btn" type="submit"><?php echo $need_name ? esc_html__( 'Email me a link', 'cheri-gallery' ) : esc_html__( 'Continue', 'cheri-gallery' ); ?></button>
			</form>
		<?php else : ?>
			<p class="ch-gate__help"><a href="<?php echo esc_url( CG_Auth::login_url( $redirect ) ); ?>"><?php esc_html_e( 'Use a different email', 'cheri-gallery' ); ?></a></p>
		<?php endif; ?>
	</div>
</main>
<?php
include cg_template( 'parts/page-end.php' );
