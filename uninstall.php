<?php
/**
 * Uninstall cleanup: remove stored settings.
 *
 * @package credits-shortcode
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly.
}

delete_option( 'credits_shortcode_settings' );
