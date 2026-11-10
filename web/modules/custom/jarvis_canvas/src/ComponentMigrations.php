<?php

declare(strict_types=1);

namespace Drupal\jarvis_canvas;

use Drupal\canvas\ComponentSource\ComponentSourceManager;

/**
 * Brings stored Canvas component trees in line with Jarvis 4.0.
 *
 * Two things changed in 4.0 that content saved under 3.x still refers to:
 *  - the Section component was removed in favor of 1 Column;
 *  - the `variant` prop on Button, Card and Person was renamed, because Drupal
 *    core reserves that name for its own component variants.
 *
 * Both conversions run from hook_rebuild(), not from update hooks. Canvas only
 * registers a changed SDC on cache rebuild and deliberately refuses to do so
 * inside `drush updatedb` (ComponentSourceManager::generateComponents() returns
 * early under the update kernel). An update hook therefore still sees the OLD
 * component versions, and Canvas rejects inputs written for the new ones.
 * Cache rebuild is also the one step every upgrade path runs, whether the
 * theme arrived by composer, git or a copied folder.
 */
final class ComponentMigrations {

  /**
   * Component ID => the name its `variant` prop has from Jarvis 4.0.
   */
  private const RENAMED_VARIANT = [
    'sdc.jarvis.button' => 'style',
    'sdc.jarvis.card' => 'layout',
    'sdc.jarvis.person' => 'layout',
  ];

  /**
   * Runs whichever conversions this site still needs.
   *
   * Cheap once both are done: a state read and a config load.
   */
  public static function run(): void {
    $etm = \Drupal::entityTypeManager();
    if (!$etm->hasDefinition('component')) {
      return;
    }
    // Both conversions are finished on this site: nothing more to do, ever.
    if (\Drupal::state()->get('jarvis_canvas.variant_renamed') && !$etm->getStorage('component')->load('sdc.jarvis.section')) {
      return;
    }
    // Make sure Canvas has registered the components that are on disk now, in
    // case this hook runs before Canvas's own. A no-op under the update kernel.
    \Drupal::service(ComponentSourceManager::class)->generateComponents();
    $etm->getStorage('component')->resetCache();
    self::sectionToOneColumn();
    self::renameVariantProp();
  }

  /**
   * Converts every placed Section into the equivalent 1 Column.
   *
   * Triggers when Section is still stored but the theme no longer ships it.
   */
  private static function sectionToOneColumn(): void {
    $components = \Drupal::entityTypeManager()->getStorage('component');
    $section = $components->load('sdc.jarvis.section');
    $sdc = \Drupal::service('plugin.manager.sdc');
    // Nothing stored, or the site still runs a theme that ships Section.
    if (!$section || $sdc->hasDefinition('jarvis:section') || !$sdc->hasDefinition('jarvis:one-column')) {
      return;
    }
    $target = $components->load('sdc.jarvis.one-column');
    if (!$target) {
      return;
    }
    $version = $target->getActiveVersion();
    $has_no_padding = isset($target->getSettings()['prop_field_definitions']['no_padding']);
    // The code on disk declares no_padding but Canvas's active version does
    // not: Canvas has not caught up yet. That is the cache rebuild at the end
    // of `drush updatedb`. Wait for the next ordinary rebuild rather than
    // convert against a stale shape.
    if (!$has_no_padding && isset($sdc->getDefinition('jarvis:one-column')['props']['properties']['no_padding'])) {
      return;
    }
    $count = self::rewrite(
      ['sdc.jarvis.section'],
      fn(string $id, array $inputs): array => [
        'component_id' => 'sdc.jarvis.one-column',
        'component_version' => $version,
        'inputs' => self::mapSectionInputs($inputs, $has_no_padding),
      ],
      // Section's one slot was "content"; 1 Column's is "first".
      ['content' => 'first'],
    );
    if ($count === NULL) {
      return;
    }
    // Nothing references it now, and with it gone this method is a no-op.
    $section->delete();
    \Drupal::logger('jarvis_canvas')->notice('Converted @count Section component(s) to 1 Column.', ['@count' => $count]);
  }

