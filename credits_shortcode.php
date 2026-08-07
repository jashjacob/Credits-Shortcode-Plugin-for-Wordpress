<?php
/*
Plugin Name: Credits Shortcode & Block
Version: 1.3
Plugin URI: https://github.com/jashjacob/Credits-Shortcode-Plugin-for-Wordpress
Description: Easy shortcode and Gutenberg block to insert Source and Via Link inside posts in WordPress.
Author: Jash Jacob
Author URI: http://jashjacob.com
Requires at least: 5.0
Requires PHP: 7.4
Tested up to: 6.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Copyright 2013-2026

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Enqueue frontend styles.
 */
add_action( 'wp_enqueue_scripts', 'credits_enqueue_styles' );
function credits_enqueue_styles() {
	wp_enqueue_style(
		'source_style',
		plugin_dir_url( __FILE__ ) . 'css/style.css',
		array(),
		'1.3'
	);
}

/**
 * Main render function for both Shortcode and Gutenberg Block.
 * Fixes CVE-2026-6256 Stored XSS by escaping output.
 * Supports custom accent colors (badgeColor, linkColor, linkTextColor).
 */
function creditsPrint( $atts, $content = null ) {
	$atts = shortcode_atts(
		array(
			'link'            => '#',
			'type'            => 'source',
			'name'            => '',
			'badgeColor'      => '',
			'badge_color'     => '',
			'linkColor'       => '',
			'link_color'      => '',
			'linkTextColor'   => '',
			'link_text_color' => '',
		),
		$atts,
		'credits'
	);

	// Handle name from shortcode content or block attribute
	$raw_name = ! empty( $content ) ? $content : $atts['name'];
	if ( empty( $raw_name ) ) {
		$raw_name = 'Credit Link';
	}

	$clean_link = esc_url( $atts['link'] );
	$clean_name = esc_html( $raw_name );
	$type       = strtolower( trim( $atts['type'] ) );
	$category   = ( $type === 'via' ) ? 'Via' : 'Source';

	// Resolve custom accent colors
	$badge_bg      = ! empty( $atts['badgeColor'] ) ? $atts['badgeColor'] : $atts['badge_color'];
	$link_bg       = ! empty( $atts['linkColor'] ) ? $atts['linkColor'] : $atts['link_color'];
	$link_text_clr = ! empty( $atts['linkTextColor'] ) ? $atts['linkTextColor'] : $atts['link_text_color'];

	$badge_style = ! empty( $badge_bg ) ? sprintf( ' style="background-color: %s;"', esc_attr( $badge_bg ) ) : '';
	$link_style  = ! empty( $link_bg ) ? sprintf( ' style="background-color: %s;"', esc_attr( $link_bg ) ) : '';
	$text_style  = ! empty( $link_text_clr ) ? sprintf( ' style="color: %s;"', esc_attr( $link_text_clr ) ) : '';

	return sprintf(
		'<ul class="credits wp-block-credits-shortcode"><li class="credits"><span class="cre_cate"%s>%s</span><span class="cre_cate_link"%s><a href="%s" target="_blank" rel="noopener noreferrer"%s>%s</a></span></li></ul>',
		$badge_style,
		$category,
		$link_style,
		$clean_link,
		$text_style,
		$clean_name
	);
}

add_shortcode( 'credits', 'creditsPrint' );

/**
 * Register Gutenberg Block
 */
add_action( 'init', 'credits_register_gutenberg_block' );
function credits_register_gutenberg_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	wp_register_script(
		'credits-block-js',
		plugins_url( 'js/credits-block.js', __FILE__ ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
		'1.3',
		true
	);

	wp_register_style(
		'credits-block-style',
		plugins_url( 'css/style.css', __FILE__ ),
		array(),
		'1.3'
	);

	register_block_type(
		'credits/shortcode',
		array(
			'editor_script'   => 'credits-block-js',
			'editor_style'    => 'credits-block-style',
			'style'           => 'credits-block-style',
			'render_callback' => function ( $attributes ) {
				return creditsPrint( $attributes );
			},
		)
	);
}

/**
 * TinyMCE Classic Editor Fallback
 */
add_action( 'init', 'credits_buttons' );
function credits_buttons() {
	if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	if ( get_user_option( 'rich_editing' ) === 'true' ) {
		add_filter( 'mce_external_plugins', 'credits_add_buttons' );
		add_filter( 'mce_buttons', 'credits_register_buttons' );
	}
}

function credits_add_buttons( $plugin_array ) {
	$plugin_array['credits'] = plugins_url( 'credits_shortcode_plugin.js', __FILE__ );
	return $plugin_array;
}

function credits_register_buttons( $buttons ) {
	array_push( $buttons, 'addcredits' );
	return $buttons;
}
