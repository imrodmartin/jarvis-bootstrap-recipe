# Jarvis Bootstrap Recipe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `recipes/jarvis` into one self-contained, shareable recipe that stands up a fresh Drupal site with the Jarvis theme, both custom modules, the real content types (Blog/Basic/Landing), curated demo content, and Canvas/media plumbing — installing the AI modules but shipping no secrets.

**Architecture:** A single `Site` recipe carries three things: (1) a module install list in `recipe.yml` plus config actions setting the default theme and front page; (2) a `config/` directory of config objects exported from the live site (with `uuid`/`_core` stripped, all `jarvis_sample` config removed); (3) a `content/` directory of curated `default_content` YAML produced with `default-content:export-module-with-references`, driven by six hand-picked node UUIDs. The AI stack installs unconfigured; API keys live in a separate, private `recipes/jarvis_ai` overlay applied only on local machines.

**Tech Stack:** Drupal 11 recipes, `default_content` (core), `drush` (config:get, dcem/dcemr), Canvas, DDEV (`ddev drush ...`).

## Global Constraints

- All drush runs through DDEV: `ddev drush ...`. Working dir: `/Users/rodmartin/code/drupaltheme`.
- Recipe config files MUST NOT contain `uuid:` or `_core:` keys. Strip them from every exported config file.
- No `jarvis_sample` config or content ships anywhere in `recipes/jarvis`.
- The bootstrap recipe ships NO `key.key.*` config and NO API key values. Keys live only in `recipes/jarvis_ai`.
- Do not mutate the live site's node titles; retitle by editing exported content YAML only.
- Curated node UUIDs (the ONLY nodes that ship):
  - Home (basic, retitle "Home"): `5d62d981-588f-4e4e-a9b4-f534d3207460`
  - About Jarvis (basic, retitle "About Jarvis"): `a97488dc-5653-4ed8-9f0e-8e53f1921d75`
  - Blog "Creating a Drupal 11 Theme…": `86529df7-8b5d-4f2d-8f8b-29110237da49`
  - Blog "Building Jarvis…": `450b9be5-b315-4109-b5b7-ec3f899ea807`
  - Blog "The Jarvis Components…": `f1649a96-c934-414d-b5fc-4d79defa029f`
  - Landing demo (ship as-is stub): `5e4807b7-692a-4096-8ad4-54a836f50ca9`
- Front page: alias `/home` → the Home node; `system.site:page.front` = `/home`.

---

## Task 1: Recipe skeleton + recipe.yml

**Files:**
- Create: `recipes/jarvis/recipe.yml` (overwrite existing)
- Reference (read for exact module names): current `recipes/jarvis/recipe.yml`, `recipes/jarvis_ai/recipe.yml`

**Interfaces:**
- Produces: a valid `Site` recipe manifest that Tasks 2–3 fill with `config/` and `content/`.

- [ ] **Step 1: Snapshot the current recipe** (so nothing is lost)

```bash
cd /Users/rodmartin/code/drupaltheme
cp recipes/jarvis/recipe.yml /tmp/jarvis-recipe.old.yml
```

- [ ] **Step 2: Write the new recipe.yml**

Create `recipes/jarvis/recipe.yml` exactly:

```yaml
name: 'Jarvis'
description: 'Bootstraps a new Drupal site with the Jarvis theme: custom modules, Canvas + media plumbing, the Blog/Basic/Landing content types, and curated demo content (home page, blog posts, about page, media). Installs the AI module stack unconfigured — ship your API keys separately via the private jarvis_ai recipe. Contains no secrets; safe to share.'
type: 'Site'
install:
  # Core + contrib plumbing
  - media
  - media_library
  - image
  - menu_ui
  - menu_link_content
  - datetime
  - options
  - path
  - default_content
  - twig_tweak
  - crop
  - focal_point
  - canvas
  - canvas_field_component
  - backup_migrate
  - asset_injector
  - simple_gmap
  - modeler_api
  # AI stack — installed unconfigured, NO keys ship here
  - key
  - ai
  - ai_agents
  - ai_ckeditor
  - ai_assistant_api
  - ai_chatbot
  - ai_image_alt_text
  - ai_media_image
  - ai_provider_anthropic
  - ai_provider_openai
  - gemini_provider
  - canvas_ai
  # Custom modules shipped in this codebase
  - jarvis_blocks
  - jarvis_canvas
  # The theme
  - jarvis
config:
  actions:
    system.theme:
      simpleConfigUpdate:
        default: jarvis
    system.site:
      simpleConfigUpdate:
        page.front: /home
```

