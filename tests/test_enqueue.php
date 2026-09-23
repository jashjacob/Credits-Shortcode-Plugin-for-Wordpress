<?php
/**
 * Conditional stylesheet loading tests.
 */

use PHPUnit\Framework\TestCase;

final class EnqueueTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['credits_test_is_admin']     = false;
		$GLOBALS['credits_test_is_singular']    = true;
		$GLOBALS['credits_test_queried_object'] = 0;
		$GLOBALS['credits_test_posts']          = array();
		$GLOBALS['credits_test_post_store']     = array();
	}

	public function test_content_includes_credits_for_shortcode(): void {
		$this->assertTrue(
			credits_content_includes_credits( '[credits link="https://example.com/"]Name[/credits]' )
		);
	}

	public function test_content_includes_credits_for_block_markup(): void {
		$this->assertTrue(
			credits_content_includes_credits( '<!-- wp:credits/shortcode {"name":"X"} /-->' )
		);
	}

	public function test_content_excludes_unrelated_content(): void {
		$this->assertFalse( credits_content_includes_credits( '<p>Hello world</p>' ) );
	}

	public function test_should_enqueue_on_singular_post_with_shortcode(): void {
		$post                                   = new WP_Post();
		$post->ID                               = 42;
		$post->post_content                     = '[credits link="https://example.com/"]X[/credits]';
		$GLOBALS['credits_test_post_store'][42] = $post;
		$GLOBALS['credits_test_is_singular']    = true;
		$GLOBALS['credits_test_queried_object'] = 42;

		$this->assertTrue( credits_should_enqueue_frontend_styles() );
	}

	public function test_should_not_enqueue_on_singular_post_without_credits(): void {
		$post                                  = new WP_Post();
		$post->ID                              = 5;
		$post->post_content                    = '<p>No credits here</p>';
		$GLOBALS['credits_test_post_store'][5] = $post;
		$GLOBALS['credits_test_is_singular']    = true;
		$GLOBALS['credits_test_queried_object'] = 5;

		$this->assertFalse( credits_should_enqueue_frontend_styles() );
	}

	public function test_should_not_enqueue_in_admin(): void {
		$GLOBALS['credits_test_is_admin'] = true;
		$this->assertFalse( credits_should_enqueue_frontend_styles() );
	}
}
