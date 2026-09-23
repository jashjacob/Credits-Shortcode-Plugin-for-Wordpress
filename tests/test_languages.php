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
}
