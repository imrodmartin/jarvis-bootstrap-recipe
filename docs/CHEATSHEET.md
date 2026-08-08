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
| Components missing from Canvas | disabled — run the keyvalue check above |
| AI features do nothing | no API keys ship in the repo; by design |
| composer prompts for a GitHub token | `"no-api": true` missing from a repositories entry |
