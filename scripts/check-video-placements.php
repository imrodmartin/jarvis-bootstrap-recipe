<?php

/**
 * @file
 * Finds video placements that theme 3.0 stopped rendering.
 *
 * Run against a site BEFORE upgrading it to Jarvis 3.0:
 *
 *   drush php:script scripts/check-video-placements.php
 *
 * From 3.0 the video components' `video_url` prop is files only. A hosted
 * player has to come from the Remote video media field instead, which is what
 * restricts the iframe to providers core's oEmbed registry already accepts.
 * A pasted YouTube or Vimeo URL left in `video_url` renders nothing at all --
 * silently, with no error anywhere.
 *
 * This applies even to placements pinned to an older component version: the
 * pin fixes the prop schema, but Twig always renders from the current file.
 *
 * Every hit needs its video re-added through the media library as a Remote
 * video, then selected in the component's "Remote video" field.
 *
 * Scans canvas pages (and anything else carrying a component_tree field) plus
 * patterns, content templates and page regions.
 */

declare(strict_types=1);

$hits = 0;

$check = function (string $where, string $component_id, mixed $inputs) use (&$hits): void {
  if (!in_array($component_id, ['sdc.jarvis.video', 'sdc.jarvis.video-with-sidebar'], TRUE)) {
    return;
  }
  // Content trees store inputs as a JSON string; config trees store an array.
  $decoded = is_string($inputs) ? json_decode($inputs, TRUE) : $inputs;
  if (!is_array($decoded)) {
    return;
  }
  $url = $decoded['video_url'] ?? NULL;
  // A link-field value arrives as ['uri' => ...].
  $url = is_array($url) ? ($url['uri'] ?? NULL) : $url;
  if (!is_string($url) || $url === '') {
    return;
  }
  // A real media file still works; only hosted players are affected.
  if (preg_match('/\.(mp4|webm|ogv|ogg)($|[?#])/i', $url)) {
    return;
  }
  $hits++;
  printf("  %-38s %s\n", $where, $url);
};

$etm = \Drupal::entityTypeManager();

foreach (\Drupal::service('entity_field.manager')->getFieldMapByFieldType('component_tree') as $entity_type_id => $fields) {
  foreach ($etm->getStorage($entity_type_id)->loadMultiple() as $entity) {
    foreach (array_keys($fields) as $field_name) {
      if (!$entity->hasField($field_name)) {
        continue;
      }
      foreach ($entity->get($field_name) as $item) {
        $value = $item->getValue();
        $check("$entity_type_id/{$entity->id()}", $value['component_id'] ?? '', $value['inputs'] ?? []);
      }
    }
  }
}

foreach (['pattern', 'content_template', 'page_region'] as $type) {
  if (!$etm->hasDefinition($type)) {
    continue;
  }
  foreach ($etm->getStorage($type)->loadMultiple() as $entity) {
    foreach ((array) $entity->get('component_tree') as $item) {
      $check("$type/{$entity->id()}", $item['component_id'] ?? '', $item['inputs'] ?? []);
    }
  }
}

echo $hits === 0
  ? "\nNo affected video placements.\n"
  : "\n$hits placement(s) will render NOTHING on Jarvis 3.0. Re-add each video via the "
    . "media library as a Remote video and pick it in the component's 'Remote video' field.\n";
return 0;
