<?php

/**
 * @file
 * Fails if a shipped Canvas pattern references an entity by hard-coded ID.
 *
 * Entity IDs are install-order dependent. A pattern that stores
 * `inputs.<prop>.target_id: '19'` lands on whatever entity happens to own that
 * ID — on recipes/jarvis-existing, applied to a site that already has content,
 * that is arbitrary. When the entity is of the wrong bundle, the prop's
 * expression throws DomainException and Canvas 1.13's Health Doctor logs it on
 * every cron run.
 *
 * Patterns are a starting point an author customises, so the shipped tree must
 * leave such props empty. Re-exporting a pattern from a live site reintroduces
 * the IDs, which is how this got shipped in the first place — hence this check.
 *
 * Usage (from the repository root):
 *   php scripts/check-pattern-media-refs.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$files = glob($root . '/recipes/*/config/canvas.pattern.*.yml');
$bad = [];

foreach ($files as $file) {
  foreach (file($file, FILE_IGNORE_NEW_LINES) as $i => $line) {
    if (preg_match('/^\s*target_id:/', $line)) {
      $bad[] = sprintf('%s:%d:%s', substr($file, strlen($root) + 1), $i + 1, trim($line));
    }
  }
}

if ($bad) {
  fwrite(STDERR, "Hard-coded entity reference in a shipped pattern:\n  " . implode("\n  ", $bad) . "\n");
  fwrite(STDERR, "Remove the prop from the pattern's component tree; the author picks the entity on insert.\n");
  exit(1);
}

printf("OK: %d pattern(s) reference no entity by ID.\n", count($files));
