#!/usr/bin/env bash
# Local CI. Fast tier (default): lint, PHPUnit, JS syntax and tests, release zip.
# --integration adds the PHPUnit suite against a real WordPress (SQLite, no MySQL).
# --e2e adds the Playwright block-editor and Classic Editor tests (SQLite, no MySQL; first run
#   downloads Chromium unless CREDITS_E2E_CHROMIUM points at an existing browser).
# --full adds both plus the WordPress smoke test (needs MySQL).
# Pick the integration WordPress with WP_VERSION (default: latest release).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

FULL=0
INTEGRATION=0
E2E=0
for arg in "$@"; do
	case "$arg" in
		--integration) INTEGRATION=1 ;;
		--e2e) E2E=1 ;;
		--full) FULL=1; INTEGRATION=1; E2E=1 ;;
		-h|--help)
			echo "Usage: scripts/ci-local.sh [--integration] [--e2e] [--full]"
			echo "  (default)      PHP lint, PHPUnit, JS syntax check and tests, release zip check"
			echo "  --integration  also PHPUnit against a real WordPress (SQLite; WP_VERSION=x.y to pick one)"
			echo "  --e2e          also the Playwright block-editor and Classic Editor tests (SQLite; downloads Chromium once)"
			echo "  --full         everything above plus the WordPress smoke test (MySQL required)"
			exit 0
			;;
		*)
			echo "Unknown option: $arg (try --help)" >&2
			exit 2
			;;
	esac
done

step() { printf '\n==> %s\n' "$1"; }

step "PHP $(php -r 'echo PHP_VERSION;')"

if [ ! -x vendor/bin/phpunit ]; then
	step "Installing Composer dependencies"
	composer install --prefer-dist --no-interaction --no-progress
fi

step "PHP lint"
while IFS= read -r -d '' file; do
	if ! out="$(php -l "$file" 2>&1)"; then
		echo "$out" >&2
		echo "Lint failed: $file" >&2
		exit 1
	fi
done < <(find . -name '*.php' -not -path './vendor/*' -not -path './.wordpress/*' -not -path './.wordpress-integration/*' -not -path '*/node_modules/*' -print0)
echo "OK"

step "JavaScript syntax"
if command -v node >/dev/null 2>&1; then
	for file in js/credits-block.js credits_shortcode_plugin.js .cursor/test-block-editor.mjs .cursor/test-classic-editor.mjs; do
		node --check "$file"
	done
	echo "OK"
else
	echo "node not found; skipping"
fi

step "JavaScript tests"
if command -v node >/dev/null 2>&1; then
	node --test tests/js/*.test.js
else
	echo "node not found; skipping"
fi

step "PHPUnit"
vendor/bin/phpunit

step "WordPress.org release zip"
bash scripts/build-wp-release.sh >/dev/null
rm -f "$ROOT"/credits-shortcode-release.zip
echo "OK"

if [ "$FULL" -eq 1 ]; then
	export WP_DB_HOST="${WP_DB_HOST:-127.0.0.1}"
	export WP_DB_USER="${WP_DB_USER:-root}"
	export WP_DB_PASSWORD="${WP_DB_PASSWORD:-root}"

	step "MySQL preflight"
	if ! command -v mysql >/dev/null 2>&1 || ! mysql -h "$WP_DB_HOST" -u "$WP_DB_USER" -p"$WP_DB_PASSWORD" -e 'SELECT 1' >/dev/null 2>&1; then
		echo "MySQL is not reachable at ${WP_DB_HOST} as ${WP_DB_USER}." >&2
		echo "Install and start it (brew install mysql && brew services start mysql), create a root" >&2
		echo "password of 'root', or set WP_DB_HOST / WP_DB_USER / WP_DB_PASSWORD." >&2
		exit 1
	fi
	mysql -h "$WP_DB_HOST" -u "$WP_DB_USER" -p"$WP_DB_PASSWORD" -e 'CREATE DATABASE IF NOT EXISTS credits_wp' >/dev/null
fi

if [ "$INTEGRATION" -eq 1 ]; then
	step "Integration WordPress setup"
	bash scripts/setup-wordpress-integration.sh

	step "PHPUnit integration (real WordPress)"
	vendor/bin/phpunit -c phpunit-integration.xml.dist
fi

if [ "$E2E" -eq 1 ]; then
	step "Editor e2e (real block editor and Classic Editor)"
	bash scripts/run-editor-e2e.sh
fi

if [ "$FULL" -eq 1 ]; then
	step "WordPress smoke test"
	bash scripts/ci-wordpress-smoke.sh
fi

printf '\nLocal CI passed%s.\n' "$([ "$FULL" -eq 1 ] && echo ' (full)' || echo '')"
