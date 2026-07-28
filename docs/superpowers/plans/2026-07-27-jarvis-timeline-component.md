# Jarvis Timeline Component Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a `jarvis:timeline` SDC — six steps of icon + name + rich text, rendered as Bootstrap tabs (default), a native `<details>` accordion, or an always-open vertical rail.

**Architecture:** One component, one Twig template, three DOM branches selected by a `display` prop. Flat numbered props (`step1_`…`step6_`) rather than slots, because slotted Twig content is opaque and a parent cannot split a child into a tab strip plus a panel list. No new JS and no new dependency: Bootstrap 5's tab plugin already loads globally in `jarvis/global`, `<details name>` gives exclusive accordion behavior natively, and the rail is static.

**Tech Stack:** Drupal 11 SDC, Twig 3, Bootstrap 5 (already loaded), Font Awesome 6 Free (already loaded via `jarvis/fontawesome`), DDEV for the render loop.

**Spec:** [`docs/superpowers/specs/2026-07-27-jarvis-timeline-design.md`](../specs/2026-07-27-jarvis-timeline-design.md)

## Global Constraints

Every task's requirements implicitly include this section.

- **No `vh`/`vw` units anywhere.** Viewport units break the Canvas preview iframe.
- **No empty-string enum values.** Use the `'none'` sentinel. An SDC carrying `enum: ['']` registers but never appears in the Canvas component list.
- **Prop `examples` values must be hash-free** (no `#`), per recipe config-action ordering.
- **Six steps is a hard cap.** Canvas pins placed components to the schema version they were saved under. Do not change the step count after this ships without a `components_component_version` migration.
- **BEM naming:** `jarvis-timeline__element`, `jarvis-timeline--modifier`. Matches every other Jarvis component.
- **Spacing props come from the shared macro,** never hand-rolled: `{% import '@jarvis/jarvis-spacing.html.twig' as jarvis %}`.
- **Touch targets ≥ 44px**, not the WCAG 2.2 minimum of 24px. Jarvis holds the stricter floor.
- **Icons are always `aria-hidden="true"`.** The adjacent label carries the meaning.
- **Verify rendered output, not source.** Grepping source has shipped regressions in this theme before. Every task's check renders the component and inspects the result.
- **`renderInIsolation()` takes its argument by reference.** Always assign the render array to a variable first — `$e = [...]; print …->renderInIsolation($e);`. Passing an array literal inline fails with "could not be passed by reference".

## Where the work happens

The theme is a git submodule. `docs/` lives in the parent repo; the component lives in the submodule.

- **Implementation repo:** `/Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis` (populated, has a working DDEV site at the parent `/Users/rodmartin/code/drupaltheme`)
- **Branch:** `feat/timeline-component` inside the submodule
- **Parent repo** takes a submodule SHA bump in the final task

The worktree at `component-brainstorm-d6b665` has the submodule uninitialized (`git submodule status` shows a leading `-`); it holds the spec and this plan only.

---

### Task 0: Branch and render harness

**Files:**
- No file changes. Establishes the test loop every later task uses.

**Interfaces:**
- Consumes: nothing.
- Produces: two harnesses — `/tmp/render_timeline.sh` (markup, used by Tasks 1–3) and `/tmp/timeline_harness.sh` (rendered page, used by Tasks 4–6).

The DDEV project is `jarvis`, served at `https://jarvis.ddev.site`. The submodule sits on `master`; Task 0 branches off it.

- [ ] **Step 1: Cut the branch**

```bash
cd /Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis && git checkout -b feat/timeline-component
```

- [ ] **Step 2: Confirm the DDEV site is up**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush status --field=bootstrap
```

Expected: `Successful`. If not, run `ddev start` first.

- [ ] **Step 3: Confirm the render harness works against a known component**

This is the red/green loop for Tasks 1–4. It renders an SDC in isolation and prints the HTML, and it throws on a schema violation — so it validates both markup and `component.yml` at once.

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush php:eval '$e = ["#type" => "component", "#component" => "jarvis:stat", "#props" => ["title" => "1,200+"]]; print \Drupal::service("renderer")->renderInIsolation($e);'
```

Expected: HTML containing `jarvis-stat__number` and `1,200+`. If this fails, stop — no later task can be verified.

- [ ] **Step 4: Build the browser harness**

Tasks 4–6 assert computed style, which needs a real rendered page. Rather than
hand-placing the component in Canvas for every check, this writes a standalone
page into the docroot carrying the four stylesheets the component actually
depends on. Task 7 does the real Canvas placement once, at the end.

```bash
cat > /tmp/timeline_harness.sh <<'OUTER'
#!/usr/bin/env bash
# Usage: timeline_harness.sh <display-mode> [jarvis-bg-class]
# Writes web/timeline-harness.html and prints its URL.
set -e
MODE="$1"; BG="${2:-}"
cd /Users/rodmartin/code/drupaltheme
BODY=$(ddev drush php:eval '
$props = ["display" => "'"$MODE"'", "icon_size" => "large"];
$labels = ["October to December", "January", "February", "March", "April", "May"];
foreach ($labels as $i => $l) {
  $n = $i + 1;
  $props["step{$n}_icon"] = $i === 1 ? "none" : "fa-solid fa-calendar-days";
  $props["step{$n}_label"] = $l;
  $props["step{$n}_content"] = "<p>Step {$n} body copy for the harness.</p>";
}
$e = ["#type" => "component", "#component" => "jarvis:timeline", "#props" => $props];
print \Drupal::service("renderer")->renderInIsolation($e);
')
cat > web/timeline-harness.html <<HTML
<!doctype html>
<html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/themes/custom/jarvis/libraries/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="/themes/custom/jarvis/css/overrides.css">
<link rel="stylesheet" href="/themes/custom/jarvis/components/timeline/timeline.css">
</head><body>
<div class="container ${BG}">${BODY}</div>
<script src="/themes/custom/jarvis/libraries/bootstrap/js/bootstrap.bundle.min.js"></script>
</body></html>
HTML
echo "https://jarvis.ddev.site/timeline-harness.html"
OUTER
chmod +x /tmp/timeline_harness.sh
```

