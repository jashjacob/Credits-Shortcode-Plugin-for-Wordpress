<?php
/**
 * How credits with a missing name or an unusable link render on the front end.
 *
 * A usable link is a non-empty sanitized URL other than a bare "#". Rules:
 * - usable link: an anchor, exactly as always (a blank name shows "Credit Link");
 * - name but no usable link: the same chip with the name as plain text, no anchor;
 * - no name and no usable link: nothing at all, so an untouched block never
 *   publishes a placeholder credit.
 * Intentional #fragment links are usable and keep rendering as links.
 */

final class IncompleteCreditsTest extends Credits_Integration_TestCase {

	/**
	 * Pull the anchor's href and text out of rendered output.
	 *
	 * @return array{href: string, text: string}
	 */
	private function anchor( string $html ): array {
		$this->assertMatchesRegularExpression( '/<a href="([^"]*)"[^>]*>([^<]*)<\/a>/', $html, 'Expected exactly one anchor in: ' . $html );
		preg_match( '/<a href="([^"]*)"[^>]*>([^<]*)<\/a>/', $html, $m );
		return array( 'href' => html_entity_decode( $m[1] ), 'text' => html_entity_decode( $m[2] ) );
	}

	/**
	 * The chip a name with no usable link becomes, with every default in place.
	 */
	private function unlinked_chip( string $name ): string {
		return '<ul class="credits wp-block-credits-shortcode credits-spacing-standard"><li class="credits">'
			. '<span class="cre_cate">Source</span>'
			. '<span class="cre_cate_link"><span class="cre_cate_text">' . $name . '</span></span>'
			. '</li></ul>';
	}

	/**
	 * Shortcode and block must agree for every input, so each case runs through both.
	 *
	 * @param array $attrs Block-style attributes.
	 * @return string[] Rendered HTML keyed by entry point.
	 */
	private function render_both( array $attrs ): array {
		$shortcode = '[credits';
		foreach ( $attrs as $key => $value ) {
			$shortcode .= ' ' . $key . '="' . $value . '"';
		}
		$shortcode .= ']';

		return array(
			'shortcode' => do_shortcode( $shortcode ),
			'block'     => $this->render_credit_block( $attrs ),
		);
	}

	public function test_untouched_block_renders_nothing(): void {
		// Inserting the block and saving without touching it stores no attributes at all.
		$this->assertSame( '', do_blocks( '<!-- wp:credits/shortcode /-->' ) );
		$this->assertSame( '', do_shortcode( '[credits]' ) );
		$this->assertSame( '', do_shortcode( '[credits][/credits]' ) );
	}

	public function test_styling_alone_does_not_make_a_credit(): void {
		$attrs = array( 'type' => 'via', 'spacing' => 'compact', 'badgeColor' => '#111111', 'className' => 'my-class' );

		$this->assertSame( '', $this->render_credit_block( $attrs ) );
	}

	public function test_no_name_and_no_usable_link_renders_nothing(): void {
		foreach ( array( '', '   ', '<b></b>', '<script>alert(1)</script>' ) as $name ) {
			foreach ( array( null, '', '   ', '#', 'javascript:alert(1)' ) as $link ) {
				$attrs = array( 'name' => $name );
				if ( null !== $link ) {
					$attrs['link'] = $link;
				}
				foreach ( $this->render_both( $attrs ) as $entry => $html ) {
					$this->assertSame( '', $html, "{$entry}: " . wp_json_encode( $attrs ) );
				}
			}
		}
	}

	public function test_name_without_a_usable_link_renders_the_chip_with_no_anchor(): void {
		foreach ( array( null, '', '   ', '#' ) as $link ) {
			$attrs = array( 'name' => 'Example' );
			if ( null !== $link ) {
				$attrs['link'] = $link;
			}
			foreach ( $this->render_both( $attrs ) as $entry => $html ) {
				$this->assertSame( $this->unlinked_chip( 'Example' ), $html, "{$entry}: " . wp_json_encode( $attrs ) );
			}
		}
	}

	public function test_unsafe_links_render_the_name_without_an_anchor(): void {
		foreach ( array( 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'vbscript:msgbox(1)', 'data:text/html;base64,PHNjcmlwdD4=' ) as $bad ) {
			foreach ( $this->render_both( array( 'name' => 'Example', 'link' => $bad ) ) as $entry => $html ) {
				$this->assertSame( $this->unlinked_chip( 'Example' ), $html, "{$entry}: {$bad}" );
				$this->assertStringNotContainsString( 'href', $html, "{$entry}: {$bad}" );
				$this->assertStringNotContainsStringIgnoringCase( 'script:', $html, "{$entry}: {$bad}" );
			}
		}
	}

