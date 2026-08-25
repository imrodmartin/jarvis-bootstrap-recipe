# Jarvis cheat sheet

Two ddev scenarios, commands only. Reasoning and the existing-site variant:
[INSTALL.md](INSTALL.md).

---

## A. New site from scratch

```bash
git clone --recurse-submodules https://github.com/imrodmartin/jarvis-bootstrap-recipe.git mysite
cd mysite
ddev start -y
ddev composer install
ddev drush site:install standard --account-name=admin --account-pass=admin -y
ddev drush recipe /var/www/html/recipes/jarvis
ddev drush cr && ddev launch
```

Log in **admin / admin**. Front page is the Canvas component showcase.

- `--recurse-submodules` is required — the theme is a separate repo. Without it
  `web/themes/custom/jarvis` is empty and the recipe fails.
- No project name in `.ddev/config.yaml` on purpose: ddev uses the directory
  name, so this becomes `mysite.ddev.site`. Clone elsewhere, get a different
  project.
- Recipe path is `recipes/jarvis` here — the directory as it sits in the repo.
- Ignore the `public_html` rename in the README; that is only for shared hosts
  that force that docroot.

---

## B. Add Jarvis to a site you already installed (standard profile)

```bash
ddev snapshot
ddev composer config repositories.jarvis-theme '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis","no-api":true}'
ddev composer config repositories.jarvis-modules '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis-modules","no-api":true}'
ddev composer config repositories.jarvis-recipe '{"type":"vcs","url":"https://github.com/imrodmartin/jarvis-recipe","no-api":true}'
ddev composer require imrodmartin/jarvis-recipe drupal/ckeditor5_markdown drupal/default_content:^2.0@beta drupal/ai_media_image:^1.0@alpha
ddev drush recipe /var/www/html/recipes/jarvis-recipe
ddev drush cr && ddev launch
```

- Recipe path is `recipes/jarvis-recipe` here — composer installs by **package**
  name, so it differs from A.
- `"no-api": true` avoids a pointless GitHub token prompt on hosts that cannot
  reach `api.github.com`.
- The three extras have no other dependent. Without `ckeditor5_markdown` the
  apply aborts: *"The provided toolbar item markdownPaste is not valid."*
- Absolute path required — drush runs from the docroot, `recipes/` is at the
  project root.

**This is a takeover.** Default theme → Jarvis, admin theme → Claro, front page
→ `/page/2`, plus demo content. Fine on a fresh site. If you have real content
or a theme you care about, use `imrodmartin/jarvis-install-recipe` instead — it
adds Jarvis and changes none of those.

---

## Afterwards

Did anything get silently switched off? Canvas disables a component whose props
it cannot populate, and never re-enables it. Empty output means all good.

```bash
ddev drush ev 'foreach (\Drupal::service("keyvalue")->get("canvas:component:reasons")->getAll() as $k => $v) { print "$k\n"; foreach ((array) $v as $r) print "   - " . strip_tags($r) . "\n"; }'
```

Login link, if you skipped the password:

```bash
ddev drush uli
```

## Updating later

```bash
ddev composer update drupal/jarvis imrodmartin/jarvis-modules imrodmartin/jarvis-recipe imrodmartin/jarvis-install-recipe
ddev drush cr
```

`update`, not `require` — `require` rewrites your version constraint.

**Composer ships files, never config.** New media types, fields and image styles
arrive only when a recipe is applied. Re-apply the recipe when a release adds
config.

## Gotchas

