<?php

/**
 * @file
 * Re-applies the Canvas entity-autocomplete fix after every composer install.
 *
 * Canvas's client-side code extracts the entity ID from an autocomplete value
 * with an anchored regex that expects the value to end in `)`. When a node
 * title contains a comma or a quote, core's EntityAutocompleteMatcher wraps the
 * WHOLE "Label (123)" string in quotes (Tags::encode), so the value ends in `)"`
 * instead and the match fails -- picking such a node silently yields no link.
 *
 * Canvas 1.10 fixed this in ui/src/utils/transforms.ts but shipped a bundle that
 * was never rebuilt, so the compiled ui/dist/assets/index.js still carries the
 * old regex. The bundle is what the browser runs, so the bug is still live.
 *
 * This used to be a patch. A patch is the wrong tool here: the regex sits on a
 * single 104KB minified line, so cweagans/composer-patches has to context-match
 * that whole line, and any bundle rebuild breaks it -- which, with
 * composer-exit-on-patch-failure, aborts `composer update` outright. The change
 * is one literal string, so do a literal replacement instead: it needs no
 * context, it is idempotent, and once upstream ships a rebuilt bundle the
 * literal is simply absent and this no-ops.
 *
 * Deliberately never fails the build. Worst case is a warning telling you the
 * upstream bundle changed and the fix needs re-checking.
 *
 * @see https://www.drupal.org/project/canvas
 */

declare(strict_types=1);

// The compiled bundle ends the match at `)` where it should tolerate `)"`.
const CANVAS_AUTOCOMPLETE_OLD = '/.+\s\(([^)]+)\)\s*$/';
const CANVAS_AUTOCOMPLETE_NEW = '/.+\s\(([^)]+)\)"?\s*$/';

$root = dirname(__DIR__);
$bundle = $root . '/web/modules/contrib/canvas/ui/dist/assets/index.js';

if (!is_file($bundle)) {
  // Canvas is not installed (or lives elsewhere): nothing to do, not an error.
  exit(0);
}

$js = file_get_contents($bundle);
if ($js === FALSE) {
  fwrite(STDERR, "canvas-autocomplete: could not read $bundle; skipping.\n");
  exit(0);
}

if (str_contains($js, CANVAS_AUTOCOMPLETE_NEW)) {
  echo "canvas-autocomplete: already fixed, nothing to do.\n";
  exit(0);
}

$count = substr_count($js, CANVAS_AUTOCOMPLETE_OLD);
if ($count === 0) {
  fwrite(STDERR, "canvas-autocomplete: WARNING -- neither the old nor the fixed regex was\n"
    . "  found in the Canvas bundle. Upstream probably rebuilt or refactored it.\n"
    . "  Re-check the quoted-label bug: pick a node whose title contains a comma\n"
    . "  in a Canvas link prop and confirm the link resolves.\n"
    . "  File: $bundle\n");
  exit(0);
}

if ($count > 1) {
  // Only ever seen once. More than one means the bundle changed shape enough
  // that a blind replacement is no longer obviously safe.
  fwrite(STDERR, "canvas-autocomplete: WARNING -- found $count occurrences, expected 1.\n"
    . "  Skipping to avoid an unsafe blanket replacement. File: $bundle\n");
  exit(0);
}

$patched = str_replace(CANVAS_AUTOCOMPLETE_OLD, CANVAS_AUTOCOMPLETE_NEW, $js);
if (file_put_contents($bundle, $patched) === FALSE) {
  fwrite(STDERR, "canvas-autocomplete: WARNING -- could not write $bundle; fix NOT applied.\n");
  exit(0);
}

echo "canvas-autocomplete: applied (entity autocomplete now tolerates quoted labels).\n";
exit(0);
