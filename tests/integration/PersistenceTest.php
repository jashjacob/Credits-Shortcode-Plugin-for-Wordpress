<?php
/**
 * Saving a block into a real post and reloading it must keep every attribute,
 * including for roles whose content is filtered by kses.
 */

final class PersistenceTest extends Credits_Integration_TestCase {

	private function saved_attributes(): array {
		return array(
			'name'          => 'Ünïcode & <Co> "Quotes"',
			'link'          => 'https://example.com/a?x=1&y=2#frag',
			'type'          => 'via',
			'spacing'       => 'spacious',
			'badgeColor'    => '#112233',
			'linkColor'     => '#445566',
			'linkTextColor' => '#778899',
			'className'     => 'my-class other-class',
		);
	}

	public function unfiltered_role_provider(): array {
		return array(
			'administrator' => array( 'administrator' ),
			'editor'        => array( 'editor' ),
		);
	}

	public function filtered_role_provider(): array {
		return array(
			'author'      => array( 'author' ),
			'contributor' => array( 'contributor' ),
		);
	}

	public function role_provider(): array {
		return $this->unfiltered_role_provider() + $this->filtered_role_provider();
	}

	private function reload_credit_attributes( int $post_id ): array {
		$blocks = array_values(
			array_filter(
				parse_blocks( get_post( $post_id )->post_content ),
				static function ( $block ) {
					return 'credits/shortcode' === $block['blockName'];
				}
			)
		);
		$this->assertCount( 1, $blocks, 'Exactly one credits block must survive saving' );
		return $blocks[0]['attrs'];
	}

	/**
	 * @dataProvider unfiltered_role_provider
	 */
	public function test_saved_block_keeps_every_attribute_after_reload( string $role ): void {
		wp_set_current_user( $this->create_user( $role ) );
		$attrs = $this->saved_attributes();

		$post_id = $this->create_post( wp_slash( $this->block_markup( $attrs ) ) );

		$this->assertSame( $attrs, $this->reload_credit_attributes( $post_id ) );
	}

	/**
	 * WordPress core's kses rewrites "&" inside block-comment JSON and strips
	 * "<...>" for roles without unfiltered_html, so text attributes may change
	 * on save. Everything the plugin depends on must survive unchanged.
	 *
	 * @dataProvider filtered_role_provider
	 */
	public function test_saved_block_keeps_its_settings_after_reload_for_filtered_roles( string $role ): void {
		wp_set_current_user( $this->create_user( $role ) );
		$attrs = $this->saved_attributes();

		$post_id = $this->create_post( wp_slash( $this->block_markup( $attrs ) ) );
		$saved   = $this->reload_credit_attributes( $post_id );

		foreach ( array( 'type', 'spacing', 'badgeColor', 'linkColor', 'linkTextColor', 'className' ) as $key ) {
			$this->assertSame( $attrs[ $key ], $saved[ $key ], "{$key} must survive saving" );
		}
		$this->assertSame( $attrs['link'], html_entity_decode( $saved['link'] ), 'The URL may only gain entity encoding' );
	}

	/**
	 * @dataProvider role_provider
	 */
	public function test_reloaded_block_renders_identically_to_the_unsaved_block( string $role ): void {
		wp_set_current_user( $this->create_user( $role ) );
		$attrs = $this->saved_attributes();

		$post_id = $this->create_post( wp_slash( $this->block_markup( $attrs ) ) );

		$this->assertSame(
			$this->render_credit_block( $attrs ),
			do_blocks( get_post( $post_id )->post_content )
		);
	}

	public function test_hostile_attributes_saved_by_a_filtered_role_still_render_safely(): void {
		wp_set_current_user( $this->create_user( 'author' ) );
		$attrs = array(
			'name'      => '<script>alert(1)</script><img src=x onerror=alert(1)>',
			'link'      => 'javascript:alert(1)',
			'badgeColor' => 'red;background:url(javascript:alert(1))',
			'className' => '"><script>alert(1)</script>',
		);

		$post_id = $this->create_post( wp_slash( $this->block_markup( $attrs ) ) );
		$html    = do_blocks( get_post( $post_id )->post_content );

		$this->assertStringNotContainsStringIgnoringCase( '<script', $html );
		$this->assertStringNotContainsStringIgnoringCase( '<img', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $html );
		$this->assertDoesNotMatchRegularExpression( '/<[a-z][^>]*[\s"\']on[a-z]+\s*=/i', $html );
	}
}
