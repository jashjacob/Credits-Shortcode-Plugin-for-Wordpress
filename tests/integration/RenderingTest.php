<?php
/**
 * Shortcode and block rendering through do_shortcode(), do_blocks() and the_content.
 */

final class RenderingTest extends Credits_Integration_TestCase {

	/** Attribute payloads that must never produce executable markup. */
	public function hostile_attribute_provider(): array {
		return array(
			'javascript link'        => array( array( 'name' => 'x', 'link' => 'javascript:alert(1)' ) ),
			'entity encoded scheme'  => array( array( 'name' => 'x', 'link' => '&#106;avascript:alert(1)' ) ),
			'data uri'               => array( array( 'name' => 'x', 'link' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==' ) ),
			'quote breakout link'    => array( array( 'name' => 'x', 'link' => '" onmouseover="alert(1)' ) ),
			'script in name'         => array( array( 'name' => '<script>alert(1)</script>' ) ),
			'img onerror in name'    => array( array( 'name' => '<img src=x onerror=alert(1)>' ) ),
			'quote breakout name'    => array( array( 'name' => '"><svg onload=alert(1)>' ) ),
			'css injection colors'   => array( array( 'name' => 'x', 'badgeColor' => 'red;background:url(javascript:alert(1))', 'linkColor' => '#fff;position:fixed', 'linkTextColor' => 'expression(alert(1))' ) ),
			'quote breakout class'   => array( array( 'name' => 'x', 'className' => 'ok" onmouseover="alert(1)' ) ),
			'tag breakout in class'  => array( array( 'name' => 'x', 'className' => '"><script>alert(1)</script>' ) ),
		);
	}

	private function assertSafeMarkup( string $html ): void {
		$this->assertNotSame( '', $html );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $html );
		$this->assertStringNotContainsStringIgnoringCase( '<svg', $html );
		$this->assertStringNotContainsStringIgnoringCase( '<img', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $html );
		$this->assertDoesNotMatchRegularExpression( '/<[a-z][^>]*[\s"\']on[a-z]+\s*=/i', $html, 'No event handler attribute may exist' );
		$this->assertDoesNotMatchRegularExpression( '/style="[^"]*(url|expression|position)/i', $html, 'No injected CSS may exist' );
	}

	/* ---------------------------------------------------------------------
	 * do_shortcode()
	 * ------------------------------------------------------------------ */

	public function test_shortcode_renders_a_credit_with_enclosed_name(): void {
		$html = do_shortcode( '[credits link="https://example.com/a?x=1&y=2" type="via"]Example Name[/credits]' );

		$this->assertStringContainsString( '>Via</span>', $html );
		$this->assertStringContainsString( '>Example Name</a>', $html );
		// kses writes the ampersand as &amp; or &#038; depending on the WordPress version.
		$this->assertMatchesRegularExpression( '/href="https:\/\/example\.com\/a\?x=1(&amp;|&#038;)y=2"/', $html );
		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
	}

	public function test_shortcode_saved_by_the_classic_editor_keeps_the_query_string_intact(): void {
		// TinyMCE serializes "&" as "&amp;" and the dialog percent-encodes "[]", so this is
		// what reaches do_shortcode(). "&copy=2" must not turn into a copyright sign.
		$html = do_shortcode( '[credits link="https://example.com/a?x=1&amp;copy=2&amp;b%5B%5D=3" type="via"]Tom &amp; Jerry[/credits]' );

		preg_match( '/<a href="([^"]*)"[^>]*>([^<]*)<\/a>/', $html, $m );
		$this->assertSame( 'https://example.com/a?x=1&copy=2&b%5B%5D=3', html_entity_decode( $m[1] ) );
		$this->assertSame( 'Tom & Jerry', html_entity_decode( $m[2] ) );
	}

	public function test_shortcode_accepts_the_name_as_an_attribute(): void {
		$html = do_shortcode( '[credits name="Attribute Name" link="https://example.com/"]' );

		$this->assertStringContainsString( '>Attribute Name</a>', $html );
	}

	public function test_shortcode_output_has_no_block_wrapper_classes(): void {
		$this->render_credit_block( array( 'name' => 'Block', 'className' => 'from-block' ) );
		$html = do_shortcode( '[credits link="https://example.com/"]Plain[/credits]' );

		$this->assertStringNotContainsString( 'from-block', $html );
	}

	/* ---------------------------------------------------------------------
	 * do_blocks() / render_block()
	 * ------------------------------------------------------------------ */

	public function test_block_renders_every_attribute(): void {
		$html = $this->render_credit_block(
			array(
				'name'          => 'Block Name',
				'link'          => 'https://example.com/post',
				'type'          => 'via',
				'spacing'       => 'compact',
				'badgeColor'    => '#111111',
				'linkColor'     => '#222222',
				'linkTextColor' => '#333333',
			)
		);

		$this->assertStringContainsString( '>Via</span>', $html );
		$this->assertStringContainsString( '>Block Name</a>', $html );
		$this->assertStringContainsString( 'href="https://example.com/post"', $html );
		$this->assertStringContainsString( 'credits-spacing-compact', $html );
		$this->assertStringContainsString( 'background-color: #111111', $html );
		$this->assertStringContainsString( 'background-color: #222222', $html );
		$this->assertStringContainsString( 'color: #333333', $html );
	}

	public function test_block_preserves_a_custom_class_without_duplicating_the_default_class(): void {
		$html = $this->render_credit_block( array( 'name' => 'x', 'className' => 'my-custom-class' ) );

		$this->assertMatchesRegularExpression( '/<ul class="[^"]*\bmy-custom-class\b[^"]*"/', $html );
		$this->assertSame( 1, substr_count( $html, 'wp-block-credits-shortcode' ) );
	}

	public function test_block_preserves_several_custom_classes(): void {
		$html = $this->render_credit_block( array( 'name' => 'x', 'className' => 'first-class  second-class' ) );

		$this->assertStringContainsString( 'first-class', $html );
		$this->assertStringContainsString( 'second-class', $html );
	}

	public function test_block_without_a_custom_class_matches_the_shortcode(): void {
		$attrs = array( 'name' => 'Same', 'link' => 'https://example.com/', 'type' => 'via' );

		$this->assertSame(
			do_shortcode( '[credits link="https://example.com/" type="via"]Same[/credits]' ),
			$this->render_credit_block( $attrs )
		);
	}

	/* ---------------------------------------------------------------------
	 * Open in new tab
	 * ------------------------------------------------------------------ */

	public function test_credits_open_in_a_new_tab_unless_told_otherwise(): void {
		// A block saved before the option existed has no newTab attribute at all.
		foreach (
			array(
				do_blocks( '<!-- wp:credits/shortcode {"name":"Old","link":"https://example.com/"} /-->' ),
				do_shortcode( '[credits link="https://example.com/"]Old[/credits]' ),
			) as $html
		) {
			$this->assertStringContainsString( 'target="_blank"', $html );
			$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
		}
	}

	public function test_block_can_open_in_the_same_tab(): void {
		$html = $this->render_credit_block( array( 'name' => 'Same tab', 'link' => 'https://example.com/', 'newTab' => false ) );

		$this->assertStringContainsString( 'href="https://example.com/"', $html );
		$this->assertStringNotContainsString( 'target=', $html );
		$this->assertStringNotContainsString( 'rel=', $html );
	}

	public function test_shortcode_can_open_in_the_same_tab_with_either_spelling(): void {
		foreach ( array( 'newtab', 'new_tab', 'newTab' ) as $attribute ) {
			$html = do_shortcode( '[credits link="https://example.com/" ' . $attribute . '="false"]Same tab[/credits]' );

			$this->assertStringContainsString( 'href="https://example.com/"', $html, $attribute );
			$this->assertStringNotContainsString( 'target=', $html, $attribute );
		}
	}

	public function test_shortcode_and_block_agree_on_link_opening(): void {
		$this->assertSame(
			do_shortcode( '[credits link="https://example.com/" newtab="false"]Same[/credits]' ),
			$this->render_credit_block( array( 'name' => 'Same', 'link' => 'https://example.com/', 'newTab' => false ) )
		);
	}

	/* ---------------------------------------------------------------------
	 * Site defaults and overrides
	 * ------------------------------------------------------------------ */

	private function site_defaults(): array {
		return array(
			'type'            => 'via',
			'spacing'         => 'spacious',
			'badge_color'     => '#aa0000',
			'link_color'      => '#00aa00',
			'link_text_color' => '#0000aa',
		);
	}

	public function test_built_in_defaults_apply_when_nothing_is_stored(): void {
		$this->assertFalse( get_option( 'credits_shortcode_settings' ) );
		$this->assertSame(
			array( 'type' => 'source', 'spacing' => 'standard', 'badge_color' => '', 'link_color' => '', 'link_text_color' => '' ),
			credits_get_settings()
		);

		foreach ( array( $this->render_credit_block( array( 'name' => 'x' ) ), do_shortcode( '[credits]x[/credits]' ) ) as $html ) {
			$this->assertStringContainsString( '>Source</span>', $html );
			$this->assertStringContainsString( 'credits-spacing-standard', $html );
			$this->assertStringNotContainsString( 'style=', $html );
		}
	}

	public function test_site_defaults_apply_when_attributes_are_absent(): void {
		$this->set_site_defaults( $this->site_defaults() );

		foreach ( array( $this->render_credit_block( array( 'name' => 'x' ) ), do_shortcode( '[credits]x[/credits]' ) ) as $html ) {
			$this->assertStringContainsString( '>Via</span>', $html );
			$this->assertStringContainsString( 'credits-spacing-spacious', $html );
			$this->assertStringContainsString( 'background-color: #aa0000', $html );
			$this->assertStringContainsString( 'background-color: #00aa00', $html );
			$this->assertStringContainsString( 'color: #0000aa', $html );
		}
	}

	public function test_explicit_attributes_override_site_defaults(): void {
		$this->set_site_defaults( $this->site_defaults() );

		$html = $this->render_credit_block(
			array(
				'name'          => 'x',
				'type'          => 'source',
				'spacing'       => 'compact',
				'badgeColor'    => '#010101',
				'linkColor'     => '#020202',
				'linkTextColor' => '#030303',
			)
		);

		$this->assertStringContainsString( '>Source</span>', $html );
		$this->assertStringContainsString( 'credits-spacing-compact', $html );
		$this->assertStringContainsString( 'background-color: #010101', $html );
		$this->assertStringContainsString( 'background-color: #020202', $html );
		$this->assertStringContainsString( 'color: #030303', $html );
		$this->assertStringNotContainsString( '#aa0000', $html );
	}

	public function test_invalid_stored_defaults_fall_back_to_safe_values(): void {
		$this->set_site_defaults( array( 'type' => 'evil', 'spacing' => 'huge', 'badge_color' => 'red', 'link_color' => '#12', 'link_text_color' => array( 'x' ) ) );

		$html = $this->render_credit_block( array( 'name' => 'x' ) );

		$this->assertStringContainsString( '>Source</span>', $html );
		$this->assertStringContainsString( 'credits-spacing-standard', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	/* ---------------------------------------------------------------------
	 * Hostile input through WordPress's own sanitizers and kses
	 * ------------------------------------------------------------------ */

	/**
	 * @dataProvider hostile_attribute_provider
	 */
	public function test_hostile_block_attributes_render_safely( array $attrs ): void {
		$this->assertSafeMarkup( $this->render_credit_block( $attrs ) );
	}

	public function test_hostile_shortcode_input_renders_safely(): void {
		foreach (
			array(
				"[credits link='javascript:alert(1)']x[/credits]",
				'[credits link="&#106;avascript:alert(1)"]x[/credits]',
				'[credits badge_color="red;background:url(x)" link_text_color="expression(1)"]<script>alert(1)</script>[/credits]',
				'[credits name="<img src=x onerror=alert(1)>"]',
			) as $shortcode
		) {
			$this->assertSafeMarkup( do_shortcode( $shortcode ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * the_content pipeline
	 * ------------------------------------------------------------------ */

	public function test_the_content_renders_blocks_and_shortcodes_together(): void {
		$content = '<!-- wp:paragraph --><p>Intro [credits link="https://example.com/inline"]Inline[/credits]</p><!-- /wp:paragraph -->'
			. $this->block_markup( array( 'name' => 'Block credit', 'link' => 'https://example.com/block', 'className' => 'in-post' ) );

		$html = apply_filters( 'the_content', $content );

		$this->assertSame( 2, substr_count( $html, '<ul class="credits' ) );
		$this->assertStringContainsString( '>Inline</a>', $html );
		$this->assertStringContainsString( '>Block credit</a>', $html );
		$this->assertStringContainsString( 'in-post', $html );
	}
}
