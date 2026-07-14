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

## Apply
```bash
ddev drush recipe recipes/jarvis_ai
ddev drush cr
```

## ⚠️ Keys are plaintext
`config/key.key.*.yml` embed live API keys (key module `config` provider, plaintext).
Personal/private use only. **Do not commit to a public repo or share.** Add
`recipes/jarvis_ai/config/key.key.*.yml` to `.gitignore` if this repo goes public,
or swap the key entities to the `env`/`file` provider first.
