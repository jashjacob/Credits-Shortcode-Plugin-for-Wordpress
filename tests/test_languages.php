<?php
/**
 * Translation bundle layout (Domain Path header).
 */

use PHPUnit\Framework\TestCase;

final class LanguagesTest extends TestCase {

	public function test_languages_directory_exists_for_textdomain(): void {
		$dir = dirname( __DIR__ ) . '/languages';
		$this->assertDirectoryExists( $dir, 'languages/ must exist when Domain Path is set in the plugin header' );
		$pot = $dir . '/credits-shortcode.pot';
		$this->assertFileExists( $pot, 'Provide at least a POT template for translators' );
		$contents = file_get_contents( $pot );
		$this->assertIsString( $contents );
		$this->assertGreaterThan(
			5,
			substr_count( $contents, 'msgid "' ),
			'POT should list plugin strings (run scripts/generate-pot.sh after copy changes)'
		);
	}

	/**
	 * Decode the msgid entries of the POT file, including multi-line ones.
	 *
	 * @return string[]
	 */
	private function pot_msgids(): array {
		$msgids  = array();
		$current = null;
		foreach ( file( dirname( __DIR__ ) . '/languages/credits-shortcode.pot', FILE_IGNORE_NEW_LINES ) as $line ) {
			if ( 0 === strpos( $line, 'msgid ' ) ) {
				$current = stripcslashes( substr( trim( substr( $line, 6 ) ), 1, -1 ) );
			} elseif ( null !== $current && 0 === strpos( $line, '"' ) ) {
				$current .= stripcslashes( substr( $line, 1, -1 ) );
			} elseif ( null !== $current ) {
				$msgids[] = $current;
				$current  = null;
			}
		}
		return array_filter( $msgids, 'strlen' );
	}

	/**
	 * Literal strings passed to a translation function with the plugin text domain.
	 *
	 * @param string $file    Path relative to the plugin root.
	 * @param string $pattern Regex with the string in group 1.
	 * @return string[]
	 */
	private function translatable_strings( string $file, string $pattern ): array {
		preg_match_all( $pattern, file_get_contents( dirname( __DIR__ ) . '/' . $file ), $matches );
		return array_map(
			static function ( $string ) {
				return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $string );
			},
			$matches[1]
		);
	}

	private const PHP_CALL = '/\b(?:__|esc_html__|esc_attr__)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'credits-shortcode\'\s*\)/';
	private const JS_CALL  = '/(?<![.\w])__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'credits-shortcode\'\s*\)/';

	public function test_every_translatable_string_in_the_source_is_in_the_pot(): void {
		$pot     = $this->pot_msgids();
		$sources = array(
			'credits_shortcode.php'   => self::PHP_CALL,
			'tinymce-i18n.php'        => self::PHP_CALL,
			'js/credits-block.js'     => self::JS_CALL,
		);

		foreach ( $sources as $file => $pattern ) {
			$strings = $this->translatable_strings( $file, $pattern );
			$this->assertNotEmpty( $strings, "{$file} should contain translatable strings" );
			foreach ( $strings as $string ) {
				$this->assertContains( $string, $pot, "\"{$string}\" from {$file} is missing from languages/credits-shortcode.pot (run scripts/generate-pot.sh)" );
			}
		}
	}

	public function test_block_editor_strings_use_a_literal_text_domain_the_extractor_can_find(): void {
		// A variable domain, as in __( 'x', textdomain ), is silently skipped by make-pot, which
		// leaves the string untranslatable. Every __() call in the editor script must be extractable.
		$source = file_get_contents( dirname( __DIR__ ) . '/js/credits-block.js' );

		$all_calls = preg_match_all( '/(?<![.\w])__\(/', $source );
		$literal   = count( $this->translatable_strings( 'js/credits-block.js', self::JS_CALL ) );

		$this->assertGreaterThan( 20, $literal, 'The block editor should have many translatable strings' );
		$this->assertSame( $all_calls, $literal, 'Every __() call in js/credits-block.js must be __( \'text\', \'credits-shortcode\' )' );
	}

	public function test_the_pot_contains_the_block_editor_and_classic_editor_strings(): void {
		$pot = $this->pot_msgids();
		foreach ( array( 'Credit Settings', 'Add credit name', 'Reset colors to site default', 'Add Credits', 'Insert credit', 'Enter a link.' ) as $string ) {
			$this->assertContains( $string, $pot, $string );
		}
	}
}
