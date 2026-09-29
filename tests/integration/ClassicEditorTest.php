<?php
/**
 * Classic Editor (TinyMCE) integration: who gets the button, and that its
 * dialog strings reach TinyMCE through WordPress's translation pipeline.
 * The dialog behavior itself is covered by tests/js and .cursor/test-classic-editor.mjs.
 */

final class ClassicEditorTest extends Credits_Integration_TestCase {

	private const FILTERS = array( 'mce_external_plugins', 'mce_external_languages', 'mce_buttons' );

	protected function setUp(): void {
		parent::setUp();
		$this->remove_button_filters();
	}

	protected function tearDown(): void {
		$this->remove_button_filters();
		remove_all_filters( 'gettext' );
		parent::tearDown();
	}

	private function remove_button_filters(): void {
		remove_filter( 'mce_external_plugins', 'credits_add_buttons' );
		remove_filter( 'mce_external_languages', 'credits_add_button_languages' );
		remove_filter( 'mce_buttons', 'credits_register_buttons' );
	}

	private function has_button_filters(): bool {
		return false !== has_filter( 'mce_external_plugins', 'credits_add_buttons' )
			&& false !== has_filter( 'mce_external_languages', 'credits_add_button_languages' )
			&& false !== has_filter( 'mce_buttons', 'credits_register_buttons' );
	}

	public function test_the_button_and_its_translations_are_added_for_users_who_can_edit_posts(): void {
		foreach ( array( 'administrator', 'editor', 'author', 'contributor' ) as $role ) {
			$this->remove_button_filters();
			wp_set_current_user( $this->create_user( $role ) );

			credits_buttons();

			$this->assertTrue( $this->has_button_filters(), "{$role} should get the Classic Editor button" );
		}
	}

	public function test_nothing_is_added_for_users_who_cannot_edit_posts(): void {
		wp_set_current_user( $this->create_user( 'subscriber' ) );

		credits_buttons();

		$this->assertFalse( has_filter( 'mce_external_plugins', 'credits_add_buttons' ) );
		$this->assertFalse( has_filter( 'mce_external_languages', 'credits_add_button_languages' ) );
		$this->assertFalse( has_filter( 'mce_buttons', 'credits_register_buttons' ) );
	}

	public function test_the_visual_editor_being_turned_off_removes_the_button(): void {
		$user_id = $this->create_user( 'editor' );
		update_user_option( $user_id, 'rich_editing', 'false', true );
		wp_set_current_user( $user_id );

		credits_buttons();

		$this->assertFalse( $this->has_button_filters() );
		$this->assertFalse( has_filter( 'mce_external_languages', 'credits_add_button_languages' ) );
	}

	public function test_the_plugin_script_and_button_are_registered_with_tinymce(): void {
		$plugins = credits_add_buttons( array() );
		$this->assertArrayHasKey( 'credits', $plugins );
		$this->assertStringContainsString( 'credits_shortcode_plugin.js', $plugins['credits'] );
		$this->assertStringContainsString( 'ver=' . CREDITS_SHORTCODE_VERSION, $plugins['credits'] );

		$this->assertContains( 'addcredits', credits_register_buttons( array( 'bold' ) ) );
	}

	/**
	 * Run the dictionary file the way _WP_Editors does and return its $strings.
	 */
	private function tinymce_dictionary_output(): string {
		$languages = credits_add_button_languages( array() );
		$this->assertArrayHasKey( 'credits', $languages );
		$this->assertFileExists( $languages['credits'] );

		// WordPress loads the editor class lazily, and always before including this file.
		require_once ABSPATH . WPINC . '/class-wp-editor.php';
		_WP_Editors::$mce_locale = 'en';

		return ( static function ( $path ) {
			include $path;
			return $strings;
		} )( $languages['credits'] );
	}

	public function test_the_tinymce_dictionary_is_valid_javascript_keyed_by_the_english_strings(): void {
		$output = $this->tinymce_dictionary_output();

		$this->assertSame( 1, preg_match( '/^tinyMCE\.addI18n\("en", (\{.*\})\);$/s', $output, $matches ), $output );
		$dictionary = json_decode( $matches[1], true );
		$this->assertIsArray( $dictionary, 'The dictionary must be valid JSON' );
		$this->assertSame( 'Add Credits', $dictionary['Add Credits'] );
		$this->assertSame( 'Open in new tab', $dictionary['Open in new tab'] );
	}

	public function test_the_tinymce_dictionary_values_go_through_wordpress_translation(): void {
		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) {
				return 'credits-shortcode' === $domain ? '«' . $text . '»' : $translation;
			},
			10,
			3
		);

		$output = $this->tinymce_dictionary_output();
		preg_match( '/(\{.*\})\);$/s', $output, $matches );
		$dictionary = json_decode( $matches[1], true );

		$this->assertNotEmpty( $dictionary );
		foreach ( $dictionary as $english => $translated ) {
			$this->assertSame( '«' . $english . '»', $translated, "Translated value for \"{$english}\"" );
		}
	}
}
