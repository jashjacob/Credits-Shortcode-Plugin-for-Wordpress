#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_DIR="$ROOT/.wordpress"
PLUGIN_LINK="$WP_DIR/wp-content/plugins/credits-shortcode"
DB_NAME="credits_wp"
DB_USER="credits_wp"
DB_PASS="credits_wp_local"
WP_URL="http://127.0.0.1:8080"

export PATH="$ROOT/.cursor/bin:${PATH}"

if ! command -v wp >/dev/null 2>&1; then
	echo "wp-cli missing; run ./.cursor/cloud-agent-install.sh first" >&2
	exit 1
fi

if ! sudo service mariadb status >/dev/null 2>&1; then
	sudo service mariadb start
fi

sudo mysql --protocol=socket -e "
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
"

mkdir -p "$WP_DIR/wp-content/plugins"

if [ ! -f "$WP_DIR/wp-load.php" ]; then
	wp core download --path="$WP_DIR" --version=6.7 --quiet
fi

if [ ! -f "$WP_DIR/wp-config.php" ]; then
	wp config create \
		--path="$WP_DIR" \
		--dbname="$DB_NAME" \
		--dbuser="$DB_USER" \
		--dbpass="$DB_PASS" \
		--dbhost="127.0.0.1" \
		--skip-check \
		--quiet
fi

if ! wp core is-installed --path="$WP_DIR" 2>/dev/null; then
	wp core install \
		--path="$WP_DIR" \
		--url="$WP_URL" \
		--title="Credits Plugin Dev" \
		--admin_user="admin" \
		--admin_password="admin" \
		--admin_email="dev@example.test" \
		--skip-email \
		--quiet
fi

ln -sfn "$ROOT" "$PLUGIN_LINK"

if ! wp plugin is-active credits-shortcode --path="$WP_DIR" 2>/dev/null; then
	wp plugin activate credits-shortcode --path="$WP_DIR" --quiet
fi

echo "WordPress ready at ${WP_URL} (admin / admin)"
