#!/usr/bin/env bash
# Playwright block-editor e2e against the SQLite integration WordPress (no MySQL needed).
# The first run downloads Playwright and Chromium.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PORT="${E2E_PORT:-8080}"
URL="http://127.0.0.1:${PORT}"

bash scripts/setup-wordpress-integration.sh
WP_DIR="$(cd "$ROOT/.wordpress-integration/current" && pwd -P)"

wp() { php -d memory_limit=512M -d error_reporting=8191 "$ROOT/.cursor/bin/wp" --path="$WP_DIR" "$@"; }

SERVER_PID=""
cleanup() {
	[ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true
	wp post delete $(wp post list --post_type=post --s="Credits editor e2e" --format=ids 2>/dev/null) --force >/dev/null 2>&1 || true
	wp option delete credits_shortcode_settings >/dev/null 2>&1 || true
	wp option update siteurl "http://localhost" >/dev/null 2>&1 || true
	wp option update home "http://localhost" >/dev/null 2>&1 || true
}
trap cleanup EXIT

wp option update siteurl "$URL" >/dev/null
wp option update home "$URL" >/dev/null
wp option update credits_shortcode_settings \
	'{"type":"via","spacing":"standard","badge_color":"","link_color":"","link_text_color":""}' \
	--format=json >/dev/null

wp server --host=127.0.0.1 --port="$PORT" >/dev/null 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 30); do
	curl -fsS -o /dev/null "$URL/wp-login.php" 2>/dev/null && break
	sleep 1
done

cd "$ROOT/.cursor"
npm install --no-save playwright@1.49.1 >/dev/null 2>&1
npx playwright install chromium >/dev/null 2>&1
CREDITS_E2E_BASE="$URL" CREDITS_E2E_DEFAULT_TYPE=via node test-block-editor.mjs
