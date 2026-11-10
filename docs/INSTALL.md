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

## Where the files land

Requiring a recipe prints `imrodmartin/jarvis-… unpacked.`:
`drupal/core-recipe-unpack` moves the recipe's own requirements into your root
`composer.json` and drops the recipe package from `require`, while leaving the
recipe files on disk — so `drush recipe recipes/<name>` still works afterwards.
Verified on a stock `drupal/recommended-project` 11.4 (composer 2.9.8):

| Piece | Path |
|---|---|
| Recipe | `recipes/jarvis-recipe/` or `recipes/jarvis-install-recipe/` |
| Theme | `web/themes/contrib/jarvis` |
| `jarvis_canvas` | `web/modules/custom/jarvis-modules/jarvis_canvas` (nested under the package name — Drupal discovers it there) |
| Contrib deps | `web/modules/contrib/` |

The theme declares `"type": "drupal-theme"`, so stock installer-paths put it in
`themes/contrib`. That works. To keep it under `themes/custom`, add
`"drupal/jarvis"` to the `web/themes/custom/{$name}` entry of
`extra.installer-paths` **and move that entry above**
`web/themes/contrib/{$name}` — composer/installers returns the first matching
entry in file order, so a later package-name match loses to an earlier
`type:drupal-theme`.

Canvas resolves to its newest `^1.8` release (1.12.0 as of 2026-11-07). If you
need it pinned — the 1.9+ releases changed the autocomplete bundle this project
patches — require the exact version before applying the recipe.

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

Rebuild caches **before** applying. A running site caches its list of modules
and themes, and composer has just added eight it has never seen. Skip this and
the recipe stops at validation with *"jarvis" is not a known module or theme*,
once for each new extension:

```bash
drush cache:rebuild
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
drush updatedb
```

```bash
drush cache:rebuild
```

`update`, not `require` — `require` rewrites the version constraint in your
`composer.json`; `update` moves within the one you already have.

`drush updatedb` is not optional either: `jarvis_canvas` ships update hooks
(3.0.0 needs `11001`, which backfills a Canvas template for content types
that predate the module).

Contrib carries the same requirement, and the AI module is the one that bites.
Moving `drupal/ai` from 1.4.x to 1.5.x relocates the AI CKEditor prompts into
`ai.ai_prompt_type.*` config entities, which are created by
`ai_ckeditor_post_update_convert_ai_prompt()` — a post update, not something
the module files provide on their own. Skip `updatedb` and every text format
that enables the AI CKEditor plugins (Jarvis HTML, Jarvis Full HTML) white-
screens on its edit form:

```
TypeError: Drupal\ai\Form\AiPromptSubform::buildForm(): Argument #4
($ai_prompt_type) must be of type Drupal\ai\Entity\AiPromptTypeInterface,
null given
```

`ai/src/Element/AiPrompt.php` loads the prompt type without a null check, so
the absent entity surfaces as a fatal rather than a warning. Confirm the
update landed:

```bash
drush ev 'print_r(\Drupal::configFactory()->listAll("ai.ai_prompt_type."));'
```

Seven entries expected. An empty array means the post update has not run.
Checking *after* `updatedb` proves nothing about what caused a crash before
it — use `drush updbst` to see what is still pending.

### Coming from modules 1.x: uninstall jarvis_blocks first

The 1.x package shipped **two** modules, `jarvis_blocks` and `jarvis_canvas`.
The 2.x line dropped `jarvis_blocks`, so upgrading deletes its directory while
`core.extension` still lists it as installed. Drupal then refuses *every*
database update on the site, not just Jarvis's:

```
The following module is marked as installed in the core.extension
configuration, but it is missing:
 * jarvis_blocks
```

It will not appear in `drush pm:list` — that only shows extensions found on
disk — which makes it easy to misread as a Jarvis 3.0 bug. It is not; it is a
leftover from the 1.x package.

Uninstall it **before** upgrading, while the files are still present:

```bash
drush pm:uninstall jarvis_blocks -y
```

