<?php
/*
Plugin Name: Credits Shortcode & Block
Version: 1.6.0
Plugin URI: https://github.com/jashjacob/Credits-Shortcode-Plugin-for-Wordpress
Description: Add clean Source and Via attribution links with a Gutenberg block, shortcode, or Classic Editor button.
Author: Jash Jacob
Author URI: https://jashjacob.com
Requires at least: 6.3
Requires PHP: 7.4
Tested up to: 7.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: credits-shortcode
Domain Path: /languages

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

if ( ! defined( 'CREDITS_SHORTCODE_VERSION' ) ) {
	define( 'CREDITS_SHORTCODE_VERSION', '1.6.0' );
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

	$hex = '';
	if ( function_exists( 'sanitize_hex_color' ) ) {
		// Not globally available before WP 5.4 (Customizer-only load).
		$hex = sanitize_hex_color( $color );
	} elseif ( preg_match( '/^#(?:[A-Fa-f0-9]{3}){1,2}$/', $color ) ) {
		$hex = $color;
	}

	return is_string( $hex ) ? $hex : '';
}

/**
 * Sanitize a credit type value against the allowlist.
 *
 * @param mixed $value Raw type value.
 * @return string Either 'source' or 'via'.
 */
function credits_sanitize_type( $value ) {
	$value = sanitize_key( credits_string_attr( $value ) );
	return ( 'via' === $value ) ? 'via' : 'source';
}

/**
 * Sanitize a spacing preset.
 *
 * @param mixed $value Raw spacing preset.
 * @return string Either 'compact', 'standard', 'spacious', or empty.
 */
function credits_sanitize_spacing( $value ) {
	$value = sanitize_key( credits_string_attr( $value ) );
	return in_array( $value, array( 'compact', 'standard', 'spacious' ), true ) ? $value : '';
}

/**
 * Sanitize the full settings array for storage.
 *
 * @param mixed $input Raw settings input.
 * @return array Clean settings.
 */
function credits_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();

	return array(
		'type'            => credits_sanitize_type( isset( $input['type'] ) ? $input['type'] : '' ),
		'spacing'         => credits_sanitize_spacing( isset( $input['spacing'] ) ? $input['spacing'] : '' ) ?: 'standard',
		'badge_color'     => credits_sanitize_color( isset( $input['badge_color'] ) ? $input['badge_color'] : '' ),
		'link_color'      => credits_sanitize_color( isset( $input['link_color'] ) ? $input['link_color'] : '' ),
		'link_text_color' => credits_sanitize_color( isset( $input['link_text_color'] ) ? $input['link_text_color'] : '' ),
	);
}

/**
 * Read site-wide default appearance from the database.
 *
 * @return array Sanitized settings.
 */
function credits_get_settings() {
	$defaults = array(
		'type'            => 'source',
		'spacing'         => 'standard',
		'badge_color'     => '',
		'link_color'      => '',
		'link_text_color' => '',
	);

	$stored = get_option( 'credits_shortcode_settings', array() );
	if ( ! is_array( $stored ) ) {
		return $defaults;
	}

	foreach ( $defaults as $key => $default ) {
		if ( ! array_key_exists( $key, $stored ) ) {
			continue;
		}
		if ( 'type' === $key ) {
			$defaults[ $key ] = credits_sanitize_type( $stored[ $key ] );
		} elseif ( 'spacing' === $key ) {
			$defaults[ $key ] = credits_sanitize_spacing( $stored[ $key ] ) ?: 'standard';
		} else {
			$defaults[ $key ] = credits_sanitize_color( $stored[ $key ] );
		}
	}

	return $defaults;
}

/**
 * Return a cache-busting version for plugin assets that can change between
 * plugin releases or test uploads without changing the public plugin version.
 *
 * @return string
 */
function credits_asset_version() {
	$style_path = plugin_dir_path( __FILE__ ) . 'css/style.css';
	$mtime      = file_exists( $style_path ) ? filemtime( $style_path ) : false;

	return $mtime ? CREDITS_SHORTCODE_VERSION . '.' . $mtime : CREDITS_SHORTCODE_VERSION;
}

/**
 * Whether post content includes the credits block or shortcode.
 *
 * @param string|WP_Post|null $post Post object, content string, or post ID.
 * @return bool
 */
function credits_content_includes_credits( $post ) {
	if ( $post instanceof WP_Post ) {
		$content = $post->post_content;
	} elseif ( is_numeric( $post ) ) {
		$loaded = get_post( (int) $post );
		$content = $loaded ? $loaded->post_content : '';
	} else {
		$content = (string) $post;
	}

	if ( '' === trim( $content ) ) {
		return false;
	}

	if ( function_exists( 'has_block' ) && has_block( 'credits/shortcode', $content ) ) {
		return true;
	}

	return has_shortcode( $content, 'credits' );
}

/**
 * Whether frontend CSS should load on this response.
 *
 * @return bool
 */