- [ ] **Step 3: Validate YAML parses**

```bash
ddev drush php:eval '\Symfony\Component\Yaml\Yaml::parseFile("recipes/jarvis/recipe.yml"); print "OK\n";'
```
Expected: `OK`

- [ ] **Step 4: Commit** (skip if not a git repo — this codebase currently is not; note it and continue)

```bash
git add recipes/jarvis/recipe.yml 2>/dev/null && git commit -m "recipe: rewrite jarvis bootstrap manifest" 2>/dev/null || echo "no git repo — skipping commit"
```

---

## Task 2: Export & strip config into recipes/jarvis/config/

**Files:**
- Create: `recipes/jarvis/config/*.yml` (many)
- Create (throwaway): `/tmp/jarvis-config-list.txt`
- Delete: any stale `recipes/jarvis/config/*jarvis_sample*` if present

**Interfaces:**
- Consumes: content types/fields/displays/canvas/media/blocks/image-style/theme config that exists on the live site.
- Produces: a `config/` dir the recipe imports on apply. No `uuid:`/`_core:` keys; no `jarvis_sample`.

- [ ] **Step 1: Build the exact config name list**

```bash
cd /Users/rodmartin/code/drupaltheme
ddev drush php:eval '
$cf = \Drupal::configFactory();
$want = [];
// content types + fields + displays for the three real types
foreach (["blog","basic","landing_page"] as $t) {
  $want[] = "node.type.$t";
  foreach ($cf->listAll("field.field.node.$t.") as $c) $want[] = $c;
  foreach ($cf->listAll("core.entity_form_display.node.$t.") as $c) $want[] = $c;
  foreach ($cf->listAll("core.entity_view_display.node.$t.") as $c) $want[] = $c;
  foreach ($cf->listAll("canvas.content_template.node.$t.") as $c) $want[] = $c;
}
foreach (["field_body","field_hero_banner","field_publication_date","field_video"] as $f) $want[] = "field.storage.node.$f";
// canvas regions + jarvis blocks
foreach ($cf->listAll("canvas.page_region.jarvis.") as $c) $want[] = $c;
foreach ($cf->listAll("block.block.jarvis") as $c) $want[] = $c;
// image styles + crop + focal point
foreach (["image.style.hero_banner","image.style.wide","image.style.portrait","crop.type.focal_point","crop.settings","focal_point.settings"] as $c) $want[] = $c;
// media types, their fields, displays, view mode
foreach (["media.type.image","media.type.remote_video","core.entity_view_mode.media.hero"] as $c) $want[] = $c;
foreach ($cf->listAll("field.storage.media.") as $c) $want[] = $c;
foreach ($cf->listAll("field.field.media.") as $c) $want[] = $c;
foreach ($cf->listAll("core.entity_form_display.media.") as $c) $want[] = $c;
foreach ($cf->listAll("core.entity_view_display.media.") as $c) $want[] = $c;
// text formats used by body fields (so bodies render)
foreach ($cf->listAll("filter.format.") as $c) $want[] = $c;
// theme settings
$want[] = "jarvis.settings";
// de-dupe, drop anything jarvis_sample, keep only existing
$want = array_values(array_unique($want));
$out = [];
foreach ($want as $name) {
  if (str_contains($name, "jarvis_sample")) continue;
  if ($cf->get($name)->isNew()) continue; // does not exist
  $out[] = $name;
}
sort($out);
file_put_contents("/tmp/jarvis-config-list.txt", implode("\n", $out)."\n");
print count($out)." config objects\n";
'
cat /tmp/jarvis-config-list.txt
```
Expected: a printed count (dozens) and a list with NO `jarvis_sample` entries. Sanity-check that `node.type.blog`, `node.type.basic`, `node.type.landing_page`, `canvas.content_template.node.blog.full`, `jarvis.settings`, and at least one `filter.format.*` appear.

