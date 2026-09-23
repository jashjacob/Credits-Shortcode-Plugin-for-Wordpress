<?php
/**
 * Translation bundle layout (Domain Path header).
 */

use PHPUnit\Framework\TestCase;

final class LanguagesTest extends TestCase {

	public function test_languages_directory_exists_for_textdomain(): void {
		$dir = dirname( __DIR__ ) . '/languages';
		$this->assertDirectoryExists( $dir, 'languages/ must exist when Domain Path is set in the plugin header' );
		$this->assertFileExists(
			$dir . '/credits-shortcode.pot',
			'Provide at least a POT template for translators'
		);
	}
}
