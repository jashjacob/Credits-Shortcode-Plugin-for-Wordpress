import json
import os
import unittest


class TestCreditsPlugin(unittest.TestCase):
    def setUp(self):
        self.base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
        self.php_file = os.path.join(self.base_dir, "credits_shortcode.php")
        self.js_file = os.path.join(self.base_dir, "js/credits-block.js")
        self.json_file = os.path.join(self.base_dir, "block.json")

    def test_block_json_validity(self):
        """Verify block.json exists and is valid JSON matching WP schema."""
        self.assertTrue(os.path.exists(self.json_file), "block.json should exist")
        with open(self.json_file, "r") as f:
            data = json.load(f)

        self.assertEqual(data.get("name"), "credits/shortcode")
        self.assertEqual(data.get("style"), "credits-shortcode")
        self.assertEqual(data.get("editorStyle"), "credits-shortcode")
        self.assertIn("link", data.get("attributes", {}))
        self.assertIn("type", data.get("attributes", {}))
        self.assertIn("name", data.get("attributes", {}))

    def test_security_sanitization_in_php(self):
        """Ensure late escaping, input sanitization, and extract() removal."""
        with open(self.php_file, "r") as f:
            content = f.read()

        self.assertNotIn("extract(", content, "extract() should be removed")
        self.assertNotIn("$clean_link", content, "Do not pre-escape URLs into intermediate variables")
        self.assertNotIn("$clean_name", content, "Do not pre-escape names into intermediate variables")
        self.assertNotIn("sprintf(", content, "Do not build HTML with sprintf of pre-escaped fragments")
        self.assertIn("esc_url_raw(", content, "esc_url_raw() must sanitize URLs on input")
        self.assertIn("sanitize_text_field(", content, "sanitize_text_field() must sanitize names on input")
        self.assertIn("sanitize_hex_color(", content, "sanitize_hex_color() must validate accent colors")
        self.assertIn("safecss_filter_attr(", content, "safecss_filter_attr() must filter CSS context")
        self.assertIn("wp_kses(", content, "wp_kses() must wrap the returned HTML")
        self.assertIn("esc_url(", content, "esc_url() must be used at href output")
        self.assertIn("esc_html(", content, "esc_html() must be used at text output")
        self.assertIn("esc_attr(", content, "esc_attr() must be used at attribute output")

    def test_color_helper_rejects_non_hex(self):
        """Color helper must not fall back to sanitize_text_field()."""
        with open(self.php_file, "r") as f:
            content = f.read()

        start = content.find("function credits_sanitize_color")
        end = content.find("function credits_enqueue_styles")
        helper = content[start:end]
        self.assertNotEqual(start, -1)
        self.assertNotIn("sanitize_text_field(", helper)

    def test_enqueue_hooks(self):
        """Ensure wp_enqueue_style is hooked into wp_enqueue_scripts."""
        with open(self.php_file, "r") as f:
            content = f.read()

        self.assertIn("add_action( 'wp_enqueue_scripts'", content)

    def test_js_block_registration(self):
        """Ensure js/credits-block.js correctly registers credits/shortcode block."""
        with open(self.js_file, "r") as f:
            content = f.read()

        self.assertIn("registerBlockType('credits/shortcode'", content)
        self.assertIn("InspectorControls", content)
        self.assertIn("SelectControl", content)


if __name__ == "__main__":
    unittest.main()
