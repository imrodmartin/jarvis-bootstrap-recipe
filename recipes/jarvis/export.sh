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

# Warn if a <drupal-media data-entity-uuid="..."> embedded in a rich-text prop
# lacks a shipped media/<uuid>.yml. Those refs are plain HTML strings, invisible
# to `content:export --with-dependencies`, so the media never ships and apply
# dies with "media item with UUID ... does not exist". Returns 1 if any missing.
audit_embedded_media() {
  local dir="$1" missing=0 u
  for u in $(grep -rho 'data-entity-uuid=[^ ]*' "$dir" 2>/dev/null \
      | grep -oE '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}' \
      | sort -u); do
    if [ ! -f "$dir/media/$u.yml" ]; then
      echo "!! MISSING embedded media: $u  (fix: $0 media <id>)" >&2
      missing=1
    fi
  done
  return $missing
}

# --- self-test: pathauto strip + embedded-media audit ------------------------
if [ "${1:-}" = "--selftest" ]; then
  t=$(mktemp -d); m=c55aa556-9054-40f9-9ce2-20662a5d6c82
  # pathauto strip removes the line
  printf 'path:\n  -\n    alias: /x\n    pathauto: 1\n' > "$t/a.yml"
  sed -i '' '/^[[:space:]]*pathauto:/d' "$t/a.yml"
  grep -q pathauto "$t/a.yml" && { echo "SELFTEST FAIL (pathauto)"; rm -rf "$t"; exit 1; }
  # audit flags an embedded uuid with no media yml...
  mkdir -p "$t/media"
  printf 'value: %s\n' "'<drupal-media data-entity-uuid=\"$m\">'" > "$t/b.yml"
  if audit_embedded_media "$t" 2>/dev/null; then echo "SELFTEST FAIL (audit missed)"; rm -rf "$t"; exit 1; fi
  # ...and passes once the media yml is shipped
  : > "$t/media/$m.yml"
  audit_embedded_media "$t" 2>/dev/null || { echo "SELFTEST FAIL (audit false +)"; rm -rf "$t"; exit 1; }
  echo "SELFTEST OK"; rm -rf "$t"; exit 0
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

if audit_embedded_media "$DEST"; then
  echo ">> embedded-media audit: all data-entity-uuid refs shipped ✓"
else
  echo ">> embedded-media audit: export the media listed above before committing" >&2
fi

echo ">> done. review with:  git diff $DEST"
echo ">> MANUAL CHECK: dev-only fields (metatags) and per-site link uris"
echo "   (entity:node/N -> internal:/<alias>) are NOT auto-scrubbed. See"
echo "   [[jarvis-recipe-canvas-gotchas]]."
