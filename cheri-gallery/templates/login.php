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
 */

defined( 'ABSPATH' ) || exit;

include cg_template( 'parts/page-start.php' );
?>
<main class="ch-gate">
	<div class="ch-gate__panel">
		<div class="ch-sh">
			<img class="ch-sh__mark" src="<?php echo esc_url( CG_URL . 'assets/img/cheri-mark.png' ); ?>" alt="">
			<hr class="ch-sh__rule">
			<?php if ( 'sent' === $state ) : ?>
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
			<?php elseif ( 'confirm' === $state ) : ?>
				<h1 class="ch-sh__title"><?php esc_html_e( 'Welcome back', 'cheri-gallery' ); ?></h1>
				<p class="ch-sh__intro"><?php esc_html_e( 'Press the button to sign in to your galleries.', 'cheri-gallery' ); ?></p>
			<?php else : ?>
				<h1 class="ch-sh__title"><?php esc_html_e( 'Client sign in', 'cheri-gallery' ); ?></h1>
				<p class="ch-sh__intro"><?php esc_html_e( 'Enter your email and we\'ll send you a link to sign in. No password needed.', 'cheri-gallery' ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( 'confirm' === $state ) : ?>
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
					<input class="ch-field__input" type="email" name="email" value="<?php echo esc_attr( $email ); ?>" autocomplete="email" required autofocus>
					<?php if ( $message ) : ?>
						<span class="ch-field__error"><?php echo esc_html( $message ); ?></span>
					<?php endif; ?>
				</label>
				<button class="ch-btn" type="submit"><?php esc_html_e( 'Email me a link', 'cheri-gallery' ); ?></button>
			</form>
		<?php else : ?>
			<p class="ch-gate__help"><a href="<?php echo esc_url( CG_Auth::login_url( $redirect ) ); ?>"><?php esc_html_e( 'Use a different email', 'cheri-gallery' ); ?></a></p>
		<?php endif; ?>
	</div>
</main>
<?php
include cg_template( 'parts/page-end.php' );
