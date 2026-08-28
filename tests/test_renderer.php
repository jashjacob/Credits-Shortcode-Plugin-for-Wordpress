<?php
/**
 * Renderer tests: execute the real credits_print_shortcode() against
 * hostile and well-formed payloads.
 */

use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['credits_test_options'] = array();
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

	public function test_version_constant_is_1_4_0(): void {
		$this->assertTrue( defined( 'CREDITS_SHORTCODE_VERSION' ), 'CREDITS_SHORTCODE_VERSION must be defined' );
		$this->assertSame( '1.4.0', CREDITS_SHORTCODE_VERSION );
	}

	public function test_credits_shortcode_tag_is_registered(): void {
		$this->assertArrayHasKey( 'credits', $GLOBALS['credits_test_registered_shortcodes'] );
	}

	public function test_empty_and_no_attrs_render_fallback_name_with_hash_link(): void {
		foreach ( array( array(), array( 'name' => '', 'link' => '' ), array( 'name' => null, 'link' => null ) ) as $atts ) {
			$out = $this->render( $atts );
			$this->assertStringContainsString( '>Credit Link</a>', $out, 'Fallback name expected for: ' . var_export( $atts, true ) );
			$this->assertStringContainsString( 'href="#"', $out, 'Fallback link "#" expected' );
		}
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
		$out = $this->html( array( 'name' => '<script>alert(1)</script>' ) );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $out, 'No script element may survive' );
		$this->assertNoEventHandlerAttribute( $out );
	}

	public function test_img_onerror_in_name_does_not_survive(): void {
		$out = $this->html( array( 'name' => '<img src=x onerror=alert(1)>' ) );
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
			$out = $this->html( array( 'name' => $payload ) );
			$this->assertStringNotContainsStringIgnoringCase( '<svg', $out );
			$this->assertStringNotContainsStringIgnoringCase( '<img', $out );
			$this->assertNoEventHandlerAttribute( $out );
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

	public function test_javascript_scheme_link_falls_back_to_hash(): void {
		$out = $this->html( array( 'name' => 'ok', 'link' => 'javascript:alert(1)' ) );
		$this->assertStringContainsString( 'href="#"', $out );
		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $out );
	}

	public function test_data_text_html_uri_falls_back_to_hash(): void {
		$out = $this->html( array( 'name' => 'ok', 'link' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==' ) );
		$this->assertStringContainsString( 'href="#"', $out );
		$this->assertStringNotContainsStringIgnoringCase( 'data:', $out );
	}

	public function test_vbscript_and_encoded_javascript_schemes_rejected(): void {
		foreach ( array( 'vbscript:msgbox(1)', '&#106;avascript:alert(1)', 'JaVaScRiPt:alert(1)', 'jav&#x09;ascript:alert(1)' ) as $bad ) {
			$out = $this->html( array( 'name' => 'ok', 'link' => $bad ) );
			$this->assertStringContainsString( 'href="#"', $out, "Payload must not survive: {$bad}" );
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
	 * Colors
	 * ------------------------------------------------------------------ */

	public function test_valid_hex_badge_color_becomes_inline_style(): void {
		$out = $this->html( array( 'name' => 'x', 'badgeColor' => '#ff0000' ) );
		$this->assertMatchesRegularExpression( '/background-color:\s*#ff0000/', $out );
	}

	public function test_valid_hex_link_text_color_applied_as_color(): void {
		$out = $this->html( array( 'name' => 'x', 'linkTextColor' => '#00ff00' ) );
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
		$out = $this->html( array( 'badgeColor' => '#abcd' ) );
		$this->assertStringNotContainsString( 'style=', $out );

		$out = $this->html( array( 'badgeColor' => '#abc' ) );
		$this->assertMatchesRegularExpression( '/background-color:\s*#abc/', $out );
	}

	public function test_snake_case_color_aliases_still_work(): void {
		$out = $this->html( array( 'badge_color' => '#112233', 'link_color' => '#445566', 'link_text_color' => '#778899' ) );
		$this->assertMatchesRegularExpression( '/background-color:\s*#112233/', $out );
		$this->assertMatchesRegularExpression( '/color:\s*#778899/', $out );
	}

	/* ---------------------------------------------------------------------
	 * Attribute merging
	 * ------------------------------------------------------------------ */

	public function test_unknown_attributes_are_ignored(): void {
		$out = $this->render( array(
			'name'      => 'Known',
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
		$out = $this->render( array( 'name' => array( 'deep' => 'array' ), 'link' => new stdClass(), 'badgeColor' => 1.5 ) );
		$this->assertIsString( $out );
		$this->assertStringContainsString( 'Credit Link', $out );
	}
}
