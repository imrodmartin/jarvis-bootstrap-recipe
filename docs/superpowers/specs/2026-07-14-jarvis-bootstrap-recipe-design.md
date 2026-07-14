# Jarvis Bootstrap Recipe — Design

**Date:** 2026-07-14
**Status:** Approved (design), pending spec review

## Problem

The existing recipes are stale and fragmented. They ship an unused `jarvis_sample`
content type, while the real site has evolved to use `blog`, `basic`, and
`landing_page` types (with hero images, Canvas content templates, and polished
demo content). There is no single recipe that stands up a fresh Drupal site with
the Jarvis theme, custom modules, real content types, and clean demo content.

Three recipes exist today:

- `recipes/jarvis` — installs the module stack + theme, composes `jarvis_ai`. No
  content types, no demo content, no blocks. Only sets default theme.
- `recipes/jarvis_ai` — AI module stack **plus API keys captured as plaintext
  config**. Unsafe to share.
- `web/themes/custom/jarvis/recipe/` — the only recipe with real plumbing
  (image styles, crop, focal point, media types, Canvas page regions, blocks,
  field storage) **but built entirely around the unused `jarvis_sample` type**.

## Goal

One self-contained, shareable recipe that bootstraps a new Drupal site with:
the Jarvis theme, both custom modules, the real content types (Blog, Basic,
Landing), a curated set of demo content (home page, blog posts, about page,
media), and the Canvas/media/theme plumbing — with the AI stack installed but
**no secrets shipped**.

## Decisions (from brainstorming)

1. **Demo content:** curated clean set — real content only, test junk and
   AI-throwaway media dropped.
2. **Content types:** Blog + Basic + Landing. Front page = a Landing page.
3. **AI scope:** install AI modules (canvas_ai, providers) but ship **no keys**.
   Keys stay in a separate, private `jarvis_ai` overlay applied locally.
4. **Front page:** resolved via a `/home` **path alias**, not `/node/N` — node
   IDs are not stable across installs, aliases are.
5. **Retitling:** the junk-titled landing/basic nodes are retitled `Home` /
   `About Jarvis` in the shipped content rather than inventing new nodes.

## Architecture

Collapse three stale recipes into two clean ones:

```
recipes/
  jarvis/            # THE bootstrap recipe. Self-contained, safe to share.
    recipe.yml
    config/          # regenerated from the LIVE site (not jarvis_sample)
    content/         # curated default_content — node selection drives media/files
  jarvis_ai/         # OPTIONAL private overlay: API keys ONLY (Key entities +
    recipe.yml       # provider->key bindings). Local-only, never shared.
    config/
```

Delete `web/themes/custom/jarvis/recipe/` — its still-valid plumbing config
migrates into `recipes/jarvis/config/`; its `jarvis_sample` config is dropped.
One source of truth.

### recipe.yml (recipes/jarvis)

**type:** `Site`

**install** (modules):
- Contrib: canvas, canvas_field_component, crop, focal_point, twig_tweak,
  media, media_library, image, menu_ui, menu_link_content, datetime, options,
  path, backup_migrate, asset_injector, simple_gmap, modeler_api, default_content
- AI (no keys configured): ai, ai_agents, ai_ckeditor, ai_assistant_api,
  ai_chatbot, ai_image_alt_text, ai_media_image, ai_provider_anthropic,
  ai_provider_openai, gemini_provider, canvas_ai, key
- Custom: jarvis_blocks, jarvis_canvas
- Theme: jarvis

The AI modules install unconfigured. `key` is installed but **no Key config
entities ship**, so no provider has a usable credential until `jarvis_ai` is
applied locally.

**config → actions:**
- `system.theme` default → `jarvis`
- `system.site` `page.front` → `/home`

### config/ (recipes/jarvis)

Regenerated from the live site via `drush config:get`, covering:

- Content types: `node.type.blog`, `node.type.basic`, `node.type.landing_page`
- Field storage + instances for: `field_body`, `field_hero_banner`,
  `field_publication_date`, `field_video` (bound to types per: blog has all four,
  basic has body + hero_banner, landing has body only)
- Form + view displays for each type (`core.entity_form_display.node.*.default`,
  `core.entity_view_display.node.*.default`)
- Canvas content templates for each type (`canvas.content_template.node.*`)
- Canvas page regions (all 11 `canvas.page_region.jarvis.*`)
- Blocks (all 17 `block.block.jarvis_*`)
- Image styles: `image.style.hero_banner`, `image.style.wide`,
  `image.style.portrait`
- Crop: `crop.type.focal_point`, `crop.settings`, `focal_point.settings`
- Media types: `media.type.image`, `media.type.remote_video`, their fields,
  view/form displays, the `hero` media view mode
- Theme settings: `jarvis.settings`

**Explicitly excluded:** every `jarvis_sample` config object.

### content/ (recipes/jarvis)

Generated with `drush default-content:export-references` per curated node, so
only media/files actually referenced by shipped nodes are exported (junk media
excluded automatically). Menu links authored/curated by hand.

Curated set:

| Type | Ships | Notes |
|------|-------|-------|
| blog | Building Jarvis; The Jarvis Components; Creating a Drupal 11 Theme | drop "Test Blog" |
| landing_page | one node, retitled **Home** | gets `/home` alias; set as front page |
| basic | one node, retitled **About Jarvis** | drop 3 junk basics |
| media/file | only what the above reference | real jarvis-banner/backgrounds; AI throwaways auto-dropped |
| menu_link_content (main) | Home, 3 blog posts, About Jarvis | rebuilt clean |

Path alias `/home → <landing node>` ships as content (`path_alias` entity) so the
`page.front: /home` action resolves on a fresh install.

### jarvis_ai (private overlay) — keys-only

Reduced to shipping just the Key config entities and provider->key bindings.
Retains the "plaintext keys, private use only, do not commit publicly" warning in
its description. Not composed by `recipes/jarvis`. Applied manually when AI is
wanted:

```
drush recipe recipes/jarvis      # full site, no secrets
drush recipe recipes/jarvis_ai   # local only — adds your API keys
```

## Validation

Apply to a fresh Drupal install against a throwaway database:

```
drush site:install --db-url=<throwaway> -y
drush recipe recipes/jarvis
drush cr
```

Assert:
- `jarvis` is the default theme (`drush cget system.theme default` -> jarvis)
- `/home` renders the Landing page (HTTP 200, landing markup, Canvas region output)
- Each blog post renders with its hero image (correct image style dimensions) and
  Canvas content-template output
- Referenced media resolves (no broken images/files)
- Main menu shows Home + 3 blog posts + About Jarvis, all links resolve
- No `jarvis_sample` type present
- AI modules enabled but no provider has a key (AI calls fail gracefully / are
  unconfigured, as expected)

## Out of scope

- `webform` content type and Webform module
- Shipping API keys in the bootstrap recipe
- Any content beyond the curated demo set
- Refactoring the custom modules themselves
