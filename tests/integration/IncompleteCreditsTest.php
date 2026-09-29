<?php
/**
 * How credits with a missing name or an unusable link render on the front end.
 *
 * These pin the current behavior so a change to it is deliberate: the tests that
 * must be edited are the ones describing what an incomplete credit becomes.
 * Links that are valid today (including intentional #fragment links) must keep
 * rendering exactly as they do now.
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

	public function test_untouched_block_renders_a_placeholder_credit_with_a_dead_link(): void {
		// Inserting the block and saving without touching it stores no attributes at all.
		$html = do_blocks( '<!-- wp:credits/shortcode /-->' );

		$anchor = $this->anchor( $html );
		$this->assertSame( '#', $anchor['href'] );
		$this->assertSame( 'Credit Link', $anchor['text'] );
		$this->assertStringContainsString( 'target="_blank"', $html );
	}

	public function test_missing_link_falls_back_to_a_hash(): void {
		foreach ( array( array( 'name' => 'Example' ), array( 'name' => 'Example', 'link' => '' ), array( 'name' => 'Example', 'link' => '#' ) ) as $attrs ) {
			foreach ( $this->render_both( $attrs ) as $entry => $html ) {
				$anchor = $this->anchor( $html );
				$this->assertSame( '#', $anchor['href'], "{$entry}: " . wp_json_encode( $attrs ) );
				$this->assertSame( 'Example', $anchor['text'], "{$entry}: " . wp_json_encode( $attrs ) );
			}
		}
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

	public function test_unsafe_links_are_replaced_with_a_hash(): void {
		foreach ( array( 'javascript:alert(1)', 'vbscript:msgbox(1)', 'data:text/html;base64,PHNjcmlwdD4=' ) as $bad ) {
			foreach ( $this->render_both( array( 'name' => 'Example', 'link' => $bad ) ) as $entry => $html ) {
				$this->assertSame( '#', $this->anchor( $html )['href'], "{$entry}: {$bad}" );
			}
		}
	}

	public function test_intentional_fragment_links_are_preserved(): void {
		foreach ( $this->render_both( array( 'name' => 'Example', 'link' => '#references' ) ) as $entry => $html ) {
			$this->assertSame( '#references', $this->anchor( $html )['href'], $entry );
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
