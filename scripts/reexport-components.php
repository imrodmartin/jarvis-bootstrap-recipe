<?php

/**
 * @file
 * Re-export Jarvis SDC component config into both recipes after a theme bump.
 *
 *   ddev drush scr scripts/reexport-components.php -- card share
 *
 * Writes the dev site's active version for each component and keeps every
 * version the recipe already shipped as history, so content and templates
 * pinned to an older version still validate on a fresh install.
 */

use Drupal\Component\Serialization\Yaml;

$ids = array_slice($extra ?? $_SERVER['argv'] ?? [], 0);
$ids = array_values(array_filter($ids, fn($a) => preg_match('/^[a-z0-9-]+$/', $a)));
if (!$ids) {
  echo "Usage: drush scr scripts/reexport-components.php -- <component> [...]\n";
  return;
}
foreach ($ids as $id) {
  $name = "canvas.component.sdc.jarvis.$id";
  $site = \Drupal::config($name)->getRawData();
  if (!$site) {
    echo "!! $name not on this site\n";
    continue;
  }
  unset($site['uuid'], $site['_core']);
  foreach (['jarvis', 'jarvis-existing'] as $recipe) {
    $file = DRUPAL_ROOT . "/../recipes/$recipe/config/$name.yml";
    $out = $site;
    if (file_exists($file)) {
      $shipped = Yaml::decode(file_get_contents($file));
      $history = $shipped['versioned_properties'];
      $history[$shipped['active_version']] = $history['active'];
      unset($history['active'], $history[$site['active_version']]);
      $out['versioned_properties'] = ['active' => $site['versioned_properties']['active']] + $history;
    }
    file_put_contents($file, Yaml::encode($out));
    echo "$recipe: $name -> {$site['active_version']} (" . (count($out['versioned_properties']) - 1) . " past versions)\n";
  }
}