If you have already upgraded and are stuck, restore the code temporarily so
Drupal can uninstall it cleanly, then delete it again:

```bash
cd "$(drush php:eval 'print DRUPAL_ROOT;')/modules/custom"
curl -L https://github.com/imrodmartin/jarvis-modules/archive/refs/tags/v1.2.0.tar.gz | tar xz --strip-components=1 jarvis-modules-1.2.0/jarvis_blocks
drush cr && drush pm:uninstall jarvis_blocks -y
```

Then remove the restored directory and run `drush cr && drush updatedb`.

**Check for content first.** `jarvis_blocks` owns five block content types with
generic names — `card`, `hero`, `image`, `text`, `video` — plus their field
storages. Uninstalling deletes those types and every block of them:

```bash
drush sqlq "SELECT type, COUNT(*) AS n FROM block_content_field_data GROUP BY type"
```

If any of those five hold blocks you want, keep the restored directory
permanently instead of uninstalling. It is self-contained module code with no
dependency on the theme, and Composer will not touch it — 2.x and later no
longer ship it.

Editing `jarvis_blocks` out of `core.extension` by hand clears the error but
strands the block types, field storages and any placed blocks as orphaned
config, which resurfaces later as config-import failures.

The cache rebuild is not optional: `hook_rebuild` is what makes Canvas re-read
the components and regenerate their config.

Then check nothing was switched off. Canvas disables any component whose props
it cannot populate, and **never re-enables it**:

```bash
drush ev 'foreach (\Drupal::service("keyvalue")->get("canvas:component:reasons")->getAll() as $k => $v) { print "$k\n"; foreach ((array) $v as $r) print "   - " . strip_tags($r) . "\n"; }'
```

Empty output means everything survived. Anything listed names the component and
the prop that could not be shaped.

### Upgrading to 4.0: Section became 1 Column

Jarvis 4.0 removes the **Section** component. It did nothing 1 Column could not
do, so 1 Column replaces it. You do not have to rebuild anything: the first
`drush cache:rebuild` after the update converts every Section on the site
(Canvas pages, all their revisions, content templates, page regions and
patterns) to a 1 Column with the same width, background and spacing, and logs
`Converted N Section component(s) to 1 Column`.

The same rebuild renames one prop on three components, and logs
`Renamed the variant prop on N Button, Card and Person component(s)`:

| Component | Was | Now |
|---|---|---|
| Button | `variant` | `style` |
| Card | `variant` | `layout` |
| Person | `variant` | `layout` |

Drupal core reserves the name `variant` for its own component variants, and
from 11.2 it passes an empty `variant` to every component rendered from PHP.
That failed these components' own list of allowed values. Everything placed in
Canvas is converted for you. Two things are yours to check:

- **Your own Twig templates.** An `include('jarvis:card', {variant: 'image-left'})`
  still works, because the components read the old name as a fallback. Switch
  to the new name when you next touch the template.
- **Unpublished Canvas drafts.** Changes that were auto-saved but not published
  before the update are not converted. Publish or discard drafts first.

What changes on screen:

- A Section set to Bootstrap's "tertiary" grey becomes the theme's Light grey
  (`#f8f9fa` to `#f5f5f5`).
- Colored column sections now obey their Padding and Vertical padding settings.
  They used to get 2rem top and bottom no matter what was chosen.
- The Base font from the theme settings now applies to body text. It was
  being overridden by Bootstrap.

Check the log afterwards:

```bash
drush watchdog:show --type=jarvis_canvas
```

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
Both recipes pin `drupal/jarvis ^4.0` and `imrodmartin/jarvis-modules ^2.2`,
and the theme itself pins `imrodmartin/jarvis-modules ^2.2`, so composer cannot
resolve a mismatched set. Theme 4.0 needs modules 2.2 because that release
carries the Section to 1 Column conversion (below). The earlier floor was `^2.1` rather than `^2.0`
because `jarvis_overlay_alpha` — which four templates call — only exists from
modules 2.1.0.