- [ ] **Step 2: Export each config object, stripping uuid/_core**

```bash
cd /Users/rodmartin/code/drupaltheme
mkdir -p recipes/jarvis/config
# clear any stale exports first
rm -f recipes/jarvis/config/*.yml
while IFS= read -r name; do
  [ -z "$name" ] && continue
  ddev drush php:eval '
    $d = \Drupal::config("'"$name"'")->getRawData();
    unset($d["uuid"], $d["_core"]);
    file_put_contents("recipes/jarvis/config/'"$name"'.yml", \Symfony\Component\Yaml\Yaml::dump($d, 10, 2));
  '
done < /tmp/jarvis-config-list.txt
ls recipes/jarvis/config | wc -l
```
Expected: file count matches the Step-1 count.

- [ ] **Step 3: Verify no forbidden keys and no jarvis_sample**

```bash
cd /Users/rodmartin/code/drupaltheme
echo "uuid/_core lines (want 0):"; grep -rlE '^(uuid|_core):' recipes/jarvis/config | wc -l
echo "jarvis_sample files (want 0):"; ls recipes/jarvis/config | grep -c jarvis_sample || true
```
Expected: both `0`.

- [ ] **Step 4: Spot-check a Canvas template + theme settings parse**

```bash
cd /Users/rodmartin/code/drupaltheme
ddev drush php:eval '
foreach (["canvas.content_template.node.blog.full","jarvis.settings","node.type.blog"] as $n) {
  $p = "recipes/jarvis/config/$n.yml";
  \Symfony\Component\Yaml\Yaml::parseFile($p);
  print "$n OK\n";
}'
```
Expected: three `… OK` lines.

- [ ] **Step 5: Commit**

```bash
git add recipes/jarvis/config 2>/dev/null && git commit -m "recipe: export blog/basic/landing config, drop jarvis_sample" 2>/dev/null || echo "no git repo — skipping commit"
```

---

## Task 3: Export curated content into recipes/jarvis/content/

**Files:**
- Create (throwaway module): `web/modules/custom/jarvis_demo_export/jarvis_demo_export.info.yml`
- Create: `recipes/jarvis/content/{node,media,file,menu_link_content,path_alias}/*.yml`
- Delete at end: `web/modules/custom/jarvis_demo_export/`

**Interfaces:**
- Consumes: the six curated node UUIDs (Global Constraints).
- Produces: `content/` dir importable by the recipe; Home node retitled + `/home` alias; main menu links; About/blog titles clean.

- [ ] **Step 1: Create a throwaway export module listing the six nodes**

Create `web/modules/custom/jarvis_demo_export/jarvis_demo_export.info.yml`:

```yaml
name: 'Jarvis Demo Export'
type: module
description: 'Throwaway module used only to export curated demo content. Delete after export.'
core_version_requirement: ^10 || ^11
dependencies:
  - drupal:node
  - drupal:default_content
default_content:
  node:
    - 5d62d981-588f-4e4e-a9b4-f534d3207460
    - a97488dc-5653-4ed8-9f0e-8e53f1921d75
    - 86529df7-8b5d-4f2d-8f8b-29110237da49
    - 450b9be5-b315-4109-b5b7-ec3f899ea807
    - f1649a96-c934-414d-b5fc-4d79defa029f
    - 5e4807b7-692a-4096-8ad4-54a836f50ca9
```

- [ ] **Step 2: Enable it and export with references**

