<?php
/**
 * PHPUnit bootstrap: minimal, faithful WordPress stubs + plugin load.
 *
 * Stubs mirror real WordPress semantics (allowlists, scheme checks,
 * hex validation) so tests exercise the real renderer, not weakened
 * reimplementations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

$GLOBALS['wp_version'] = '7.1';

/* -------------------------------------------------------------------------
 * Hooks / registration
 * ---------------------------------------------------------------------- */

$GLOBALS['credits_test_registered_shortcodes'] = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback = null, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback = null, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['credits_test_registered_shortcodes'][ $tag ] = $callback;
		return true;
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Faithful merge: defaults win for missing keys, unknown keys dropped.
	 */
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		$atts = (array) $atts;
		$out  = array();
		foreach ( $pairs as $name => $default ) {
			if ( array_key_exists( $name, $atts ) ) {
				$out[ $name ] = $atts[ $name ];
			} else {
				$out[ $name ] = $default;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'register_block_type' ) ) {
	function register_block_type( $block_type, $args = array() ) {
		return true;
	}
}

if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
	function register_block_type_from_metadata( $file_or_folder, $args = array() ) {
		return true;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
		return true;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
		return true;
	}
}

if ( ! function_exists( 'wp_set_script_translations' ) ) {
	function wp_set_script_translations( $handle, $domain = 'default', $path = null ) {
		return true;
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
		return true;
	}
}

/* -------------------------------------------------------------------------
 * URLs
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.test/wp-content/plugins/credits-shortcode/';
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ) {
		$base = 'http://example.test/wp-content/plugins/credits-shortcode/';
		return '' === $path ? $base : $base . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Supports both real signatures: (array $args, string|'' $url) and
	 * (string $key, string $value, string $url).
	 */
	function add_query_arg( ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			return 'http://example.test/?' . http_build_query( $args[0] );
		}

		if ( 2 === count( $args ) && is_array( $args[0] ) ) {
			$url = (string) $args[1];
			return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args[0] );
		}

		$url     = isset( $args[2] ) ? (string) $args[2] : '';
		$pairs   = array( (string) $args[0] => isset( $args[1] ) ? rawurlencode( (string) $args[1] ) : '' );
		$glue    = str_contains( $url, '?' ) ? '&' : '?';
		return $url . $glue . http_build_query( $pairs );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return false;
	}
}

if ( ! function_exists( 'get_user_option' ) ) {
	function get_user_option( $option, $user = 0 ) {
		return '';
	}
}

/* -------------------------------------------------------------------------
 * Options / settings screen
 * ---------------------------------------------------------------------- */

