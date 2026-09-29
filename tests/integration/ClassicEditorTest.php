<?php
/**
 * Classic Editor (TinyMCE) dialog translations: the strings reach TinyMCE through
 * WordPress's own translation pipeline. The dialog itself is covered by tests/js.
 */

final class ClassicEditorTest extends Credits_Integration_TestCase {

	protected function tearDown(): void {
		remove_all_filters( 'gettext' );
		parent::tearDown();
	}

	public function test_the_dictionary_file_is_registered_for_tinymce(): void {
		wp_set_current_user( $this->create_user( 'editor' ) );
		remove_all_filters( 'mce_external_languages' );

		credits_buttons();

		$languages = apply_filters( 'mce_external_languages', array() );
		$this->assertArrayHasKey( 'credits', $languages );
		$this->assertFileExists( $languages['credits'] );
	}

	/**
	 * Run the dictionary file the way _WP_Editors does and return the JS it prints.
	 */
	private function tinymce_dictionary_output(): string {
		// WordPress loads the editor class lazily, and always before including this file.
		require_once ABSPATH . WPINC . '/class-wp-editor.php';
		_WP_Editors::$mce_locale = 'en';

		return ( static function ( $path ) {
			include $path;
			return $strings;
		} )( credits_add_button_languages( array() )['credits'] );
	}

	public function test_the_dictionary_is_valid_javascript_keyed_by_the_english_strings(): void {
		$output = $this->tinymce_dictionary_output();

		$this->assertSame( 1, preg_match( '/^tinyMCE\.addI18n\("en", (\{.*\})\);$/s', $output, $matches ), $output );
		$dictionary = json_decode( $matches[1], true );
		$this->assertIsArray( $dictionary, 'The dictionary must be valid JSON' );
		$this->assertSame( 'Add Credits', $dictionary['Add Credits'] );
	}

	public function test_the_dictionary_values_go_through_wordpress_translation(): void {
		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) {
				return 'credits-shortcode' === $domain ? '«' . $text . '»' : $translation;
			},
			10,
			3
		);

		preg_match( '/(\{.*\})\);$/s', $this->tinymce_dictionary_output(), $matches );
		$dictionary = json_decode( $matches[1], true );

		$this->assertNotEmpty( $dictionary );
		foreach ( $dictionary as $english => $translated ) {
			$this->assertSame( '«' . $english . '»', $translated, "Translated value for \"{$english}\"" );
		}
	}
}
