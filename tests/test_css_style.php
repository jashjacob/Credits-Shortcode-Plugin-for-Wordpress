<?php
/**
 * Stylesheet structure guards (spacing tokens, scoped selectors, !important budget).
 */

use PHPUnit\Framework\TestCase;

final class CssStyleTest extends TestCase {

	public function test_stylesheet_uses_spacing_tokens_and_scoped_selectors(): void {
		$css = file_get_contents( dirname( __DIR__ ) . '/css/style.css' );
		$this->assertIsString( $css );
		$this->assertStringContainsString( '--credits-margin-block', $css );
		$this->assertStringContainsString( '.credits-spacing-compact', $css );
		$this->assertStringContainsString( ':is(ul.credits, div.credits) .cre_cate', $css );
		$this->assertDoesNotMatchRegularExpression(
			'/^\s*\.cre_cate\s*\{/m',
			$css,
			'Badge styles must be scoped under .credits wrappers'
		);
	}

	public function test_stylesheet_important_count_stays_within_budget(): void {
		$css     = file_get_contents( dirname( __DIR__ ) . '/css/style.css' );
		$count   = substr_count( $css, '!important' );
		$budget  = 20;
		$this->assertLessThanOrEqual(
			$budget,
			$count,
			"Keep !important for theme list/flow overrides only (found {$count}, budget {$budget})"
		);
	}
}
