#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
WP_PATH="$REPO_ROOT/.wordpress"
cd "$SCRIPT_DIR"
export PATH="$REPO_ROOT/.cursor/bin:${PATH}"

if [ -f "$WP_PATH/wp-load.php" ]; then
	wp option update credits_shortcode_settings \
		'{"type":"via","spacing":"standard","badge_color":"","link_color":"","link_text_color":""}' \
		--format=json --path="$WP_PATH" --quiet
	export CREDITS_E2E_DEFAULT_TYPE="$(wp option get credits_shortcode_settings --path="$WP_PATH" --format=json | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo is_array($d)&&isset($d["type"])?$d["type"]:"via";')"
else
	export CREDITS_E2E_DEFAULT_TYPE="${CREDITS_E2E_DEFAULT_TYPE:-via}"
fi
npm install --no-save playwright@1.49.1 >/dev/null 2>&1
npx playwright install chromium >/dev/null 2>&1
node test-block-editor.mjs
