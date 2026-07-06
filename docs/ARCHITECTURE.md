# YooY AI Studio Architecture

## Product principle
YooY AI Studio is not a single generator. It is an AI Creator OS.

## Core Engine
`YooY_Core_Engine` is the central hub that boots, registers, and connects all modules.

- `plugin/yooy-ai-studio/includes/core/` — Engine, Registry, REST Controller
- `modules/*/module.php` — Module entry points (auto-discovered)
- REST namespace: `yoy-ai-studio/v1`

### Connected modules
- AI Router — provider selection and failover
- **Video Studio** — Generator, Canvas, Templates, Advanced, Gallery, History, Prompt Reuse, Storyboard, API Router (Runway/Topview)
- **Image Studio** — Prompt, Reference Image, Aspect Ratio, Resolution, Lighting, Composition, Style, Negative Prompt, Seed, Quality, Image Count, Edit, Upscale, Inpaint, Outpaint, Prompt Reuse, Gallery, API Router (GPT Image/Topview)
- **Music Studio** — Lyrics, Genre, Mood, Tempo, Instrument, Vocal, Language, Structure, Reference Song, Negative Prompt, Advanced Settings, History, Gallery, Prompt Reuse, API Router, Credits (Suno)
- **Avatar Studio** — Avatar, Voice, Lip Sync, Expression, Gesture, Camera, Emotion, Subtitle, Background, Scene, API Router, Gallery, Prompt History (Vidu/HeyGen)
- **Voice Studio** — Text to Speech, Voice Clone, Emotion, Language, Speed, Pitch, Pause, Advanced, History, Gallery, API Router (ElevenLabs)
- Credits — balance, ledger, plans
- Gallery — showcase and My Works
- Projects — user workspace
- Prompt Library — saved prompts and Korean presets
- User Profile — identity and preferences
- Marketplace — prompt templates and guides
- Community — public feed
- Settings — studio and global config

## Core modules
- Studio Shell: left navigation, account profile, credits, workspace router
- AI Router: provider selection and failover
- Generator Engines: Video, Image, Music, Voice, Avatar, Writing
- Korean Context Engine: Korea-first prompt localization
- Gallery Engine: Official Showcase, Community Gallery, My Works
- Prompt Market: prompt, guide, settings, remix flow
- Credit Engine: KRW-fixed credits, YOY realtime conversion, WooCommerce payment
- Admin Console: providers, models, credits, products, templates, gallery, logs

## Result lifecycle
Generate -> Store work -> Show result actions -> Add to My Works -> Optional public gallery -> Prompt reuse -> Marketplace.