```bash
cd /Users/rodmartin/code/drupaltheme
ddev drush en jarvis_demo_export -y
ddev drush dcemr jarvis_demo_export
find web/modules/custom/jarvis_demo_export/content -type f | sort
```
Expected: `content/node/*.yml` (6 files) plus `content/media/*.yml` and `content/file/*.yml` for referenced hero images. (Hero media referenced: node 4→media 5, nodes 5 & 9→media 18, node 8→media 4.)

- [ ] **Step 3: Move exported content into the recipe**

```bash
cd /Users/rodmartin/code/drupaltheme
mkdir -p recipes/jarvis/content
rm -rf recipes/jarvis/content/node recipes/jarvis/content/media recipes/jarvis/content/file
cp -R web/modules/custom/jarvis_demo_export/content/* recipes/jarvis/content/
find recipes/jarvis/content -type f | sort
```
Expected: node/media/file YAML now under `recipes/jarvis/content/`.

- [ ] **Step 4: Retitle Home and About in the exported YAML** (do NOT touch the live site)

Home node file is the one whose `default:` has `uuid: 5d62d981-588f-4e4e-a9b4-f534d3207460`. In that file, set the title to `Home`. In Drupal default_content YAML the title lives under `default.title[0].value`.

```bash
cd /Users/rodmartin/code/drupaltheme
HOME=$(grep -rl '5d62d981-588f-4e4e-a9b4-f534d3207460' recipes/jarvis/content/node)
ABOUT=$(grep -rl 'a97488dc-5653-4ed8-9f0e-8e53f1921d75' recipes/jarvis/content/node)
ddev drush php:eval '
foreach ([["'"$HOME"'","Home"],["'"$ABOUT"'","About Jarvis"]] as [$p,$t]) {
  $d = \Symfony\Component\Yaml\Yaml::parseFile($p);
  $d["default"]["title"][0]["value"] = $t;
  file_put_contents($p, \Symfony\Component\Yaml\Yaml::dump($d, 12, 2));
  print basename($p)." -> $t\n";
}'
grep -A1 'title:' "$HOME" | head -3
```
Expected: prints the two retitles; Home file shows `value: Home`.

- [ ] **Step 5: Author the `/home` path alias as content**

Create `recipes/jarvis/content/path_alias/home.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: path_alias
  uuid: 7b3f1e2a-0000-4a00-9000-000000000001
  default_langcode: en
default:
  path:
    - value: '/node/4'
  alias:
    - value: '/home'
  langcode:
    - value: en
  status:
    - value: true
```

> NOTE: `/node/4` is the live nid of the Home node. On a fresh site the imported nid may differ, which would break `/home`. Task 6 Step 4 verifies this and provides a verbatim fallback if it 404s.

- [ ] **Step 6: Author the main menu links**

Create one file per link under `recipes/jarvis/content/menu_link_content/`. menu_link_content stores `link[0].uri`. To stay nid-independent, link via the node path aliases (shipped in Step 5/7), using `internal:` URIs.

`recipes/jarvis/content/menu_link_content/01-home.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: menu_link_content
  uuid: 8c000000-0000-4a00-9000-000000000001
  default_langcode: en
default:
  title:
    - value: 'Home'
  link:
    - uri: 'internal:/home'
  menu_name:
    - value: main
  weight:
    - value: -10
  enabled:
    - value: true
  langcode:
    - value: en
```

`recipes/jarvis/content/menu_link_content/02-building-jarvis.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: menu_link_content
  uuid: 8c000000-0000-4a00-9000-000000000002
  default_langcode: en
default:
  title:
    - value: 'Building Jarvis'
  link:
    - uri: 'internal:/blog/building-jarvis-one-source-truth-your-markup'
  menu_name:
    - value: main
  weight:
    - value: -8
  enabled:
    - value: true
  langcode:
    - value: en
```

