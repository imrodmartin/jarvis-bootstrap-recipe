# New site conversion checklist

Use this every time a client site moves onto Jarvis. Each site gets the same
start, so nothing depends on remembering how the last one went.

> Installing commands live in [INSTALL.md](INSTALL.md). Upgrading an existing
> Jarvis site lives in [upgrade-jarvis.html](upgrade-jarvis.html). This page is
> the order of operations around them.

---

## 1. Set up the project

One Claude project per client site. Jarvis keeps its own project.

- [ ] Create a project named for the site (for example `fbcaurora.in`).
- [ ] Add the site's own repo.
- [ ] Add the Jarvis repos: `jarvis`, `jarvis-modules`, `jarvis-recipe`,
      `jarvis-install-recipe`, `jarvis-bootstrap-recipe`.
- [ ] Record these in project memory on day 1:

| Fact | Example |
|---|---|
| Production URL | `https://littlemiamistatepark.org` |
| Dev / staging URL | `https://dev.littlemiamistatepark.org` |
| Host and ssh entry | cPanel, `ssh nti-littlemiamistate` |
| Docroot | `public_html` or `web` |
| ddev project name | `littlemiami` |
| Jarvis route | A (composer), B (submodule) or C (folder copy) |
| Versions at start | Drupal, Canvas, Jarvis theme, jarvis-modules |
| Client contact and deadline | Who signs off, and by when |

- [ ] Write the testing rule into memory too: **tests run on a throwaway ddev
      site only.** Never on `~/code/drupaltheme`, never on the client's live
      site. Create `~/code/jarvis-test-<topic>`, test there, then remove it:

```bash
ddev delete -Oy
```

**A Jarvis bug is fixed in the Jarvis project, not the site project.** Fix it
there, release it, then upgrade the site. `~/code/drupaltheme` is the reference
site and always runs the latest of everything, so compare against it when
something looks off.

---

## 2. Pre-flight

Nothing gets installed until you can put the site back the way you found it.

- [ ] Back up the database. On ddev:

```bash
ddev snapshot --name=pre-jarvis
```

On a host, from the composer root:

```bash
mkdir -p ~/backups && drush sql:dump --gzip --extra-dump='--no-tablespaces' --result-file=$HOME/backups/pre-jarvis.sql
```

`mkdir` and `--no-tablespaces` are both there for shared hosting. The reasons
are in [upgrade-jarvis.html](upgrade-jarvis.html).

- [ ] Back up the files: `web/sites/default/files` and the code (a git commit
      or a tarball).
- [ ] Capture the starting versions and save them to project memory:

```bash
drush core:status --fields=drupal-version,theme,admin-theme
composer show drupal/canvas drupal/canvas_field_component drupal/jarvis imrodmartin/jarvis-modules 2>/dev/null | grep -E '^(name|versions)'
```

- [ ] Pull a copy into ddev and do the whole conversion there first. Production
      comes last.

---

## 3. Pick the install path

Two packages, not interchangeable.

| The site is | Use | Recipe path after `composer require` |
|---|---|---|
| New, or a blank Drupal | `imrodmartin/jarvis-recipe` | `recipes/jarvis-recipe` |
| Live, with its own content and config | `imrodmartin/jarvis-install-recipe` | `recipes/jarvis-install-recipe` |

In this repo the same two recipes sit at `recipes/jarvis` and
`recipes/jarvis-existing`. The composer packages are what you install on a
client site.

**The full recipe is a takeover.** It sets the default theme, the admin theme,
the front page and adds demo content. Never run it on a site with real content.
If you're unsure, it's an existing site.