	public function test_unlinked_names_are_still_escaped(): void {
		foreach ( $this->render_both( array( 'name' => 'Tom & Jerry <b>bold</b>' ) ) as $entry => $html ) {
			$this->assertSame( $this->unlinked_chip( 'Tom &amp; Jerry bold' ), $html, $entry );
		}
	}

	public function test_link_text_color_goes_on_the_text_span_when_there_is_no_link(): void {
		$span = '<span class="cre_cate_link" style="background-color: #222222"><span class="cre_cate_text" style="color: #00ff00">Example</span></span>';

		$this->assertStringContainsString(
			$span,
			$this->render_credit_block( array( 'name' => 'Example', 'linkColor' => '#222222', 'linkTextColor' => '#00ff00' ) )
		);
		$this->assertStringContainsString(
			$span,
			do_shortcode( '[credits link_color="#222222" link_text_color="#00ff00"]Example[/credits]' )
		);
	}

	public function test_site_default_link_text_color_applies_when_there_is_no_link(): void {
		$this->set_site_defaults( array( 'link_text_color' => '#0000aa' ) );

		foreach ( $this->render_both( array( 'name' => 'Example' ) ) as $entry => $html ) {
			$this->assertStringContainsString( '<span class="cre_cate_text" style="color: #0000aa">Example</span>', $html, $entry );
			$this->assertStringNotContainsString( '<a ', $html, $entry );
		}
	}

	public function test_unlinked_credit_ignores_the_new_tab_option(): void {
		foreach ( array( true, false ) as $new_tab ) {
			$html = $this->render_credit_block( array( 'name' => 'Example', 'newTab' => $new_tab ) );

			$this->assertSame( $this->unlinked_chip( 'Example' ), $html );
		}
	}

	public function test_unlinked_credit_keeps_the_block_wrapper_classes(): void {
		$html = $this->render_credit_block( array( 'name' => 'Example', 'className' => 'my-custom-class' ) );

		$this->assertMatchesRegularExpression( '/<ul class="[^"]*\bmy-custom-class\b[^"]*"/', $html );
		$this->assertSame( 1, substr_count( $html, 'wp-block-credits-shortcode' ) );
		$this->assertStringNotContainsString( '<a ', $html );
	}

	public function test_missing_or_blank_name_falls_back_to_credit_link_and_keeps_a_valid_link(): void {
		foreach ( array( '', '   ' ) as $name ) {
			foreach ( $this->render_both( array( 'name' => $name, 'link' => 'https://example.com/source' ) ) as $entry => $html ) {
				$anchor = $this->anchor( $html );
				$this->assertSame( 'https://example.com/source', $anchor['href'], $entry );
				$this->assertSame( 'Credit Link', $anchor['text'], $entry );
			}
		}
	}

	public function test_intentional_fragment_links_are_preserved(): void {
		foreach ( $this->render_both( array( 'name' => 'Example', 'link' => '#references' ) ) as $entry => $html ) {
			$this->assertSame( '#references', $this->anchor( $html )['href'], $entry );
		}
	}

	public function test_fragment_link_without_a_name_is_still_a_credit_link(): void {
		foreach ( $this->render_both( array( 'link' => '#references' ) ) as $entry => $html ) {
			$anchor = $this->anchor( $html );
			$this->assertSame( '#references', $anchor['href'], $entry );
			$this->assertSame( 'Credit Link', $anchor['text'], $entry );
		}
	}

	public function test_valid_links_are_preserved_across_supported_schemes(): void {
		$links = array(
			'https://example.com/a?x=1&y=2' => 'https://example.com/a?x=1&y=2',
			'http://example.com'            => 'http://example.com',
			'mailto:editor@example.com'     => 'mailto:editor@example.com',
			'/relative/path'                => '/relative/path',
			'example.com/no-scheme'         => 'http://example.com/no-scheme',
		);
		foreach ( $links as $input => $expected ) {
			foreach ( $this->render_both( array( 'name' => 'Example', 'link' => $input ) ) as $entry => $html ) {
				$this->assertSame( $expected, $this->anchor( $html )['href'], "{$entry}: {$input}" );
			}
		}
	}

	public function test_block_and_shortcode_agree_for_incomplete_credits(): void {
		foreach (
			array(
				array(),
				array( 'name' => 'Only a name' ),
				array( 'link' => 'https://example.com/only-a-link' ),
				array( 'name' => 'Bad link', 'link' => 'javascript:alert(1)' ),
			) as $attrs
		) {
			$rendered = $this->render_both( $attrs );
			$this->assertSame( $rendered['shortcode'], $rendered['block'], wp_json_encode( $attrs ) );
		}
	}
}