`recipes/jarvis/content/menu_link_content/03-components.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: menu_link_content
  uuid: 8c000000-0000-4a00-9000-000000000003
  default_langcode: en
default:
  title:
    - value: 'The Jarvis Components'
  link:
    - uri: 'internal:/blog/jarvis-components-flexible-design-accessible-default'
  menu_name:
    - value: main
  weight:
    - value: -7
  enabled:
    - value: true
  langcode:
    - value: en
```

`recipes/jarvis/content/menu_link_content/04-creating-theme.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: menu_link_content
  uuid: 8c000000-0000-4a00-9000-000000000004
  default_langcode: en
default:
  title:
    - value: 'Creating a Drupal 11 Theme'
  link:
    - uri: 'internal:/blog/creating-drupal-11-theme-single-directory-components'
  menu_name:
    - value: main
  weight:
    - value: -6
  enabled:
    - value: true
  langcode:
    - value: en
```

`recipes/jarvis/content/menu_link_content/05-about.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: menu_link_content
  uuid: 8c000000-0000-4a00-9000-000000000005
  default_langcode: en
default:
  title:
    - value: 'About Jarvis'
  link:
    - uri: 'internal:/about'
  menu_name:
    - value: main
  weight:
    - value: -5
  enabled:
    - value: true
  langcode:
    - value: en
```

> The blog aliases above are the live aliases confirmed to exist. The About link uses `/about`; Step 7 ships that alias so it resolves.

- [ ] **Step 7: Ship the `/about` and blog aliases**

Create `recipes/jarvis/content/path_alias/about.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: path_alias
  uuid: 7b3f1e2a-0000-4a00-9000-000000000002
  default_langcode: en
default:
  path:
    - value: '/node/12'
  alias:
    - value: '/about'
  langcode:
    - value: en
  status:
    - value: true
```

Create `recipes/jarvis/content/path_alias/blog-building.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: path_alias
  uuid: 7b3f1e2a-0000-4a00-9000-000000000003
  default_langcode: en
default:
  path: [ { value: '/node/8' } ]
  alias: [ { value: '/blog/building-jarvis-one-source-truth-your-markup' } ]
  langcode: [ { value: en } ]
  status: [ { value: true } ]
```

Create `recipes/jarvis/content/path_alias/blog-components.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: path_alias
  uuid: 7b3f1e2a-0000-4a00-9000-000000000004
  default_langcode: en
default:
  path: [ { value: '/node/9' } ]
  alias: [ { value: '/blog/jarvis-components-flexible-design-accessible-default' } ]
  langcode: [ { value: en } ]
  status: [ { value: true } ]
```

Create `recipes/jarvis/content/path_alias/blog-creating.yml`:

```yaml
_meta:
  version: '1.0'
  entity_type: path_alias
  uuid: 7b3f1e2a-0000-4a00-9000-000000000005
  default_langcode: en
default:
  path: [ { value: '/node/5' } ]
  alias: [ { value: '/blog/creating-drupal-11-theme-single-directory-components' } ]
  langcode: [ { value: en } ]
  status: [ { value: true } ]
```

- [ ] **Step 8: Remove the throwaway export module**

```bash
cd /Users/rodmartin/code/drupaltheme
ddev drush pmu jarvis_demo_export -y
rm -rf web/modules/custom/jarvis_demo_export
```
Expected: module uninstalled and directory gone.

- [ ] **Step 9: Verify content set**

```bash
cd /Users/rodmartin/code/drupaltheme
echo "nodes (want 6):"; ls recipes/jarvis/content/node | wc -l
echo "menu links (want 5):"; ls recipes/jarvis/content/menu_link_content | wc -l
echo "aliases (want 5):"; ls recipes/jarvis/content/path_alias | wc -l
echo "no jarvis_sample content (want 0):"; grep -rl jarvis_sample recipes/jarvis/content | wc -l
```
Expected: 6, 5, 5, 0.

- [ ] **Step 10: Commit**

```bash
git add recipes/jarvis/content 2>/dev/null && git commit -m "recipe: curated demo content (home, 3 blog, about, landing) + menu + aliases" 2>/dev/null || echo "no git repo — skipping commit"
```