- [ ] Follow [INSTALL.md section A](INSTALL.md#a-a-new-site-from-scratch) for a
      fresh install.
- [ ] Follow [INSTALL.md section B](INSTALL.md#b-an-existing-site) for an
      existing site. Run its `EXISTS:` check before applying, and note anything
      it prints: that config is yours, and the recipe skips it silently.
- [ ] Use route A (composer). Routes B and C exist only on older sites.

> **Canvas 1.13 needs jarvis-modules v2.3.3.** A fresh install on Canvas 1.13
> crashes until v2.3.3 is tagged. That release carries the `field_display`
> workaround and the orphan-component check (jarvis-bootstrap-recipe PRs #46
> and #47, jarvis-modules PRs #1 and #2). Confirm the tag exists before you
> start:
>
> ```bash
> git ls-remote --tags https://github.com/imrodmartin/jarvis-modules.git | grep v2.3.3
> ```
>
> No output means it isn't tagged yet. Wait, or install on Canvas 1.12.

---

## 4. Canvas compatibility

Run these after the recipe applies and before anyone builds a page.

- [ ] Canvas version is recorded, and you're not downgrading it. A Canvas above
      Jarvis's `^1.8` floor is fine.
- [ ] **The `field_display` workaround check.** Is the
      `canvas_field_component` bug fixed upstream yet?

```bash
grep -n "choices" web/modules/contrib/canvas_field_component/src/Hook/ConfigSchemaHooks.php
```

No output: still broken, keep the Jarvis hook. Any output: fixed upstream. Take
that finding to the Jarvis project and remove the hook there, following
[the field_display check](upgrade-jarvis.html#field-display-check). Don't edit
`jarvis_canvas` on a client site.

- [ ] Nothing was disabled. Canvas switches off any component whose props it
      can't populate, and never switches it back on:

```bash
drush ev 'foreach (\Drupal::service("keyvalue")->get("canvas:component:reasons")->getAll() as $k => $v) { print "$k\n"; foreach ((array) $v as $r) print "   - " . strip_tags($r) . "\n"; }'
```

Empty output is the pass.

---

## 5. Verify

Four checks. All four pass, or the conversion isn't done.

- [ ] **Components validate.** All Jarvis SDCs exist and are enabled:

```bash
drush php:eval '$c = \Drupal::entityTypeManager()->getStorage("component")->loadMultiple();
$s = array_filter($c, fn($x) => str_starts_with($x->id(), "sdc.jarvis."));
printf("sdc.jarvis: %d (enabled %d)\n", count($s), count(array_filter($s, fn($x) => $x->status())));'
```

The two numbers must match. Then open `/admin/appearance/component/status`:
anything Canvas refused is listed there with a reason.

- [ ] **Cron runs clean.**

```bash
drush cron
```

- [ ] **dblog is clean.** On ddev, clear it before the checks so old noise
      doesn't hide new errors. On production, skip the delete. Then read it:

```bash
drush watchdog:delete all -y
```

```bash
drush watchdog:show --count=20 --severity=Error
```

```bash
drush watchdog:show --type=jarvis_canvas
```

- [ ] **Pages load.** Every key URL returns 200:

```bash
for p in / /user/login /node/1; do printf "%s %s\n" "$(curl -s -o /dev/null -w '%{http_code}' "https://SITE$p")" "$p"; done
```

Swap in the site's own important paths. Then open the front page, a Canvas
page and a content page in a browser with the console open. A 200 with a
console error still fails.

- [ ] Open a Canvas page in the editor and place one component. The editor
      loading is a separate test from the page rendering.

---

## 6. Go live

- [ ] Take a fresh production backup the same day (section 2 again, on prod).
- [ ] Deploy code with composer on the host, then:

```bash
drush updatedb -y && drush cache:rebuild
```

- [ ] Apply the recipe on production, using the same recipe as in ddev.
- [ ] Switch the theme only when you're ready to be seen:

```bash
drush config:set system.theme default jarvis -y
```

- [ ] Rerun every check in section 5 against production.
- [ ] Keep the backup until the client signs off.

---

## 7. Hand off

- [ ] Tell editors what changed and what didn't. Old placements keep their old
      shape until re-placed. See
      [New props do not appear](upgrade-jarvis.html#pinned).
- [ ] Give the client admin accounts for their people, not a shared login.
- [ ] Update project memory with the finishing versions and the go-live date.
- [ ] List any open Jarvis bugs found during the conversion, with links to
      the issues or PRs in the Jarvis project.

---

## 8. Add the site to the Jarvis sites list

The list is [Sites and their routes](upgrade-jarvis.html#sites) in
upgrade-jarvis.html. Every upgrade walks that table, so a site that isn't in it
gets missed.

- [ ] Add a row: site, where it lives (path or ssh entry, docroot), route, and
      versions with the date.
- [ ] Commit it in the Jarvis project, not the site project.

**If it isn't in the table, it doesn't get upgraded.**
