# Jarvis site recipe

Self-contained bootstrap recipe: stands up a **fully functional Drupal site**
with the Jarvis theme — Canvas visual editing, demo content, forms, editorial
workflow, SEO, an accessibility checker, admin UX, and a configured AI stack.

Validated end-to-end on a fresh `standard` install: the recipe applies with
zero warnings and every demo page (nodes and Canvas pages) renders. See the
repository-root README for the quick start.

## What it does

- **Installs the Jarvis theme** and sets it as the default; applies the theme
  settings (colours, fonts, sizes, logo). Claro becomes the admin theme and
  content is edited in it, like the standard profile.
- **Canvas, fully wired**: canvas + canvas_field_component, component config
  for the theme's 16 SDCs and the block/views components the library folders
  use, content templates for the three content types, page regions, 5
  reusable patterns, and the custom `jarvis_blocks` + `jarvis_canvas` glue
  modules. Component folders are organised by `jarvis_canvas` on every cache
  rebuild (they cannot ship as config — Canvas auto-creates folders during
  component sync and an item may only live in one folder).
- **Admin experience**: core Navigation sidebar with shortcuts, and
  `config.import` pulls each module's admin views (content/media/files/
  people/blocks listings), the media-library style/widget, menus, and the
  shortcut set — recipes skip module-shipped config entities by design, so
  the import list is what makes the admin UI whole.
- **Site features**: webform stack (contact form included), workflows +
  content_moderation (Editorial workflow ships unassigned), metatag
  (+ Open Graph/Twitter Cards) with module defaults, editoria11y, antibot,
  extlink, back_to_top, coffee, save_edit, better_exposed_filters,
  better_social_sharing_buttons, field_ui/views_ui, big_pipe, automated_cron.
- **Installs and configures the AI stack**: ai, ai_agents, ai_automators,
  ai_ckeditor, ai_assistant_api, ai_chatbot, ai_image_alt_text,
  ai_media_image, the Anthropic/OpenAI/Gemini/ElevenLabs providers, and
  canvas_ai with nine Canvas agents. **No API keys ship here** — add them via
  the private jarvis_ai overlay (see the AI section below). Until keys exist
  the providers idle.
- **Imports demo content** (core Default Content format, shipped in
  `content/`): 7 nodes (Home, About, Accessibility Statement, 3 blog posts,
  1 landing demo), **2 Canvas pages** (the component showcase and a Test
  Page), 7 block_content entities, the referenced media + files, and the
  main-menu links. Node URL aliases are carried inline, so they attach
  regardless of imported node IDs. The front page is the Canvas component
  showcase (`/page/2`).

Every module is required in the project `composer.json`, so `composer install`
downloads them; the recipe enables + configures.

## Apply

Designed for a **fresh / empty site** (validated on the `standard` profile).

The Jarvis theme lives in its own repository, wired in as a git submodule at
`web/themes/custom/jarvis` — clone with `--recurse-submodules` or the theme
directory arrives empty and the recipe fails:

```bash
git clone --recurse-submodules https://github.com/imrodmartin/jarvis-bootstrap-recipe.git mysite
cd mysite
# (already cloned without submodules? run: git submodule update --init)
ddev start          # .ddev/config.yaml ships in the repo; project name = directory name
ddev composer install
ddev drush site:install standard -y
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
> theme to Jarvis, the admin theme to Claro, the front page to the demo Canvas
> page (`/page/2`), and imports the demo content alongside your own.

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
