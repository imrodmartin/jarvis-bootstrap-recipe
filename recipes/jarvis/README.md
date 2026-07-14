# Jarvis site recipe

Provisions a Drupal site with the Jarvis theme and its full module stack.

## What it does

- Composes `jarvis_ai` (AI core + agents, CKEditor AI, alt-text/media-image,
  Anthropic/OpenAI/Gemini providers, Canvas + Canvas AI, Key entities).
- Enables the supporting contrib: Backup & Migrate, asset_injector,
  canvas_field_component, crop, elevenlabs, focal_point, modeler_api,
  simple_gmap, twig_tweak, default_content.
- Enables the custom modules: jarvis_blocks, jarvis_canvas.
- Installs the Jarvis theme and sets it as the default theme.
- Applies the current theme settings (colours, fonts, sizes, logo) as defaults
  from `config/jarvis.settings.yml`.

Every module is already required in the project `composer.json`, so
`composer install` downloads them; the recipe only enables + configures.

## Apply

```bash
composer install
drush recipe recipes/jarvis      # run from the project root
drush cache:rebuild
```

## Media and files

Media entities and their files are **not** shipped in the recipe (the
default_content 2.0.0-beta1 exporter is incompatible with Canvas media). Restore
them from the site backup instead:

```bash
# Database (contains media entities + all content):
drush sql:drop -y
gunzip -c backups/jarvis-db.sql.gz | drush sql:cli

# Public files (the actual images/derivatives + the theme logo):
tar -xzf backups/jarvis-public-files.tar.gz -C web/sites/default

drush cache:rebuild
```

The `backups/` artifacts contain API keys and user data — keep them private,
transfer them out of band, do not commit them.

Restoring the full DB gives you an exact clone (media + content + config); use
the recipe alone when you want a fresh structure without this site's content.
