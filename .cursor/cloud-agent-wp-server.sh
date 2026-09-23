#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_DIR="$ROOT/.wordpress"

export PATH="$ROOT/.cursor/bin:${PATH}"

"$ROOT/.cursor/cloud-agent-start.sh"

exec wp server --host=127.0.0.1 --port=8080 --path="$WP_DIR"
