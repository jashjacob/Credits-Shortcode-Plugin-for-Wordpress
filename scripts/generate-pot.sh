#!/usr/bin/env bash
# Regenerate languages/credits-shortcode.pot from PHP and block editor JS.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! command -v wp >/dev/null 2>&1; then
	if [ -x "$ROOT/.cursor/bin/wp" ]; then
		export PATH="$ROOT/.cursor/bin:$PATH"
	else
		echo "wp-cli required (install via .cursor/cloud-agent-install.sh or PATH)" >&2
		exit 1
	fi
fi

mkdir -p "$ROOT/languages"

wp i18n make-pot "$ROOT" "$ROOT/languages/credits-shortcode.pot" \
	--domain=credits-shortcode \
	--exclude=vendor,tests,.wordpress,.cursor,node_modules \
	--headers='{"Report-Msgid-Bugs-To":"https://github.com/jashjacob/Credits-Shortcode-Plugin-for-Wordpress/issues"}'

echo "Wrote languages/credits-shortcode.pot"
