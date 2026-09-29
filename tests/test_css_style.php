<?php
/**
 * Stylesheet structure guards (spacing tokens, scoped selectors, !important allow-list,
 * RTL, zoom, narrow screens, focus, reduced motion, readable link text).
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

	public function test_stylesheet_important_is_limited_to_declarations_a_compatibility_check_needed(): void {
		$declarations = preg_replace( '#/\*.*?\*/#s', '', $this->css() );
		preg_match_all( '/([a-z-]+)\s*:[^;{}]*!important/', $declarations, $matches );
		$this->assertSame(
			array( 'margin-block', 'max-width', 'outline' ),
			$matches[1],
			'Wrapper margin-block/max-width beat Twenty Twenty-One column rules; the focus ring beats theme a:focus rules. Prove a new one is needed before adding it.'
		);
	}

	public function test_stylesheet_uses_logical_properties_so_rtl_mirrors(): void {
		$css = $this->css();
		$this->assertDoesNotMatchRegularExpression( '/text-align:\s*(left|right)|(margin|padding)-(left|right)|border-(top|bottom)-(left|right)-radius/', $css );
		$this->assertDoesNotMatchRegularExpression( '/border-radius:\s*[^;\s]+\s+[^;\s]/', $css, 'Multi-value border-radius is physical; use the logical corner properties' );
		$this->assertStringContainsString( 'border-start-start-radius', $css );
		$this->assertStringContainsString( 'margin-inline: auto', $css );
	}

	public function test_stylesheet_sizes_text_and_padding_in_rem(): void {
		$css = $this->css();
		$this->assertStringContainsString( '--credits-font-size: 0.8125rem', $css );
		$this->assertDoesNotMatchRegularExpression( '/(font-size|padding):\s*[\d.]+px/', $css, 'px ignores the reader\'s font-size preference' );
	}

	public function test_stylesheet_lets_long_names_wrap_instead_of_overflowing(): void {
		$css = $this->css();
		$this->assertStringContainsString( 'overflow-wrap: anywhere', $css );
		$this->assertMatchesRegularExpression( '/\.credits-inner\)\s*\{[^}]*max-width:\s*100%/', $css );
		$this->assertMatchesRegularExpression( '/\.cre_cate_link\s*\{[^}]*min-width:\s*0/', $css );
	}

	public function test_stylesheet_has_visible_focus_and_respects_reduced_motion(): void {
		$css = $this->css();
		$this->assertMatchesRegularExpression( '/a:focus-visible\s*\{[^}]*outline:\s*2px solid currentColor/', $css );
		$this->assertMatchesRegularExpression( '/@media \(prefers-reduced-motion: reduce\)\s*\{.*transition:\s*none.*:hover\s*\{\s*transform:\s*none/s', $css );
	}

	public function test_stylesheet_keeps_link_text_readable_and_styles_unlinked_names_like_links(): void {
		$css = $this->css();
		$this->assertMatchesRegularExpression( '/\.cre_cate_link\s*\{[^}]*color:\s*var\(--credits-link-text\)/', $css, 'Link text must not depend on the theme text color' );
		$this->assertMatchesRegularExpression( '/\.cre_cate_link\[style\*="background"\]\s*\{\s*color:\s*inherit/', $css, 'A custom link background keeps the earlier theme-following text color' );
		$this->assertMatchesRegularExpression( '/\.cre_cate_link :is\(a, \.cre_cate_text\)\s*\{[^}]*font-weight:\s*500/', $css );
		$this->assertStringContainsString( 'span:not(.cre_cate_text)', $css, 'The unlinked name span sits inside the padded link area and must not be padded again' );
	}

	private function css(): string {
		$css = file_get_contents( dirname( __DIR__ ) . '/css/style.css' );
		$this->assertIsString( $css );
		return $css;
	}
}
