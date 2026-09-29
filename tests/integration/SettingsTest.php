<?php
/**
 * Settings sanitization and uninstall cleanup through real WordPress options.
 */

final class SettingsTest extends Credits_Integration_TestCase {

	public function test_settings_are_sanitized_by_the_registered_option_filter(): void {
		credits_register_settings();

		try {
			update_option(
				'credits_shortcode_settings',
				array(
					'type'            => 'via<script>',
					'spacing'         => 'huge',
					'badge_color'     => 'red',
					'link_color'      => '#12345',
					'link_text_color' => '#ABC',
				)
			);

			$this->assertSame(
				array(
					'type'            => 'source',
					'spacing'         => 'standard',
					'badge_color'     => '',
					'link_color'      => '',
					'link_text_color' => '#ABC',
				),
				get_option( 'credits_shortcode_settings' )
			);
		} finally {
			unregister_setting( 'credits_shortcode', 'credits_shortcode_settings' );
		}
	}

	public function test_valid_settings_round_trip_through_the_database(): void {
		credits_register_settings();

		try {
			$settings = array(
				'type'            => 'via',
				'spacing'         => 'compact',
				'badge_color'     => '#010203',
				'link_color'      => '#040506',
				'link_text_color' => '#070809',
			);
			update_option( 'credits_shortcode_settings', $settings );

			$this->assertSame( $settings, get_option( 'credits_shortcode_settings' ) );
			$this->assertSame( $settings, credits_get_settings() );
		} finally {
			unregister_setting( 'credits_shortcode', 'credits_shortcode_settings' );
		}
	}

	public function test_uninstall_removes_the_stored_settings(): void {
		update_option( 'credits_shortcode_settings', array( 'type' => 'via' ) );
		$this->assertNotFalse( get_option( 'credits_shortcode_settings' ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'credits-shortcode/credits_shortcode.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertFalse( get_option( 'credits_shortcode_settings' ) );
	}
}