---

## Task 4: Reduce jarvis_ai to keys-only overlay

**Files:**
- Modify: `recipes/jarvis_ai/recipe.yml`
- Reference: existing `recipes/jarvis_ai/` config (Key entities, provider bindings)

**Interfaces:**
- Consumes: AI modules already installed by `recipes/jarvis`.
- Produces: an overlay recipe that adds only `key.key.*` config + provider→key bindings.

- [ ] **Step 1: List what config jarvis_ai currently ships**

```bash
cd /Users/rodmartin/code/drupaltheme
ls recipes/jarvis_ai/config 2>/dev/null || echo "(no config dir — keys were captured via install only)"
cat recipes/jarvis_ai/recipe.yml
```
Expected: see current install list + any config. Identify Key entity config (`key.key.*`) and any `ai_provider_*.settings` that reference a key.

- [ ] **Step 2: Export the live Key entities (stripped) into the overlay**

```bash
cd /Users/rodmartin/code/drupaltheme
mkdir -p recipes/jarvis_ai/config
for name in $(ddev drush php:eval 'foreach(\Drupal::configFactory()->listAll("key.key.") as $c) print $c."\n";'); do
  ddev drush php:eval '$d=\Drupal::config("'"$name"'")->getRawData(); unset($d["uuid"],$d["_core"]); file_put_contents("recipes/jarvis_ai/config/'"$name"'.yml", \Symfony\Component\Yaml\Yaml::dump($d,10,2));'
done
# Also capture any provider->key binding settings that name a key.
for name in $(ddev drush php:eval 'foreach(["ai_provider_anthropic.settings","ai_provider_openai.settings","gemini_provider.settings"] as $c){ if(!\Drupal::config($c)->isNew()) print $c."\n"; }'); do
  ddev drush php:eval '$d=\Drupal::config("'"$name"'")->getRawData(); unset($d["uuid"],$d["_core"]); file_put_contents("recipes/jarvis_ai/config/'"$name"'.yml", \Symfony\Component\Yaml\Yaml::dump($d,10,2));'
done
ls recipes/jarvis_ai/config
```
Expected: one file per Key entity plus any provider settings files.

- [ ] **Step 3: Rewrite jarvis_ai/recipe.yml as keys-only**

Overwrite `recipes/jarvis_ai/recipe.yml`:

```yaml
name: 'Jarvis AI keys (private)'
description: 'PRIVATE, LOCAL-ONLY overlay. Adds the Key entities holding plaintext API credentials and binds the Anthropic/OpenAI/Gemini providers to them. Assumes the AI modules are already installed (they are, by the jarvis recipe). Contains secrets — never commit to a public repo, never share.'
type: 'Site'
install: {  }
config:
  import:
    key: '*'
    ai_provider_anthropic: '*'
    ai_provider_openai: '*'
    gemini_provider: '*'
```

> If Step 2 produced no `ai_provider_*.settings` files, drop those `import` lines — keep only `key: '*'`.

- [ ] **Step 4: Confirm the bootstrap recipe does NOT reference jarvis_ai or ship keys**

```bash
cd /Users/rodmartin/code/drupaltheme
echo "jarvis_ai refs in bootstrap (want 0):"; grep -c jarvis_ai recipes/jarvis/recipe.yml || true
echo "key.key files in bootstrap (want 0):"; ls recipes/jarvis/config 2>/dev/null | grep -c '^key\.key\.' || true
```
Expected: both `0`.

- [ ] **Step 5: Commit**

```bash
git add recipes/jarvis_ai 2>/dev/null && git commit -m "recipe: reduce jarvis_ai to private keys-only overlay" 2>/dev/null || echo "no git repo — skipping commit"
```

---

## Task 5: Delete the stale theme recipe

**Files:**
- Delete: `web/themes/custom/jarvis/recipe/` (entire directory — the jarvis_sample-based source)

**Interfaces:**
- Consumes: nothing (its still-valid config was regenerated in Task 2).
- Produces: one source of truth (`recipes/jarvis`).