$GLOBALS['credits_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['credits_test_options'] )
			? $GLOBALS['credits_test_options'][ $option ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['credits_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		unset( $GLOBALS['credits_test_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'add_options_page' ) ) {
	function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
		return '';
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( $option_group, $option_name, $args = array() ) {
		return true;
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( $id, $title, $callback, $page ) {
		return true;
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {
		return true;
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( $option_group ) {
		return null;
	}
}

if ( ! function_exists( 'do_settings_sections' ) ) {
	function do_settings_sections( $page ) {
		return null;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = array() ) {
		return null;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $helper, $current = true, $display = true ) {
		$result = (string) $helper === (string) $current ? ' selected=\'selected\'' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		return true;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	$GLOBALS['credits_test_localized_scripts'] = array();

	function wp_localize_script( $handle, $object_name, $l10n ) {
		$GLOBALS['credits_test_localized_scripts'][ $handle ][ $object_name ] = $l10n;
		return true;
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		return true;
	}
}

/* -------------------------------------------------------------------------
 * Sanitization
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mirrors wp_strip_all_tags + whitespace collapse: tags removed
	 * entirely (script/style contents included), never escaped in place.
	 */
	function sanitize_text_field( $str ) {
		$str = (string) $str;
		$str = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $str );
		$str = strip_tags( $str );
		$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
		$str = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $str );
		return trim( $str );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	/**
	 * Valid 3/6-digit hex returned as-is; everything else rejected.
	 */
	function sanitize_hex_color( $color ) {
		$color = trim( (string) $color );
		if ( '' === $color ) {
			return '';
		}
		if ( preg_match( '/^#(?:[A-Fa-f0-9]{3}){1,2}$/', $color ) ) {
			return $color;
		}
		return '';
	}
}

if ( ! function_exists( 'safecss_filter_attr' ) ) {
	/**
	 * Only background-color/color declarations with a 3/6-digit hex value
	 * survive. Multiple declarations split on ';'. Anything else dropped.
	 */
	function safecss_filter_attr( $css ) {
		$allowed = '/^(background-color|color)\s*:\s*#(?:[A-Fa-f0-9]{3}){1,2}$/i';
		$good    = array();
		foreach ( explode( ';', (string) $css ) as $declaration ) {
			$declaration = trim( $declaration );
			if ( '' === $declaration ) {
				continue;
			}
			if ( preg_match( $allowed, $declaration ) ) {
				$good[] = preg_replace( '/^([a-z-]+)\s*:\s*(.+)$/i', '$1: $2', $declaration );
			}
		}
		return implode( '; ', $good );
	}
}

/* -------------------------------------------------------------------------
 * Escaping
 * ---------------------------------------------------------------------- */

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'credits_test_filter_url' ) ) {
	/**
	 * Shared esc_url()/esc_url_raw() core.
	 *
	 * - Strips whitespace and control characters anywhere in the URL.
	 * - Decodes numeric entities before scheme sniffing so
	 *   "&#106;avascript:" cannot smuggle a bad scheme through.
	 * - Allows only http/https/mailto/ftp; any other scheme -> ''.
	 * - Scheme-less (relative) URLs pass through cleaned.
	 */
	function credits_test_filter_url( $url ) {
		$url = (string) $url;
		if ( '' === trim( $url ) ) {
			return '';
		}

		// Whitespace + control characters are stripped, never encoded away.
		$url = preg_replace( '/[\x00-\x20\x7f]/', '', $url );

		// Decode entities so encoded schemes are caught by the allowlist.
		$decoded = html_entity_decode( $url, ENT_QUOTES | ENT_HTML401, 'UTF-8' );
		$decoded = preg_replace( '/[\x00-\x20\x7f]/', '', $decoded );

		if ( preg_match( '#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $decoded, $m ) ) {
			$scheme = strtolower( $m[1] );
			if ( ! in_array( $scheme, array( 'http', 'https', 'mailto', 'ftp' ), true ) ) {
				return '';
			}
		}

		return $decoded;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return credits_test_filter_url( $url );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		$url = credits_test_filter_url( $url );
		if ( '' === $url ) {
			return '';
		}
		return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

/* -------------------------------------------------------------------------
 * kses
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'wp_kses' ) ) {
	/**
	 * Minimal allowlist parser honoring the shape of credits_allowed_html():
	 * tags not in the map are dropped entirely (their text content stays),
	 * allowed tags are rebuilt keeping only whitelisted attributes, which
	 * are re-escaped on output.
	 */
	function wp_kses( $string, $allowed_html, $allowed_protocols = array() ) {
		$string = (string) $string;
		$parts  = preg_split( '/(<[^>]*>)/', $string, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts || 1 === count( $parts ) ) {
			return $string;
		}

		$out = array();
		foreach ( $parts as $part ) {
			if ( '' === $part || '<' !== substr( $part, 0, 1 ) ) {
				$out[] = $part;
				continue;
			}

			// Malformed fragments and comments are dropped.
			if ( ! preg_match( '/^<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9-]*)/', $part, $tag_match ) ) {
				continue;
			}

			$tag     = strtolower( $tag_match[2] );
			$closing = '' !== $tag_match[1];

			if ( ! isset( $allowed_html[ $tag ] ) || ! is_array( $allowed_html[ $tag ] ) ) {
				continue;
			}

			if ( $closing ) {
				$out[] = '</' . $tag . '>';
				continue;
			}

			$self_close = (bool) preg_match( '/\/\s*>$/', $part );

			$attrs = array();
			if ( preg_match_all( '/([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/', $part, $attr_matches, PREG_SET_ORDER ) ) {
				foreach ( $attr_matches as $am ) {
					$name = strtolower( $am[1] );
					if ( '"' === $am[2][0] ) {
						$value = $am[3];
					} elseif ( "'" === $am[2][0] ) {
						$value = $am[4];
					} else {
						$value = $am[5];
					}
					if ( ! empty( $allowed_html[ $tag ][ $name ] ) ) {
						$attrs[ $name ] = $value;
					}
				}
			}

			$html = '<' . $tag;
			foreach ( $attrs as $name => $value ) {
				$html .= ' ' . $name . '="' . htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '"';
			}
			$html .= ( $self_close ? ' />' : '>' );
			$out[] = $html;
		}

		return implode( '', $out );
	}
}

/* -------------------------------------------------------------------------
 * Conditional enqueue / query stubs
 * ---------------------------------------------------------------------- */

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		/** @var int */
		public $ID;
		/** @var string */
		public $post_content;
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var array<int, WP_Post> */
		public $posts = array();
	}
}

if ( ! function_exists( 'has_shortcode' ) ) {
	function has_shortcode( $content, $tag ) {
		$content = (string) $content;
		$tag     = preg_quote( (string) $tag, '/' );
		return (bool) preg_match( '/\[' . $tag . '(\s|\]|\/)/', $content );
	}
}

if ( ! function_exists( 'has_block' ) ) {
	function has_block( $block_name, $post = null ) {
		if ( $post instanceof WP_Post ) {
			$content = $post->post_content;
		} else {
			$content = (string) $post;
		}
		return str_contains( $content, '<!-- wp:' . (string) $block_name );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['credits_test_is_admin'] );
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular() {
		return ! empty( $GLOBALS['credits_test_is_singular'] );
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return isset( $GLOBALS['credits_test_queried_object'] ) ? (int) $GLOBALS['credits_test_queried_object'] : 0;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null ) {
		if ( null === $post ) {
			return null;
		}
		$id = (int) $post;
		if ( isset( $GLOBALS['credits_test_post_store'][ $id ] ) ) {
			$stored = $GLOBALS['credits_test_post_store'][ $id ];
			if ( $stored instanceof WP_Post ) {
				return $stored;
			}
			$obj                = new WP_Post();
			$obj->ID            = $id;
			$obj->post_content  = isset( $stored->post_content ) ? (string) $stored->post_content : '';
			return $obj;
		}
		return null;
	}
}

/* -------------------------------------------------------------------------
 * Load the plugin itself
 * ---------------------------------------------------------------------- */

require_once dirname( __DIR__ ) . '/credits_shortcode.php';
