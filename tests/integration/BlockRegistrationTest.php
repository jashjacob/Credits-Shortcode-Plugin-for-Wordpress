<?php
/**
 * Block registration and editor script localization against real WordPress.
 */

final class BlockRegistrationTest extends Credits_Integration_TestCase {

	private function block_type(): WP_Block_Type {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'credits/shortcode' );
		$this->assertNotNull( $type, 'credits/shortcode must be registered' );
		return $type;
	}

	private function block_json(): array {
		$data = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/block.json' ), true );
		$this->assertIsArray( $data );
		return $data;
	}

	private function editor_script_source(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/js/credits-block.js' );
	}

	/**
	 * Read the creditsShortcodeSettings object WordPress will print before the script.
	 */
	private function localized_settings( string $handle = 'credits-block-js' ): array {
		$data = wp_scripts()->get_data( $handle, 'data' );
		$this->assertIsString( $data, 'Editor script must have localized data' );
		$this->assertSame( 1, preg_match( '/var creditsShortcodeSettings = (\{.*\});/s', $data, $matches ), 'creditsShortcodeSettings must be localized' );
		$decoded = json_decode( $matches[1], true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}

	public function test_block_is_registered_with_the_render_callback(): void {
		$this->assertSame( 'credits_render_block', $this->block_type()->render_callback );
	}

	public function test_php_and_javascript_register_the_same_block_api_version(): void {
		$this->assertSame( 1, preg_match( '/apiVersion:\s*(\d+)/', $this->editor_script_source(), $matches ), 'js/credits-block.js must declare apiVersion' );

		$php_side  = $this->block_type()->api_version;
		$json_side = $this->block_json()['apiVersion'];
		$js_side   = (int) $matches[1];

		$this->assertSame( 3, $php_side, 'WordPress must register the block with API version 3' );
		$this->assertSame( $php_side, $json_side, 'block.json and the registered block type must agree' );
		$this->assertSame( $php_side, $js_side, 'The editor script must register the same API version as PHP' );
	}

	public function test_registered_attributes_match_block_json_and_editor_script(): void {
		$registered = $this->block_type()->attributes;
		$source     = $this->editor_script_source();

		foreach ( $this->block_json()['attributes'] as $name => $definition ) {
			$this->assertArrayHasKey( $name, $registered, "Attribute {$name} must be registered" );
			$this->assertSame( $definition['default'], $registered[ $name ]['default'], "Default for {$name}" );
			$this->assertSame( 1, preg_match( '/\b' . preg_quote( $name, '/' ) . ':\s*\{/', $source ), "Editor script must define attribute {$name}" );
		}
	}

	public function test_block_supports_the_custom_class_attribute_the_renderer_relies_on(): void {
		$this->assertArrayHasKey( 'className', $this->block_type()->attributes );
	}

	public function test_editor_script_and_styles_are_registered_and_attached_to_the_block(): void {
		$type = $this->block_type();

		$this->assertTrue( wp_script_is( 'credits-block-js', 'registered' ) );
		$this->assertContains( 'credits-block-js', $type->editor_script_handles );
		$this->assertContains( 'credits-shortcode', $type->style_handles );
		$this->assertContains( 'credits-shortcode', $type->editor_style_handles );
	}

	public function test_editor_script_receives_settings_and_no_api_version(): void {
		$localized = $this->localized_settings();

		$this->assertSame(
			array( 'type', 'spacing', 'badge_color', 'link_color', 'link_text_color' ),
			array_keys( $localized )
		);
		$this->assertArrayNotHasKey( 'block_api_version', $localized, 'API version is declared in block.json and JS, never localized' );
	}

	public function test_site_defaults_reach_the_editor_script(): void {
		$defaults = array(
			'type'            => 'via',
			'spacing'         => 'spacious',
			'badge_color'     => '#112233',
			'link_color'      => '#445566',
			'link_text_color' => '#778899',
		);

		$original_scripts = $GLOBALS['wp_scripts'] ?? null;
		unregister_block_type( 'credits/shortcode' );

		try {
			// A fresh script registry lets the plugin's real registration run again with new defaults.
			$GLOBALS['wp_scripts'] = new WP_Scripts();
			$this->set_site_defaults( $defaults );
			credits_register_gutenberg_block();

			$this->assertSame( $defaults, $this->localized_settings() );
		} finally {
			$GLOBALS['wp_scripts'] = $original_scripts;
			unregister_block_type( 'credits/shortcode' );
			register_block_type_from_metadata( dirname( __DIR__, 2 ) . '/block.json', array( 'render_callback' => 'credits_render_block' ) );
		}
	}
}
