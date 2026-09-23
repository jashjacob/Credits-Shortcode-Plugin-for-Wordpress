#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
mkdir -p "$ROOT/.cursor/bin"

if ! command -v php >/dev/null 2>&1; then
	export DEBIAN_FRONTEND=noninteractive
	apt-get update -qq
	apt-get install -y -qq \
		php-cli php-xml php-mbstring php-curl php-mysql php-intl php-zip \
		mariadb-server mariadb-client \
		composer unzip git curl
fi

if ! php -m 2>/dev/null | grep -q '^mysqli$'; then
	export DEBIAN_FRONTEND=noninteractive
	apt-get update -qq
	apt-get install -y -qq php-mysql mariadb-server mariadb-client
fi

if ! command -v wp >/dev/null 2>&1; then
	if [ ! -x "$ROOT/.cursor/bin/wp" ]; then
		curl -fsSL -o "$ROOT/.cursor/bin/wp" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
		chmod +x "$ROOT/.cursor/bin/wp"
	fi
fi

export PATH="$ROOT/.cursor/bin:${PATH}"

composer install --prefer-dist --no-interaction --no-progress
