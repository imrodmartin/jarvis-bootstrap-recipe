# Jarvis site recipe

Self-contained bootstrap recipe: stands up a fresh Drupal site with the Jarvis
theme, the custom modules, the Blog/Basic/Landing content types, all the
Canvas/media plumbing, and a curated set of demo content (home page, three blog
posts, an about page, media, and the component-library block content).

Validated end-to-end: applies cleanly to a fresh `minimal` install and renders
(home, blog posts with hero images, and the about page all return 200).

## What it does

- **Installs modules**: media/media_library/image/link, menu + path, Canvas
  (canvas, canvas_field_component) and its deps (block_content, layout_builder,
  editor, ckeditor5, navigation, help), crop + focal_point, linkit, token +
  token_filter, twig_tweak, backup_migrate, asset_injector, simple_gmap,
  modeler_api, default_content, and the custom `jarvis_blocks` + `jarvis_canvas`.
- **Installs the AI stack unconfigured**: ai, ai_agents, ai_ckeditor,
  ai_provider_anthropic/openai, gemini_provider, canvas_ai, key — enabled but
  with **no keys and no AI config**. See the AI section below.
- **Installs the Jarvis theme** and sets it as the default; applies the theme
  settings (colours, fonts, sizes, logo).
- **Imports config**: the three content types + fields + form/view displays,
  Canvas content templates + page regions + components, the six block_content
  component types, image styles, media types, editor/filter formats, blocks.
- **Imports demo content** (via default_content, shipped in `content/`): 6 nodes
  (Home, About Jarvis, 3 blog posts, 1 landing demo), 7 block_content entities,
  the referenced media + image files, and the main-menu links. Node URL aliases
  are carried inline on the nodes, so they attach correctly regardless of the
  imported node IDs. The front page is set to `/home`.

Every module is required in the project `composer.json`, so `composer install`
downloads them; the recipe enables + configures.

## Apply

Designed for a **fresh / empty site** (validated on the `minimal` profile):

```bash
composer install
drush site:install minimal -y     # or start from an empty site
drush recipe recipes/jarvis       # run from the project root
drush cache:rebuild
```

> **Profile caveat:** the recipe ships a few config objects under stock names
> (`filter.format.basic_html`/`full_html`/`restricted_html`,
> `editor.editor.basic_html`/`full_html`, `image.style.large`/`medium`/`thumbnail`).
> On a `minimal` install these don't pre-exist (or come from the image module the
> recipe installs) and apply cleanly. On the `standard` profile they already
> exist and would be **overwritten** with Jarvis's versions. Apply to a
> minimal/empty site.

## AI keys (optional, private)

The bootstrap recipe installs the AI modules but ships **no keys and no AI
config** — it contains no secrets and is safe to share. To enable AI, apply the
private overlay locally after this recipe:

```bash
drush recipe recipes/jarvis_ai    # local only — adds AI config + your API keys
drush cache:rebuild
```

The overlay's `key.key.*.yml` (plaintext credentials) are gitignored and are not
in this repo — supply your own. See `recipes/jarvis_ai/README.md`.

## Known limitations

- The `header` and `content_bottom` Canvas regions ship **empty**. They placed
  per-entity `block_content` blocks whose Canvas components are derived from
  content that imports after config, so they can't validate at recipe apply. The
  demo block content (an "Alert" and a "Bottom Block") still ships as entities —
  place them via the Canvas UI after install if you want them back.
