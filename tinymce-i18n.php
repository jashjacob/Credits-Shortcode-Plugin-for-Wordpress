<?php
/**
 * Translations for the Classic Editor button and dialog.
 *
 * TinyMCE cannot use the block editor's wp.i18n data, so WordPress includes this
 * file (registered through the mce_external_languages filter) and prints the
 * $strings variable it defines into the editor page. Keys are the English strings
 * that credits_shortcode_plugin.js passes to editor.translate().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$credits_mce_strings = array(
	'Add Credits'                => __( 'Add Credits', 'credits-shortcode' ),
	'Credit Type'                => __( 'Credit Type', 'credits-shortcode' ),
	'Use site default'           => __( 'Use site default', 'credits-shortcode' ),
	'Source'                     => __( 'Source', 'credits-shortcode' ),
	'Via'                        => __( 'Via', 'credits-shortcode' ),
	'Name'                       => __( 'Name', 'credits-shortcode' ),
	'Link URL'                   => __( 'Link URL', 'credits-shortcode' ),
	'Open in new tab'            => __( 'Open in new tab', 'credits-shortcode' ),
	'Insert credit'              => __( 'Insert credit', 'credits-shortcode' ),
	'Cancel'                     => __( 'Cancel', 'credits-shortcode' ),
	'Enter a name and a link.'   => __( 'Enter a name and a link.', 'credits-shortcode' ),
	'Enter a name.'              => __( 'Enter a name.', 'credits-shortcode' ),
	'Enter a link.'              => __( 'Enter a link.', 'credits-shortcode' ),
	'Use a link that starts with http://, https://, mailto: or ftp://, or a relative address.' => __( 'Use a link that starts with http://, https://, mailto: or ftp://, or a relative address.', 'credits-shortcode' ),
);

$strings = 'tinyMCE.addI18n("' . _WP_Editors::$mce_locale . '", ' . wp_json_encode( $credits_mce_strings ) . ');';
