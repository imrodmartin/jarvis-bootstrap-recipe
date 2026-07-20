# Jarvis — a complete Drupal site, one recipe

Jarvis is a component-driven Drupal theme (Bootstrap 5, Single Directory
Components, WCAG 2.2 AA safeguards built in) plus a Drupal recipe that stands
up a **fully functional site** around it: Drupal Canvas visual editing, demo
content, forms, editorial workflow, SEO defaults, an accessibility checker,
and a configured AI stack. One apply, working site.

## What you get

- **The Jarvis theme** — 16 SDC components (hero, cards, CTA, stats, people,
  video, maps, 1/2/3-column layouts, rich text …) with automatic WCAG
  contrast enforcement: image overlays darken until text passes AA, unsafe
  colour picks fall back to safe ones, and the theme settings form shows live
  contrast badges with one-click fixes.
- **Drupal Canvas**, wired: components organised into folders, 5 reusable
  patterns, content templates for the Blog/Basic/Landing content types, and
  two editable demo Canvas pages (a component showcase and a test page).
- **Demo content** — home page, about page, accessibility statement, three
  blog posts, media, menus. The front page is the Canvas component
  showcase (`/page/2`).
- **A real admin experience** — Claro admin theme, the core Navigation
  sidebar with working Content/Media/Files/People listings, shortcuts,
  field & views UIs.
- **Site features** — Webform (contact form included), editorial workflow
  (ships unassigned; opt content types in), Metatag with sane defaults,
  Editoria11y accessibility checker, antibot, social sharing, and more.
