<?php
/**
 * Plugin Name: Credits e2e locale switch
 * Description: Test fixture. Lets the editor e2e tests request a locale with a cookie so only that
 *              browser context is translated; no language pack download or site-wide change needed.
 */

add_filter(
	'locale',
	static function ( $locale ) {
		if ( isset( $_COOKIE['credits_e2e_locale'] ) && preg_match( '/^[a-z]{2}_[A-Z]{2}$/', $_COOKIE['credits_e2e_locale'] ) ) {
			return $_COOKIE['credits_e2e_locale'];
		}
		return $locale;
	}
);
