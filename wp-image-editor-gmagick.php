<?php
/**
 * Plugin Name: WP Image Editor Gmagick
 * Plugin URI: https://github.com/Ultimate-Multisite/wp-image-editor-gmagick
 * Description: WordPress image editor using Gmagick (GraphicsMagick). Faster alternative to Imagick with WebP and AVIF support.
 * Version: 1.0.0
 * Author: Ultimate Multisite
 * Author URI: https://ultimatemultisite.com
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP: 7.4
 * Requires at least: 5.3
 *
 * @package WP_Image_Editor_Gmagick
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register( function ( $class ) {
	if ( 'WP_Image_Editor_Gmagick' === $class ) {
		if ( ! class_exists( 'WP_Image_Editor', false ) ) {
			require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		}
		require_once __DIR__ . '/class-wp-image-editor-gmagick.php';
	}
} );

add_filter( 'wp_image_editors', function ( $editors ) {
	if ( ! extension_loaded( 'gmagick' ) || ! class_exists( 'Gmagick', false ) ) {
		return $editors;
	}

	// Prepend so Gmagick is tried first.
	array_unshift( $editors, 'WP_Image_Editor_Gmagick' );

	// Remove Imagick — gmagick and imagick extensions are mutually exclusive.
	$editors = array_values( array_diff( $editors, [ 'WP_Image_Editor_Imagick' ] ) );

	return $editors;
} );