| Symptom | Cause |
|---|---|
| Recipe aborts on `markdownPaste` | `drupal/ckeditor5_markdown` not required |
| `web/themes/custom/jarvis` empty | cloned without `--recurse-submodules` — fix with `git submodule update --init` |
| Recipe path not found | relative path used, or `recipes/jarvis` vs `recipes/jarvis-recipe` confused |
| Components missing from Canvas | disabled — run the keyvalue check above; if you just deleted a media type, see below |
| Media reference field accepts nothing | its `target_bundles` was emptied by a media type deletion — see below |
| AI features do nothing | no API keys ship in the repo; by design |
| composer prompts for a GitHub token | `"no-api": true` missing from a repositories entry |
| `drush updb` refuses: `jarvis_blocks` is missing | upgraded from modules 1.x, which shipped it; 2.x dropped it. Uninstall it — see INSTALL.md "Coming from modules 1.x" |
| Video component renders nothing | since theme 3.0 `video_url` is files-only; hosted players come from the media library's Remote video field |

## Deleting or renaming a media type

**Repoint everything that references the bundle BEFORE you delete it.** Deleting
a media type fires Drupal's config dependency removal, and it does not simply
delete the dependents — it *quietly mutates* them:

- **Entity reference fields** keep existing, with `target_bundles` emptied. The
  field then references nothing and the widget offers no options. You get one
  warning per field on the CLI and nothing at all in the UI afterwards.
- **Canvas components** are reset to their fallback state: `status: false`,
  `active_version: fallback`, `dependencies: []`, and the real settings moved
  out of `versioned_properties.active` into a keyed version, leaving `active`
  holding only a `last_active_version` marker. They vanish from the Canvas
  library. Re-enabling alone does not fix them — `active_version` is still
  `fallback`.

Correct order when renaming a bundle (this is what the `jarvis_image` → `image`
migration did):

1. Create the new media type
2. Move the content: update `bundle` in `media`, `media_field_data`,
   `media__field_media_*` and `media_revision__field_media_*`
3. Repoint every referrer — field `target_bundles`, `media_embed`'s
   `allowed_media_types`, Canvas component `prop_field_definitions`
4. **Then** delete the old type, which by now has no dependents
5. Remap Canvas version pins in content (`canvas_page__components` and
   `canvas_page_revision__components`), because changing `target_bundles`
   changes the component version hash

Take `ddev snapshot` first. Doing steps 3 and 4 in the wrong order is recoverable
but tedious.

### Recovering components already reset to fallback

Do not guess the version. Every component keeps its history in
`versioned_properties`; find the one whose stored key equals the hash of its own
settings and restore that:

```bash
ddev drush ev '$m=\Drupal::service(Drupal\canvas\ComponentSource\ComponentSourceManager::class);
foreach (\Drupal::configFactory()->listAll("canvas.component.sdc.") as $n) {
  $cfg=\Drupal::configFactory()->getEditable($n); $c=$cfg->getRawData();
  if ($c["active_version"] !== "fallback") continue;
  foreach ($c["versioned_properties"] as $k => $v) {
    if ($k === "active" || !isset($v["settings"]["prop_field_definitions"])) continue;
    try { $h=$m->createInstance($c["source"],["local_source_id"=>$c["source_local_id"],...$v["settings"]])->generateVersionHash(); } catch (\Throwable $e) { continue; }
    if ($h === $k) { $c["versioned_properties"]["active"]=$v; $c["active_version"]=$k; $c["status"]=TRUE; $cfg->setData($c)->save(TRUE); print "restored ".$c["id"]." -> $k\n"; break; }
  }
}'
```

Keep the historical version keys — content pinned to an older version needs them
to still exist. Afterwards confirm nothing dangles:

```bash
ddev drush ev '$a=[]; foreach (\Drupal::configFactory()->listAll("canvas.component.") as $n) { $d=\Drupal::config($n)->getRawData(); foreach (array_keys($d["versioned_properties"]) as $k) if ($k!=="active") $a[$d["id"]][$k]=1; $a[$d["id"]][$d["active_version"]]=1; }
$b=0; foreach (["canvas_page__components","canvas_page_revision__components"] as $t) foreach (\Drupal::database()->query("SELECT DISTINCT components_component_id cid, components_component_version v FROM $t") as $r) if (!isset($a[$r->cid][$r->v])) { $b++; print "DANGLING $t: $r->cid @ $r->v\n"; }
print "dangling pins: $b\n";'
```
