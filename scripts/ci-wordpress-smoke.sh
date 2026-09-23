#!/usr/bin/env bash
# Boot WordPress against a TCP MySQL instance (GitHub Actions service or local).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_DIR="$ROOT/.wordpress"
PLUGIN_LINK="$WP_DIR/wp-content/plugins/credits-shortcode"

DB_HOST="${WP_DB_HOST:-127.0.0.1}"
DB_NAME="${WP_DB_NAME:-credits_wp}"
DB_USER="${WP_DB_USER:-root}"
DB_PASS="${WP_DB_PASSWORD:-root}"
WP_URL="${WP_URL:-http://127.0.0.1:8080}"

mkdir -p "$ROOT/.cursor/bin"
export PATH="$ROOT/.cursor/bin:${PATH}"

if ! command -v wp >/dev/null 2>&1; then
	curl -fsSL -o "$ROOT/.cursor/bin/wp" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	chmod +x "$ROOT/.cursor/bin/wp"
fi

wait_for_mysql() {
	local attempt
	for attempt in $(seq 1 45); do
		if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" >/dev/null 2>&1; then
			return 0
		fi
		sleep 2
	done
	echo "MySQL not reachable at ${DB_HOST} as ${DB_USER}" >&2
	return 1
}

if [ -n "${GITHUB_ACTIONS:-}" ]; then
	rm -rf "$WP_DIR"
fi

mkdir -p "$WP_DIR/wp-content/plugins"

if [ ! -f "$WP_DIR/wp-load.php" ]; then
	wp core download --path="$WP_DIR" --version=6.7 --quiet
fi

wait_for_mysql

if [ ! -f "$WP_DIR/wp-config.php" ]; then
	wp config create \
		--path="$WP_DIR" \
		--dbname="$DB_NAME" \
		--dbuser="$DB_USER" \
		--dbpass="$DB_PASS" \
		--dbhost="$DB_HOST" \
		--skip-check \
		--quiet
fi

if ! wp core is-installed --path="$WP_DIR" 2>/dev/null; then
	wp core install \
		--path="$WP_DIR" \
		--url="$WP_URL" \
		--title="Credits Plugin CI" \
		--admin_user="admin" \
		--admin_password="admin" \
		--admin_email="ci@example.test" \
		--skip-email \
		--quiet
fi

ln -sfn "$ROOT" "$PLUGIN_LINK"

if ! wp plugin is-active credits-shortcode --path="$WP_DIR" 2>/dev/null; then
	wp plugin activate credits-shortcode --path="$WP_DIR" --quiet
fi

echo "WordPress smoke: core ready at ${WP_URL}"
bash "$ROOT/.cursor/cloud-agent-verify.sh"
bash "$ROOT/.cursor/cloud-agent-test-variations.sh"
