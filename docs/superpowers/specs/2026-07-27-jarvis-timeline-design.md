# Jarvis Timeline Component — Design

**Date:** 2026-07-27
**Component:** `jarvis:timeline`
**Status:** Approved design, not yet implemented

## Purpose

A step/phase component: each step has an icon, a name, and rich-text content. Three
display modes share one set of props — tabs (default), accordion, and vertical rail.

Modeled on [meridian-charter.dripyard.com](https://meridian-charter.dripyard.com/admissions-process),
which renders the same content shape two ways: a Shoelace `<sl-tab-group>` with the
icon stacked above the label, and a bordered `<details>` accordion for the five-phase
admissions timeline. Jarvis gets both plus an always-open rail, in one component.

## Why one component, not parent + child

Dripyard splits this into `tab-group` + `tab` + `tab-panel` because Shoelace is a web
component — `slot="nav"` lets a single child element project its button into the tab
strip and its content into the panel area simultaneously.

Plain Twig cannot do that. Slotted content arrives as opaque rendered markup, so a
parent cannot split a child into a `<nav>` list plus a separate panel list. A
slot-based design would make tabs mode impossible to build.

Flat numbered props keep every step's data visible to the template, so one template
can emit three completely different DOM shapes. This also matches the existing
`large-call-to-action`, which uses the same `row1_`/`row2_`/`row3_` pattern.

## Props

### Display

| Prop | Type | Values | Default |
|---|---|---|---|
| `display` | enum | `tabs`, `accordion`, `rail` | `tabs` |
| `icon_position` | enum | `above`, `inline` | `above` |
| `icon_size` | enum | `small` (24px), `medium` (32px), `large` (48px) | `medium` |
| `align` | enum | `start`, `center` | `center` |
| `heading_level` | enum | `h2`, `h3`, `h4` | `h3` |

`heading_level` applies to rail mode only — in tabs mode the label is a `<button>`
and in accordion mode it is a `<summary>`, neither of which should be a heading.
Follows the `heading_level` precedent set by `card`.

### Steps

Six groups, `step1_` through `step6_`, each with three props:

| Suffix | Type | Notes |
|---|---|---|
| `_icon` | enum | Font Awesome class string, plus a `'none'` sentinel. Defined once as a YAML anchor (`&icon`) and reused via `<<: *icon`, same as `large-call-to-action`. |
| `_label` | string | Step name. |
| `_content` | string | `contentMediaType: text/html`, `x-formatting-context: block` — renders as a WYSIWYG in the Canvas tray. Same shape as `stat`'s `body`. |

Icon enum values (all `fa-solid`): `none`, `calendar-days`, `clipboard-check`,
`file-lines`, `envelope`, `user-plus`, `users`, `graduation-cap`,
`magnifying-glass`, `comments`, `handshake`, `flag-checkered`, `location-dot`,
`dollar-sign`, `star`, `check`, `circle-check`, `arrow-right`.

Steps with neither a label nor content render nothing. A three-step timeline and a
six-step timeline use the same component.

### Spacing

Standard Jarvis set via `@jarvis/jarvis-spacing.html.twig`: `padding`,
`padding_size`, `margin`, `margin_size`, `vertical_padding`.

## Markup per mode

The template builds a filtered `steps` array first, then branches once on `display`.

```twig
{% set uid = random(100000) %}
{% set steps = [
  { icon: step1_icon|default('none'), label: step1_label, content: step1_content },
  ...
]|filter(s => s.label or s.content) %}
```

### `tabs` — Bootstrap 5

`bootstrap.bundle.min.js` already loads globally in `jarvis/global`, so tab behavior
costs zero added bytes and arrives with correct ARIA and arrow-key navigation.

```html
<ul class="nav nav-tabs jarvis-timeline__nav" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tl-{uid}-tab-1"
            data-bs-toggle="tab" data-bs-target="#tl-{uid}-pane-1"
            type="button" role="tab"
            aria-controls="tl-{uid}-pane-1" aria-selected="true">
      <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
      <span class="jarvis-timeline__label">October – December</span>
    </button>
  </li>
</ul>
<div class="tab-content">
  <div class="tab-pane fade show active" id="tl-{uid}-pane-1"
       role="tabpanel" aria-labelledby="tl-{uid}-tab-1" tabindex="0">…</div>
</div>
```

`tabindex="0"` on the pane so keyboard users can scroll a panel whose content has no
focusable element.

### `accordion` — native `<details>`

```html
<details class="jarvis-timeline__item" name="tl-{uid}" open>
  <summary class="jarvis-timeline__summary">
    <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
    <span class="jarvis-timeline__label">October – December</span>
  </summary>
  <div class="jarvis-timeline__content">…</div>
</details>
```

First step gets `open`. The `name` attribute makes the group exclusive with no JS
(Chrome 120+, Safari 17.2+, Firefox 130+). On older browsers steps simply all open
independently — a cosmetic degradation, not a break.

### `rail` — always open

```html
<ol class="jarvis-timeline__rail">
  <li class="jarvis-timeline__step">
    <span class="jarvis-timeline__marker" aria-hidden="true">
      <i class="fa-solid fa-calendar-days"></i>
    </span>
    <div class="jarvis-timeline__body">
      <h3 class="jarvis-timeline__label">October – December</h3>
      <div class="jarvis-timeline__content">…</div>
    </div>
  </li>
</ol>
```

Genuinely different DOM, not `<details open>` with the toggle suppressed — a
focusable `<summary>` that does nothing is worse than no summary. `<ol>` because
step order carries meaning.

## Behavior details

- **Icon `none` renders the step number** as the marker instead of an empty circle.
  Keeps rail and tabs looking deliberate on icon-free content. Decorative, so
  `aria-hidden`.
- **`uid` from `random()`** so two timelines on one page don't collide on element
  IDs or on the `<details name>` group. Dripyard does the same (their panel names
  carry a hash suffix, e.g. `lottery-system-1072969130`).

## CSS

`components/timeline/timeline.css`, keyed off a modifier class on the wrapper
(`jarvis-timeline--tabs`, `--accordion`, `--rail`).

- Icon size via a custom property (`--jarvis-timeline-icon`) set by the
  `icon_size` class; the `<i>` reads it for `font-size`.
- `icon_position: above` → `.nav-link { display: grid; justify-items: center; }`;
  `inline` → `display: inline-flex; gap: .5rem;`.
- Tabs nav on narrow screens: `flex-wrap: nowrap; overflow-x: auto;` with
  `scroll-snap-type: x proximity`. Six tabs otherwise crush on a phone.
- Rail line: `grid-template-columns: auto 1fr` on the `<li>`, connector drawn as a
  `::before` on the marker column. No absolute positioning against a fixed height.
- `@media (prefers-reduced-motion: reduce)` disables the Bootstrap `.fade`
  transition on panes.
- **No `vh`/`vw` anywhere** — viewport units break the Canvas preview iframe.

## Accessibility

- Bootstrap supplies `role="tablist"`/`tab`/`tabpanel` and arrow-key navigation in
  tabs mode; `<details>`/`<summary>` and `<ol>` are natively correct in the other two.
- All icons are `aria-hidden="true"`; the adjacent label carries the meaning. This
  matches the existing note on the `jarvis/fontawesome` library.
- Labels are real headings in rail mode only, at the author's chosen level.
- Accordion content stays findable by Ctrl+F in browsers that render closed
  `<details>` content as searchable.

## Canvas gotchas

- **Six steps is a hard cap.** Canvas pins placed components to the schema version
  they were saved under, so raising the cap later means re-placing existing
  instances or running a `components_component_version` migration.
- **`'none'` sentinel, never an empty-string enum value.** An SDC carrying an
  `enum: ['']` registers fine but never appears in the Canvas component list.
- Prop `examples` must be hash-free (config-action ordering: config import runs
  before actions).
- The `contentMediaType: text/html` props render a CKEditor field in the settings
  tray. Known unrelated limitation: the `ai_ckeditor` AI Assistant button no-ops
  inside the Canvas tray.

## Files

```
web/themes/custom/jarvis/components/timeline/timeline.component.yml
web/themes/custom/jarvis/components/timeline/timeline.twig
web/themes/custom/jarvis/components/timeline/timeline.css
```

`libraryOverrides` declares `jarvis/fontawesome`. No new JS file and no new
dependency — Bootstrap and Font Awesome are already loaded.

Jarvis is a git submodule (`web/themes/custom/jarvis` →
`github.com/imrodmartin/jarvis.git`); the implementation commits there and the
parent repo takes a submodule bump.

## Verification

Rendered checks, not source greps — grepping source has shipped regressions in this
theme before.

1. Place the component in Canvas in each of the three modes; confirm all three
   appear in the component list and all props bind.
2. Screenshot each mode at 1280px and 375px. Confirm the tabs nav scrolls rather
   than wrapping into a crush at 375px.
3. Keyboard: arrow keys move between tabs and only the active tab is in the tab
   order; Enter/Space toggles a `<summary>`; the rail has no phantom focus stops.
4. Confirm accordion exclusivity — opening step 3 closes step 1.
5. Confirm two timelines on one page don't cross-trigger each other.
6. Check computed contrast on the active `.nav-link` against the section
   background in both light and dark section themes.
7. Confirm a step with `icon: none` shows its step number.

## Out of scope

Per-step links, per-step dates, per-step images, animated scroll reveal, a
horizontal rail variant, and Views-driven population. Add when a real page needs one.
