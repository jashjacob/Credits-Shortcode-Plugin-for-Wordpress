=== Credits Shortcode & Block ===
Contributors: jashjacob
Donate link: http://jashjacob.com
Tags: credits, source, via, shortcode, block
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.3.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easy shortcode and Gutenberg block to insert Source and Via Link inside posts in WordPress.

== Description ==

Credits Shortcode & Block allows content creators to easily add formatted Source or Via attribution links to WordPress posts and pages.

Supports the modern **Gutenberg Block Editor** (with live preview, customizable accent color pickers, and sidebar controls), traditional **Shortcodes**, and legacy **Classic Editor** toolbar buttons.

== Installation ==

1. Upload `credits-shortcode` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Changelog ==

= 1.3.1 =
* Sanitize attributes on input (`esc_url_raw`, `sanitize_text_field`, `sanitize_key`, `sanitize_hex_color`).
* Escape only when building output: `esc_url()` for href, `esc_html()` for text, `esc_attr( safecss_filter_attr() )` for inline CSS.
* Restrict accent colors to hex values and run the final markup through `wp_kses()`.

= 1.3 =
* Added native Gutenberg Block support (`credits/shortcode`).
* Added custom Accent Color Pickers in the Block Editor sidebar and shortcodes.
* Added micro-interaction hover lift animations and smooth CSS transitions.
* Fixed Stored XSS vulnerability (CVE-2026-6256).

= 1.2 =
* Initial public release with Classic Editor support.

== Upgrade Notice ==

= 1.3.1 =
Security and output-escaping update required to comply with WordPress.org plugin review.
