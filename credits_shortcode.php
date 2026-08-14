<?php
/*
Plugin Name: Credits Shortcode & Block
Version: 1.3.1
Plugin URI: https://github.com/jashjacob/Credits-Shortcode-Plugin-for-Wordpress
Description: Easy shortcode and Gutenberg block to insert Source and Via Link inside posts in WordPress.
Author: Jash Jacob
Author URI: http://jashjacob.com
Requires at least: 5.0
Requires PHP: 7.4
Tested up to: 7.0
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
 * Allowed HTML for the rendered credits markup.
 *
 * @return array
 */
function credits_allowed_html() {
	return array(
		'ul'   => array(
			'class' => true,
		),
		'li'   => array(
			'class' => true,
		),
		'span' => array(
			'class' => true,
			'style' => true,
		),
		'a'    => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
			'style'  => true,
		),
	);
}

/**
 * Return a scalar attribute as a string.
 *
 * @param mixed $value Raw attribute.
 * @return string
 */
function credits_string_attr( $value ) {
	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Sanitize a user-supplied color. Hex only.
 *
 * @param mixed $color Raw color value.
 * @return string Sanitized hex color or empty string.
 */
function credits_sanitize_color( $color ) {
	$color = trim( credits_string_attr( $color ) );
	if ( '' === $color ) {
		return '';
	}

	$hex = sanitize_hex_color( $color );
	return is_string( $hex ) ? $hex : '';
}

/**
 * Enqueue frontend styles.
 */
add_action( 'wp_enqueue_scripts', 'credits_enqueue_styles' );
function credits_enqueue_styles() {
	wp_enqueue_style(
		'credits-shortcode',
		plugin_dir_url( __FILE__ ) . 'css/style.css',
		array(),
		'1.3.1'
	);
}

/**
 * Render the credits shortcode and dynamic block.
 *
 * Sanitize/validate first. Escape only when a value is concatenated into HTML,
 * using the function that matches that context.
 *
 * @param array       $atts    Shortcode or block attributes.
 * @param string|null $content Optional shortcode inner content (the name).
 * @return string
 */
function creditsPrint( $atts, $content = null ) {
	if ( ! is_array( $atts ) ) {
		$atts = array();
	}

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

	// Sanitize / validate early. These variables stay unescaped.
	if ( is_string( $content ) && '' !== trim( $content ) ) {
		$name = sanitize_text_field( $content );
	} else {
		$name = sanitize_text_field( credits_string_attr( $atts['name'] ) );
	}
	if ( '' === $name ) {
		$name = 'Credit Link';
	}

	$link = esc_url_raw( credits_string_attr( $atts['link'] ) );
	if ( '' === $link ) {
		$link = '#';
	}

	$type     = sanitize_key( credits_string_attr( $atts['type'] ) );
	$category = ( 'via' === $type ) ? 'Via' : 'Source';

	$badge_bg      = credits_sanitize_color( ! empty( $atts['badgeColor'] ) ? $atts['badgeColor'] : $atts['badge_color'] );
	$link_bg       = credits_sanitize_color( ! empty( $atts['linkColor'] ) ? $atts['linkColor'] : $atts['link_color'] );
	$link_text_clr = credits_sanitize_color( ! empty( $atts['linkTextColor'] ) ? $atts['linkTextColor'] : $atts['link_text_color'] );

	$html  = '<ul class="credits wp-block-credits-shortcode">';
	$html .= '<li class="credits">';
	$html .= '<span class="cre_cate"';
	if ( '' !== $badge_bg ) {
		$html .= ' style="' . esc_attr( safecss_filter_attr( 'background-color: ' . $badge_bg ) ) . '"';
	}
	$html .= '>' . esc_html( $category ) . '</span>';
	$html .= '<span class="cre_cate_link"';
	if ( '' !== $link_bg ) {
		$html .= ' style="' . esc_attr( safecss_filter_attr( 'background-color: ' . $link_bg ) ) . '"';
	}
	$html .= '>';
	$html .= '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer"';
	if ( '' !== $link_text_clr ) {
		$html .= ' style="' . esc_attr( safecss_filter_attr( 'color: ' . $link_text_clr ) ) . '"';
	}
	$html .= '>' . esc_html( $name ) . '</a>';
	$html .= '</span></li></ul>';

	return wp_kses( $html, credits_allowed_html() );
}

add_shortcode( 'credits', 'creditsPrint' );

/**
 * Block render callback. Pass attributes only so inner block HTML is not treated as the name.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function credits_render_block( $attributes ) {
	return creditsPrint( is_array( $attributes ) ? $attributes : array() );
}

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
		'1.3.1',
		true
	);

	wp_register_style(
		'credits-shortcode',
		plugins_url( 'css/style.css', __FILE__ ),
		array(),
		'1.3.1'
	);

	register_block_type(
		'credits/shortcode',
		array(
			'editor_script'   => 'credits-block-js',
			'editor_style'    => 'credits-shortcode',
			'style'           => 'credits-shortcode',
			'render_callback' => 'credits_render_block',
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
	$buttons[] = 'addcredits';
	return $buttons;
}
