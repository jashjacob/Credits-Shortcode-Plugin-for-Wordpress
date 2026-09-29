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

	public function test_every_settings_label_points_at_exactly_one_control(): void {
		credits_register_settings();

		try {
			ob_start();
			do_settings_sections( 'credits-shortcode' );
			$html = ob_get_clean();
		} finally {
			unregister_setting( 'credits_shortcode', 'credits_shortcode_settings' );
		}

		preg_match_all( '/<label for="([^"]+)"/', $html, $labels );
		$this->assertCount( 5, $labels[1], 'Type, spacing and the three colors each need a label' );

		foreach ( $labels[1] as $id ) {
			$this->assertSame( 1, preg_match_all( '/\bid="' . preg_quote( $id, '/' ) . '"/', $html ), "Exactly one control must have the id \"{$id}\"" );
		}
		$this->assertSame( 1, preg_match_all( '/aria-describedby="credits_default_spacing_description"/', $html ) );
		$this->assertSame( 1, preg_match_all( '/id="credits_default_spacing_description"/', $html ) );
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
