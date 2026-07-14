# Jarvis AI recipe

Captures the full AI stack added to the Jarvis site.

## What it installs
- **key** — API key entities: `claude`, `openai`, `gemini`, `elevenlabs`
- **ai** + **ai_agents** — Drupal AI core, default provider settings, Canvas AI agents (orchestrator, component, page/template builder, title/metadata generation, triage/config agents)
- **ai_ckeditor** — AI in CKEditor
- **ai_assistant_api**, **ai_chatbot** — assistant/chatbot (modules only; no config captured)
- **ai_image_alt_text** — AI alt text + its `ai_image_alt_text` image style
- **ai_media_image** — AI media image generation
- **ai_provider_anthropic**, **ai_provider_openai**, **gemini_provider** — LLM providers
- **canvas_ai** — Canvas AI settings (requires `canvas`)

Private overlay — apply **after** the `jarvis` bootstrap recipe, on your local
machine only:

```bash
ddev drush recipe recipes/jarvis      # full site, no secrets
ddev drush recipe recipes/jarvis_ai   # local only — adds AI config + your keys
ddev drush cr
```

## ⚠️ Keys are gitignored (plaintext)
`config/key.key.{claude,openai,gemini,elevenlabs}.yml` embed live API keys (key
module `config` provider, plaintext). They are **gitignored** (`/recipes/jarvis_ai/config/`)
and are NOT tracked or committed — the rest of the AI config (agents, providers)
ships normally.

On a fresh clone the key files are absent, so the providers install unconfigured.
To enable AI, create the four `key.key.*.yml` files locally with your own
credentials (copy the shape of the other `config/*.yml`), or set the key entities
to the `env`/`file` provider and point them at your secrets.