Six steps and `icon_size: large` because those are the conditions the mobile
task has to survive. Pass a second argument to wrap the component in a coloured
section — Task 6 uses `jarvis-bg--primary`.

**Harness caveat for Task 6.** On a real page `jarvis_preprocess_html` computes
`--bs-body-color` to an ADA-safe foreground for the section background; the
static harness does not run that preprocess. Task 6 therefore sets the property
by hand in the harness to stand in for it, and Task 7 re-checks the same
assertion on a genuine Canvas page where the preprocess does run. Do not treat
the harness result alone as proof for Task 6.

---

### Task 1: Component schema and tabs mode

**Files:**
- Create: `components/timeline/timeline.component.yml`
- Create: `components/timeline/timeline.twig`

**Interfaces:**
- Consumes: `@jarvis/jarvis-spacing.html.twig` macro `classes(padding, padding_size, margin, margin_size, vertical_padding)`.
- Produces: component ID `jarvis:timeline`; props `display`, `icon_position`, `icon_size`, `align`, `heading_level`, and `step1_icon`/`step1_label`/`step1_content` through `step6_*`; wrapper classes `jarvis-timeline`, `jarvis-timeline--tabs|accordion|rail`, `jarvis-timeline--icon-above|inline`, `jarvis-timeline--icon-size-small|medium|large`, `jarvis-timeline--align-start|center`; element classes `jarvis-timeline__nav`, `__label`, `__icon`, `__icon--number`, `__content`, `__panels`. Element IDs follow `jt-{uid}-tab-{n}` and `jt-{uid}-pane-{n}`.

- [ ] **Step 1: Write the failing test**

Save this as `/tmp/render_timeline.sh` — Tasks 2, 3 and 4 reuse it with a different `display` value.

```bash
cat > /tmp/render_timeline.sh <<'SH'
#!/usr/bin/env bash
# Usage: render_timeline.sh <display-mode>
cd /Users/rodmartin/code/drupaltheme
ddev drush php:eval '
$e = ["#type" => "component", "#component" => "jarvis:timeline", "#props" => [
  "display" => "'"$1"'",
  "step1_icon" => "fa-solid fa-calendar-days",
  "step1_label" => "October to December",
  "step1_content" => "<p>Information sessions run monthly.</p>",
  "step2_icon" => "none",
  "step2_label" => "January",
  "step2_content" => "<p>Application opens.</p>",
]];
print \Drupal::service("renderer")->renderInIsolation($e);
'
SH
chmod +x /tmp/render_timeline.sh
```

- [ ] **Step 2: Run it to verify it fails**

```bash
/tmp/render_timeline.sh tabs
```

Expected: FAIL — `Drupal\Core\Render\Component\Exception\ComponentNotFoundException` naming `jarvis:timeline`.

- [ ] **Step 3: Write the schema**

`components/timeline/timeline.component.yml`. The `&icon` / `&label` / `&content` anchors are reused with `<<:` so the six step groups stay readable — the same technique `large-call-to-action.component.yml` uses for its three rows. Explicit keys after a merge key override the merged value, which is how each group gets its own `title`.

