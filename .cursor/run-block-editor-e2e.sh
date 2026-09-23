#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
export PATH="$ROOT/.cursor/bin:${PATH}"
if [ -f "$ROOT/.wordpress/wp-load.php" ]; then
	wp option update credits_shortcode_settings \
		'{"type":"via","spacing":"standard","badge_color":"","link_color":"","link_text_color":""}' \
		--format=json --path="$ROOT/.wordpress" --quiet
fi
npm install --no-save playwright@1.49.1 >/dev/null 2>&1
npx playwright install chromium >/dev/null 2>&1
node test-block-editor.mjs