  /**
   * Renames the stored `variant` input on Button, Card and Person.
   *
   * Runs once, then records that in state. It waits until Canvas's active
   * version of each component carries the new prop name, so it does nothing on
   * a site that updated this module but still runs a 3.x theme.
   */
  private static function renameVariantProp(): void {
    $state = \Drupal::state();
    if ($state->get('jarvis_canvas.variant_renamed')) {
      return;
    }
    $components = \Drupal::entityTypeManager()->getStorage('component');
    $versions = [];
    foreach (self::RENAMED_VARIANT as $id => $prop) {
      $component = $components->load($id);
      if (!$component) {
        continue;
      }
      if (!isset($component->getSettings()['prop_field_definitions'][$prop])) {
        return;
      }
      $versions[$id] = $component->getActiveVersion();
    }
    if (!$versions) {
      return;
    }
    $count = self::rewrite(
      array_keys($versions),
      function (string $id, array $inputs) use ($versions): ?array {
        if (!array_key_exists('variant', $inputs)) {
          return NULL;
        }
        $prop = self::RENAMED_VARIANT[$id];
        $renamed = [];
        // Rebuilt key by key so the input keeps its place in the order.
        foreach ($inputs as $key => $value) {
          if ($key === 'variant') {
            // A value already saved under the new name wins.
            $renamed += [$prop => $inputs[$prop] ?? $value];
          }
          else {
            $renamed[$key] = $value;
          }
        }
        return ['component_id' => $id, 'component_version' => $versions[$id], 'inputs' => $renamed];
      },
    );
    if ($count === NULL) {
      return;
    }
    $state->set('jarvis_canvas.variant_renamed', TRUE);
    if ($count) {
      \Drupal::logger('jarvis_canvas')->notice('Renamed the variant prop on @count Button, Card and Person component(s).', ['@count' => $count]);
    }
  }

