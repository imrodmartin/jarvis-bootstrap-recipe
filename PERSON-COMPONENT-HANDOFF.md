# Handoff: Person SDC greyed out in Canvas palette

## Paste this into a fresh session

> The `jarvis:person` SDC component shows greyed with a warning triangle in the Canvas component palette. Hover tooltip:
> `Twig\Error\RuntimeError … in Preview rendering component Person.: … ("[jarvis:person/variant] Does not have a value in the enumeration ["card","plain","image-left"]. The provided value is: "".") in "jarvis:person" at line 1.`
>
> Root cause is already isolated — do NOT re-investigate from scratch. Fix only the specific failure below.
>
> **Failure:** Canvas renders the palette preview (and any render) of person with `variant = ""`. SDC validates `""` against the enum before Twig's `default('card')` runs → crash. The person Component config entity is correctly configured (`variant` prop-source `default_value = [{"value":"card"}]`, field type `list_string`, `allowed_values_function: canvas_load_allowed_values_for_component_prop`). Yet the rendered value is `""` — meaning `card` is being coerced to empty because the allowed-values set comes back empty at render time.
>
> **Task:** Read `canvas_load_allowed_values_for_component_prop()` (in `web/modules/contrib/canvas/canvas.module` or an `.inc`) and determine why it returns an empty allowed-values array for `jarvis:person`'s `variant` enum. When allowed values are empty, the `list_string` default `card` isn't a valid option → coerced to `""` → enum validation throws. Fix so the enum's allowed values resolve (so stored `card` validates and the preview renders).
>
> **Verify** with an authenticated (user 1) HTTP-kernel sub-request rendering the palette preview, or `drush` rendering the component — confirm no enum RuntimeError and output contains `jarvis-person--card`.

## Facts established (don't redo)

- SDC `jarvis:person` loads fine; Component config entity `sdc.jarvis.person` is ENABLED, version IN SYNC (`active == generateVersionHash == 8fb8b1279b75ba75`), no incompatibility reason, `isBroken()=NO`.
- ZERO placed instances anywhere (scanned `canvas_page`, `page_region`, `content_template`); ZERO auto-save drafts. So it is NOT a corrupted-instance problem — it is the palette PREVIEW render.
- `variant` prop-source stored `default_value` is correctly `card`. Config is not the problem.
- Component list API (`/canvas/api/v0/config/component`, authed) returns person present + healthy (HTTP 200). So it is not missing/disabled — only the preview thumbnail crashes → palette greys it.

## Dead ends (do NOT repeat)

- Adding `''` to the enum → makes the prop shape UNSTORABLE → Canvas disables the Component entity (person disappears from list entirely). Reverted.
- Adding `examples` / `default` to enum props, making `name` optional+default → harmless, did NOT fix (fault is below the SDC layer, in Canvas allowed-values resolution).
- Deleting/re-enabling the Component entity → config was already correct, so no effect.

## Files touched this session (theme SDC)

- `web/themes/custom/jarvis/components/person/person.component.yml` — clean enums restored; `name` made optional with `default: 'Jane Doe'`; `examples` added to `variant`/`padding`/`padding_size`/`margin`/`margin_size`. These are cosmetically fine but did not fix the bug. Reverting them is optional.

## Environment

- ddev: `ddev drush …` (site `drupaltheme.ddev.site`, not reachable from in-app browser; use drush/HTTP-kernel sub-requests as user 1 for verification).
- Login link: `ddev drush uli --uri=https://drupaltheme.ddev.site`.
