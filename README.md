# Jarvis — a complete Drupal site, one recipe

Jarvis is a component-driven Drupal theme (Bootstrap 5, Single Directory
Components, WCAG 2.2 AA safeguards built in) plus a Drupal recipe that stands
up a **fully functional site** around it: Drupal Canvas visual editing, demo
content, forms, editorial workflow, SEO defaults, an accessibility checker,
and a configured AI stack. One apply, working site.

## What you get

- **The Jarvis theme** — 20 SDC components (hero, cards, CTA, stats, people,
  video, maps, 1/2/3-column layouts, rich text …) with automatic WCAG
  contrast enforcement: image overlays darken until text passes AA, unsafe
  colour picks fall back to safe ones, and the theme settings form shows live
  contrast badges with one-click fixes.
- **Drupal Canvas**, wired: components organised into folders, 5 reusable
  patterns, content templates for the Blog/Basic content types, and
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
ddev drush site:install standard -y
ddev drush recipe /var/www/html/recipes/jarvis
ddev drush cache:rebuild         # also organises the Canvas component folders
ddev drush uli                   # log in
```

Install with the **standard** profile — the tested, supported path.
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
composer config repositories.jarvis-recipe '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis-recipe","no-api":true}'
composer config repositories.jarvis-theme '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis","no-api":true}'
composer config repositories.jarvis-modules '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis-modules","no-api":true}'
composer require imrodmartin/jarvis-recipe drupal/ckeditor5_markdown \
  drupal/default_content:^2.0@beta drupal/ai_media_image:^1.0@alpha

drush recipe recipes/jarvis-recipe
drush cache:rebuild
```

`"no-api": true` matters: all three repositories are public, but without it
composer calls `api.github.com` to read them, and on a host that cannot reach
it — shared hosting often can't, failing with `curl error 92 … HTTP/2` —
composer falls back to prompting for a GitHub token. None is needed. With
`no-api` it clones over plain HTTPS instead and the prompt never appears.
Under **ddev**, pass the absolute in-container path to the last two commands
(`ddev drush recipe /var/www/html/recipes/jarvis-recipe`): drush's working
directory is the docroot, while `recipes/` sits at the project root.

Last verified end-to-end on a blank `drupal/recommended-project` with the
standard profile (2026-07-29, released tags only — recipe v1.1.0, theme
v2.1.0, modules v1.1.0): theme lands in `themes/contrib/jarvis`, the
custom modules in `modules/custom/jarvis-modules` (Drupal discovers both
nested modules), the recipe in `recipes/jarvis-recipe`, and the apply produces
the full site. Notes:

- `drupal/ckeditor5_markdown` is spelled out because nothing else depends on
  it: it supplies the `markdownPaste` toolbar item that
  `editor.editor.jarvis_html` references, and without it the apply aborts with
  *"The provided toolbar item markdownPaste is not valid."*
- The two `@beta`/`@alpha` packages carry stability flags that only work in the
  root `composer.json` — that's why they're spelled out too.
- No drush yet? Add `drush/drush` to the same require.
- The three `repositories` lines disappear once the packages are on
  Packagist.

## Installing Drupal 11 with Jarvis on a Webserver Using `public_html` as the Docroot

### 1. Verify PHP configuration

Make sure PHP is updated and `memory_limit` is high enough for Composer.

### 2. Scaffold a fresh Drupal 11 project

```bash
composer create-project drupal/recommended-project:^11.4 tmp --no-install \
  && mv tmp/composer.json . \
  && rm -rf tmp
```

### 3. Point the docroot at `public_html` instead of `web`

```bash
sed -i 's|"web/|"public_html/|g; s|: "web"|: "public_html"|g' composer.json \
  && grep -n "public_html" composer.json | head
```

> **Check the output.** Every path in `composer.json` should now read `public_html/...`.
> If any still say `web/`, stop and paste the output before continuing.

### 4. Install Drupal core and Jarvis

Register the repositories:

```bash
composer config repositories.jarvis-recipe '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis-recipe","no-api":true}'
composer config repositories.jarvis-theme '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis","no-api":true}'
composer config repositories.jarvis-modules '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis-modules","no-api":true}'
```

Then require the packages:

```bash
composer require \
  imrodmartin/jarvis-recipe \
  drupal/ckeditor5_markdown \
  drupal/default_content:^2.0@beta \
  drupal/ai_media_image:^1.0@alpha \
  drush/drush
```

Composer places files as follows:

| Component | Destination |
| --- | --- |
| Theme | `public_html/themes/contrib/jarvis` |
| Custom modules | `public_html/modules/custom/jarvis-modules` |
| Recipe | `recipes/jarvis-recipe` |

> **Don't clone or copy any of those by hand.** That's what broke the last attempt.

### 5. Install the site

Fill in your real database credentials. This drops and recreates those tables.

```bash
vendor/bin/drush --root=public_html site:install standard \
  --db-url=mysql://DBUSER:DBPASS@localhost/DBNAME \
  --account-name=admin \
  -y
```

### 6. Apply the recipe

Use an absolute path. A relative path resolves against the docroot and fails.

```bash
vendor/bin/drush --root=public_html recipe "$(pwd)/recipes/jarvis-recipe" \
  && vendor/bin/drush --root=public_html cr \
  && vendor/bin/drush --root=public_html uli
```
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
  drupal/ckeditor5_markdown \
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

## Applying to a site that already has config

The recipe is built for a **blank site**, and applying it is an opinionated
takeover: default theme → Jarvis, admin theme → Claro, front page → the demo
Canvas page (`/page/2`), demo content imported. It will apply to a site that
already has content and config, but know what it does and does not touch:

- **Config the site already has is left exactly as it is.** The recipe ships
  `strict: false`, so it creates only the config names your site does not
  already own. Nothing of yours is overwritten.
- **The flip side is silence.** Where your site owns a drifted copy of
  something the recipe ships — a customised `views.view.content`, your own
  `image.style.large`, an existing `workflows.workflow.editorial` — the
  recipe's version simply never lands, and you get no warning. Expect to
  reconcile those by hand afterwards.
- **Watch for name collisions.** The recipe ships generic names that an
  existing site may already use for something else:
  `field.storage.node.field_body`, the `card`/`hero`/`image`/`text`/`video`
  block content types and their fields. Yours win; the components that expect
  the recipe's shape may not find what they need. The hero banner image style
  used to be on that list; it is now namespaced as
  `image.style.jarvis_hero_banner` so it can never be shadowed. The `document`
  and `video` media types go the other way on purpose — they keep core's names
  precisely so a standard-profile site reuses the ones it already has.
- **Config *actions* still run regardless** — they are what set the default
  theme, the front page and the text-format permissions, and they act on
  whatever config is there.

Want the recipe to stop rather than silently skip? Set `strict: true` in
`recipes/jarvis/recipe.yml` (core's default). Every config name the recipe can
supply — the ~217 files in `config/` plus the ~133 it pulls from other
extensions via `import:` — must then be absent or byte-identical, or the apply
throws `RecipePreExistingConfigException` before changing anything. That is a
clean abort with a named config, and on any real site it will almost certainly
trigger.

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
- Re-applying the recipe to the same site is not supported. It no longer stops
  at the config check (that was `strict`, now false — a re-apply validates and
  finds nothing new to create), but the config actions and the demo-content
  import run again, which is untested. Reinstall instead.

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
