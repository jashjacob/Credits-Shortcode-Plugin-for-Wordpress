#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> PHP: $(php -r 'echo PHP_VERSION;')"
echo "==> Lint main plugin file"
php -l credits_shortcode.php

echo "==> PHPUnit"
vendor/bin/phpunit

echo "==> Render sample Source credit (plugin core action)"
php -r '
require "tests/bootstrap.php";
$html = credits_print_shortcode(
	array(
		"name" => "TechZei",
		"link" => "https://techzei.com/",
		"type" => "source",
		"spacing" => "standard",
	)
);
if ( ! str_contains( $html, "TechZei" ) || ! str_contains( $html, "https://techzei.com/" ) ) {
	fwrite( STDERR, "Rendered HTML missing expected attribution.\n" );
	exit( 1 );
}
echo $html . "\n";
'

echo "==> All checks passed"
