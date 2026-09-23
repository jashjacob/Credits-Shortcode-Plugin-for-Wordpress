<?php
/**
 * WordPress runtime checks for Credits plugin variations.
 *
 * Run: wp eval-file .cursor/test-credit-variations.php --path=.wordpress
 */

if ( ! function_exists( 'do_shortcode' ) ) {
	fwrite( STDERR, "WordPress not loaded.\n" );
	exit( 1 );
}

class Credits_Variation_Test_Runner {
	public static int $pass = 0;
	public static int $fail = 0;

	public static function assert( $label, $condition, $detail = '' ) {
		if ( $condition ) {
			echo "  PASS: {$label}\n";
			++self::$pass;
			return;
		}
		echo "  FAIL: {$label}" . ( $detail ? " ({$detail})" : '' ) . "\n";
		++self::$fail;
	}

	public static function assert_contains( $label, $haystack, $needle ) {
		self::assert( $label, is_string( $haystack ) && str_contains( $haystack, $needle ), "missing: {$needle}" );
	}
}

function credits_test_assert( $label, $condition, $detail = '' ) {
	Credits_Variation_Test_Runner::assert( $label, $condition, $detail );
}

function credits_test_assert_contains( $label, $haystack, $needle ) {
	Credits_Variation_Test_Runner::assert_contains( $label, $haystack, $needle );
}

echo "Credits plugin variation tests (WordPress runtime)\n";
echo str_repeat( '=', 50 ) . "\n\n";

$html = do_shortcode( '[credits link="https://source-one.example/" type="source"]Alpha Source[/credits]' );
credits_test_assert_contains( 'Single Source label', $html, '>Source</span>' );
credits_test_assert_contains( 'Single Source name', $html, 'Alpha Source' );
credits_test_assert_contains( 'Single Source URL', $html, 'https://source-one.example/' );

$html = do_shortcode( '[credits link="https://via-one.example/" type="via"]Beta Via[/credits]' );
credits_test_assert_contains( 'Via toggle label', $html, '>Via</span>' );
credits_test_assert_contains( 'Via toggle name', $html, 'Beta Via' );

$html = do_shortcode(
	'[credits link="https://colors.example/" type="source" badge_color="#0073aa" link_color="#f0f0f0" link_text_color="#0073aa"]Blue Theme[/credits]'
);
credits_test_assert_contains( 'Custom badge color', $html, 'background-color: #0073aa' );
credits_test_assert_contains( 'Custom name', $html, 'Blue Theme' );

$html = do_shortcode( '[credits link="https://c.example/" spacing="compact"]Compact[/credits]' );
credits_test_assert_contains( 'Spacing compact class', $html, 'credits-spacing-compact' );

$html = do_shortcode( '[credits link="https://s.example/" spacing="spacious"]Spacious[/credits]' );
credits_test_assert_contains( 'Spacing spacious class', $html, 'credits-spacing-spacious' );

$multi = '[credits link="https://a.example/" type="source" badge_color="#ef4423" link_color="#E0D9D9" link_text_color="#ef4423"]First Source[/credits]' . "\n\n"
	. '[credits link="https://b.example/" type="source" badge_color="#0073aa" link_color="#cce5ff" link_text_color="#004a7c"]Second Source[/credits]' . "\n\n"
	. '[credits link="https://c.example/" type="via" badge_color="#2e7d32" link_color="#e8f5e9" link_text_color="#1b5e20"]Via Partner[/credits]';
$multi_html = do_shortcode( $multi );
credits_test_assert_contains( 'Multiple: first badge color', $multi_html, 'background-color: #ef4423' );
credits_test_assert_contains( 'Multiple: second badge color', $multi_html, 'background-color: #0073aa' );
credits_test_assert_contains( 'Multiple: via in stack', $multi_html, '>Via</span>' );
credits_test_assert_contains( 'Multiple: first name', $multi_html, 'First Source' );
credits_test_assert_contains( 'Multiple: second name', $multi_html, 'Second Source' );
credits_test_assert_contains( 'Multiple: via name', $multi_html, 'Via Partner' );

update_option(
	'credits_shortcode_settings',
	array(
		'type'            => 'via',
		'spacing'         => 'standard',
		'badge_color'     => '',
		'link_color'      => '',
		'link_text_color' => '',
	)
);
$html = do_shortcode( '[credits link="https://default.example/"]Default Type Name[/credits]' );
credits_test_assert_contains( 'Site default type = via', $html, '>Via</span>' );
update_option(
	'credits_shortcode_settings',
	array(
		'type'            => 'source',
		'spacing'         => 'standard',
		'badge_color'     => '',
		'link_color'      => '',
		'link_text_color' => '',
	)
);

$block = '<!-- wp:credits/shortcode {"link":"https://block-source.example/","type":"source","name":"Block Source","badgeColor":"#111111","linkColor":"#dddddd","linkTextColor":"#111111"} /-->

<!-- wp:credits/shortcode {"link":"https://block-via.example/","type":"via","name":"Block Via","badgeColor":"#990000","linkColor":"#ffcccc","linkTextColor":"#660000"} /-->';
$block_html = do_blocks( $block );
credits_test_assert_contains( 'Block: source name', $block_html, 'Block Source' );
credits_test_assert_contains( 'Block: via name', $block_html, 'Block Via' );
credits_test_assert_contains( 'Block: via label', $block_html, '>Via</span>' );
credits_test_assert_contains( 'Block: custom color', $block_html, 'background-color: #111111' );

$post_id = wp_insert_post(
	array(
		'post_title'   => 'All Credit Variations',
		'post_status'  => 'publish',
		'post_content' => $multi . "\n\n" . $block,
	)
);
credits_test_assert( 'Published variation post created', $post_id && ! is_wp_error( $post_id ), 'wp_insert_post failed' );

if ( $post_id && ! is_wp_error( $post_id ) ) {
	$url = get_permalink( $post_id );
	echo "\nPublished post: {$url} (ID {$post_id})\n";
}

echo "\n" . str_repeat( '=', 50 ) . "\n";
echo 'Results: ' . Credits_Variation_Test_Runner::$pass . ' passed, ' . Credits_Variation_Test_Runner::$fail . " failed\n";

exit( Credits_Variation_Test_Runner::$fail > 0 ? 1 : 0 );
