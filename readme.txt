=== Credits Shortcode & Block ===
Contributors: jashjacob
Donate link: https://jashjacob.com
Tags: attribution, source, credits, block, shortcode
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 1.6.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add clean Source and Via attribution links with a Gutenberg block, shortcode, or Classic Editor button.

== Description ==

Give readers a clear path back to the original source. Credits Shortcode & Block adds compact, theme-friendly Source or Via attribution links to WordPress posts and pages.

= Highlights =

* Native Gutenberg block with a live editor preview.
* Simple `[credits]` shortcode for any post, page, or widget area.
* Site-wide defaults under Settings > Credits.
* Optional colors for the badge, link background, and link text.
* Compact, standard, or spacious gaps between consecutive credits.
* Classic Editor toolbar button for legacy workflows.
* Responsive, theme-friendly output with subtle hover feedback.
* Translation-ready and compatible with WordPress multisite.
* No external service, account, tracking, or front-end JavaScript.

= Gutenberg Block =

1. Open a post or page in the Block Editor.
2. Add the **Credits Link** block by clicking **+** or typing `/credits`.
3. Choose Source or Via, enter the attribution name, and add its URL.
4. Optionally choose accent colors in the block sidebar.

The block is rendered dynamically, so saved content stays clean and current.

= Shortcode =

Basic source attribution:

`[credits link="https://example.com"]Source Name[/credits]`

Via attribution:

`[credits link="https://example.com" type="via"]Publication Name[/credits]`

Using a name attribute:

`[credits name="Source Name" link="https://example.com"]`

Custom colors:

`[credits link="https://example.com" type="source" badge_color="#0073aa" link_color="#f0f0f0" link_text_color="#0073aa"]Source Name[/credits]`

Supported attributes:

* `name` - attribution text; inner shortcode content takes precedence.
* `link` - destination URL.
* `type` - `source` or `via`.
* `badge_color` - badge background as a 3- or 6-digit hex color.
* `link_color` - link area background as a 3- or 6-digit hex color.
* `link_text_color` - link text as a 3- or 6-digit hex color.
* `spacing` - `compact`, `standard`, or `spacious`; defaults to the site-wide setting.

To keep several credits close together:

`[credits link="https://example.com" spacing="compact"]Source Name[/credits]`

= Site-wide Defaults =

Go to **Settings > Credits** to choose the default credit type, spacing, and optional accent colors. Individual blocks and shortcodes can override those defaults.

== Installation ==

1. In WordPress, go to **Plugins > Add New**.
2. Search for **Credits Shortcode & Block**.
3. Click **Install Now**, then **Activate**.
4. Add a Credits Link block, use the `[credits]` shortcode, or configure defaults under **Settings > Credits**.

For a manual installation, upload the plugin folder to `/wp-content/plugins/` and activate it from the Plugins screen.

== Frequently Asked Questions ==

= Can I change how the credit line looks? =

Yes. Set colors for an individual block or shortcode, configure site-wide defaults under Settings > Credits, or use custom CSS. Without custom colors, the plugin uses neutral styling that inherits the active theme's link color.

= Can I add more than one credit to a post? =

Yes. Add as many blocks or shortcodes as needed. Each credit is rendered independently.

= What happens if I deactivate the plugin? =

Your posts are not modified or deleted. Dynamic blocks stop rendering, and shortcode tags remain in post content until the plugin is reactivated.

= Does the plugin send data to another service? =

No. The plugin makes no external requests and includes no analytics or tracking.

= Does it add rel="nofollow" to links? =

No. Credit links open in a new tab with `noopener noreferrer`. The plugin does not add `nofollow` automatically.

= Does it work with the Classic Editor? =

Yes. When the Classic Editor is active, an Add Credits toolbar button inserts the shortcode for you.

== Screenshots ==

1. A Source or Via attribution rendered on the front end.
2. Credits Link settings in the Gutenberg block sidebar.

== Changelog ==

= 1.6.0 =
* Requires WordPress 6.3 or newer.
* Registers the Gutenberg block with Block API version 3 consistently.
* Removed the legacy Block API version 1 registration fallback.

= 1.5.0 =
* Added site-wide spacing defaults under Settings > Credits.
* Added Compact, Standard, and Spacious spacing presets for each block or shortcode.
* Added spacing inheritance and validation tests.

= 1.4.1 =
* Fixed Gutenberg block registration on WordPress 5.0 through 5.4.
* Fixed new blocks so they inherit the site-wide default credit type.
* Added block-editor JavaScript translation loading.
* Refreshed the WordPress.org banner, icons, and screenshots.
* Improved directory copy and release metadata.

= 1.4.0 =
* Added internationalization support for PHP and block-editor strings.
* Registered the block from `block.json` metadata on supported WordPress versions.
* Added site-wide defaults under Settings > Credits.
* Added a PHPUnit test suite and PHP 7.4 through 8.4 continuous integration.
* Switched to neutral, theme-friendly default styling while preserving saved colors.

= 1.3.1 =
* Sanitized shortcode attributes and applied context-aware escaping to rendered output.
* Restricted accent colors to valid hex values.
* Added a strict HTML allowlist for final shortcode markup.

= 1.3 =
* Added the native Credits Link Gutenberg block.
* Added per-credit accent color controls.
* Added hover feedback and block-theme layout compatibility.
* Hardened shortcode rendering and output escaping.

= 1.2 =
* Initial public release with shortcode and Classic Editor support.

== Upgrade Notice ==

= 1.6.0 =
Requires WordPress 6.3 or newer and registers the Credits Link block with Block API version 3.
