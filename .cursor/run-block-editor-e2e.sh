#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
npm install --no-save playwright@1.49.1 >/dev/null 2>&1
npx playwright install chromium >/dev/null 2>&1
node test-block-editor.mjs
