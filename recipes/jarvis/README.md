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
  modeler_api, default_content, editoria11y (editorial accessibility checker),
  and the custom `jarvis_blocks` + `jarvis_canvas`.
- **Installs and configures the AI stack**: ai, ai_agents, ai_ckeditor,
  ai_provider_anthropic/openai, gemini_provider, canvas_ai, key — including
  default providers, the Canvas AI agents, CKEditor AI, and alt-text/media
  image settings. **No API keys ship here** — add them via the private
  jarvis_ai overlay (see the AI section below). Until keys exist the
  providers simply run unconfigured.
- **Installs the Jarvis theme** and sets it as the default; applies the theme
  settings (colours, fonts, sizes, logo).
- **Imports config**: the three content types + fields + form/view displays,
  Canvas content templates + page regions + components, the six block_content
  component types, image styles, media types, editor/filter formats, blocks.
- **Imports demo content** (via default_content, shipped in `content/`): 6 nodes
  (Home, About Jarvis, 3 blog posts, 1 landing demo), **2 Canvas pages** (the
  component showcase page and a Test Page), 7 block_content entities, the
  referenced media + image files, and the main-menu links. Node URL aliases
  are carried inline on the nodes, so they attach correctly regardless of the
  imported node IDs. The front page is set to `/home`.

Every module is required in the project `composer.json`, so `composer install`
downloads them; the recipe enables + configures.

## Apply

Designed for a **fresh / empty site** (validated on the `minimal` profile).

The Jarvis theme lives in its own repository, wired in as a git submodule at
`web/themes/custom/jarvis` — clone with `--recurse-submodules` or the theme
directory arrives empty and the recipe fails:

```bash
git clone --recurse-submodules https://github.com/imrodmartin/jarvis-bootstrap-recipe.git mysite
cd mysite
# (already cloned without submodules? run: git submodule update --init)
ddev start          # .ddev/config.yaml ships in the repo; project name = directory name
ddev composer install
ddev drush site:install minimal -y
# NOTE: under ddev, drush's working dir is the docroot (web/), but recipes/ lives
# at the project root — pass the absolute in-container path:
ddev drush recipe /var/www/html/recipes/jarvis
ddev drush cache:rebuild
```

Without ddev (drush run from the project root), the path is just `recipes/jarvis`.

> **Existing sites:** everything the recipe ships is namespaced away from the
> standard profile's config — text formats are `jarvis_html`/`jarvis_full_html`
> (Drupal's own Basic/Full HTML are untouched), the media types are `jarvis_image`/`jarvis_video`,
> the basic block type is `jarvis_basic`, the Linkit profile is `jarvis` — so
> applying to a site created with the standard profile no longer collides with
> or overwrites its formats, media types, or block types. Shared field storages
> (`media.field_media_image`, `block_content.body`) are byte-identical with the
> standard profile's, so they pass the recipe's strict check. Note that applying
> to an existing site is still an opinionated takeover: it sets the default
> theme to Jarvis, the admin theme to Claro, the front page to the demo `/home`,
> and imports the demo content alongside your own.

## AI keys (optional, private)

The bootstrap recipe installs and configures the AI stack but ships **no
keys** — it contains no secrets and is safe to share. To activate the
providers, put your gitignored `key.key.*.yml` files in
`recipes/jarvis_ai/config/` and apply the keys-only overlay:

```bash
ddev drush recipe /var/www/html/recipes/jarvis_ai
ddev drush cache:rebuild
```

The overlay's `key.key.*.yml` (plaintext credentials) are gitignored and are not
in this repo — supply your own. See `recipes/jarvis_ai/README.md`.

## Known limitations

- The `header`, `content_bottom` and right-sidebar Canvas regions ship
  **empty**. Their per-entity `block_content` placements were removed from the
  recipe: content imports after config, so the block plugins don't exist at
  apply time (which produced "block plugin was not found" warnings), and under
  Canvas PageRegions block-layout placements never render anyway. The demo
  block content (an "Alert", a "Bottom Block", a "More" block) still ships as
  entities — place them via the Canvas UI after install if you want them.