```yaml
$schema: https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json
name: Timeline
status: stable
description: 'Steps with an icon, a name and rich text — shown as tabs, an accordion or a vertical rail.'
category: Jarvis
libraryOverrides:
  dependencies:
    - jarvis/fontawesome
props:
  type: object
  properties:
    display:
      title: Display
      description: 'Tabs is the default. Rail shows every step open at once.'
      type: string
      enum: [tabs, accordion, rail]
      'meta:enum':
        tabs: Tabs
        accordion: Accordion
        rail: 'Vertical rail'
      default: tabs
      examples:
        - tabs
    icon_position:
      title: 'Icon position'
      description: 'Above the name, or beside it. Applies to tabs only — accordion and rail are always inline.'
      type: string
      enum: [above, inline]
      'meta:enum':
        above: 'Above the name'
        inline: 'Beside the name'
      default: above
      examples:
        - above
    icon_size:
      title: 'Icon size'
      type: string
      enum: [small, medium, large]
      'meta:enum':
        small: 'Small (24px)'
        medium: 'Medium (32px)'
        large: 'Large (48px)'
      default: medium
      examples:
        - medium
    align:
      title: Alignment
      type: string
      enum: [start, center]
      'meta:enum':
        start: 'Left aligned'
        center: Centered
      default: center
      examples:
        - center
    heading_level:
      title: 'Heading level'
      description: 'Used by the vertical rail only. In tabs the name is a button and in the accordion it is a summary, so neither is a heading.'
      type: string
      enum: [h2, h3, h4]
      'meta:enum':
        h2: 'Heading 2'
        h3: 'Heading 3'
        h4: 'Heading 4'
      default: h3
      examples:
        - h3
    step1_icon: &icon
      title: 'Step 1 icon'
      description: 'Choosing None shows the step number in a circle instead.'
      # Value is the full Font Awesome class string, printed straight into the <i>.
      type: string
      enum:
        - 'none'
        - 'fa-solid fa-calendar-days'
        - 'fa-solid fa-clipboard-check'
        - 'fa-solid fa-file-lines'
        - 'fa-solid fa-envelope'
        - 'fa-solid fa-user-plus'
        - 'fa-solid fa-users'
        - 'fa-solid fa-graduation-cap'
        - 'fa-solid fa-magnifying-glass'
        - 'fa-solid fa-comments'
        - 'fa-solid fa-handshake'
        - 'fa-solid fa-flag-checkered'
        - 'fa-solid fa-location-dot'
        - 'fa-solid fa-dollar-sign'
        - 'fa-solid fa-star'
        - 'fa-solid fa-check'
        - 'fa-solid fa-circle-check'
        - 'fa-solid fa-arrow-right'
      'meta:enum':
        'none': 'None (show the step number)'
        'fa-solid fa-calendar-days': Calendar
        'fa-solid fa-clipboard-check': Clipboard
        'fa-solid fa-file-lines': Document
        'fa-solid fa-envelope': Envelope
        'fa-solid fa-user-plus': 'Add person'
        'fa-solid fa-users': People
        'fa-solid fa-graduation-cap': Graduation
        'fa-solid fa-magnifying-glass': Search
        'fa-solid fa-comments': Conversation
        'fa-solid fa-handshake': Handshake
        'fa-solid fa-flag-checkered': Finish
        'fa-solid fa-location-dot': Location
        'fa-solid fa-dollar-sign': Money
        'fa-solid fa-star': Star
        'fa-solid fa-check': Check
        'fa-solid fa-circle-check': 'Check in a circle'
        'fa-solid fa-arrow-right': Arrow
      default: 'none'
      examples:
        - 'fa-solid fa-calendar-days'
    step1_label: &label
      title: 'Step 1 name'
      type: string
      examples:
        - 'October to December'
    step1_content: &content
      title: 'Step 1 content'
      type: string
      contentMediaType: text/html
      'x-formatting-context': block
      examples:
        - '<p>Information sessions run monthly.</p>'
    step2_icon:
      <<: *icon
      title: 'Step 2 icon'
    step2_label:
      <<: *label
      title: 'Step 2 name'
    step2_content:
      <<: *content
      title: 'Step 2 content'
    step3_icon:
      <<: *icon
      title: 'Step 3 icon'
    step3_label:
      <<: *label
      title: 'Step 3 name'
    step3_content:
      <<: *content
      title: 'Step 3 content'
    step4_icon:
      <<: *icon
      title: 'Step 4 icon'
    step4_label:
      <<: *label
      title: 'Step 4 name'
    step4_content:
      <<: *content
      title: 'Step 4 content'
    step5_icon:
      <<: *icon
      title: 'Step 5 icon'
    step5_label:
      <<: *label
      title: 'Step 5 name'
    step5_content:
      <<: *content
      title: 'Step 5 content'
    step6_icon:
      <<: *icon
      title: 'Step 6 icon'
    step6_label:
      <<: *label
      title: 'Step 6 name'
    step6_content:
      <<: *content
      title: 'Step 6 content'
    padding:
      title: Padding
      type: string
      enum: [none, all, top, bottom, left, right]
      'meta:enum':
        none: None
        all: 'All sides'
        top: Top
        bottom: Bottom
        left: Left
        right: Right
      default: none
    padding_size:
      title: 'Padding size'
      type: string
      enum: [small, medium, large]
      'meta:enum':
        small: 'Small (10px)'
        medium: 'Medium (20px)'
        large: 'Large (30px)'
      default: medium
    margin:
      title: Margin
      type: string
      enum: [none, all, top, bottom, left, right]
      'meta:enum':
        none: None
        all: 'All sides'
        top: Top
        bottom: Bottom
        left: Left
        right: Right
      default: none
    margin_size:
      title: 'Margin size'
      type: string
      enum: [small, medium, large]
      'meta:enum':
        small: 'Small (10px)'
        medium: 'Medium (20px)'
        large: 'Large (30px)'
      default: medium
    vertical_padding:
      title: 'Vertical padding (30px)'
      type: boolean
      default: true
```

- [ ] **Step 4: Write the template with tabs mode only**

`components/timeline/timeline.twig`. Tasks 2 and 3 fill in the other two branches.

```twig
{#
/**
 * @file
 * Jarvis Timeline SDC. Six steps of icon + name + rich text, rendered as
 * Bootstrap tabs (default), a native <details> accordion, or an always-open
 * vertical rail.
 *
 * No component JS: Bootstrap's tab plugin already ships in jarvis/global and
 * <details name> gives exclusive accordion behaviour natively.
 *
 * Flat step1_*..step6_* props rather than slots — slotted content arrives as
 * opaque markup, so a parent cannot split a child into a nav list plus a panel
 * list, which tabs mode requires.
 */
#}
{% import '@jarvis/jarvis-spacing.html.twig' as jarvis %}
{% import _self as tl %}

{% macro marker(icon, number) %}
  {%- if icon and icon != 'none' -%}
    <i class="{{ icon }} jarvis-timeline__icon" aria-hidden="true"></i>
  {%- else -%}
    <span class="jarvis-timeline__icon jarvis-timeline__icon--number" aria-hidden="true">{{ number }}</span>
  {%- endif -%}
{% endmacro %}

{% set space = jarvis.classes(padding|default('none'), padding_size|default('medium'), margin|default('none'), margin_size|default('medium'), (vertical_padding is defined ? vertical_padding : true)) %}
{% set mode = display|default('tabs') %}
{% set h = heading_level|default('h3') ?: 'h3' %}
{# Unique per render so two timelines on one page never share element ids or a
   <details name> group. #}
{% set uid = random(1000000) %}
{% set steps = [
  { icon: step1_icon|default('none'), label: step1_label|default(''), content: step1_content|default('') },
  { icon: step2_icon|default('none'), label: step2_label|default(''), content: step2_content|default('') },
  { icon: step3_icon|default('none'), label: step3_label|default(''), content: step3_content|default('') },
  { icon: step4_icon|default('none'), label: step4_label|default(''), content: step4_content|default('') },
  { icon: step5_icon|default('none'), label: step5_label|default(''), content: step5_content|default('') },
  { icon: step6_icon|default('none'), label: step6_label|default(''), content: step6_content|default('') },
]|filter(s => s.label or s.content) %}
{% set wrapper = [
  'jarvis-timeline',
  'jarvis-timeline--' ~ mode,
  'jarvis-timeline--icon-' ~ (icon_position|default('above')),
  'jarvis-timeline--icon-size-' ~ (icon_size|default('medium')),
  'jarvis-timeline--align-' ~ (align|default('center')),
  space,
]|join(' ')|trim %}

{% if steps is not empty %}
  <div class="{{ wrapper }}">
    {% if mode == 'tabs' %}
      <ul class="nav nav-tabs jarvis-timeline__nav" role="tablist">
        {% for step in steps %}
          <li class="nav-item" role="presentation">
            <button class="nav-link{% if loop.first %} active{% endif %}"
                    id="jt-{{ uid }}-tab-{{ loop.index }}"
                    data-bs-toggle="tab"
                    data-bs-target="#jt-{{ uid }}-pane-{{ loop.index }}"
                    type="button"
                    role="tab"
                    aria-controls="jt-{{ uid }}-pane-{{ loop.index }}"
                    aria-selected="{{ loop.first ? 'true' : 'false' }}">
              {{ tl.marker(step.icon, loop.index) }}
              <span class="jarvis-timeline__label">{{ step.label }}</span>
            </button>
          </li>
        {% endfor %}
      </ul>
      <div class="tab-content jarvis-timeline__panels">
        {% for step in steps %}
          {# tabindex="0" so a panel with no focusable content is still
             scrollable from the keyboard. #}
          <div class="tab-pane fade{% if loop.first %} show active{% endif %} jarvis-timeline__content"
               id="jt-{{ uid }}-pane-{{ loop.index }}"
               role="tabpanel"
               aria-labelledby="jt-{{ uid }}-tab-{{ loop.index }}"
               tabindex="0">{{ step.content|raw }}</div>
        {% endfor %}
      </div>
    {% endif %}
  </div>
{% endif %}
```

