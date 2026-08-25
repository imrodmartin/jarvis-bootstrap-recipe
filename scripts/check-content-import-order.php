<?php

/**
 * @file
 * Verifies a recipe's demo content can actually be imported, in order.
 *
 * Run with `drush php:script`, optionally passing a content directory:
 *
 *   ddev drush php:script scripts/check-content-import-order.php
 *   ddev drush php:script scripts/check-content-import-order.php -- /abs/path/content
 *
 * A Canvas component input that points at an entity is exported as
 * `{CANVAS_ENTITY_REFERENCE: {target_uuid, target_type}}`, and on import
 * Canvas's DefaultContentSubscriber resolves that uuid and asserts the result
 * is an entity. Core decides the import order from each file's `_meta.depends`
 * and nothing else, so a reference that is not ALSO listed there gets resolved
 * before the target exists: loadEntityByUuid() returns NULL, the assert fires,
 * and the whole recipe aborts on an AssertionError that names no uuid.
 *
 * `drush content:export --with-dependencies` writes depends correctly. Hand
 * edits are what drift -- swapping a component input from a plain string to a
 * media reference is exactly the edit that forgets it, and that shipped as
 * jarvis-recipe v3.0.0, which fataled on every install.
 *
 * Cheap enough to run before every tag. It is the ordering half of the release
 * check; scripts/../hashcheck covers component active_version drift, which is a
 * different failure and does not imply this one passes.
 */

declare(strict_types=1);

use Drupal\Core\DefaultContent\Finder;

$dir = $extra[0] ?? DRUPAL_ROOT . '/../recipes/jarvis/content';
if (!is_dir($dir)) {
  echo "No such content directory: $dir\n";
  return 1;
}

$finder = new Finder($dir);
// Finder returns the entities already sorted into a safe import order, keyed by
// uuid. A target has to appear earlier in that order than whatever points at it.
$position = array_flip(array_keys($finder->data));

$problems = 0;
foreach ($finder->data as $uuid => $entity) {
  // $finder->data holds decoded arrays, not entity objects.
  $json = json_encode($entity);
  if (!str_contains($json, 'CANVAS_ENTITY_REFERENCE')) {
    continue;
  }
  preg_match_all('/"target_uuid":"([0-9a-f-]+)"/', $json, $matches);
  foreach (array_unique($matches[1]) as $target) {
    if (!isset($position[$target])) {
      $problems++;
      printf("FAIL %s references %s, which is not in the content set at all\n",
        substr($uuid, 0, 8), substr($target, 0, 8));
    }
    elseif ($position[$target] > $position[$uuid]) {
      $problems++;
      printf("FAIL %s references %s, which imports LATER -- add it to _meta.depends\n",
        substr($uuid, 0, 8), substr($target, 0, 8));
    }
  }
}

printf("\n%d entities checked. %s\n", count($position), $problems === 0
  ? 'Every component entity reference is created before it is used.'
  : "$problems ordering problem(s) -- this recipe will fatal on install.");
return $problems === 0 ? 0 : 1;
