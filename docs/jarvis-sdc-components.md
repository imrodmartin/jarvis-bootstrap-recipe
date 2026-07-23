# Inside the Jarvis Theme: Every Component, and How It Stays Accessible

Jarvis is a Single Directory Component (SDC) theme for Drupal. Every visual building block on the page — a hero, a card, a column layout, a stat — is its own self-contained component: one folder holding its Twig template, its styles, its schema, and its behavior. Site builders drop these into Canvas or Layout Builder and compose a page without touching code. This post walks through all nineteen components Jarvis ships, one paragraph each. But first, the part that runs underneath all of them: accessibility.

## How Jarvis Handles ADA Compliance

Most themes treat accessibility as something you audit after the fact. Jarvis treats it as something the theme enforces while you build. The centerpiece is a shared WCAG contrast engine (`js/contrast.js`) that runs on the hero, card, and one/two/three-column components. When you place text over a background image, the engine samples the worst-case block of that image, calculates the real contrast ratio, and automatically raises the overlay opacity until the text clears WCAG AA (4.5:1). Dark overlays get tuned against white text; light overlays get tuned against near-black body text. You don't have to eyeball it — the floor is guaranteed.

That protection extends into the editor itself. In Canvas, every "overlay opacity" field becomes a live slider with a ✓/✗ badge (`js/canvas-overlay.js`) that scores your chosen value against the actual image in the preview and tells you, before you save, whether your setting passes on its own. Fixed-color pairings — a button's text on its background, a section's foreground on its fill — are checked the same way through `theme-settings.php`, with one-click fixes offered when the foreground color is editable. The idea is simple: surface the contrast problem at the moment you can still fix it easily.

The rest of the suite covers the WCAG success criteria that themes usually miss. Menus get full keyboard and screen-reader support through `js/menu-touch.js`: submenu parents carry `aria-haspopup` and `aria-expanded`, non-link parents become focusable buttons with Enter/Space toggles, and Escape closes an open submenu and returns focus to its trigger. The base font size emits as a percentage so a visitor's browser font preference survives (WCAG 1.4.4). Prose links are underlined, not just colored, so color isn't the only cue (1.4.1). Videos accept a captions track, new-tab links announce themselves, and the skip link has a reveal fallback for editor pages. Stat and person text colors run through a deny-by-default matrix so a light label can never land on a light section. None of this is optional configuration — it ships on by default.

## The Components

### Layout and structure

**Section** is the container that holds everything else. It's a wrapper component with a slot, giving you a full-width or boxed band you can drop other components into. Sections carry the background and spacing context that the color and contrast rules key off of, so they're the frame the rest of the page hangs on.

**1 Column** is the simplest row: a single column whose content spans the full width. Reach for it when you want one block of content — text, an image, an embedded component — to run the full measure without competing for horizontal space.

**2 Columns** places two columns side by side on desktop and stacks them full-width on mobile. It's the workhorse for the classic content-beside-content pattern, and because it participates in the shared contrast engine, a background image behind the pair is auto-tuned for readable text.

**3 Columns** gives you three columns on desktop, two on tablet, and a full-width stack on mobile. Use it for feature triplets, service grids, or any set of three parallel items that should collapse gracefully as the screen narrows — again with automatic overlay contrast when a background image is involved.

### Heroes and calls to action

**Hero** is the full-width banner that usually opens a page: a heading, subtext, a call-to-action button, and a background image. It's the flagship user of the contrast engine — drop in any photo and the overlay lifts itself until the headline stays legible, so a busy image never quietly breaks your hero text.

**Call to Action** is a centered conversion block: an overline subtitle, a heading, supporting text, and a slot for buttons. It's built for the mid-page "here's your next step" moment, using Jarvis's own background classes rather than stock Bootstrap ones so its colors stay WCAG-safe even after you change the site's primary color.

**Large Call to Action** is the heavier, more editorial CTA: an eyebrow, a large heading, lead text, three icon-plus-sentence rows separated by rules, a primary button, and a reversed-color link. Use it when a call to action needs to carry more argument — three supporting points and two ways to respond — instead of a single line.

**Button** is exactly what it says: a single call-to-action button with Bootstrap styling. It's the atomic action element other components compose with, and it's the one whose text-on-fill contrast is validated as a fixed-color pair.

### Cards and feature bands

**Card** is the flexible content card, with the image placed at the top, left, right, or as a full background. In background mode it joins the contrast engine so overlaid text stays readable; it also exposes a `heading_level` prop so its title lands at the correct place in your document outline rather than a fixed heading level.

**Card Full Image** is a full-width, two-column band where an image fills its half edge-to-edge beside a rich-text column. It's the alternating image/text "story band" you scroll past on marketing pages — bigger and more immersive than a standard card, without the boxed margins.

**Image Overlay** is an image with a colored text card tucked into its lower corner, left or right. It's a compact way to caption or headline a photo with a solid, readable panel instead of text floating directly on the image.

**Stat card** is a centered statistic: an icon, a big number, and a short description. Its number color is a four-option enum (primary, secondary, dark, light) rendered through custom classes and guarded by the deny-by-default color matrix, so a light number can't end up on a light section.

### Media

**Image** is a responsive image with an optional caption and a full-width toggle. It's the plain, dependable way to place a single picture with proper responsive behavior — the default when you don't need the framing of a card or overlay.

**Video** is a responsive video that accepts either an embed URL (YouTube or Vimeo) or a direct video file. It supports a captions track and language through `<track>`, so a video placed here can meet caption requirements rather than shipping as an inaccessible embed.

**Video with Sidebar** puts a video on one side — from the media library or a plain URL — with an eyebrow, title, description, and call-to-action buttons alongside it. It can be placed directly in Canvas or driven by a View, making it useful both for one-off feature sections and for templated, content-driven video rows.

**Map** embeds a Google Map from a one-line address, keyless in the `simple_gmap` style, with zoom control and a link out to the full map. It's the low-friction "here's where we are" block — type an address, get a map, no API key wrangling.

### Text and people

**Text** is a rich-text block with width and alignment controls. It's the everyday prose component; its links are underlined by default so they stay distinguishable from body text even inside colored sections where link color is forced to match.

**WYSIWYG** is a freeform CKEditor rich-text block, full-width by default with an optional readable measure. Where **Text** gives you constrained, aligned prose, WYSIWYG is the open canvas for longer editorial content, with the option to cap line length for comfortable reading.

**Person** is a profile block: portrait, name, position, phone, email, and a link. It's built for team and staff listings, and its name text runs through the same color-inheritance guard as the stat number so it stays readable against any section background.

## The Takeaway

Nineteen components, one consistent idea: give site builders composable blocks that look right and *stay* accessible without extra effort. The contrast engine, the editor badges, the menu ARIA, the color guards — none of it asks the person building the page to be an accessibility expert. That's the point. Good defaults, enforced quietly, so the accessible version is also the easy version.