function credits_should_enqueue_frontend_styles() {
	if ( is_admin() ) {
		return false;
	}

	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		return $post_id && credits_content_includes_credits( $post_id );
	}

	global $wp_query;
	if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
		foreach ( $wp_query->posts as $post ) {
			if ( $post instanceof WP_Post && credits_content_includes_credits( $post ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Enqueue frontend styles.
 */
add_action( 'wp_enqueue_scripts', 'credits_enqueue_styles' );
function credits_enqueue_styles() {
	if ( ! credits_should_enqueue_frontend_styles() ) {
		return;
	}

	wp_enqueue_style(
		'credits-shortcode',
		plugin_dir_url( __FILE__ ) . 'css/style.css',
		array(),
		credits_asset_version()
	);
}

/**
 * Load plugin textdomain.
 */
add_action( 'init', 'credits_load_textdomain' );
function credits_load_textdomain() {
	load_plugin_textdomain( 'credits-shortcode', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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
function credits_print_shortcode( $atts, $content = null ) {
	if ( ! is_array( $atts ) ) {
		$atts = array();
	}

	$attr = static function ( $key ) use ( $atts ) {
		return isset( $atts[ $key ] ) ? credits_string_attr( $atts[ $key ] ) : '';
	};

	// Sanitize / validate early. These variables stay unescaped.
	if ( is_string( $content ) && '' !== trim( $content ) ) {
		$name = sanitize_text_field( $content );
	} else {
		$name = sanitize_text_field( $attr( 'name' ) );
	}
	if ( '' === $name ) {
		$name = __( 'Credit Link', 'credits-shortcode' );
	}

	$link = esc_url_raw( $attr( 'link' ) );
	if ( '' === $link ) {
		$link = '#';
	}

	$settings = credits_get_settings();
	$spacing  = credits_sanitize_spacing( $attr( 'spacing' ) );
	if ( '' === $spacing ) {
		$spacing = $settings['spacing'];
	}

	$type     = sanitize_key( $attr( 'type' ) );
	if ( '' === $type ) {
		$type = $settings['type'];
	}
	$category = ( 'via' === $type ) ? __( 'Via', 'credits-shortcode' ) : __( 'Source', 'credits-shortcode' );

	$badge_bg      = credits_sanitize_color( $attr( 'badgeColor' ) );
	$link_bg       = credits_sanitize_color( $attr( 'linkColor' ) );
	$link_text_clr = credits_sanitize_color( $attr( 'linkTextColor' ) );

	if ( '' === $badge_bg ) {
		$badge_bg = credits_sanitize_color( $attr( 'badge_color' ) );
	}
	if ( '' === $link_bg ) {
		$link_bg = credits_sanitize_color( $attr( 'link_color' ) );
	}
	if ( '' === $link_text_clr ) {
		$link_text_clr = credits_sanitize_color( $attr( 'link_text_color' ) );
	}

	if ( '' === $badge_bg ) {
		$badge_bg = $settings['badge_color'];
	}
	if ( '' === $link_bg ) {
		$link_bg = $settings['link_color'];
	}
	if ( '' === $link_text_clr ) {
		$link_text_clr = $settings['link_text_color'];
	}

	$html  = '<ul class="credits wp-block-credits-shortcode credits-spacing-' . esc_attr( $spacing ) . '">';
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

add_shortcode( 'credits', 'credits_print_shortcode' );

/**
 * Block render callback. Pass attributes only so inner block HTML is not treated as the name.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function credits_render_block( $attributes ) {
	return credits_print_shortcode( is_array( $attributes ) ? $attributes : array() );
}

/**
 * Register Gutenberg Block
 */
add_action( 'init', 'credits_register_gutenberg_block' );

function credits_register_gutenberg_block() {
	if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
		return;
	}

	// wp-block-editor was split from wp-editor in WordPress 5.2.
	$block_editor_dependency = function_exists( 'wp_script_is' ) && wp_script_is( 'wp-block-editor', 'registered' )
		? 'wp-block-editor'
		: 'wp-editor';

	wp_register_script(
		'credits-block-js',
		plugins_url( 'js/credits-block.js', __FILE__ ),
		array( 'wp-blocks', 'wp-element', $block_editor_dependency, 'wp-components', 'wp-i18n' ),
		CREDITS_SHORTCODE_VERSION,
		true
	);
	$editor_settings = credits_get_settings();
	wp_localize_script( 'credits-block-js', 'creditsShortcodeSettings', $editor_settings );
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'credits-block-js', 'credits-shortcode', plugin_dir_path( __FILE__ ) . 'languages' );
	}

	wp_register_style(
		'credits-shortcode',
		plugins_url( 'css/style.css', __FILE__ ),
		array(),
		credits_asset_version()
	);


	register_block_type_from_metadata(
		__DIR__ . '/block.json',
		array(
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
	$plugin_array['credits'] = add_query_arg( 'ver', CREDITS_SHORTCODE_VERSION, plugins_url( 'credits_shortcode_plugin.js', __FILE__ ) );
	return $plugin_array;
}

function credits_register_buttons( $buttons ) {
	$buttons[] = 'addcredits';
	return $buttons;
}

/**
 * Register the Settings -> Credits page.
 */
add_action( 'admin_menu', 'credits_add_settings_page' );
function credits_add_settings_page() {
	add_options_page(
		__( 'Credits Shortcode', 'credits-shortcode' ),
		__( 'Credits', 'credits-shortcode' ),
		'manage_options',
		'credits-shortcode',
		'credits_render_settings_page'
	);
}

/**
 * Register settings, section and fields.
 */
add_action( 'admin_init', 'credits_register_settings' );
function credits_register_settings() {
	register_setting(
		'credits_shortcode',
		'credits_shortcode_settings',
		array( 'sanitize_callback' => 'credits_sanitize_settings' )
	);

	add_settings_section(
		'credits_shortcode_defaults',
		__( 'Default Credit Appearance', 'credits-shortcode' ),
		'credits_settings_section_intro',
		'credits-shortcode'
	);

	add_settings_field(
		'credits_default_type',
		__( 'Default credit type', 'credits-shortcode' ),
		'credits_field_default_type',
		'credits-shortcode',
		'credits_shortcode_defaults'
	);

	add_settings_field(
		'credits_default_spacing',
		__( 'Default spacing between credits', 'credits-shortcode' ),
		'credits_field_default_spacing',
		'credits-shortcode',
		'credits_shortcode_defaults'
	);

	foreach ( array(
		'badge_color'     => __( 'Badge background color', 'credits-shortcode' ),
		'link_color'      => __( 'Link background color', 'credits-shortcode' ),
		'link_text_color' => __( 'Link text color', 'credits-shortcode' ),
	) as $key => $label ) {
		add_settings_field(
			'credits_' . $key,
			$label,
			'credits_field_color',
			'credits-shortcode',
			'credits_shortcode_defaults',
			array( 'color_key' => $key )
		);
	}
}

/**
 * Section intro copy.
 */
function credits_settings_section_intro() {
	echo '<p>' . esc_html( __( 'These defaults apply when a shortcode or block does not set its own values. Individual credits can override spacing and colors. Leave colors empty to inherit neutral plugin styling.', 'credits-shortcode' ) ) . '</p>';
}

/**
 * Default credit type dropdown.
 */
function credits_field_default_type() {
	$type = credits_get_settings()['type'];
	?>
	<select name="credits_shortcode_settings[type]">
		<option value="source" <?php selected( 'source', $type ); ?>><?php echo esc_html( __( 'Source', 'credits-shortcode' ) ); ?></option>
		<option value="via" <?php selected( 'via', $type ); ?>><?php echo esc_html( __( 'Via', 'credits-shortcode' ) ); ?></option>
	</select>
	<?php
}

/**
 * Default spacing preset dropdown.
 */
function credits_field_default_spacing() {
	$spacing = credits_get_settings()['spacing'];
	?>
	<select name="credits_shortcode_settings[spacing]">
		<option value="compact" <?php selected( 'compact', $spacing ); ?>><?php echo esc_html( __( 'Compact', 'credits-shortcode' ) ); ?></option>
		<option value="standard" <?php selected( 'standard', $spacing ); ?>><?php echo esc_html( __( 'Standard', 'credits-shortcode' ) ); ?></option>
		<option value="spacious" <?php selected( 'spacious', $spacing ); ?>><?php echo esc_html( __( 'Spacious', 'credits-shortcode' ) ); ?></option>
	</select>
	<p class="description"><?php echo esc_html( __( 'Controls the vertical gap between consecutive credits.', 'credits-shortcode' ) ); ?></p>
	<?php
}

/**
 * Color picker field.
 *
 * @param array $args Field args, expects 'color_key'.
 */
function credits_field_color( $args ) {
	$key     = isset( $args['color_key'] ) ? sanitize_key( $args['color_key'] ) : '';
	$allowed = array( 'badge_color', 'link_color', 'link_text_color' );
	if ( '' === $key || ! in_array( $key, $allowed, true ) ) {
		return;
	}

	$value = credits_get_settings()[ $key ];
	?>
	<input
		type="text"
		class="credits-color-picker"
		name="credits_shortcode_settings[<?php echo esc_attr( $key ); ?>]"
		value="<?php echo esc_attr( $value ); ?>"
		data-default-color="<?php echo esc_attr( $value ); ?>"
	/>
	<?php
}

/**
 * Render the settings page.
 */
function credits_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'Credits Shortcode', 'credits-shortcode' ) ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'credits_shortcode' );
			do_settings_sections( 'credits-shortcode' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Enqueue the WordPress color picker on the Credits settings screen only.
 */
add_action( 'admin_enqueue_scripts', 'credits_admin_assets' );
function credits_admin_assets( $hook ) {
	if ( 'settings_page_credits-shortcode' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script(
		'wp-color-picker',
		'jQuery(function($){ $(\'.credits-color-picker\').wpColorPicker(); });'
	);
}
