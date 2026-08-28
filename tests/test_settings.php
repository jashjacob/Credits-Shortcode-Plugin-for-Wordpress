<?php
/**
 * Settings tests: site-wide defaults stored in wp_options must feed the
 * renderer only when no explicit attribute overrides them.
 */

use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['credits_test_options'] = array();
	}

	private function render( array $atts = array() ) {
		return credits_print_shortcode( $atts );
	}

	public function test_site_default_colors_apply_when_attribute_missing(): void {
		update_option(
			'credits_shortcode_settings',
			array(
				'type'            => 'source',
				'badge_color'     => '#112233',
				'link_color'      => '#445566',
				'link_text_color' => '#778899',
			)
		);

		$out = $this->render( array( 'name' => 'x' ) );

		$this->assertMatchesRegularExpression( '/background-color:\s*#112233/', $out );
		$this->assertMatchesRegularExpression( '/background-color:\s*#445566/', $out );
		$this->assertMatchesRegularExpression( '/<a [^>]*style="[^"]*color:\s*#778899/', $out );
	}

	public function test_explicit_attributes_override_site_defaults(): void {
		update_option(
			'credits_shortcode_settings',
			array(
				'type'            => 'source',
				'badge_color'     => '#112233',
				'link_color'      => '#445566',
				'link_text_color' => '#778899',
			)
		);

		$out = $this->render(
			array(
				'name'       => 'x',
				'badgeColor' => '#ff0000',
			)
		);

		$this->assertMatchesRegularExpression( '/background-color:\s*#ff0000/', $out );
		$this->assertDoesNotMatchRegularExpression( '/background-color:\s*#112233/', $out );
	}

	public function test_invalid_stored_colors_are_ignored(): void {
		update_option(
			'credits_shortcode_settings',
			array(
				'type'            => 'source',
				'badge_color'     => 'javascript:alert(1)',
				'link_color'      => 'red',
				'link_text_color' => '#12345',
			)
		);

		$out = $this->render( array( 'name' => 'x' ) );

		$this->assertStringNotContainsString( 'style=', $out );
	}

	public function test_stored_default_type_applies_to_shortcodes_without_type(): void {
		update_option(
			'credits_shortcode_settings',
			array(
				'type'            => 'via',
				'badge_color'     => '',
				'link_color'      => '',
				'link_text_color' => '',
			)
		);

		$out = $this->render( array( 'name' => 'x' ) );

		$this->assertStringContainsString( '>Via</span>', $out );
	}

	public function test_sanitize_settings_keeps_valid_and_drops_invalid_values(): void {
		$clean = credits_sanitize_settings(
			array(
				'type'            => 'VIA',
				'badge_color'     => '#AABBCC',
				'link_color'      => '<script>alert(1)</script>',
				'link_text_color' => 'not-a-color',
			)
		);

		$this->assertSame( 'via', $clean['type'] );
		$this->assertSame( '#aabbcc', strtolower( $clean['badge_color'] ) );
		$this->assertSame( '', $clean['link_color'] );
		$this->assertSame( '', $clean['link_text_color'] );
	}

	public function test_sanitize_settings_whitelists_type(): void {
		foreach ( array( 'garbage', '', null, array() ) as $bad ) {
			$clean = credits_sanitize_settings( array( 'type' => $bad ) );
			$this->assertSame( 'source', $clean['type'], "Type '" . var_export( $bad, true ) . "' must fall back to source" );
		}
	}

	public function test_non_array_stored_option_is_ignored(): void {
		update_option( 'credits_shortcode_settings', 'nonsense' );

		$out = $this->render( array( 'name' => 'x' ) );

		$this->assertStringNotContainsString( 'style=', $out );
	}

	public function test_block_registration_localizes_current_settings_for_editor_preview(): void {
		update_option(
			'credits_shortcode_settings',
			array(
				'type'            => 'source',
				'badge_color'     => '#ABCDEF',
				'link_color'      => '',
				'link_text_color' => '',
			)
		);
		$GLOBALS['credits_test_localized_scripts'] = array();

		credits_register_gutenberg_block();

		$this->assertArrayHasKey( 'credits-block-js', $GLOBALS['credits_test_localized_scripts'] );
		$data = $GLOBALS['credits_test_localized_scripts']['credits-block-js']['creditsShortcodeSettings'];
		$this->assertSame( '#ABCDEF', $data['badge_color'] );
	}
}
