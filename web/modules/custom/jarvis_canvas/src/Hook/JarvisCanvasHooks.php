<?php

declare(strict_types=1);

namespace Drupal\jarvis_canvas\Hook;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferencedBundleSpecificBranches;
use Drupal\canvas\PropShape\CandidateStorablePropShape;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;
use Drupal\node\NodeTypeInterface;

/**
 * Canvas integration hooks for the Jarvis theme.
 *
 * OOP hook implementations: procedural hook discovery is removed in Drupal 12
 * and this module declares core_version_requirement ^11 || ^12.
 */
final class JarvisCanvasHooks {

  use StringTranslationTrait;

  /**
   * Font-size slots and their theme defaults, mirroring _jarvis_font_sizes().
   *
   * key => [desktop default, unit].
   */
  private const FONT_SIZES = [
    'base' => [16, 'px'],
    'h1' => [2.5, 'rem'],
    'h2' => [2, 'rem'],
    'h3' => [1.75, 'rem'],
    'h4' => [1.5, 'rem'],
    'h5' => [1.25, 'rem'],
    'h6' => [1, 'rem'],
  ];

  /**
   * Implements hook_page_attachments().
   *
   * Feeds the theme's configured font sizes into the CKEditor 5 editing area.
   *
   * css/ckeditor5.css styles .ck-content from the --jarvis-fs-* variables, but
   * those are emitted by jarvis_preprocess_html(), which only runs when Jarvis
   * is the ACTIVE theme. Node forms render in the admin theme, so the variables
   * were absent and the stylesheet fell back to its defaults: the editor showed
   * a 16px base and a 40px h1 while the site rendered 20px and 50px. The theme
   * settings are meant to be the single source of truth, so the editor has to
   * read them too.
   *
   * This lives in a module rather than the theme precisely because a theme's
   * hooks only fire while that theme is the active one.
   *
   * Heading sizes are converted from rem to em. On the front end they resolve
   * against <html>, whose font-size the theme sets from the base setting. Inside
   * the editor <html> belongs to the admin theme and must not be touched, so the
   * same ratios are anchored to .ck-content's own font-size instead, which
   * reproduces the front-end result exactly while staying scoped to the editor.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $declarations = [];
    foreach (self::FONT_SIZES as $key => [$default, $unit]) {
      $value = \Drupal::service(ThemeSettingsProvider::class)->getSetting('jarvis_fs_' . $key, 'jarvis');
      $value = is_numeric($value) ? (float) $value : $default;
      // Base is absolute; the rest become em so they scale from it.
      $declarations[] = $unit === 'px'
        ? sprintf('--jarvis-fs-%s:%spx;', $key, $value)
        : sprintf('--jarvis-fs-%s:%sem;', $key, $value);
    }

    $attachments['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        // Scoped to .ck-content, and doubled to match the specificity
        // css/ckeditor5.css needs in order to beat CKEditor's inline rules.
        '#value' => '.ck-content.ck-content{' . implode('', $declarations) . '}',
      ],
      'jarvis-ckeditor5-font-sizes',
    ];
    // Rebuild this markup when the theme settings change.
    $attachments['#cache']['tags'][] = 'config:jarvis.settings';
  }

  /**
   * Implements hook_canvas_storable_prop_shape_alter().
   *
   * Canvas hardwires every block rich-text prop to its locked canvas_html_block
   * format. Swap in the site's Basic/Full HTML formats so editors get a real
   * text-format picker — but ONLY for props that explicitly opt in with the
   * `x-jarvis-html-format` schema marker (currently just the WYSIWYG component).
   *
   * Canvas exposes no per-component scope, but the custom `x-` marker survives
   * shape normalization (see PropShape::normalizePropSchema), so it reaches this
   * hook. Every other rich-text prop lacks the marker and keeps Canvas's default
   * editor.
   */
  #[Hook('canvas_storable_prop_shape_alter')]
  public function canvasStorablePropShapeAlter(CandidateStorablePropShape $storable_prop_shape): void {
    $schema = $storable_prop_shape->shape->schema;

    // Remote-video props (the video and video-with-sidebar SDCs): give them the
    // media library instead of an autocomplete.
    //
    // Canvas only knows how to turn a prop into a media picker for two media
    // SOURCE plugins — Image and VideoFile (see
    // ShapeMatchingHooks::SCHEMA_TO_MEDIA_SOURCE). There is no shape for oEmbed,
    // so a component that wants a YouTube/Vimeo item has to go through
    // content-entity-reference, and Canvas hands that an
    // entity_reference_autocomplete: you can only pick media whose name you
    // already know, and you cannot add a new one from the prop. Swapping in
    // media_library_widget restores browse-and-add.
    //
    // Bundles are resolved from the site rather than hardcoded, so this works
    // whichever oEmbed video type a site has -- the recipe ships core's
    // `remote_video`, but a site that renamed it, or carries more than one, is
    // covered too. If no oEmbed video type exists, leave Canvas's default in
    // place rather than handing the widget an empty bundle list.
    if (!empty($schema['x-jarvis-remote-video'])) {
      $branches = [];
      foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $id => $media_type) {
        if (!str_starts_with((string) $media_type->getSource()->getPluginId(), 'oembed:video')) {
          continue;
        }
        $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type)?->getName();
        if ($source_field === NULL) {
          continue;
        }
        // The oEmbed source field stores the provider URL as a plain string,
        // which is exactly what the prop wants — no derivative, no traversal in
        // the template.
        $branches["entity:media:$id"] = new FieldPropExpression(
          BetterEntityDataDefinition::create('media', $id),
          $source_field,
          NULL,
          'value',
        );
      }
      if ($branches) {
        $bundles = [];
        foreach (array_keys($branches) as $branch) {
          $bundle = substr($branch, strlen('entity:media:'));
          $bundles[$bundle] = $bundle;
        }
        $storable_prop_shape->fieldTypeProp = new ReferenceFieldTypePropExpression(
          referencer: new FieldTypePropExpression('entity_reference', 'entity'),
          referenced: count($branches) === 1
            ? reset($branches)
            : new ReferencedBundleSpecificBranches($branches),
        );
        $storable_prop_shape->fieldWidget = 'media_library_widget';
        $storable_prop_shape->fieldStorageSettings = ['target_type' => 'media'];
        $storable_prop_shape->fieldInstanceSettings = [
          'handler' => 'default:media',
          'handler_settings' => ['target_bundles' => $bundles],
        ];
      }
      return;
    }

    if (($schema['contentMediaType'] ?? NULL) !== 'text/html'
      || empty($schema['x-jarvis-html-format'])) {
      return;
    }
    // jarvis_html / jarvis_full_html ship in the recipe, not in this module, so
    // enabling jarvis_canvas on its own would otherwise hand the prop a list of
    // formats that do not exist — leaving the editor with no usable format at
    // all. Only offer the ones actually installed, and if neither is, leave
    // Canvas's own default in place rather than breaking the widget.
    $formats = array_values(array_filter(
      ['jarvis_html', 'jarvis_full_html'],
      static fn (string $id): bool => \Drupal::entityTypeManager()
        ->getStorage('filter_format')->load($id) !== NULL
    ));
    if ($formats) {
      $storable_prop_shape->fieldInstanceSettings['allowed_formats'] = $formats;
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for node_type.
   *
   * Auto-create an empty Canvas Full-content template for every new content type,
   * so the Canvas editor is immediately usable at
   * `/canvas/template/node/<bundle>/full` (Manage display → Full content tab).
   *
   * Canvas's own "Create with Canvas" flow is broken on Drupal 11.4: its CTA
   * route check misses the per-view-mode forms, and even when clicked its boot
   * opens the generic Canvas shell WITHOUT creating a template. This closes that
   * gap so new types don't need the manual clone-an-existing-template workaround.
   *
   * The tree is empty (no field references) so it is valid for ANY bundle,
   * including brand-new types that have no body field yet — the editor is where
   * fields/components get added.
   *
   * @see https://www.drupal.org/project/canvas/issues/3498525
   */
  #[Hook('node_type_insert')]
  public function nodeTypeInsert(NodeTypeInterface $type): void {
    // Config sync brings its own content templates; don't race it.
    if (\Drupal::isConfigSyncing()) {
      return;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('content_template');
    $id = 'node.' . $type->id() . '.full';
    if ($storage->load($id)) {
      return;
    }
    $storage->create([
      'id' => $id,
      // Disabled, NOT enabled. Canvas's ContentTemplateAwareViewBuilder swaps the
      // view display for any template whose status() is TRUE and unsets #theme
      // with it, so an enabled template with an empty component_tree rendered
      // every node of the new type as a blank page until someone opened Canvas
      // and built a tree. Disabled still leaves the template loadable and
      // editable (VisibleWhenDisabledCanvasConfigEntityAccessControlHandler);
      // it just doesn't hijack output before it has any.
      'status' => FALSE,
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => $type->id(),
      'content_entity_type_view_mode' => 'full',
      'exposed_slots' => [],
      'component_tree' => [],
    ])->save();
  }

  /**
   * Implements hook_form_FORM_ID_alter() for Manage display.
   *
   * Link the Canvas editor from a view mode's "Manage display" form while its
   * content template is still disabled.
   *
   * Nothing else links there: Canvas's own "Create with Canvas" CTA only shows
   * when NO template exists for the bundle (FieldUiHooks), and its redirect to
   * the editor only fires once the template is enabled
   * (ViewModeDisplayController::__invoke). ::nodeTypeInsert() creates the
   * template disabled — deliberately, so an empty tree cannot blank out every
   * node — which lands squarely between the two: the template exists, so no
   * CTA, and it is disabled, so no redirect. A new content type therefore had
   * no route into Canvas at all short of typing the URL.
   *
   * Canvas enables the template itself the first time the editor publishes
   * (ContentTemplate::autoSavePublish()), from which point its own redirect
   * takes over and this link stops being rendered.
   */
  #[Hook('form_entity_view_display_edit_form_alter')]
  public function formEntityViewDisplayEditFormAlter(array &$form, FormStateInterface $form_state): void {
    $display = $form_state->getFormObject()->getEntity();
    if (!$display instanceof EntityDisplayInterface) {
      return;
    }
    $entity_type_id = $display->getTargetEntityTypeId();
    $bundle = $display->getTargetBundle();
    $view_mode = $display->getMode();
    $template = \Drupal::entityTypeManager()
      ->getStorage('content_template')
      ->load("$entity_type_id.$bundle.$view_mode");
    if ($template === NULL || $template->status()) {
      return;
    }

    $form['jarvis_canvas_edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Design this view mode with Canvas'),
      '#url' => Url::fromUri("base:canvas/template/$entity_type_id/$bundle/$view_mode"),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => -100,
      // Drop the link as soon as the template is enabled or deleted.
      '#cache' => ['tags' => $template->getCacheTags()],
    ];
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for node_type.
   *
   * Remove the auto-created Full-content template when its content type is
   * deleted. An empty template has no config dependency on the bundle (nothing
   * in its component tree references the bundle's fields), so Drupal won't
   * cascade the delete — without this the template is orphaned, pointing at a
   * bundle that no longer exists.
   */
  #[Hook('node_type_delete')]
  public function nodeTypeDelete(NodeTypeInterface $type): void {
    // During config sync the config system handles deletions itself.
    if (\Drupal::isConfigSyncing()) {
      return;
    }
    $template = \Drupal::entityTypeManager()
      ->getStorage('content_template')
      ->load('node.' . $type->id() . '.full');
    if ($template) {
      $template->delete();
    }
  }

  /**
   * Implements hook_rebuild().
   *
   * Organize Canvas components and patterns into the Jarvis folder structure.
   * Folders can NOT ship as recipe config: Canvas auto-creates provider folders
   * during component sync, and an item may only live in one folder — imported
   * folder entities collide ("It is impossible for an item to exist in multiple
   * Folders"). So this runs on cache rebuild instead (the recipe's documented
   * final step), is idempotent, and converges: find-or-create each folder by
   * name, move only the listed items into it (API-side, deduped), leave
   * everything else where Canvas put it. Items whose component/pattern does not
   * exist are skipped, so partial installs are safe.
   */
  #[Hook('rebuild')]
  public function rebuild(): void {
    // hook_rebuild() fires from drupal_flush_all_caches(), which config import
    // calls at the end of its run. Without this guard the folder plan was
    // re-applied on every `drush cr` and after every `drush cim`, so any folder
    // reorganisation done in the Canvas UI was silently reverted, and the config
    // saves re-invalidated the caches being rebuilt.
    if (\Drupal::isConfigSyncing()) {
      return;
    }
    $etm = \Drupal::entityTypeManager();
    if (!$etm->hasDefinition('folder')) {
      return;
    }

    // name => [configEntityTypeId, weight, items].
    $plan = [
      'Content' => ['component', 0, [
        'sdc.jarvis.button', 'sdc.jarvis.card', 'sdc.jarvis.card-full-image',
        'sdc.jarvis.cta', 'sdc.jarvis.hero', 'sdc.jarvis.image',
        'sdc.jarvis.image-overlay', 'sdc.jarvis.large-call-to-action',
        'sdc.jarvis.map', 'sdc.jarvis.person', 'sdc.jarvis.stat',
        'sdc.jarvis.text', 'sdc.jarvis.video', 'sdc.jarvis.video-with-sidebar',
        'sdc.jarvis.wysiwyg',
        // navigation:title and navigation:message are core Navigation's admin
        // TOOLBAR components — their CSS is scoped to [data-drupal-admin-styles],
        // so they render unstyled on the front end, and their text lives in a slot
        // rather than a prop, which reads as "does nothing" to an editor. Both are
        // disabled in the recipe; do not fold them into a content folder.
      ]],
      'Sections' => ['component', 1, [
        'sdc.jarvis.one-column', 'sdc.jarvis.section',
        'sdc.jarvis.three-column', 'sdc.jarvis.two-column',
      ]],
      'Content Type' => ['component', 2, ['field_display.field_display']],
      'Forms' => ['component', 3, ['block.user_login_block']],
      'core' => ['component', 4, [
        'block.local_actions_block', 'block.local_tasks_block', 'block.page_title_block',
      ]],
      'Menus' => ['component', 5, [
        'block.system_menu_block.account', 'block.system_menu_block.admin',
        'block.system_menu_block.content', 'block.system_menu_block.footer',
        'block.system_menu_block.main', 'block.system_menu_block.navigation-user-links',
        'block.system_menu_block.tools',
      ]],
      'User' => ['component', 6, ['block.views_block.who_s_new-block_1']],
      'Help' => ['component', 7, ['block.help_block']],
      'System' => ['component', 8, [
        'block.system_branding_block', 'block.system_breadcrumb_block',
        'block.system_clear_cache_block', 'block.system_messages_block',
        'block.system_powered_by_block',
      ]],
      'Lists (Views)' => ['component', 9, [
        'block.views_block.blog-block_1', 'block.views_block.content_recent-block_1',
        'block.views_block.ed11y_result-block_most_issues',
        'block.views_block.who_s_online-who_s_online_block',
      ]],
      'Editoria11y' => ['component', 10, [
        'block.views_block.ed11y_action-recent_dismissals',
        'block.views_block.ed11y_result-block_recent_issues',
        'block.views_block.ed11y_result-block_top_issues',
      ]],
      'Webform' => ['component', 11, ['block.webform_block']],
      'Better Social Sharing Buttons' => ['component', 12, ['block.social_sharing_buttons_block']],
      'Navigation' => ['component', 13, [
        'block.navigation_link', 'block.navigation_shortcuts', 'block.navigation_user',
      ]],
      'Card Patterns' => ['pattern', 0, ['2cards']],
      'Person Patterns' => ['pattern', 1, ['personcards2column', 'personcards3column']],
    ];

    $folder_storage = $etm->getStorage('folder');
    $component_storage = $etm->getStorage('component');
    $pattern_storage = $etm->hasDefinition('pattern') ? $etm->getStorage('pattern') : NULL;
    $all_folders = $folder_storage->loadMultiple();
    $dirty = [];

    foreach ($plan as $name => [$type, $weight, $items]) {
      // Only organize items whose target entity actually exists.
      $items = array_values(array_filter($items, static fn (string $id): bool =>
        $type === 'component'
          ? $component_storage->load($id) !== NULL
          : ($pattern_storage !== NULL && $pattern_storage->load($id) !== NULL)
      ));
      if (!$items) {
        continue;
      }

      // Find-or-create the target folder by name + type.
      $target = NULL;
      foreach ($all_folders as $folder) {
        if ($folder->label() === $name && $folder->get('configEntityTypeId') === $type) {
          $target = $folder;
          break;
        }
      }
      if ($target === NULL) {
        $target = $folder_storage->create([
          'name' => $name,
          'configEntityTypeId' => $type,
          'weight' => $weight,
          'items' => [],
        ]);
        $all_folders[] = $target;
      }

      // Move each item: pull it out of whichever other folder holds it, then add
      // it here. Folder::addItems()/removeItem() are setters that do not save, so
      // they compose with the batched save below — an item may only live in one
      // folder, hence the removal pass. Folders are scanned in memory rather than
      // via Folder::loadByItemAndConfigEntityTypeId(), which would be one query
      // per item.
      $before = $target->get('items') ?? [];
      foreach ($items as $item) {
        foreach ($all_folders as $other) {
          if ($other === $target || $other->get('configEntityTypeId') !== $type) {
            continue;
          }
          if (in_array($item, $other->get('items') ?? [], TRUE)) {
            $other->removeItem($item);
            $dirty[spl_object_id($other)] = $other;
          }
        }
      }
      $target->addItems($items);
      if ($target->get('items') !== $before || $target->isNew() || $target->get('weight') !== $weight) {
        $target->set('weight', $weight);
        $dirty[spl_object_id($target)] = $target;
      }
    }

    foreach ($dirty as $folder) {
      $folder->save();
    }
  }
}
