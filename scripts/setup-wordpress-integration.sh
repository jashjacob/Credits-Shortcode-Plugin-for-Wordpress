#!/usr/bin/env bash
# Install a disposable WordPress backed by SQLite (no MySQL or Docker needed) for
# the integration suite. Idempotent; each WordPress version gets its own directory.
#
#   WP_VERSION=6.3 bash scripts/setup-wordpress-integration.sh   # a specific version
#   bash scripts/setup-wordpress-integration.sh                  # latest release
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE="$ROOT/.wordpress-integration"
mkdir -p "$BASE" "$ROOT/.cursor/bin"
WP_CLI_PHAR="$ROOT/.cursor/bin/wp"

if [ ! -f "$WP_CLI_PHAR" ]; then
	curl -fsSL -o "$WP_CLI_PHAR" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	chmod +x "$WP_CLI_PHAR"
fi

# The default 128M CLI memory limit is too small for wp-cli to unpack WordPress.
wp() { php -d memory_limit=512M -d error_reporting=8191 "$WP_CLI_PHAR" "$@"; }

WP_VERSION="${WP_VERSION:-latest}"
if [ "$WP_VERSION" = "latest" ]; then
	WP_VERSION="$(curl -fsS --max-time 15 https://api.wordpress.org/core/version-check/1.7/ 2>/dev/null \
		| php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["offers"][0]["version"] ?? "";' 2>/dev/null || true)"
	if [ -z "$WP_VERSION" ] && [ -L "$BASE/current" ]; then
		WP_VERSION="$(basename "$(readlink "$BASE/current")")"
	fi
	if [ -z "$WP_VERSION" ]; then
		echo "Could not resolve the latest WordPress version (offline?). Set WP_VERSION=x.y." >&2
		exit 1
	fi
fi

WP_DIR="$BASE/$WP_VERSION"
PLUGINS_DIR="$WP_DIR/wp-content/plugins"
SQLITE_DIR="$PLUGINS_DIR/sqlite-database-integration"

if [ ! -f "$WP_DIR/wp-load.php" ]; then
	wp core download --path="$WP_DIR" --version="$WP_VERSION" --quiet
fi

if [ ! -f "$SQLITE_DIR/load.php" ]; then
	tmp="$(mktemp -d)"
	curl -fsSL -o "$tmp/sqlite.zip" https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
	unzip -q "$tmp/sqlite.zip" -d "$PLUGINS_DIR"
	rm -rf "$tmp"
fi

if [ ! -f "$WP_DIR/wp-content/db.php" ]; then
	sed \
		-e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#${SQLITE_DIR}#g" \
		-e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" \
		"$SQLITE_DIR/db.copy" > "$WP_DIR/wp-content/db.php"
fi

if [ ! -f "$WP_DIR/wp-config.php" ]; then
	wp config create \
		--path="$WP_DIR" \
		--dbname=wordpress --dbuser=unused --dbpass=unused --dbhost=localhost \
		--skip-check --quiet
	wp config set WP_DEBUG true --raw --path="$WP_DIR" --quiet
	wp config set WP_DEBUG_DISPLAY false --raw --path="$WP_DIR" --quiet
	wp config set WP_DEBUG_LOG true --raw --path="$WP_DIR" --quiet
fi

if ! wp core is-installed --path="$WP_DIR" 2>/dev/null; then
	wp core install \
		--path="$WP_DIR" \
		--url="http://localhost" \
		--title="Credits Integration" \
		--admin_user="admin" \
		--admin_password="admin" \
		--admin_email="integration@example.test" \
		--skip-email \
		--quiet
fi

ln -sfn "$ROOT" "$PLUGINS_DIR/credits-shortcode"
if ! wp plugin is-active credits-shortcode --path="$WP_DIR" 2>/dev/null; then
	wp plugin activate credits-shortcode --path="$WP_DIR" --quiet
fi

# Start every run from a clean settings state.
wp option delete credits_shortcode_settings --path="$WP_DIR" >/dev/null 2>&1 || true

ln -sfn "$WP_DIR" "$BASE/current"
echo "WordPress $(wp core version --path="$WP_DIR") ready for integration tests at $WP_DIR"
