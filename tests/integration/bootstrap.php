<?php
/**
 * Integration bootstrap: loads a real WordPress (SQLite-backed, installed by
 * scripts/setup-wordpress-integration.sh) with this plugin active.
 *
 * Override the install with WP_INTEGRATION_PATH; pick a version at setup time
 * with WP_VERSION.
 */

use PHPUnit\Framework\TestCase;

$credits_wp_root = getenv( 'WP_INTEGRATION_PATH' ) ?: dirname( __DIR__, 2 ) . '/.wordpress-integration/current';

if ( ! is_file( $credits_wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "No WordPress found at {$credits_wp_root}.\nRun: bash scripts/setup-wordpress-integration.sh\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require $credits_wp_root . '/wp-load.php';

// Settings API helpers and wp_delete_user() only load in wp-admin.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

// PHPUnit includes this file inside a function, so variables WordPress assigns at
// file scope (for example $shortcode_tags) are locals that PHPUnit copies over the
// live globals afterwards, wiping everything registered through `global`. Bind
// each such local to its live global so the copy is a no-op.
foreach ( array_keys( get_defined_vars() ) as $credits_var ) {
	if ( 0 !== strpos( $credits_var, 'credits_' ) && isset( $GLOBALS[ $credits_var ] ) ) {
		$$credits_var = &$GLOBALS[ $credits_var ];
	}
}
unset( $credits_var );

if ( ! function_exists( 'credits_print_shortcode' ) ) {
	fwrite( STDERR, "The credits-shortcode plugin is not active in {$credits_wp_root}.\nRun: bash scripts/setup-wordpress-integration.sh\n" );
	exit( 1 );
}

/**
 * Shared helpers. Every test starts from and returns to a clean site: settings
 * option removed, any posts/users it created deleted, current user reset.
 */
abstract class Credits_Integration_TestCase extends TestCase {

	/** @var int[] */
	private $post_ids = array();

	/** @var int[] */
	private $user_ids = array();

	protected function setUp(): void {
		parent::setUp();
		delete_option( 'credits_shortcode_settings' );
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->post_ids = array();
		$this->user_ids = array();
		delete_option( 'credits_shortcode_settings' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Serialize attributes into real block markup, exactly as the editor saves it.
	 */
	protected function block_markup( array $attrs = array() ): string {
		return serialize_block(
			array(
				'blockName'    => 'credits/shortcode',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Render a block through WordPress's own block renderer.
	 */
	protected function render_credit_block( array $attrs = array() ): string {
		return do_blocks( $this->block_markup( $attrs ) );
	}

	protected function set_site_defaults( array $settings ): void {
		update_option( 'credits_shortcode_settings', $settings );
	}

	protected function create_user( string $role ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'credits_' . $role . '_' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user_id, 'Test user must be created' );
		$this->user_ids[] = $user_id;
		return $user_id;
	}

	protected function create_post( string $content ): int {
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Credits integration',
				'post_content' => $content,
				'post_status'  => 'publish',
			),
			true
		);
		$this->assertIsInt( $post_id, 'Test post must be created' );
		$this->post_ids[] = $post_id;
		return $post_id;
	}
}
