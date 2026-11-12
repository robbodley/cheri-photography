<?php
/**
 * Plugin-wide settings stored in one option.
 */

defined( 'ABSPATH' ) || exit;

class CG_Settings {

	const OPTION = 'cg_settings';

	public static function defaults() {
		return array(
			'from_email'        => 'photome@cheri.photography',
			'from_name'         => 'Cheri Photography',
			'notify_email'      => 'photome@cheri.photography',
			'website_url'       => home_url( '/' ),
			'instagram_url'     => '',
			'location'          => 'Worcestershire',
			'web_size'          => 2048,
			'thumb_size'        => 900,
			'jpeg_quality'      => 86,
			'default_watermark' => 'off',
			'gallery_base'      => 'gallery',
			'gallery_header'    => 'none',
			'password_signin'   => 1,
			'pages_header'      => 'theme',
			'site_footer'       => 'theme',
			'templates'         => self::default_templates(),
			'session_types'     => self::default_session_types(),
		);
	}

	/**
	 * Session types: key => name (in the gallery's dropdown) and short (label on gallery cards).
	 * The key never changes once made, because galleries and the [cheri_galleries type=""] shortcode use it.
	 */
	public static function default_session_types() {
		return array(
			'wedding'   => array( 'name' => __( 'Wedding & parties', 'cheri-gallery' ), 'short' => __( 'Wedding', 'cheri-gallery' ) ),
			'portrait'  => array( 'name' => __( 'Portrait & fashion', 'cheri-gallery' ), 'short' => __( 'Portrait', 'cheri-gallery' ) ),
			'newborn'   => array( 'name' => __( 'Newborn & baby', 'cheri-gallery' ), 'short' => __( 'Newborn', 'cheri-gallery' ) ),
			'corporate' => array( 'name' => __( 'Corporate events', 'cheri-gallery' ), 'short' => __( 'Corporate', 'cheri-gallery' ) ),
		);
	}

	public static function default_templates() {
		return array(
			'wedding'  => array(
				'name'    => __( 'Wedding', 'cheri-gallery' ),
				'subject' => 'Your photos from {names}\'s wedding are ready',
				'body'    => "Hi {names},\n\nYour wedding photos are ready. It was such a joy being part of your day. Feel free to forward this email to family and friends so they can enjoy them too.\n\nCheri x",
			),
			'portrait' => array(
				'name'    => __( 'Portrait', 'cheri-gallery' ),
				'subject' => 'Your portrait photos are ready',
				'body'    => "Hi {names},\n\nThank you for a lovely shoot. Your photos are ready to view. Heart your favourites and send them over when you're ready.\n\nCheri x",
			),
			'newborn'  => array(
				'name'    => __( 'Newborn', 'cheri-gallery' ),
				'subject' => 'Your newborn photos are ready',
				'body'    => "Hi {names},\n\nYour newborn gallery is ready. I hope you love these as much as I loved taking them.\n\nCheri x",
			),
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $values ) {
		$all = array_merge( self::all(), $values );
		update_option( self::OPTION, $all, false );
	}
}
