#!/usr/bin/env bash
#
# Export demo-site content (plus everything it references) into the Jarvis
# recipe, then strip install-time noise so the recipe reinstalls cleanly from
# GitHub.
#
# Run from the repo root (the directory that holds .ddev/):
#   recipes/jarvis/export.sh node 5
#   recipes/jarvis/export.sh node 5 media 3 menu_link_content 8
#   recipes/jarvis/export.sh --selftest        # prove the cleanup works
#
# Uses CORE's `drush content:export ... --with-dependencies` (NOT contrib
# `dcer`/default-content:export-references). Canvas hooks core's export event to
# emit portable CANVAS_ENTITY_REFERENCE {target_uuid} refs and to drop per-site
# component_version pins; dcer bypasses that subscriber and writes non-portable
# dev-site numeric ids that crash on a fresh recipe apply.
# Re-exporting the same entity reuses its UUID, so diffs stay clean.
set -euo pipefail

# Host path (repo-relative) — used for the sed cleanup below.
DEST="recipes/jarvis/content"

# Absolute path INSIDE the ddev web container for `drush dcer --folder`.
# `ddev drush` runs with its working directory set to the Drupal docroot
# (/var/www/html/web), so a repo-relative --folder would land one level deep
# under web/. The project root mounts at /var/www/html, so target that directly.
CONTAINER_DEST="/var/www/html/recipes/jarvis/content"

# Delete any `pathauto:` line. The recipe ships explicit inline aliases; a stray
# `pathauto: 1` regenerates the alias on install and fights the fixed one.
strip_pathauto() {
  # ponytail: blunt line-delete — no field is legitimately named "pathauto".
  # If that ever changes, switch to a YAML-aware strip.
  find "$DEST" -name '*.yml' -exec sed -i '' '/^[[:space:]]*pathauto:/d' {} +
}

# --- self-test: the only non-trivial logic is the pathauto strip -------------
if [ "${1:-}" = "--selftest" ]; then
  t=$(mktemp)
  printf 'path:\n  -\n    alias: /x\n    pathauto: 1\n' > "$t"
  sed -i '' '/^[[:space:]]*pathauto:/d' "$t"
  if grep -q pathauto "$t"; then echo "SELFTEST FAIL"; rm -f "$t"; exit 1; fi
  echo "SELFTEST OK"; rm -f "$t"; exit 0
fi

if [ "$#" -lt 2 ] || [ $(( $# % 2 )) -ne 0 ]; then
  echo "usage: $0 ENTITY_TYPE ID [ENTITY_TYPE ID ...]" >&2
  echo "       $0 --selftest" >&2
  exit 1
fi

while [ "$#" -gt 0 ]; do
  echo ">> exporting $1 $2"
  ddev drush content:export "$1" "$2" --with-dependencies --dir="$CONTAINER_DEST" -y
  shift 2
done

strip_pathauto
echo ">> stripped pathauto flags"
echo ">> done. review with:  git diff $DEST"
echo ">> MANUAL CHECK: dev-only fields (metatags) and per-site link uris"
echo "   (entity:node/N -> internal:/<alias>) are NOT auto-scrubbed. See"
echo "   [[jarvis-recipe-canvas-gotchas]]."