- [ ] **Step 5: Clear caches and run the test to verify it passes**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush cr && /tmp/render_timeline.sh tabs
```

Expected output contains:
- `class="jarvis-timeline jarvis-timeline--tabs jarvis-timeline--icon-above jarvis-timeline--icon-size-medium jarvis-timeline--align-center jp-vpad"`
- `role="tablist"`
- `<i class="fa-solid fa-calendar-days jarvis-timeline__icon" aria-hidden="true">` for step 1
- `<span class="jarvis-timeline__icon jarvis-timeline__icon--number" aria-hidden="true">2</span>` for step 2, whose icon was `none`
- exactly two `role="tab"` and two `role="tabpanel"` — steps 3–6 were empty and filtered out
- `aria-selected="true"` exactly once
- `<p>Information sessions run monthly.</p>` unescaped

- [ ] **Step 6: Verify empty input renders nothing**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush php:eval '$e = ["#type" => "component", "#component" => "jarvis:timeline", "#props" => ["display" => "tabs"]]; print "[" . trim(\Drupal::service("renderer")->renderInIsolation($e)) . "]";'
```

Expected: `[]` — no wrapper div when every step is empty.

- [ ] **Step 7: Commit**

```bash
cd /Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis && git add components/timeline/ && git commit -m "feat(timeline): component schema and tabs rendering

Six flat step groups of icon + name + rich text. Tabs mode uses the
Bootstrap 5 tab plugin already loaded in jarvis/global, so the component
adds no JS. Steps with neither a name nor content are filtered out, and
a step whose icon is 'none' renders its number instead."
```

---

### Task 2: Accordion mode

**Files:**
- Modify: `components/timeline/timeline.twig` — add the `{% elseif mode == 'accordion' %}` branch after the tabs branch.

**Interfaces:**
- Consumes: `steps`, `uid`, and the `tl.marker(icon, number)` macro from Task 1.
- Produces: element classes `jarvis-timeline__items`, `__item`, `__summary`. The `<details>` group name is `jt-{uid}`.

- [ ] **Step 1: Write the failing test**

```bash
/tmp/render_timeline.sh accordion
```

Expected: FAIL — the output is an empty `<div class="jarvis-timeline jarvis-timeline--accordion …"></div>` with no `<details>`, because no branch matches.

- [ ] **Step 2: Add the branch**

Insert immediately before the closing `{% endif %}` of the mode branch in `timeline.twig`:

```twig
    {% elseif mode == 'accordion' %}
      <div class="jarvis-timeline__items">
        {% for step in steps %}
          {# name= makes the group exclusive with no JS. Unsupported in older
             browsers, where steps simply open independently — cosmetic only. #}
          <details class="jarvis-timeline__item" name="jt-{{ uid }}"{% if loop.first %} open{% endif %}>
            <summary class="jarvis-timeline__summary">
              {{ tl.marker(step.icon, loop.index) }}
              <span class="jarvis-timeline__label">{{ step.label }}</span>
            </summary>
            <div class="jarvis-timeline__content">{{ step.content|raw }}</div>
          </details>
        {% endfor %}
      </div>
```

- [ ] **Step 3: Run the test to verify it passes**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush cr && /tmp/render_timeline.sh accordion
```

Expected output contains:
- exactly two `<details class="jarvis-timeline__item" name="jt-`, both sharing the same uid
- ` open>` on the first `<details>` only
- one `<summary class="jarvis-timeline__summary">` per step
- no `role="tab"` anywhere

- [ ] **Step 4: Verify two timelines get different group names**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush php:eval '
$p = ["display" => "accordion", "step1_label" => "A", "step1_content" => "<p>a</p>"];
$r = \Drupal::service("renderer");
$out = "";
foreach ([1, 2] as $i) {
  $e = ["#type" => "component", "#component" => "jarvis:timeline", "#props" => $p];
  $out .= $r->renderInIsolation($e);
}
preg_match_all("/name=\"(jt-\d+)\"/", $out, $m);
print count(array_unique($m[1])) . " unique group names\n";
'
```

Expected: `2 unique group names`.

- [ ] **Step 5: Commit**

```bash
cd /Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis && git add components/timeline/timeline.twig && git commit -m "feat(timeline): accordion mode