- **The AI stack, configured** — providers (OpenAI/Anthropic/Gemini/
  ElevenLabs), nine Canvas AI agents, CKEditor AI, automatic image alt text.
  **No API keys ship in this repo** — see [AI keys](#ai-keys-optional).

## Requirements

- Docker + [ddev](https://ddev.com) (or your own PHP 8.3+ / MariaDB stack)
- git, with submodule support

## Create a site (the supported path)

The theme lives in its own repository as a git submodule, so the
`--recurse-submodules` flag is **required**:

```bash
git clone --recurse-submodules https://github.com/imrodmartin/jarvis-bootstrap-recipe.git mysite
cd mysite
ddev start                       # ddev config ships in the repo
ddev composer install
ddev drush site:install minimal -y
ddev drush recipe /var/www/html/recipes/jarvis
ddev drush cache:rebuild         # also organises the Canvas component folders
ddev drush uli                   # log in
```

Both the `minimal` and `standard` install profiles are tested and supported.
On a standard-profile site, everything Jarvis ships is namespaced
(`jarvis_html`, `jarvis_image`, `jarvis_basic`, …) so the site's own Basic
HTML format, block types, and media types are left untouched.

Cloned without submodules by accident? `git submodule update --init` fixes it.

## Add Jarvis to an existing Drupal project (composer package — recommended)

The recipe is published as a composer `drupal-recipe` package that pulls in
the theme, the custom modules, and every contrib module. From your project
root (any docroot name — `web`, `public_html`, whatever your installer-paths
say):

```bash
composer config repositories.jarvis-recipe vcs https://github.com/imrodmartin/jarvis-recipe
composer config repositories.jarvis-theme vcs https://github.com/imrodmartin/jarvis
composer config repositories.jarvis-modules vcs https://github.com/imrodmartin/jarvis-modules
composer require imrodmartin/jarvis-recipe drupal/default_content:^2.0@beta drupal/ai_media_image:^1.0@alpha

drush recipe recipes/jarvis-recipe
drush cache:rebuild
```

Verified end-to-end on a blank `drupal/recommended-project` with the standard
profile: theme lands in `themes/contrib/jarvis`, the custom modules in
`modules/custom/jarvis-modules` (Drupal discovers both nested modules), the
recipe in `recipes/jarvis-recipe`, and the apply produces the full site.
Notes:

- The two extra packages on the `require` line carry stability flags
  (`@beta`/`@alpha`) that only work in the root `composer.json` — that's why
  they're spelled out.
- No drush yet? Add `drush/drush` to the same require.
- The three `repositories` lines disappear once the packages are on
  Packagist.

## Add Jarvis by hand (no composer package)

Prefer to vendor the pieces yourself? Assemble the four pieces, then apply:

```bash
# 1. Theme (its own repository)
git clone https://github.com/imrodmartin/jarvis.git web/themes/custom/jarvis

# 2. Recipe + custom modules, copied out of this repo
git clone --depth 1 https://github.com/imrodmartin/jarvis-bootstrap-recipe.git /tmp/jarvis-src
mkdir -p recipes web/modules/custom
cp -r /tmp/jarvis-src/recipes/jarvis recipes/
cp -r /tmp/jarvis-src/recipes/jarvis_ai recipes/
cp -r /tmp/jarvis-src/web/modules/custom/jarvis_blocks web/modules/custom/
cp -r /tmp/jarvis-src/web/modules/custom/jarvis_canvas web/modules/custom/
rm -rf /tmp/jarvis-src

# 3. Contrib dependencies
composer require drupal/canvas drupal/canvas_field_component \
  drupal/twig_tweak drupal/linkit drupal/token_filter drupal/focal_point \
  drupal/backup_migrate drupal/asset_injector drupal/simple_gmap \
  drupal/default_content drupal/editoria11y drupal/key drupal/ai \
  drupal/ai_agents drupal/ai_image_alt_text drupal/ai_media_image \
  drupal/ai_provider_anthropic drupal/ai_provider_openai \
  drupal/gemini_provider drupal/canvas_ai drupal/elevenlabs \
  drupal/webform drupal/webform_ui drupal/metatag drupal/antibot \
  drupal/extlink drupal/back_to_top drupal/coffee drupal/save_edit \
  drupal/better_exposed_filters drupal/better_social_sharing_buttons

# 4. Apply to a fresh/blank site
drush recipe recipes/jarvis
drush cache:rebuild
```

**Different docroot?** Many hosts use `public_html` (or `docroot`) instead of
`web`. Nothing in the recipe cares — just aim the theme and custom-module
copies at *your* docroot (`public_html/themes/custom/jarvis`,
`public_html/modules/custom/…`). Composer places contrib wherever your
project's installer-paths already point, and `recipes/` lives at the project
root next to `composer.json`, not inside the docroot.

**Why isn't this one command?** Drupal recipes enable and configure modules —
they never download code; that is Composer's job by design. The clone flow
above is effectively two commands only because this repo's `composer.json`
already lists everything. On an existing project the `composer require` is a
one-time step; from then on it's `drush recipe` + `drush cache:rebuild`.

The recipe targets a **blank site**. A site that already runs Canvas or the
AI modules with drifted settings will fail the recipe's config checks.
Applying is also an opinionated takeover: default theme → Jarvis, admin
theme → Claro, front page → the demo Canvas page (`/page/2`), demo content
imported.

## AI keys (optional)

The AI stack installs fully configured but keyless — providers idle until
keys exist. Put your gitignored `key.key.*.yml` files (ids `openai`,
`claude`, `gemini`, `elevenlabs`) in `recipes/jarvis_ai/config/`, then:

```bash
ddev drush recipe /var/www/html/recipes/jarvis_ai
ddev drush cache:rebuild
```

No key files? The overlay is a harmless no-op. Key file shape and details:
[recipes/jarvis_ai/README.md](recipes/jarvis_ai/README.md).

## After install — worth knowing

- **Canvas component folders** are organised on cache rebuild (idempotent) —
  if the library ever shows everything under "Other", run `drush cr`.
- **Editing a Canvas content template**: enter through the Canvas app
  (`/canvas` → Templates panel → Content types). Deep links to the template
  editor 500 in Canvas 1.8 (upstream: [#3551708](https://www.drupal.org/i/3551708)).
- **AI in CKEditor** works on node/block forms. Inside the Canvas settings
  tray the AI button is inert (upstream gap in ai_ckeditor ↔ Canvas); use
  the Canvas **AI Panel** there instead.
- **Editoria11y** flags content accessibility issues on every page for
  logged-in editors — publish with zero flags.
- **Accessibility statement** ships at `/accessibility-statement` — replace
  the contact placeholder and review date before launch.
- Re-applying the recipe to the same site fails by design (recipes are not
  idempotent). Reinstall instead.

## Repository layout

| Path | What it is |
|------|------------|
| `recipes/jarvis/` | The site recipe: config, demo content, README |
| `recipes/jarvis_ai/` | Private keys-only overlay (key files gitignored) |
| `web/themes/custom/jarvis/` | The theme — git submodule of [imrodmartin/jarvis](https://github.com/imrodmartin/jarvis) |
| `web/modules/custom/jarvis_blocks` | Block content types → SDC mapping |
| `web/modules/custom/jarvis_canvas` | Canvas glue: text formats on props, template CTA, folder organiser |

Deeper recipe details and known limitations:
[recipes/jarvis/README.md](recipes/jarvis/README.md).
