<?php
/**
 * Renderer tests: execute the real credits_print_shortcode() against
 * hostile and well-formed payloads.
 */

use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['credits_test_options'] = array();
		unset( $GLOBALS['credits_test_block_wrapper_attributes'] );
	}

	/**
	 * Call the plugin renderer with an attribute array.
	 *
	 * @param array $atts Shortcode/block attributes.
	 * @return string Rendered HTML.
	 */
	private function render( array $atts = array() ) {
		$this->assertTrue(
			function_exists( 'credits_print_shortcode' ),
			'credits_print_shortcode() must exist per the plugin contract'
		);
		return credits_print_shortcode( $atts );
	}

	private function html( array $atts = array() ) {
		return $this->render( $atts );
	}

	/* ---------------------------------------------------------------------
	 * Contract basics
	 * ------------------------------------------------------------------ */

	/**
	 * Event handlers must never exist as real tag attributes. Escaped
	 * handler-like text in text nodes is inert and acceptable.
	 */
	private function assertNoEventHandlerAttribute( string $out ): void {
		$this->assertDoesNotMatchRegularExpression(
			'/<[a-z][^>]*[\s"\']on[a-z]+\s*=/i',
			$out,
			'No on* event handler may appear inside an open tag'
		);
	}

	public function test_version_constant_is_1_6_1(): void {
		$this->assertTrue( defined( 'CREDITS_SHORTCODE_VERSION' ), 'CREDITS_SHORTCODE_VERSION must be defined' );
		$this->assertSame( '1.6.1', CREDITS_SHORTCODE_VERSION );
	}

	public function test_spacing_preset_is_added_to_wrapper_class(): void {
		$out = $this->render( array( 'name' => 'x', 'spacing' => 'compact' ) );
		$this->assertStringContainsString( 'credits-spacing-compact', $out );

		$out = $this->render( array( 'name' => 'x', 'spacing' => 'invalid' ) );
		$this->assertStringContainsString( 'credits-spacing-standard', $out );
	}

	public function test_credits_shortcode_tag_is_registered(): void {
		$this->assertArrayHasKey( 'credits', $GLOBALS['credits_test_registered_shortcodes'] );
	}

	/* ---------------------------------------------------------------------
	 * Incomplete credits: no dead links, no placeholder credit
	 * ------------------------------------------------------------------ */

	public function test_no_name_and_no_usable_link_renders_nothing(): void {
		foreach (
			array(
				array(),
				array( 'name' => '', 'link' => '' ),
				array( 'name' => null, 'link' => null ),
				array( 'name' => '   ' ),
				array( 'name' => '   ', 'link' => '#' ),
				array( 'name' => '', 'link' => 'javascript:alert(1)' ),
				array( 'name' => '<script>alert(1)</script>' ),
				array( 'name' => '<img src=x onerror=alert(1)>', 'link' => '   ' ),
				array( 'name' => array( 'deep' => 'array' ), 'link' => new stdClass() ),
			) as $atts
		) {
			$this->assertSame( '', $this->render( $atts ), 'Nothing expected for: ' . var_export( $atts, true ) );
		}
	}

	public function test_name_without_a_usable_link_renders_the_chip_without_an_anchor(): void {
		$expected = '<ul class="credits wp-block-credits-shortcode credits-spacing-standard"><li class="credits">'
			. '<span class="cre_cate">Source</span>'
			. '<span class="cre_cate_link"><span class="cre_cate_text">Example</span></span>'
			. '</li></ul>';

		foreach ( array( null, '', '   ', '#', 'javascript:alert(1)', 'vbscript:msgbox(1)' ) as $link ) {
			$atts = array( 'name' => 'Example' );
			if ( null !== $link ) {
				$atts['link'] = $link;
			}
			$this->assertSame( $expected, $this->render( $atts ), 'Link: ' . var_export( $link, true ) );
		}
	}

	public function test_unlinked_credit_has_no_anchor_href_or_target_even_when_new_tab_is_requested(): void {
		$out = $this->html( array( 'name' => 'Example', 'link' => '#', 'newTab' => true ) );
		$this->assertStringNotContainsString( '<a ', $out );
		$this->assertStringNotContainsString( 'href', $out );
		$this->assertStringNotContainsString( 'target=', $out );
		$this->assertStringNotContainsString( 'rel=', $out );
	}

	public function test_link_text_color_goes_on_the_text_span_of_an_unlinked_credit(): void {
		$out = $this->html( array( 'name' => 'Example', 'linkColor' => '#222222', 'linkTextColor' => '#00ff00' ) );
		$this->assertStringContainsString( '<span class="cre_cate_link" style="background-color: #222222"><span class="cre_cate_text" style="color: #00ff00">Example</span></span>', $out );

		$out = $this->html( array( 'name' => 'Example' ) );
		$this->assertStringContainsString( '<span class="cre_cate_text">Example</span>', $out, 'No color, no style attribute' );
	}

	public function test_site_default_link_text_color_applies_to_an_unlinked_credit(): void {
		update_option( 'credits_shortcode_settings', array( 'link_text_color' => '#778899' ) );

		$out = $this->html( array( 'name' => 'Example' ) );
		$this->assertStringContainsString( '<span class="cre_cate_text" style="color: #778899">Example</span>', $out );
	}

	public function test_fragment_links_are_usable_links(): void {
		$out = $this->html( array( 'name' => 'Example', 'link' => '#references' ) );
		$this->assertStringContainsString( '<a href="#references"', $out );
		$this->assertStringContainsString( '>Example</a>', $out );
		$this->assertStringNotContainsString( 'cre_cate_text', $out );
	}

	public function test_blank_name_with_a_usable_link_keeps_the_credit_link_fallback_text(): void {
		foreach ( array( array( 'name' => '' ), array( 'name' => '   ' ), array() ) as $extra ) {
			$out = $this->html( array_merge( $extra, array( 'link' => 'https://example.com/source' ) ) );
			$this->assertStringContainsString( '<a href="https://example.com/source"', $out );
			$this->assertStringContainsString( '>Credit Link</a>', $out );
		}
	}

	public function test_untouched_block_renders_nothing(): void {
		$GLOBALS['credits_test_block_wrapper_attributes'] = 'class="wp-block-credits-shortcode my-custom-class"';

		$this->assertSame( '', credits_render_block( array() ) );
		$this->assertSame( '', credits_render_block( array( 'className' => 'my-custom-class', 'type' => 'via', 'badgeColor' => '#111111' ) ) );
	}

	public function test_output_is_a_string_starting_with_allowed_ul_li_markup(): void {
		$out = $this->render( array( 'name' => 'X', 'link' => 'https://example.com/' ) );
		$this->assertSame( '<ul', substr( trim( $out ), 0, 3 ) );
		$this->assertStringEndsWith( '</ul>', trim( $out ) );
	}

	/* ---------------------------------------------------------------------
	 * XSS in the name attribute
	 * ------------------------------------------------------------------ */

	public function test_script_tag_in_name_does_not_survive(): void {
		// The name sanitizes to nothing: with no usable link there is nothing to render.
		$this->assertSame( '', $this->html( array( 'name' => '<script>alert(1)</script>' ) ) );

		$out = $this->html( array( 'name' => '<script>alert(1)</script>', 'link' => 'https://example.com/' ) );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $out, 'No script element may survive' );
		$this->assertStringNotContainsString( 'alert(1)', $out );
		$this->assertStringContainsString( '>Credit Link</a>', $out );
		$this->assertNoEventHandlerAttribute( $out );
	}

	public function test_img_onerror_in_name_does_not_survive(): void {
		$this->assertSame( '', $this->html( array( 'name' => '<img src=x onerror=alert(1)>' ) ) );

		$out = $this->html( array( 'name' => '<img src=x onerror=alert(1)>', 'link' => 'https://example.com/' ) );
		$this->assertStringNotContainsStringIgnoringCase( '<img', $out );
		$this->assertStringNotContainsStringIgnoringCase( 'onerror', $out );
		$this->assertStringNotContainsStringIgnoringCase( '<svg', $out );
	}

	public function test_attribute_breakout_in_name_is_escaped_not_executable(): void {
		$payloads = array(
			'"><svg onload=alert(1)>',
			"' onload='alert(1)'",
			'"><img src=x onerror=alert(1)>',
		);
		foreach ( $payloads as $payload ) {
			// Once without a link (unlinked chip, or nothing) and once with one (anchor).
			foreach ( array( array(), array( 'link' => 'https://example.com/' ) ) as $extra ) {
				$out = $this->html( array_merge( array( 'name' => $payload ), $extra ) );
				$this->assertStringNotContainsStringIgnoringCase( '<svg', $out );
				$this->assertStringNotContainsStringIgnoringCase( '<img', $out );
				$this->assertNoEventHandlerAttribute( $out );
			}
		}
	}

	public function test_html_special_chars_in_name_are_entity_encoded(): void {
		$out = $this->html( array( 'name' => 'Tom & <b>"Jerry"</b>' ) );
		$this->assertStringContainsString( '&amp;', $out );
		$this->assertStringNotContainsString( '<b>', $out );
		$this->assertStringNotContainsString( '"Jerry"', $out );
	}

	/* ---------------------------------------------------------------------
	 * XSS via link attributes / scheme smuggling
	 * ------------------------------------------------------------------ */

	public function test_javascript_scheme_link_renders_the_name_without_an_anchor(): void {
		$out = $this->html( array( 'name' => 'ok', 'link' => 'javascript:alert(1)' ) );
		$this->assertStringNotContainsString( '<a ', $out );
		$this->assertStringNotContainsString( 'href', $out );
		$this->assertStringContainsString( '<span class="cre_cate_text">ok</span>', $out );
		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $out );
	}

	public function test_data_text_html_uri_renders_the_name_without_an_anchor(): void {
		$out = $this->html( array( 'name' => 'ok', 'link' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==' ) );
		$this->assertStringNotContainsString( '<a ', $out );
		$this->assertStringNotContainsString( 'href', $out );
		$this->assertStringContainsString( '<span class="cre_cate_text">ok</span>', $out );
		$this->assertStringNotContainsStringIgnoringCase( 'data:', $out );
	}

	public function test_vbscript_and_encoded_javascript_schemes_rejected(): void {
		foreach ( array( 'vbscript:msgbox(1)', '&#106;avascript:alert(1)', 'JaVaScRiPt:alert(1)', 'jav&#x09;ascript:alert(1)' ) as $bad ) {
			$out = $this->html( array( 'name' => 'ok', 'link' => $bad ) );
			$this->assertStringNotContainsString( '<a ', $out, "Payload must not survive: {$bad}" );
			$this->assertStringNotContainsString( 'href', $out, "Payload must not survive: {$bad}" );
			$this->assertStringNotContainsStringIgnoringCase( 'script:', $out, "Payload must not survive: {$bad}" );
			$this->assertStringContainsString( '<span class="cre_cate_text">ok</span>', $out, "Payload must not survive: {$bad}" );

			// With no name either, the credit disappears entirely.
			$this->assertSame( '', $this->html( array( 'link' => $bad ) ), "Payload must not survive: {$bad}" );
		}
	}

	public function test_quote_breakouts_into_link_tag_do_not_add_event_handlers(): void {
		$payloads = array(
			'" onmouseover="alert(1)',
			'"><svg onload=alert(1)><a href="',
			"' onclick='alert(1)",
		);
		foreach ( $payloads as $payload ) {
			$out = $this->html( array( 'name' => 'ok', 'link' => $payload ) );
			$this->assertStringNotContainsStringIgnoringCase( '<svg', $out );
			$this->assertNoEventHandlerAttribute( $out );
			$this->assertMatchesRegularExpression( '/<a href="[^"]*"/', $out, 'href must remain a quoted, closed attribute' );
		}
	}

	public function test_malformed_urls_do_not_fatal_and_stay_safe(): void {
		foreach ( array( 'http://exa mple.com', '//evil', 'http://ex%20ample.com/<script>' ) as $url ) {
			$out = $this->html( array( 'name' => 'ok', 'link' => $url ) );
			$this->assertStringNotContainsStringIgnoringCase( '<script', $out );
			$this->assertStringNotContainsStringIgnoringCase( 'onerror', $out );
			$this->assertStringContainsString( '<a ', $out );
		}
	}

	public function test_valid_https_link_is_preserved(): void {
		$out = $this->html( array( 'name' => 'Example', 'link' => 'https://example.com/post?a=1&b=2' ) );
		$this->assertStringContainsString( 'https://example.com/post', $out );
	}

	/* ---------------------------------------------------------------------
	 * rel / target hardening
	 * ------------------------------------------------------------------ */

	public function test_target_blank_and_noopener_present_when_link_renders(): void {
		$out = $this->html( array( 'name' => 'Example', 'link' => 'https://example.com/' ) );
		$this->assertStringContainsString( 'target="_blank"', $out );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $out );
	}

	/* ---------------------------------------------------------------------
	 * Open in new tab
	 * ------------------------------------------------------------------ */

	public function test_new_tab_is_the_default_when_no_attribute_is_given(): void {
		$out = $this->html( array( 'name' => 'Example', 'link' => 'https://example.com/' ) );
		$this->assertStringContainsString( 'target="_blank"', $out );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $out );
	}

	public function test_explicit_false_values_open_in_the_same_tab_without_target_or_rel(): void {
		foreach ( array( false, 'false', 'FALSE', '0', 0, 'no', 'off', ' false ' ) as $off ) {
			foreach ( array( 'newTab', 'new_tab', 'newtab' ) as $key ) {
				$out = $this->html( array( 'name' => 'Example', 'link' => 'https://example.com/', $key => $off ) );
				$this->assertStringNotContainsString( 'target=', $out, $key . '=' . var_export( $off, true ) );
				$this->assertStringNotContainsString( 'rel=', $out, $key . '=' . var_export( $off, true ) );
				$this->assertStringContainsString( 'href="https://example.com/"', $out );
			}
		}
	}

	public function test_true_empty_and_unrecognised_values_keep_the_new_tab_default(): void {
		foreach ( array( true, 'true', '1', 1, 'yes', 'on', '', null, 'garbage', array( 'x' ), new stdClass() ) as $on ) {
			$out = $this->html( array( 'name' => 'Example', 'link' => 'https://example.com/', 'newTab' => $on ) );
			$this->assertStringContainsString( 'target="_blank"', $out, var_export( $on, true ) );
			$this->assertStringContainsString( 'rel="noopener noreferrer"', $out, var_export( $on, true ) );
		}
	}

	public function test_block_render_honors_the_new_tab_attribute(): void {
		$GLOBALS['credits_test_block_wrapper_attributes'] = 'class="wp-block-credits-shortcode"';

		$same_tab = credits_render_block( array( 'name' => 'x', 'link' => 'https://example.com/', 'newTab' => false ) );
		$this->assertStringNotContainsString( 'target=', $same_tab );

		$new_tab = credits_render_block( array( 'name' => 'x', 'link' => 'https://example.com/', 'newTab' => true ) );
		$this->assertStringContainsString( 'target="_blank"', $new_tab );
	}

	/* ---------------------------------------------------------------------
	 * Colors
	 * ------------------------------------------------------------------ */

	public function test_valid_hex_badge_color_becomes_inline_style(): void {
		$out = $this->html( array( 'name' => 'x', 'badgeColor' => '#ff0000' ) );
		$this->assertMatchesRegularExpression( '/background-color:\s*#ff0000/', $out );
	}

	public function test_valid_hex_link_text_color_applied_as_color(): void {
		$out = $this->html( array( 'name' => 'x', 'link' => 'https://example.com/', 'linkTextColor' => '#00ff00' ) );
		$this->assertMatchesRegularExpression( '/<a [^>]*style="[^"]*color:\s*#00ff00/', $out );
	}

	public function test_uppercase_hex_accepted(): void {
		$out = $this->html( array( 'name' => 'x', 'badgeColor' => '#ABCDEF' ) );
		$this->assertMatchesRegularExpression( '/background-color:\s*#ABCDEF/', $out );
	}

	public function test_invalid_colors_are_dropped_without_style_attribute(): void {
		foreach ( array( 'red', '#12345', '#zzzzzz', '#1234567890', 'javascript:alert(1)', '#fff; color:red' ) as $bad ) {
			$out = $this->html( array( 'badgeColor' => $bad, 'linkColor' => $bad, 'linkTextColor' => $bad, 'name' => 'x' ) );
			$this->assertStringNotContainsString( 'style=', $out, "Invalid color '{$bad}' must produce no inline style" );
			$this->assertStringNotContainsStringIgnoringCase( 'red;', $out );
		}
	}

	public function test_four_digit_hex_rejected_but_three_digit_hex_accepted(): void {
		$out = $this->html( array( 'name' => 'x', 'badgeColor' => '#abcd' ) );
		$this->assertStringContainsString( 'cre_cate', $out, 'The credit itself still renders' );
		$this->assertStringNotContainsString( 'style=', $out );

		$out = $this->html( array( 'name' => 'x', 'badgeColor' => '#abc' ) );
		$this->assertMatchesRegularExpression( '/background-color:\s*#abc/', $out );
	}

	public function test_snake_case_color_aliases_still_work(): void {
		$out = $this->html( array( 'name' => 'x', 'badge_color' => '#112233', 'link_color' => '#445566', 'link_text_color' => '#778899' ) );
		$this->assertMatchesRegularExpression( '/background-color:\s*#112233/', $out );
		$this->assertMatchesRegularExpression( '/color:\s*#778899/', $out );
	}

	/* ---------------------------------------------------------------------
	 * Attribute merging
	 * ------------------------------------------------------------------ */

	public function test_unknown_attributes_are_ignored(): void {
		$out = $this->render( array(
			'name'      => 'Known',
			'link'      => 'https://example.com/',
			'evil_attr' => '"><script>alert(1)</script>',
			'onmouseover' => 'alert(1)',
		) );
		$this->assertStringContainsString( '>Known</a>', $out );
		$this->assertStringNotContainsString( 'evil_attr', $out );
		$this->assertStringNotContainsStringIgnoringCase( 'onmouseover', $out );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $out );
	}

	public function test_type_via_switches_category_label(): void {
		$out = $this->html( array( 'type' => 'via', 'name' => 'Someone' ) );
		$this->assertStringContainsString( '>Via</span>', $out );

		$out = $this->html( array( 'type' => 'source<script>', 'name' => 'Someone' ) );
		$this->assertStringContainsString( '>Source</span>', $out );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $out );
	}

	public function test_non_scalar_att_values_do_not_fatal(): void {
		// A non-scalar name and link are treated as absent, so nothing renders.
		$out = $this->render( array( 'name' => array( 'deep' => 'array' ), 'link' => new stdClass(), 'badgeColor' => 1.5 ) );
		$this->assertSame( '', $out );

		$out = $this->render( array( 'name' => array( 'deep' => 'array' ), 'link' => 'https://example.com/', 'badgeColor' => 1.5 ) );
		$this->assertStringContainsString( '>Credit Link</a>', $out );

		$out = $this->render( array( 'name' => 'Named', 'link' => new stdClass(), 'badgeColor' => 1.5 ) );
		$this->assertStringContainsString( '<span class="cre_cate_text">Named</span>', $out );
	}

	/* ---------------------------------------------------------------------
	 * Block wrapper custom CSS class preservation
	 * ------------------------------------------------------------------ */

	public function test_block_render_preserves_custom_class_from_advanced_panel(): void {
		$this->assertTrue( function_exists( 'credits_render_block' ), 'credits_render_block() must exist per the plugin contract' );

		$GLOBALS['credits_test_block_wrapper_attributes'] = 'class="wp-block-credits-shortcode my-custom-class"';
		$out = credits_render_block( array( 'name' => 'x', 'className' => 'my-custom-class' ) );

		$this->assertMatchesRegularExpression( '/<ul class="[^"]*\bmy-custom-class\b[^"]*"/', $out );
	}

	public function test_block_render_does_not_duplicate_default_wrapper_class(): void {
		$GLOBALS['credits_test_block_wrapper_attributes'] = 'class="wp-block-credits-shortcode"';
		$out = credits_render_block( array( 'name' => 'x' ) );

		$this->assertSame( 1, substr_count( $out, 'wp-block-credits-shortcode' ), 'Default block class must appear exactly once' );
	}

	public function test_block_render_merges_multiple_custom_classes(): void {
		$GLOBALS['credits_test_block_wrapper_attributes'] = 'class="wp-block-credits-shortcode  align-me   another-class"';
		$out = credits_render_block( array( 'name' => 'x' ) );

		$this->assertStringContainsString( 'align-me', $out );
		$this->assertStringContainsString( 'another-class', $out );
	}

	public function test_block_render_sanitizes_hostile_custom_class(): void {
		$GLOBALS['credits_test_block_wrapper_attributes'] = 'class="wp-block-credits-shortcode" onmouseover="alert(1)"';
		$out = credits_render_block( array( 'name' => 'x' ) );

		$this->assertStringNotContainsStringIgnoringCase( 'onmouseover', $out );
		$this->assertNoEventHandlerAttribute( $out );
	}

	public function test_shortcode_rendering_is_independent_of_block_wrapper_context(): void {
		// A block elsewhere on the page leaving wrapper state behind must never
		// leak into a plain [credits] shortcode call's output.
		$GLOBALS['credits_test_block_wrapper_attributes'] = 'class="wp-block-credits-shortcode leaked-block-class"';
		$out = $this->render( array( 'name' => 'x' ) );

		$this->assertStringNotContainsString( 'leaked-block-class', $out );
	}

	public function test_registered_shortcode_callback_ignores_the_tag_argument_wordpress_passes(): void {
		// do_shortcode() calls the callback as ( $atts, $content, $tag ).
		$callback = $GLOBALS['credits_test_registered_shortcodes']['credits'];
		$out      = call_user_func( $callback, array( 'name' => 'x' ), null, 'credits' );

		$this->assertSame( $this->render( array( 'name' => 'x' ) ), $out );
		$this->assertMatchesRegularExpression( '/<ul class="credits wp-block-credits-shortcode credits-spacing-standard">/', $out );
	}

	public function test_block_render_without_custom_class_matches_shortcode_output(): void {
		$GLOBALS['credits_test_block_wrapper_attributes'] = 'class="wp-block-credits-shortcode"';
		$block_out    = credits_render_block( array( 'name' => 'x', 'link' => 'https://example.com/' ) );
		$shortcode_out = $this->render( array( 'name' => 'x', 'link' => 'https://example.com/' ) );

		$this->assertSame( $shortcode_out, $block_out );
	}
}