- [ ] **Step 1: Confirm nothing references the theme recipe path**

```bash
cd /Users/rodmartin/code/drupaltheme
grep -rn 'themes/custom/jarvis/recipe' --include='*.md' --include='*.yml' --include='*.php' . | grep -v node_modules || echo "no references"
```
Expected: `no references` (or only doc mentions you will update).

- [ ] **Step 2: Delete it**

```bash
cd /Users/rodmartin/code/drupaltheme
rm -rf web/themes/custom/jarvis/recipe
ls web/themes/custom/jarvis/recipe 2>/dev/null || echo "gone"
```
Expected: `gone`.

- [ ] **Step 3: Commit**

```bash
git add -A web/themes/custom/jarvis 2>/dev/null && git commit -m "recipe: remove stale jarvis_sample theme recipe (superseded by recipes/jarvis)" 2>/dev/null || echo "no git repo — skipping commit"
```

---

## Task 6: End-to-end validation on a fresh install

**Files:**
- None created. This task proves the recipe applies cleanly and the site renders.

**Interfaces:**
- Consumes: the completed `recipes/jarvis`.
- Produces: a pass/fail signal + a verbatim fallback if the front-page alias needs it.

- [ ] **Step 1: Fresh install into a throwaway DB, then apply the recipe**

Use a separate DB so the working site is untouched.

```bash
cd /Users/rodmartin/code/drupaltheme
ddev drush site:install minimal --db-url=mysql://db:db@db/scratch -y --account-pass=admin
ddev drush recipe /var/www/html/recipes/jarvis -y
ddev drush cr
```
Expected: install completes; `recipe` reports applied with no fatal error. Note any config-already-exists collisions — a clean minimal profile should have none for our namespaced config.

- [ ] **Step 2: Assert theme + types + no jarvis_sample**

```bash
ddev drush cget system.theme default
ddev drush php:eval 'print implode(",", array_keys(\Drupal::entityTypeManager()->getStorage("node_type")->loadMultiple()))."\n";'
ddev drush php:eval 'print \Drupal::entityTypeManager()->getStorage("node_type")->load("jarvis_sample") ? "FAIL jarvis_sample present\n" : "OK no jarvis_sample\n";'
```
Expected: `default: jarvis`; node types include `blog,basic,landing_page`; `OK no jarvis_sample`.

- [ ] **Step 3: Assert content imported (6 nodes, retitles, media)**

```bash
ddev drush php:eval '
$s=\Drupal::entityTypeManager()->getStorage("node");
$n=$s->loadByProperties(["uuid"=>"5d62d981-588f-4e4e-a9b4-f534d3207460"]); $n=reset($n);
print "Home title: ".$n->label()."\n";
print "node count: ".count($s->loadMultiple())."\n";
print "media count: ".count(\Drupal::entityTypeManager()->getStorage("media")->loadMultiple())."\n";
'
```
Expected: `Home title: Home`; node count `6`; media count ≥ 3 (the referenced hero images).

- [ ] **Step 4: Assert front page `/home` resolves; apply fallback if not**

```bash
ddev drush php:eval '
$front=\Drupal::config("system.site")->get("page.front");
$al=\Drupal::service("path_alias.manager")->getPathByAlias("/home");
print "page.front=$front  /home -> $al\n";
print ($al!=="/home" ? "OK alias resolves\n" : "WARN /home alias missing/unmapped\n");
'
curl -s -o /dev/null -w "%{http_code}\n" "$(ddev drush php:eval 'print \Drupal::request()->getSchemeAndHttpHost();')/home"
```
Expected: `page.front=/home`, `/home ->` a real system path (e.g. `/node/1`), HTTP `200`.

**Fallback (only if `/home` did NOT resolve because the imported nid ≠ 4):** the path_alias `path: /node/4` pointed at a nid that doesn't exist on the fresh site. Fix by resolving nodes by UUID and rewriting aliases + front page. Add this function to `web/modules/custom/jarvis_canvas/jarvis_canvas.module` (jarvis_canvas is installed by the recipe), run it once, then re-test:

