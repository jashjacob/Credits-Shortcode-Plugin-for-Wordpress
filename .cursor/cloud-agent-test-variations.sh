#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP="$ROOT/.wordpress"
if [ ! -f "$WP/wp-load.php" ]; then
	echo "WordPress not initialized. Run ./.cursor/cloud-agent-start.sh first." >&2
	exit 1
fi
cd "$WP"
php -r 'require "wp-load.php"; include "../.cursor/test-credit-variations.php";'