Native <details name> group, exclusive with no JS. The per-render uid
keeps two timelines on one page from cross-triggering each other."
```

---

### Task 3: Vertical rail mode

**Files:**
- Modify: `components/timeline/timeline.twig` — add the `{% else %}` branch.

**Interfaces:**
- Consumes: `steps`, `h`, and `tl.marker(icon, number)` from Task 1.
- Produces: element classes `jarvis-timeline__rail`, `__step`, `__marker`, `__body`.

- [ ] **Step 1: Write the failing test**

```bash
/tmp/render_timeline.sh rail
```

Expected: FAIL — empty wrapper div, no `<ol>`.

- [ ] **Step 2: Add the branch**

Insert immediately before the closing `{% endif %}` of the mode branch:

```twig
    {% else %}
      {# Plain markup, not <details open> with the toggle suppressed — a
         focusable summary that does nothing is worse than no summary.
         <ol> because step order carries meaning. #}
      <ol class="jarvis-timeline__rail">
        {% for step in steps %}
          <li class="jarvis-timeline__step">
            <span class="jarvis-timeline__marker">{{ tl.marker(step.icon, loop.index) }}</span>
            <div class="jarvis-timeline__body">
              {% if step.label %}<{{ h }} class="jarvis-timeline__label">{{ step.label }}</{{ h }}>{% endif %}
              <div class="jarvis-timeline__content">{{ step.content|raw }}</div>
            </div>
          </li>
        {% endfor %}
      </ol>
```

- [ ] **Step 3: Run the test to verify it passes**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush cr && /tmp/render_timeline.sh rail
```

Expected output contains:
- one `<ol class="jarvis-timeline__rail">`
- two `<li class="jarvis-timeline__step">`
- `<h3 class="jarvis-timeline__label">October to December</h3>`
- no `<button`, no `<details`, no `role="tab"`

- [ ] **Step 4: Verify heading_level is honoured**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush php:eval '$e = ["#type" => "component", "#component" => "jarvis:timeline", "#props" => ["display" => "rail", "heading_level" => "h2", "step1_label" => "A", "step1_content" => "<p>a</p>"]]; print \Drupal::service("renderer")->renderInIsolation($e);'
```

Expected: `<h2 class="jarvis-timeline__label">A</h2>`.

- [ ] **Step 5: Commit**

```bash
cd /Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis && git add components/timeline/timeline.twig && git commit -m "feat(timeline): vertical rail mode

Static <ol>, every step open. The author-chosen heading level applies
here only — in tabs the name is a button and in the accordion a summary."
```

---

### Task 4: Base styles

**Files:**
- Create: `components/timeline/timeline.css`

**Interfaces:**
- Consumes: the class names produced by Tasks 1–3.
- Produces: the custom properties `--jarvis-timeline-icon` (glyph size) and `--jarvis-timeline-accent` (icon colour), both retargeted by Tasks 5 and 6.

- [ ] **Step 1: Write the failing test**

An SDC auto-attaches a same-named CSS file, but `renderInIsolation()` returns only
the component fragment — `#attached` libraries never become `<link>` tags in it, so
grepping that output for the stylesheet can never succeed. The signal is computed
style on the harness page, which links the stylesheet directly.

```bash
/tmp/timeline_harness.sh tabs
```

Open the printed URL and evaluate:

```js
getComputedStyle(document.querySelector('.jarvis-timeline__icon')).fontSize
```

Expected before the fix: the browser's default (`16px`), because
`components/timeline/timeline.css` does not exist yet and the harness's `<link>` to
it 404s. After the fix it is `48px` — the harness renders at `icon_size: large`.

Auto-attachment on a real Drupal page is a separate claim this harness cannot make;
Task 7 Step 1 verifies it in Canvas.

- [ ] **Step 2: Write the stylesheet**

```css
/* Jarvis Timeline — one component, three renderings. Everything keys off the
   wrapper modifier so the three DOM shapes share the icon, label and content
   rules. */

.jarvis-timeline {
  --jarvis-timeline-icon: 2rem; /* medium; size modifiers override */
  --jarvis-timeline-accent: var(--bs-primary, #2d6cdf);
}
.jarvis-timeline--icon-size-small { --jarvis-timeline-icon: 1.5rem; }
.jarvis-timeline--icon-size-medium { --jarvis-timeline-icon: 2rem; }
.jarvis-timeline--icon-size-large { --jarvis-timeline-icon: 3rem; }

.jarvis-timeline__icon {
  font-size: var(--jarvis-timeline-icon);
  line-height: 1;
  color: var(--jarvis-timeline-accent);
  flex: 0 0 auto;
}
/* icon = none: the step number in a circle the same size the glyph would be,
   so a partly-iconned set keeps one rhythm. */
.jarvis-timeline__icon--number {
  display: inline-grid;
  place-items: center;
  inline-size: var(--jarvis-timeline-icon);
  block-size: var(--jarvis-timeline-icon);
  border: 2px solid currentColor;
  border-radius: 50%;
  font-size: calc(var(--jarvis-timeline-icon) * 0.5);
  font-weight: 700;
}

.jarvis-timeline__label {
  font-weight: 600;
}

/* Editors paste tables and oversized images into the rich-text props. Contain
   them or they force the whole document to scroll sideways. */
.jarvis-timeline__content :is(img, table, iframe, pre, video) {
  max-width: 100%;
}
.jarvis-timeline__content img {
  height: auto;
}

/* --- Tabs --- */
.jarvis-timeline__nav .nav-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px; /* WCAG 2.2 target size; Jarvis holds 44, not the 24 minimum */
  white-space: nowrap;
  color: var(--bs-body-color);
}
.jarvis-timeline--icon-above .jarvis-timeline__nav .nav-link {
  flex-direction: column;
  gap: 0.35rem;
}
.jarvis-timeline__panels {
  padding-block-start: 1.5rem;
}
@media (prefers-reduced-motion: reduce) {
  .jarvis-timeline .tab-pane.fade {
    transition: none;
  }
}

/* --- Accordion --- */
.jarvis-timeline__item {
  border-block-end: 1px solid var(--bs-border-color, #dee2e6);
}
.jarvis-timeline__item:first-child {
  border-block-start: 1px solid var(--bs-border-color, #dee2e6);
}
.jarvis-timeline__summary {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 44px;
  padding-block: 0.75rem;
  cursor: pointer;
  list-style: none;
}
.jarvis-timeline__summary::-webkit-details-marker {
  display: none;
}
/* Chevron, rotated when open. */
.jarvis-timeline__summary::after {
  content: "";
  margin-inline-start: auto;
  inline-size: 0.6rem;
  block-size: 0.6rem;
  border-inline-end: 2px solid currentColor;
  border-block-end: 2px solid currentColor;
  transform: rotate(45deg);
  transition: transform 0.2s;
}
.jarvis-timeline__item[open] .jarvis-timeline__summary::after {
  transform: rotate(-135deg);
}
.jarvis-timeline__item .jarvis-timeline__content {
  padding-block-end: 1rem;
}
@media (prefers-reduced-motion: reduce) {
  .jarvis-timeline__summary::after {
    transition: none;
  }
}

/* --- Vertical rail --- */
.jarvis-timeline__rail {
  margin: 0;
  padding: 0;
  list-style: none;
}
.jarvis-timeline__step {
  display: grid;
  /* minmax(0, 1fr) so a long unbroken string wraps instead of widening the grid */
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0 1rem;
}
/* Two rows: the marker, then the connector filling whatever height is left.
   Grid rather than absolute positioning, so no fixed height is assumed. */
.jarvis-timeline__marker {
  display: grid;
  grid-template-rows: auto 1fr;
  justify-items: center;
  align-self: stretch;
}
.jarvis-timeline__step:not(:last-child) .jarvis-timeline__marker::after {
  content: "";
  grid-row: 2;
  inline-size: 2px;
  block-size: 100%;
  margin-block: 0.5rem;
  background: var(--bs-border-color, #dee2e6);
}
.jarvis-timeline__body {
  padding-block-end: 2rem;
}
.jarvis-timeline__rail .jarvis-timeline__label {
  margin-block: 0 0.5rem;
}
```

- [ ] **Step 3: Rebuild and confirm the stylesheet attaches**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush cr && /tmp/timeline_harness.sh tabs
```

Reload the printed URL and re-evaluate the Step 1 expression. Expected: `48px`.

- [ ] **Step 4: Confirm computed sizes in the browser**

```bash
/tmp/timeline_harness.sh tabs
```

Open the printed URL at a 1280px viewport (the harness already uses
`icon_size: large`) and run in the console:

```js
({
  glyph: getComputedStyle(document.querySelector('.jarvis-timeline__icon')).fontSize,
  circle: getComputedStyle(document.querySelector('.jarvis-timeline__icon--number')).borderRadius,
})
```

Expected: `glyph: "48px"`, `circle: "50%"`. Repeat with `/tmp/timeline_harness.sh rail` and confirm the connector renders between markers but not after the last one.

- [ ] **Step 5: Commit**

```bash
cd /Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis && git add components/timeline/timeline.css && git commit -m "style(timeline): base styles for all three modes

Icon size flows through a custom property so the mobile step-down and the
section-background contrast overrides have one thing to retarget. The rail
connector is a grid row rather than absolute positioning, so it assumes no
height."
```

---

### Task 5: Mobile

**Files:**
- Modify: `components/timeline/timeline.css` — append the mobile block.

**Interfaces:**
- Consumes: `--jarvis-timeline-icon` and `.jarvis-timeline__nav` from Task 4.
- Produces: no new names.

- [ ] **Step 1: Write the failing test**

```bash
/tmp/timeline_harness.sh tabs
```

Open the printed URL, resize the viewport to 375px, and run in the console:

```js
const nav = document.querySelector('.jarvis-timeline__nav');
({
  wrap: getComputedStyle(nav).flexWrap,
  scrolls: nav.scrollWidth > nav.clientWidth,
  docOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
  tabHeight: document.querySelector('.jarvis-timeline__nav .nav-link').getBoundingClientRect().height,
})
```

Expected before the fix: `wrap: "wrap"` — Bootstrap's default — and `scrolls: false`, with the strip stacked into three lines. That is the bug.

- [ ] **Step 2: Append the mobile block**

```css
/* --- Mobile ---
   Bootstrap's .nav-tabs wraps by default. Six tabs at 375px then become a
   three-line block that pushes the panel below the fold, so pin the strip to
   one line and let it scroll. */
.jarvis-timeline__nav {
  flex-wrap: nowrap;
  overflow-x: auto;
  scroll-snap-type: x proximity;
  scroll-padding-inline: 1rem;
  -webkit-overflow-scrolling: touch;
  /* Keep the platform's own affordance rather than hiding it and rebuilding an
     edge fade. iOS hides it until scroll anyway. */
  scrollbar-width: thin;
}
.jarvis-timeline__nav .nav-link {
  scroll-snap-align: start;
}
/* `safe` matters: a centred flex container that overflows puts its leading
   items out of reach of scrolling. */
.jarvis-timeline--align-center .jarvis-timeline__nav {
  justify-content: safe center;
}

/* A wide table or pre scrolls inside its own box instead of taking the
   document with it. */
.jarvis-timeline__content {
  overflow-x: auto;
}

@media (max-width: 767.98px) {
  /* A 48px icon above a label is a ~90px tall tab on a phone. */
  .jarvis-timeline--icon-size-large { --jarvis-timeline-icon: 2rem; }
  .jarvis-timeline--icon-size-medium { --jarvis-timeline-icon: 1.5rem; }

  .jarvis-timeline__step {
    gap: 0 0.75rem;
  }
  .jarvis-timeline__body {
    padding-block-end: 1.5rem;
  }
}
```

- [ ] **Step 3: Re-run the browser assertions at 375px**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush cr
```

Then, at a 375px viewport:

```js
const nav = document.querySelector('.jarvis-timeline__nav');
const doc = document.documentElement;
console.assert(getComputedStyle(nav).flexWrap === 'nowrap', 'strip must not wrap');
console.assert(nav.scrollWidth > nav.clientWidth, 'strip must scroll');
console.assert(doc.scrollWidth <= doc.clientWidth, 'document must not scroll sideways');
console.assert(document.querySelector('.jarvis-timeline__nav .nav-link').getBoundingClientRect().height >= 44, 'tab must be at least 44px tall');
'all assertions passed';
```

All four must pass.

- [ ] **Step 4: Repeat the overflow assertion for the other two modes and for hostile content**

Edit `/tmp/timeline_harness.sh` to put a 12-column table and a 2000px-wide image
into step 1's content, then regenerate for each mode. At 375px, for each of
`tabs`, `accordion` and `rail`:

```js
const doc = document.documentElement;
const c = document.querySelector('.jarvis-timeline__content');
({ pageOk: doc.scrollWidth <= doc.clientWidth, tableScrollsInside: c.scrollWidth > c.clientWidth })
```

Expected: `pageOk: true` in all three modes, and `tableScrollsInside: true`.

- [ ] **Step 5: Commit**

```bash
cd /Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis && git add components/timeline/timeline.css && git commit -m "style(timeline): mobile behaviour for the tabs strip

Bootstrap's .nav-tabs wraps by default, so six tabs at 375px became a
three-line block that pushed the panel below the fold. Pins the strip to
one line with horizontal scroll-snap, keeps the native scrollbar as the
affordance, steps the icon size down under 768px, and contains panel
content so a pasted wide table cannot scroll the document sideways.

justify-content uses \`safe center\` — a centred flex container that
overflows puts its leading items out of reach of scrolling."
```

---

### Task 6: Coloured-section contrast

**Files:**
- Modify: `components/timeline/timeline.css` — append the background-override block.

**Interfaces:**
- Consumes: `--jarvis-timeline-accent` from Task 4.
- Produces: no new names.

Every Jarvis component with a fixed accent carries this block; `large-call-to-action.css:58-83` is the established form. A parent Section sets `jarvis-bg--*`, and `jarvis_preprocess_html` has already computed `--bs-body-color` to an ADA-safe foreground for that background.

- [ ] **Step 1: Write the failing test**

```bash
/tmp/timeline_harness.sh tabs jarvis-bg--primary
```

The static harness does not run `jarvis_preprocess_html`, so stand in for it by
setting the property the preprocess would compute, then read the icon colour:

```js
document.querySelector('.jarvis-bg--primary').style.setProperty('--bs-body-color', 'rgb(255, 255, 255)');
getComputedStyle(document.querySelector('.jarvis-timeline__icon')).color;  // want "rgb(255, 255, 255)"
```

Expected before the fix: the primary blue, not white — the icon is
primary-on-primary and effectively invisible. Task 7 re-checks this on a real
Canvas page where the preprocess actually runs.

- [ ] **Step 2: Append the override block**

```css
/* --- Coloured sections ---
   On a jarvis-bg--* parent the fixed accent can fail contrast (primary text on
   a primary fill vanishes). Fall back to the section's ADA-safe foreground,
   which jarvis_preprocess_html has already computed into --bs-body-color. */
:is(
  .jarvis-bg--primary,
  .jarvis-bg--secondary,
  .jarvis-bg--bg1,
  .jarvis-bg--bg2,
  .jarvis-bg--dark,
  .jarvis-bg--light,
  .jarvis-bg--image
) .jarvis-timeline {
  --jarvis-timeline-accent: var(--bs-body-color);
}

/* Rules and the rail connector need to read on the coloured fill too. */
:is(
  .jarvis-bg--primary,
  .jarvis-bg--secondary,
  .jarvis-bg--bg1,
  .jarvis-bg--bg2,
  .jarvis-bg--dark,
  .jarvis-bg--image
) .jarvis-timeline__item {
  border-color: rgba(255, 255, 255, 0.25);
}
:is(
  .jarvis-bg--primary,
  .jarvis-bg--secondary,
  .jarvis-bg--bg1,
  .jarvis-bg--bg2,
  .jarvis-bg--dark,
  .jarvis-bg--image
) .jarvis-timeline__marker::after {
  background: rgba(255, 255, 255, 0.25);
}

/* Bootstrap's tab chrome is light-theme by default: a white active tab on a
   dark section reads as a hole. Retarget its custom properties. */
:is(
  .jarvis-bg--primary,
  .jarvis-bg--secondary,
  .jarvis-bg--bg1,
  .jarvis-bg--bg2,
  .jarvis-bg--dark,
  .jarvis-bg--image
) .jarvis-timeline__nav {
  --bs-nav-tabs-border-color: rgba(255, 255, 255, 0.25);
  --bs-nav-tabs-link-active-color: var(--bs-body-color);
  --bs-nav-tabs-link-active-bg: transparent;
  --bs-nav-tabs-link-active-border-color: currentColor;
  --bs-nav-tabs-link-hover-border-color: rgba(255, 255, 255, 0.4);
}
```

- [ ] **Step 3: Re-run the assertion**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush cr
```

```bash
/tmp/timeline_harness.sh tabs jarvis-bg--primary
```

```js
const sec = document.querySelector('.jarvis-bg--primary');
sec.style.setProperty('--bs-body-color', 'rgb(255, 255, 255)');
console.assert(
  getComputedStyle(document.querySelector('.jarvis-timeline__icon')).color === 'rgb(255, 255, 255)',
  'icon must fall back to the safe foreground'
);
'passed';
```

- [ ] **Step 4: Measure the active tab's contrast**

With the timeline on a primary-background Section, read the pair:

```js
const link = document.querySelector('.jarvis-timeline__nav .nav-link.active');
({ fg: getComputedStyle(link).color, bg: getComputedStyle(link.closest('[class*="jarvis-bg--"]')).backgroundColor })
```

Confirm the theme's own contrast engine still self-checks, then require the measured pair to reach at least 4.5:1:

```bash
cd /Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis && node js/wcag.js
```

Repeat the measurement on a light-background Section.

- [ ] **Step 5: Commit**

```bash
cd /Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis && git add components/timeline/timeline.css && git commit -m "style(timeline): fall back to the safe foreground on coloured sections

Same pattern as large-call-to-action: on a jarvis-bg--* parent the fixed
accent can vanish (primary on primary), so icons, rules and the rail
connector drop to the ADA-safe --bs-body-color the theme already computes.
Bootstrap's tab chrome is retargeted too — a white active tab on a dark
section reads as a hole."
```

---

### Task 7: Canvas verification, docs and submodule bump

**Files:**
- Modify: `/Users/rodmartin/code/drupaltheme/docs/jarvis-sdc-components.md`
- Modify: parent repo submodule pointer at `web/themes/custom/jarvis`

**Interfaces:**
- Consumes: everything from Tasks 1–6.
- Produces: nothing downstream.

- [ ] **Step 1: Confirm the component appears in Canvas and every prop binds**

```bash
cd /Users/rodmartin/code/drupaltheme && ddev drush cr
```

In the Canvas editor, confirm `Timeline` is listed under the Jarvis category and that all 23 props render in the settings tray — in particular that the six `_content` props show a CKEditor field, and that the icon selects list `None (show the step number)` rather than a blank first option.

Then confirm the stylesheet **auto-attaches**. Tasks 4–6 proved the CSS rules are
correct, but they proved it on a harness page that links `timeline.css` by hand.
Nothing so far has shown that Drupal attaches it on a real page. On the rendered
Canvas page, with no harness involved:

```js
[...document.querySelectorAll('link[rel=stylesheet]')].some(l => l.href.includes('timeline.css'))
```

Expected: `true`. If it is `false` the component is unstyled everywhere except the
harness, and no earlier task would have caught it.

- [ ] **Step 2: Run the verification matrix**

Place a six-step timeline. For each of `tabs`, `accordion` and `rail`, at 1280px, 768px and 375px: assert `document.documentElement.scrollWidth <= document.documentElement.clientWidth` and capture a screenshot.

- [ ] **Step 3: Re-check the contrast fallback on a real page**

Task 6 proved this only against the static harness, where `--bs-body-color` was
set by hand. Place the timeline inside a Section with a primary background in
Canvas, where `jarvis_preprocess_html` actually computes the value, and confirm:

```js
getComputedStyle(document.querySelector('.jarvis-timeline__icon')).color === getComputedStyle(document.body).color
```

Expected: `true`. Repeat on a light-background Section.

- [ ] **Step 4: Keyboard and screen-reader pass**

- Tabs: Left/Right arrows move between tabs; only the active tab is in the tab order (Bootstrap's roving tabindex); Tab from the strip lands on the active panel.
- Accordion: Enter and Space toggle a `<summary>`; opening step 3 closes step 1.
- Rail: tabbing through the component produces no focus stop on a marker or a label.
- Two timelines on one page: opening a step in the first does not close one in the second.
- A step with `icon: none` shows its number.

- [ ] **Step 5: Remove the harness page**

The harness wrote a file into the docroot. It is scratch, not a deliverable.

```bash
rm -f /Users/rodmartin/code/drupaltheme/web/timeline-harness.html
cd /Users/rodmartin/code/drupaltheme && git status --porcelain web/ | grep timeline-harness && echo "STILL PRESENT — remove it" || echo "clean"
```

Expected: `clean`.

- [ ] **Step 6: Document the component**

Append to the "Text and people" section of `docs/jarvis-sdc-components.md`, matching the one-paragraph-per-component style already in use:

```markdown
**Timeline** is a set of up to six steps, each with an icon, a name, and rich-text content, rendered three ways from the same props: as tabs (the default), as an accordion, or as an always-open vertical rail. It ships no JavaScript of its own — tabs reuse the Bootstrap bundle the theme already loads, and the accordion is a native `<details>` group that is exclusive without script. A step whose icon is set to None shows its position number in a circle instead, so a partly-iconned set keeps one rhythm. On a phone the tab strip stays on one line and scrolls rather than wrapping into a block that would push the panel below the fold, tab targets hold a 44px floor, and panel content is contained so a pasted wide table scrolls inside its own box instead of taking the page with it.
```

That document's opening line currently reads "all nineteen components" — update the count.

- [ ] **Step 7: Commit the theme and push the branch**

```bash
cd /Users/rodmartin/code/drupaltheme/web/themes/custom/jarvis && git add -A && git commit -m "docs(timeline): document the timeline component" && git push -u origin feat/timeline-component && git rev-parse HEAD
```

- [ ] **Step 8: Bump the submodule in the parent repo**

```bash
cd /Users/rodmartin/code/drupaltheme && git add web/themes/custom/jarvis docs/jarvis-sdc-components.md && git commit -m "theme: bump jarvis — timeline component"
```

---

## Self-Review

**Spec coverage.** Props → Task 1. The three markup modes → Tasks 1, 2, 3. Behaviour details (number fallback, per-render uid) → Tasks 1 and 2. CSS → Task 4. Mobile → Task 5. Accessibility → Tasks 1–3 for markup, Task 7 Step 3 for the keyboard pass. Canvas gotchas → Global Constraints and Task 7 Step 1. The spec's ten verification items → Tasks 5, 6 and 7.

**Two deliberate deviations from the original spec draft**, both since corrected in the spec itself:
1. The tabs strip keeps a thin native scrollbar rather than hiding it behind a scroll-driven `mask-image` fade.
2. `justify-content: safe center`, not plain `center`.

**Naming consistency.** `--jarvis-timeline-icon` and `--jarvis-timeline-accent` are declared in Task 4 and consumed unchanged in Tasks 5 and 6. Element IDs are `jt-{uid}-tab-{n}` / `jt-{uid}-pane-{n}` in Task 1 and the `<details>` group is `jt-{uid}` in Task 2 — same prefix, no collision, since the two modes never render together.

**Known gap.** `icon_position` affects tabs only; accordion and rail are always inline. This is stated in the prop's own description rather than enforced in code, because hiding a select for two of three modes needs Canvas form augmentation that would not survive a version re-pin.
