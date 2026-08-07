import json
import re
import unittest
import os

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
        self.assertEqual(data.get("style"), "credits-block-style")
        self.assertEqual(data.get("editorStyle"), "credits-block-style")
        self.assertIn("link", data.get("attributes", {}))
        self.assertIn("type", data.get("attributes", {}))
        self.assertIn("name", data.get("attributes", {}))

    def test_security_sanitization_in_php(self):
        """Ensure esc_url() and esc_html() are used and extract() is removed."""
        with open(self.php_file, "r") as f:
            content = f.read()
        
        self.assertNotIn("extract(", content, "extract() should be removed to prevent XSS/variable pollution")
        self.assertIn("esc_url(", content, "esc_url() must be used for URL sanitization")
        self.assertIn("esc_html(", content, "esc_html() must be used for text sanitization")

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
