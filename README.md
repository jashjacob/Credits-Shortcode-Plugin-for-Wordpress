# Credits Shortcode & Block Plugin for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![Version](https://img.shields.io/badge/version-1.3-green.svg)](https://github.com/jashjacob/Credits-Shortcode-Plugin-for-Wordpress)
[![License](https://img.shields.io/badge/license-GPLv2-orange.svg)](LICENSE)

**Credits Shortcode & Block** is a lightweight, secure WordPress plugin that lets content creators seamlessly insert **"Source"** or **"Via"** attribution links into posts and pages.

Supports the modern **Gutenberg Block Editor** (with live preview, customizable accent color pickers, and sidebar controls), traditional **Shortcodes**, and legacy **Classic Editor** toolbar buttons.

---

## 📸 Screenshots

| Block Editor Sidebar Controls | Front-End Post Preview |
| :---: | :---: |
| <img src="assets/gutenberg-sidebar-settings.png" alt="Gutenberg Sidebar Settings" width="380" /> | <img src="assets/frontend-post-preview.png" alt="Front-End Post Preview" width="500" /> |

---

## 🚀 Features

- 🧩 **Gutenberg Block Editor**: Add credits using a native WordPress block (`/credits`) featuring live in-editor previews and block sidebar controls.
- 🎨 **Custom Accent Colors**: Pick custom badge background, link background, and link text colors directly from the Block Editor sidebar or shortcode parameters.
- ✨ **Micro-Interaction Hover Effects**: Smooth 0.2s CSS transitions with subtle hover elevation and brightness shifts.
- 📐 **Flush Block Layout Alignment**: Auto-adapts to modern WordPress block themes (Twenty Twenty-Four, Twenty Twenty-Five) to sit 100% flush with paragraph content.
- 🔒 **Security Hardened**: Fully sanitized inputs (`esc_url()`, `esc_html()`) protecting against Stored XSS (CVE-2026-6256 fix).
- ⚡ **Zero Build Dependencies**: Built using standard WordPress JS globals (`wp.blocks`, `wp.element`, `wp.blockEditor`). No `npm build` required.
- 🔄 **100% Backward Compatible**: Legacy shortcodes `[credits]` continue to render seamlessly.

---

## 🛠️ Usage Guide

### Option 1: Gutenberg Block Editor (Recommended)

1. Open any post or page in the WordPress Block Editor.
2. Click **`+`** or type `/credits` to insert the **Credits Link** block.
3. Use the block sidebar (Inspector Controls) to configure:
   - **Credit Type**: Select `Source` or `Via`.
   - **Source / Via Name**: Enter attribution name (e.g., *TechZei*).
   - **Link URL**: Enter source URL (e.g., `http://techzei.com`).
4. Expand **Accent Color Settings** in the block sidebar to customize:
   - **Badge Background Color** (default: `#ef4423`)
   - **Link Background Color** (default: `#E0D9D9`)
   - **Link Text Color** (default: `#ef4423`)

---

### Option 2: WordPress Shortcodes

Insert shortcodes directly in paragraph blocks, shortcode blocks, or text widgets:

#### **Standard Source Attribution**
```text
[credits link="https://example.com" type="source"]Source Name[/credits]
```

#### **Standard Via Attribution**
```text
[credits link="https://example.com" type="via"]Via Name[/credits]
```

#### **Custom Accent Colors Shortcode**
```text
[credits link="https://example.com" type="source" badge_color="#0073aa" link_color="#f0f0f0" link_text_color="#0073aa"]Source Name[/credits]
```

---

### Option 3: Classic Editor (Legacy TinyMCE)

If you use the Classic Editor plugin:
1. Highlight text or click the **Credits Logo** button in the TinyMCE toolbar.
2. Enter the attribution type (`Source` or `Via`), URL, and name when prompted.

---

## 🎨 Customizing Styles via CSS

You can also override the default appearance in **Appearance > Customize > Additional CSS**:

```css
/* Custom background color for Source / Via badge */
.cre_cate {
    background: #0073aa !important;
    font-style: normal;
}

/* Custom background color for link container */
.cre_cate_link {
    background: #f0f0f0 !important;
}

/* Custom link text color on hover */
.cre_cate_link a:hover {
    text-decoration: underline !important;
}
```

---

## 📦 Installation

1. Download or clone this repository:
   ```bash
   git clone https://github.com/jashjacob/Credits-Shortcode-Plugin-for-Wordpress.git
   ```
2. Upload the `Credits-Shortcode-Plugin-for-Wordpress` directory to your WordPress site's `/wp-content/plugins/` directory (or upload the `.zip` file via **Plugins > Add New > Upload Plugin**).
3. Activate the plugin through the **Plugins** menu in WordPress.

---

## 📜 Changelog

### Version 1.3
- 🌟 Added native Gutenberg Block support (`credits/shortcode`).
- 🎨 Added custom **Accent Color Pickers** in the Block Editor sidebar and shortcode parameters (`badge_color`, `link_color`, `link_text_color`).
- ✨ Added micro-interaction hover lift animations and smooth CSS transitions.
- 📐 Fixed block layout container specificity for modern WordPress Block Themes (Twenty Twenty-Four, Twenty Twenty-Five).
- 🔒 Fixed Stored XSS vulnerability (CVE-2026-6256) by removing `extract()` and escaping all URLs/HTML output.
- 📋 Added official WordPress header tags (`Requires at least: 5.0`, `Requires PHP: 7.4`, `Tested up to: 6.7`).

### Version 1.2
- Added TinyMCE editor toolbar button integration.
- Initial public release on GitHub & WordPress.org.

---

## 📄 License

Distributed under the GNU General Public License v2 or later. See `LICENSE` for details.

Developed by [Jash Jacob](http://jashjacob.com).
