# Jarvis AI keys (private overlay)

The main `jarvis` recipe installs **and configures** the whole AI stack:
Drupal AI core, agents (Canvas orchestrator/component/page/template/title/
metadata + triage agents), CKEditor AI, alt-text and media image generation,
the Anthropic/OpenAI/Gemini providers, and Canvas AI — everything except the
API keys.

This overlay is now **keys only**.

## ⚠️ Keys are gitignored (plaintext)

`config/key.key.{claude,openai,gemini,elevenlabs}.yml` embed live API keys
(key module `config` provider, plaintext). They are **gitignored** and never
committed. On a fresh clone this directory contains no key files and applying
the overlay is a harmless no-op — the AI providers run unconfigured until keys
exist.

## Apply

Put your `key.key.*.yml` files in `config/` (or copy them from your previous
site), then:

```bash
ddev drush recipe /var/www/html/recipes/jarvis_ai
ddev drush cr
```

Without ddev, the path is just `recipes/jarvis_ai` from the project root.

No partial config import is needed anymore — the AI configuration itself ships
in the main recipe; this only adds credentials.

## Key file shape

Each file is a standard key entity, e.g. `key.key.claude.yml`:

```yaml
langcode: en
status: true
dependencies: {  }
id: claude
label: Claude
description: ''
key_type: authentication
key_type_settings: {  }
key_provider: config
key_provider_settings:
  key_value: sk-ant-REPLACE-ME
key_input: text_field
key_input_settings: {  }
```

Create them via the Key UI (`/admin/config/system/keys`) on any site and
export, or copy this shape.
