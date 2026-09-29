#!/usr/bin/env bash
# Local CI. Fast tier (default): lint, PHPUnit, JS syntax, release zip.
# --integration adds the PHPUnit suite against a real WordPress (SQLite, no MySQL).
# --full adds that plus the WordPress smoke test and block-editor e2e (needs MySQL).
# Pick the integration WordPress with WP_VERSION (default: latest release).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

FULL=0
INTEGRATION=0
for arg in "$@"; do
	case "$arg" in
		--integration) INTEGRATION=1 ;;
		--full) FULL=1; INTEGRATION=1 ;;
		-h|--help)
			echo "Usage: scripts/ci-local.sh [--integration] [--full]"
			echo "  (default)      PHP lint, PHPUnit, JS syntax check, release zip check"
			echo "  --integration  also PHPUnit against a real WordPress (SQLite; WP_VERSION=x.y to pick one)"
			echo "  --full         also the WordPress smoke test + block-editor e2e (MySQL required)"
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
	for file in js/credits-block.js credits_shortcode_plugin.js .cursor/test-block-editor.mjs; do
		node --check "$file"
	done
	echo "OK"
else
	echo "node not found; skipping"
fi

step "PHPUnit"
vendor/bin/phpunit

step "WordPress.org release zip"
bash scripts/build-wp-release.sh >/dev/null
rm -f "$ROOT"/credits-shortcode-release.zip
echo "OK"

if [ "$INTEGRATION" -eq 1 ]; then
	step "Integration WordPress setup"
	bash scripts/setup-wordpress-integration.sh

	step "PHPUnit integration (real WordPress)"
	vendor/bin/phpunit -c phpunit-integration.xml.dist
fi

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

	step "WordPress smoke test"
	bash scripts/ci-wordpress-smoke.sh

	step "Block editor e2e"
	export PATH="$ROOT/.cursor/bin:$PATH"
	wp server --host=127.0.0.1 --port=8080 --path="$ROOT/.wordpress" >/dev/null 2>&1 &
	WP_SERVER_PID=$!
	trap 'kill "$WP_SERVER_PID" 2>/dev/null || true' EXIT
	for _ in $(seq 1 30); do
		curl -fsS -o /dev/null http://127.0.0.1:8080/wp-login.php && break
		sleep 1
	done
	bash .cursor/run-block-editor-e2e.sh
fi

printf '\nLocal CI passed%s.\n' "$([ "$FULL" -eq 1 ] && echo ' (full)' || echo '')"
