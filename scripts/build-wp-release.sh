#!/usr/bin/env bash
# Build a WordPress.org-style plugin zip (respects .distignore).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="credits-shortcode"
STAGING="$(mktemp -d)"
TRAP_DIR="$STAGING"
trap 'rm -rf "$TRAP_DIR"' EXIT

DEST="$STAGING/$SLUG"
mkdir -p "$DEST"

RSYNC_EXCLUDES=(--exclude '.git/' --exclude "$SLUG/")
while IFS= read -r line || [ -n "$line" ]; do
	line="${line%%#*}"
	line="$(echo "$line" | xargs)"
	[ -z "$line" ] && continue
	RSYNC_EXCLUDES+=(--exclude "$line")
done < "$ROOT/.distignore"

rsync -a "${RSYNC_EXCLUDES[@]}" "$ROOT/" "$DEST/"

OUT="$ROOT/${SLUG}-release.zip"
rm -f "$OUT"
(cd "$STAGING" && zip -rq "$OUT" "$SLUG")

echo "Built $OUT"
echo "Contents:"
unzip -l "$OUT" | tail -n +4 | head -n -2

if unzip -l "$OUT" | grep -q '\.cursor/'; then
	echo "ERROR: .cursor still in zip" >&2
	exit 1
fi

echo "OK: no .cursor/ in release zip"