  /**
   * Rewrites stored component instances everywhere Canvas keeps a tree.
   *
   * Covers content entities (every revision) and the config entities that
   * hold a tree: content templates, page regions and patterns. Content rows
   * are rewritten in the field tables directly: an old revision left on a
   * shape its component no longer accepts cannot be reverted to or previewed.
   *
   * All or nothing: everything runs in one transaction, so a failure cannot
   * leave pages holding inputs their component rejects.
   *
   * @param string[] $component_ids
   *   The stored component IDs to visit.
   * @param callable $rewrite
   *   Receives (string $component_id, array $inputs). Returns an array with
   *   component_id, component_version and inputs, or NULL to leave the
   *   instance alone.
   * @param array<string, string> $slot_renames
   *   Old slot name => new slot name, applied to the children of every
   *   instance that was rewritten.
   *
   * @return int|null
   *   How many instances were rewritten, or NULL when it failed and was
   *   rolled back.
   */
  private static function rewrite(array $component_ids, callable $rewrite, array $slot_renames = []): ?int {
    $etm = \Drupal::entityTypeManager();
    $db = \Drupal::database();
    $count = 0;
    $transaction = $db->startTransaction();
    try {
      $field_manager = \Drupal::service('entity_field.manager');
      foreach ($field_manager->getFieldMapByFieldType('component_tree') as $entity_type_id => $fields) {
        $storage = $etm->getStorage($entity_type_id);
        $mapping = $storage->getTableMapping();
        $definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
        foreach (array_keys($fields) as $field_name) {
          $definition = $definitions[$field_name];
          $col = fn(string $property) => $mapping->getFieldColumnName($definition, $property);
          $tables = [$mapping->getDedicatedDataTableName($definition)];
          if ($storage->getEntityType()->isRevisionable()) {
            $tables[] = $mapping->getDedicatedRevisionTableName($definition);
          }
          foreach (array_filter($tables, [$db->schema(), 'tableExists']) as $table) {
            $rows = $db->select($table, 't')
              ->fields('t', ['entity_id', 'revision_id', 'langcode', 'delta', $col('uuid'), $col('component_id'), $col('inputs')])
              ->condition($col('component_id'), $component_ids, 'IN')
              ->execute()
              ->fetchAll();
            $parents = [];
            foreach ($rows as $row) {
              $new = $rewrite($row->{$col('component_id')}, json_decode($row->{$col('inputs')}, TRUE) ?: []);
              if ($new === NULL) {
                continue;
              }
              $db->update($table)
                ->fields([
                  $col('component_id') => $new['component_id'],
                  $col('component_version') => $new['component_version'],
                  $col('inputs') => json_encode($new['inputs']),
                ])
                ->condition('entity_id', $row->entity_id)
                ->condition('revision_id', $row->revision_id)
                ->condition('langcode', $row->langcode)
                ->condition('delta', $row->delta)
                ->execute();
              $parents[$row->{$col('uuid')}] = TRUE;
              $count++;
            }
            if ($parents) {
              foreach ($slot_renames as $old => $new_slot) {
                $db->update($table)
                  ->fields([$col('slot') => $new_slot])
                  ->condition($col('parent_uuid'), array_keys($parents), 'IN')
                  ->condition($col('slot'), $old)
                  ->execute();
              }
            }
          }
        }
        $storage->resetCache();
      }

      foreach (['content_template', 'page_region', 'pattern'] as $entity_type_id) {
        if (!$etm->hasDefinition($entity_type_id)) {
          continue;
        }
        foreach ($etm->getStorage($entity_type_id)->loadMultiple() as $entity) {
          $tree = $entity->get('component_tree') ?: [];
          $parents = [];
          foreach ($tree as $key => $item) {
            if (!in_array($item['component_id'] ?? '', $component_ids, TRUE)) {
              continue;
            }
            $new = $rewrite($item['component_id'], $item['inputs'] ?? []);
            if ($new === NULL) {
              continue;
            }
            $tree[$key] = $new + $item;
            $parents[$item['uuid']] = TRUE;
            $count++;
          }
          if (!$parents) {
            continue;
          }
          foreach ($tree as $key => $item) {
            $slot = $item['slot'] ?? '';
            if (isset($parents[$item['parent_uuid'] ?? '']) && isset($slot_renames[$slot])) {
              $tree[$key]['slot'] = $slot_renames[$slot];
            }
          }
          $entity->set('component_tree', $tree)->save();
        }
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      \Drupal::logger('jarvis_canvas')->error('Jarvis 4.0 component conversion failed and was rolled back: @m', ['@m' => $e->getMessage()]);
      return NULL;
    }
    unset($transaction);
    return $count;
  }

  /**
   * Maps a Section's stored inputs onto 1 Column's props.
   *
   * The differences are names and vocabularies only:
   *  - width "normal" is 1 Column's "boxed";
   *  - "bg" is "background", and Bootstrap's body-tertiary (a light grey with
   *    no theme equivalent) becomes the theme's own Light;
   *  - a colored Section had no padding of its own, while a colored 1 Column
   *    adds 2rem top and bottom by default. Where the Section asked for no
   *    vertical padding at all, no_padding keeps it flush, so a full-bleed
   *    image inside a dark band still touches the band's edges.
   *
   * @param array $in
   *   The Section's stored inputs.
   * @param bool $has_no_padding
   *   Whether the site's 1 Column declares no_padding.
   */
  public static function mapSectionInputs(array $in, bool $has_no_padding): array {
    $background = ['light' => 'light', 'dark' => 'dark', 'primary' => 'primary', 'body-tertiary' => 'light'][$in['bg'] ?? 'none'] ?? 'none';
    $vertical = !empty($in['vertical_padding']);
    $out = [
      'width' => ($in['width'] ?? 'normal') === 'full' ? 'full' : 'boxed',
      'background' => $background,
      'gap' => 'normal',
      'vertical_padding' => $vertical && empty($in['no_padding']),
    ];
    foreach (['padding', 'padding_size', 'margin', 'margin_size'] as $key) {
      if (isset($in[$key])) {
        $out[$key] = $in[$key];
      }
    }
    if ($has_no_padding) {
      $pads_vertically = $vertical || in_array($in['padding'] ?? 'none', ['all', 'top', 'bottom'], TRUE);
      $out['no_padding'] = !empty($in['no_padding']) || ($background !== 'none' && !$pads_vertically);
    }
    return $out;
  }

}
