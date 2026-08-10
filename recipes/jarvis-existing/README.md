# Jarvis for a site that already exists

`recipes/jarvis` is a blank-site install: it flips your default theme, repoints
your front page, imports demo content and installs an AI stack. On a site that
already has content types, a block layout and its own configuration, that is a
takeover.

This recipe is the other half. It adds the Jarvis theme and its Canvas
component library and **changes nothing you already had**.

```bash
drush recipe /full/path/to/recipes/jarvis-existing
drush cache:rebuild
```

Use an absolute path — `ddev drush` runs with its working directory at the
docroot, so a relative path resolves one level too deep. The project root is
`/var/www/html` inside the container regardless of what your docroot is called.

## What it does not touch

Verified, not asserted. On a Drupal 11.4.4 standard site with Layout Builder,
Article + Basic page, an Image media type and 20 placed blocks, a full
`config:export` was taken before and after. Of 171 pre-existing config objects,
**two** changed and **none** were deleted:

| Config | Change |
|---|---|
| `core.extension` | the modules and theme this recipe installs |
| `user.role.authenticated` | gains `use text format jarvis_html` |

Your default theme, admin theme, front page, block layout, content types,
media types, field storages and view displays came through byte-identical.
96 new config objects were added, nearly all named `jarvis*`. (That count is
from the test run above; the recipe has since gained the `remote_video` Remote
video media type and its field + displays, 7 objects more.)

The Video and Video with Sidebar components take their source from a Remote
video media item, so `media.type.remote_video` ships here even though this
recipe is otherwise sparing with media config — their component config declares
a hard dependency on it. It is core's own bundle name, not a namespaced copy,
so a site already running `remote_video` keeps exactly what it has: `strict`
is false, the existing type is left alone, and no second type is created.

Specifically **not** done, unlike `recipes/jarvis`:

- No `system.theme` action. Jarvis is installed, not enabled. Switch when ready:
  `drush config:set system.theme default jarvis`
- No `system.site` action. Your front page stays.
- No demo content, no `default_content`.
- No content types and no Canvas content templates.
- No `canvas.page_region.*` — see below, this is the important one.
- No AI stack, no webform/metatag/workflows, no editorial or UX contrib.

## Page regions: why they are not here

Canvas renders a theme's regions from its own `component_tree`. Once
`canvas.page_region.jarvis.*` config exists, blocks you placed through the
normal block layout stop appearing in those regions — not deleted, just never
rendered until re-placed inside Canvas.

On a live site that is the single most disruptive thing this recipe could do,
so it does not. Jarvis components are available to Layout Builder and to Canvas
for page content; your header, footer and sidebars keep working exactly as they
do today.

When you do want Canvas to own the regions, copy them in one at a time from
`recipes/jarvis/config/canvas.page_region.jarvis.*.yml` and re-place that
region's blocks in Canvas before moving to the next. All eleven at once is how
you lose a header on a Friday afternoon.

## Config names that are not namespaced

`strict: false` means pre-existing config is never overwritten — but also that
where your site already owns a name, this recipe's version silently never
lands. Almost everything here is namespaced `jarvis*`, but five names are not:

| Name | If your site already has it |
|---|---|
| `field.storage.media.field_media_image` | Yours wins. Fine in practice — the `image` media type simply attaches to your existing image storage. This is what happened in the test above, with core's Image media type already owning it. |
| `image.style.portrait` | Yours wins. `person.twig` renders through it. |
| `image.style.wide` | Yours wins. |
| `core.entity_view_mode.media.hero` | Yours wins. |

Check before applying:

```bash
for n in field.storage.media.field_media_image \
         image.style.portrait image.style.wide core.entity_view_mode.media.hero; do
  drush config:get "$n" >/dev/null 2>&1 && echo "EXISTS: $n"
done
```

Anything listed is yours and stays yours. Only the three image styles are worth
a second look, and only if their dimensions differ from what Jarvis expects.

## Block content types

Earlier releases shipped `card`, `hero`, `image`, `text` and `video` block
content types plus a `jarvis_blocks` module mapping them to SDCs. That whole
pathway has been retired — nothing rendered those blocks on a Canvas site,
and the generic names collided with existing sites' own types. The Jarvis
SDC components never needed them.

## What gets installed

Modules, derived from the shipped components' declared dependencies rather than
guessed: `options`, `file`, `media`, `media_library`, `image`, `link`, `text`,
`crop`, `focal_point`, `twig_tweak`, `editor`, `ckeditor5`, `linkit`,
`layout_builder`, `path`, `canvas`, `canvas_field_component`, `jarvis_canvas`,
and the `jarvis` theme.

Two of those are easy to miss and both abort the install if absent:
`linkit`, because `filter.format.jarvis_html` enables its filter plugin, and
`twig_tweak`, because `image.twig` and `person.twig` call its `|image_style`
filter. (The theme's own filter is `jarvis_image_style` — a different thing.)

The two Jarvis text formats are shipped with the AI, markdown-paste and
token-browser toolbar items removed, since those need modules this recipe
deliberately leaves out. Add them back yourself along with the modules if you
want them.

## After applying

1. `drush cache:rebuild` — this is the SDC registration step, not a formality.
   Components 404 until it runs.
2. Confirm the library: `drush sqlq "SELECT COUNT(*) FROM config WHERE name LIKE 'canvas.component.sdc.jarvis%'"` — expect 21.
3. Preview Jarvis on a throwaway page before switching the default theme.
4. Re-applying is not supported. Snapshot the database first:
   `drush sql:dump --result-file=../pre-jarvis.sql`