```php
/**
 * Repoint demo aliases + front page at nodes by UUID (nid-independent).
 */
function jarvis_canvas_fix_demo_aliases(): void {
  $map = [
    '5d62d981-588f-4e4e-a9b4-f534d3207460' => '/home',
    'a97488dc-5653-4ed8-9f0e-8e53f1921d75' => '/about',
    '450b9be5-b315-4109-b5b7-ec3f899ea807' => '/blog/building-jarvis-one-source-truth-your-markup',
    'f1649a96-c934-414d-b5fc-4d79defa029f' => '/blog/jarvis-components-flexible-design-accessible-default',
    '86529df7-8b5d-4f2d-8f8b-29110237da49' => '/blog/creating-drupal-11-theme-single-directory-components',
  ];
  $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
  $aliasStorage = \Drupal::entityTypeManager()->getStorage('path_alias');
  foreach ($map as $uuid => $alias) {
    $nodes = $nodeStorage->loadByProperties(['uuid' => $uuid]);
    $node = reset($nodes);
    if (!$node) {
      continue;
    }
    $existing = $aliasStorage->loadByProperties(['alias' => $alias]);
    $entity = reset($existing) ?: $aliasStorage->create(['alias' => $alias, 'langcode' => 'en']);
    $entity->set('path', '/node/' . $node->id())->save();
  }
  \Drupal::configFactory()->getEditable('system.site')->set('page.front', '/home')->save();
}
```

Run once: `ddev drush php:eval 'jarvis_canvas_fix_demo_aliases();'` then re-run Step 4. If needed, this is the shippable fix — keep the function in `jarvis_canvas.module` so re-applying the recipe self-heals.

- [ ] **Step 5: Assert a blog post renders with its hero image**

```bash
ddev drush php:eval '
$nodes=\Drupal::entityTypeManager()->getStorage("node")->loadByProperties(["uuid"=>"450b9be5-b315-4109-b5b7-ec3f899ea807"]);
$n=reset($nodes);
$hero=$n->get("field_hero_banner");
print "Building Jarvis hero set: ".($hero->isEmpty()?"NO":"YES")."\n";
'
curl -s -o /dev/null -w "%{http_code}\n" "$(ddev drush php:eval 'print \Drupal::request()->getSchemeAndHttpHost();')/blog/building-jarvis-one-source-truth-your-markup"
```
Expected: `hero set: YES`; HTTP `200`.

- [ ] **Step 6: Tear down the scratch DB** (leave working site alone)

```bash
ddev mysql -e "DROP DATABASE IF EXISTS scratch;" 2>/dev/null || echo "drop scratch db manually if it persists"
```

- [ ] **Step 7: Final commit (docs + any fallback code)**

```bash
git add -A 2>/dev/null && git commit -m "recipe: validated jarvis bootstrap end-to-end" 2>/dev/null || echo "no git repo — skipping commit"
```

---

## Notes on rendering (why thin bodies still render)

The Blog/Basic/Landing types render through `canvas.content_template.node.<type>.full` config (shipped in Task 2), not through per-node component trees. The template composes the layout (hero, body, etc.) from node fields, so even a short-body node renders the full designed page. Do not expect a `component_tree`/`canvas` field on the nodes — there isn't one.

## Self-review coverage map

- Spec "collapse 3 recipes → 2" → Tasks 1, 4, 5.
- Spec "config regenerated from live, no jarvis_sample, no uuid/_core" → Task 2.
- Spec "curated content, node-driven media, retitles, menu, /home alias, front page" → Task 3 + Task 1 config action.
- Spec "AI modules no keys; jarvis_ai keys-only private" → Task 1 install list + Task 4.
- Spec validation asserts → Task 6 (theme, /home, blog hero, media, no jarvis_sample, AI-without-keys implied by no key.key config shipped).
