<?php
/**
 * End of the plugin's full pages: the website's footer (default) or the gallery's branded footer.
 */

defined( 'ABSPATH' ) || exit;
$cg_settings = CG_Settings::all();
$cg_theme_footer = CG_Frontend::theme_footer();

// Client links (My galleries / Sign out) go here unless the gallery logo bar or the gallery's own
// bar under the cover photo already shows them.
$cg_links_elsewhere = 'gallery' === CG_Frontend::header_style() || 'gallery' === CG_Frontend::page_kind();
if ( ! $cg_links_elsewhere || ! $cg_theme_footer ) :
	?>
<footer class="ch-foot<?php echo $cg_theme_footer ? ' ch-foot--slim' : ''; ?>">
	<?php if ( ! $cg_theme_footer ) : ?>
		<a href="<?php echo esc_url( $cg_settings['website_url'] ); ?>"><img class="ch-foot__logo ch-logo" src="<?php echo esc_url( CG_URL . 'assets/img/cheri-logo.png' ); ?>" alt="<?php echo esc_attr( $cg_settings['from_name'] ); ?>" width="120" height="60"></a>
	<?php endif; ?>
	<p class="ch-foot__links">
		<?php if ( is_user_logged_in() ) : ?>
			<a href="<?php echo esc_url( CG_Auth::account_url() ); ?>"><?php esc_html_e( 'My galleries', 'cheri-gallery' ); ?></a>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'cheri-gallery' ); ?></a>
		<?php else : ?>
			<a href="<?php echo esc_url( CG_Auth::login_url() ); ?>"><?php esc_html_e( 'Client sign in', 'cheri-gallery' ); ?></a>
		<?php endif; ?>
		<?php if ( ! $cg_theme_footer && $cg_settings['instagram_url'] ) : ?>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url( $cg_settings['instagram_url'] ); ?>">Instagram</a>
		<?php endif; ?>
	</p>
	<?php if ( ! $cg_theme_footer ) : ?>
		<p class="ch-foot__copy">&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . $cg_settings['from_name'] ); ?></p>
	<?php endif; ?>
</footer>
<?php endif; ?>
</div>
<?php
if ( $cg_theme_footer ) {
	get_footer();
} else {
	wp_footer();
	echo "</body>\n</html>\n";
}
