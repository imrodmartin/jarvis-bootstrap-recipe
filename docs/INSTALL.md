# Installing Jarvis with composer

> Just want the commands? [CHEATSHEET.md](CHEATSHEET.md).

Two situations, two packages. Pick the right one — they are not
interchangeable.

| You have | Use | What it does |
|---|---|---|
| Nothing, or a blank Drupal | `imrodmartin/jarvis-recipe` | Builds a whole site: theme, Canvas, demo content, forms, workflow, SEO, AI stack. Sets the default theme, admin theme and front page. |
| A working site with its own content and config | `imrodmartin/jarvis-install-recipe` | Adds Jarvis alongside what you have. Changes no theme, no front page, no content. |

Both need the same repositories registered first, because none of these
packages are on Packagist.

---

## Common step: register the repositories

Run these once, in your project root:

```bash
composer config repositories.jarvis-theme '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis","no-api":true}'
```

```bash
composer config repositories.jarvis-modules '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis-modules","no-api":true}'
```

Then whichever recipe you need:

```bash
composer config repositories.jarvis-recipe '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis-recipe","no-api":true}'
```

```bash
composer config repositories.jarvis-install-recipe '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis-install-recipe","no-api":true}'
```

`"no-api": true` matters. All the repositories are public, but without it
composer calls `api.github.com` to read them. Hosts that cannot reach it fail
with `curl error 92 … HTTP/2`, and composer then prompts for a GitHub token —
none is needed. With `no-api` it clones over plain HTTPS and the prompt never
appears.

---

## A. A new site, from scratch

```bash
composer create-project drupal/recommended-project:^11 mysite && cd mysite
```

Register the repositories (above), then:

```bash
composer require imrodmartin/jarvis-recipe drupal/ckeditor5_markdown drupal/default_content:^2.0@beta drupal/ai_media_image:^1.0@alpha drush/drush
```

Those extras are spelled out because nothing else pulls them in.
`ckeditor5_markdown` supplies the `markdownPaste` toolbar item that
`editor.editor.jarvis_html` references — without it the recipe aborts with *"The
provided toolbar item markdownPaste is not valid."* The `@beta`/`@alpha` ones
carry stability flags that only work in the root `composer.json`.

```bash
drush site:install standard --account-name=admin -y
```

Install with the **standard** profile — the tested path. What Jarvis adds is
namespaced (`jarvis_html`, `jarvis_full_html`), so standard's own formats and
block types are left untouched — Jarvis ships no block types of its own. Media types are the deliberate exception: Jarvis uses
core's `image`, `remote_video`, `video` and `document` bundles rather than
namespaced copies, since contrib widely assumes those names. Media types the
site already has are left as they are.

Apply the recipe with an **absolute** path; a relative one resolves against the
docroot and fails:

```bash
drush recipe "$(pwd)/recipes/jarvis-recipe"
```

```bash
drush cache:rebuild
```

Under ddev, prefix with `ddev` and use the in-container path
(`ddev drush recipe /var/www/html/recipes/jarvis-recipe`): drush's working
directory is the docroot, while `recipes/` sits at the project root.

You now have a working site, with the Canvas component showcase as the front
page.

---

## B. An existing site

Back up first — this creates config on a live site.

```bash
drush sql:dump --result-file=../backup-before-jarvis.sql
```

Register the repositories (above), then:

```bash
composer require imrodmartin/jarvis-install-recipe drush/drush
```

Before applying, see what of yours it will step around. Anything printed here is
config your site already owns — yours wins and the recipe's copy is skipped
**silently**:

```bash
for n in field.storage.media.field_media_image image.style.portrait image.style.wide core.entity_view_mode.media.hero; do drush config:get "$n" >/dev/null 2>&1 && echo "EXISTS: $n"; done
```

```bash
drush recipe "$(pwd)/recipes/jarvis-install-recipe"
```

```bash
drush cache:rebuild
```

Jarvis is now installed but **not enabled** — your theme is still serving. Look
around, then switch when ready:

```bash
drush config:set system.theme default jarvis
```

### What this recipe deliberately does not do

- No `system.theme` action — your default and admin themes are untouched.
- No `system.site` action — your front page stays.
- No demo content, no content types, no menu links.
- Nothing is overwritten. It applies non-strict, so where a config name already
  exists the recipe's version never lands.

The trade-off is silence: where your site owns a drifted copy of something
Jarvis expects, you get no warning, and a component may not find the shape it
needs. Expect to reconcile those by hand.

---

## Updating

```bash
composer update drupal/jarvis imrodmartin/jarvis-modules imrodmartin/jarvis-recipe imrodmartin/jarvis-install-recipe
```

```bash
drush cache:rebuild
```

`update`, not `require` — `require` rewrites the version constraint in your
`composer.json`; `update` moves within the one you already have.

The cache rebuild is not optional: `hook_rebuild` is what makes Canvas re-read
the components and regenerate their config.

Then check nothing was switched off. Canvas disables any component whose props
it cannot populate, and **never re-enables it**:

```bash
drush ev 'foreach (\Drupal::service("keyvalue")->get("canvas:component:reasons")->getAll() as $k => $v) { print "$k\n"; foreach ((array) $v as $r) print "   - " . strip_tags($r) . "\n"; }'
```

Empty output means everything survived. Anything listed names the component and
the prop that could not be shaped.

**Config does not arrive by composer update.** New media types, fields and image
styles reach a site only when a recipe is applied. A `composer update` ships
files — theme templates, components, module code — and nothing else. When a
release adds config, re-apply the recipe.

---

## The packages

| Package | What it is |
|---|---|
| `drupal/jarvis` | The theme. Repo: `imrodmartin/jarvis`. |
| `imrodmartin/jarvis-modules` | `jarvis_canvas` glue module. |
| `imrodmartin/jarvis-recipe` | Full-site recipe (new sites). |
| `imrodmartin/jarvis-install-recipe` | Existing-site recipe. |

They are coupled and released together: the theme's video components need the
module's prop-shape hook, and its renamed image style needs the recipes' config.
Both recipes pin `drupal/jarvis ^2.2` and `imrodmartin/jarvis-modules ^1.2`, so
composer cannot resolve a mismatched set.
