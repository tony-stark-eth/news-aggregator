# News Aggregator

## What It Is

A self-hosted RSS/Atom aggregator that uses free AI models to categorize, summarize, extract keywords, translate, and score every article automatically. It runs on a single Docker host with zero external dependencies beyond an optional OpenRouter API key. Without AI, the system falls back to rule-based enrichment and keeps working.

## Key Features

**Feed Management**
- 16+ preconfigured sources across 5 categories (Politics, Business, Tech, Science, Sports)
- Add any RSS/Atom feed with configurable fetch intervals and reliability weights
- Source health monitoring with automatic disable on persistent failures
- OPML import and export for bulk source management with duplicate detection

**AI Enrichment (Three-Phase Pipeline)**
- Phase 1 (sync): rule-based categorization, summarization, keyword extraction, scoring -- articles appear instantly
- Phase 1.5 (sync): full-text article fetch via Readability with per-domain rate limiting
- Phase 2 (async): AI enrichment via OpenRouter free models -- articles upgrade in-place when complete
- Keyword quality filter removes noise tokens, keeps named entities (people, orgs, places)
- Sentiment scoring (-1.0 to +1.0) extracted in the same AI call at zero extra cost; rule-based keyword fallback
- Multi-language translation to all configured display languages (EN/DE/FR) with originals preserved

**Smart Alerts**
- Keyword-based rules (instant, free, no AI)
- AI-powered severity evaluation with custom context prompts ("I hold Tesla stock, alert on EV policy changes")
- Hybrid mode: keyword match first, AI confirms only on hits -- cuts API calls by ~90%
- Transport-agnostic notifications (Pushover, Telegram, Slack, Discord, email)
- Per-rule cooldown, urgency levels, category filters
- YAML fixture files for version-controlled alert strategies

**Periodic Digests**
- Configurable daily/weekly AI-generated editorial summaries
- Category filters, article limits, cron scheduling
- On-demand generation via "Run Now" button
- Rule-based fallback produces structured article lists without AI

**Scoring & Ranking**
- Category weight, recency decay, source reliability, multi-source coverage bonus
- AI confidence boost for enriched articles
- Sentiment slider (-10 to +10) in navbar re-ranks articles by mood; extreme values filter opposite sentiment
- Chat assistant tone adapts based on slider position (hopeful at +4+, critical at -4-)
- Score explanation tooltip on every article
- Periodic rescoring keeps rankings fresh

**Article Management**
- Bookmarks -- save for later, persist per-user, filter dashboard
- Per-user read state tracking
- Content fingerprint deduplication across sources (URL, title similarity, content hash)
- Configurable data retention with automatic cleanup

**Real-time Updates**
- Mercure SSE push via built-in FrankenPHP/Caddy hub -- zero additional infrastructure
- New articles banner appears while reading
- Article cards update in-place when AI enrichment completes
- htmx for declarative partial page updates, no-reload filtering, inline actions

**Search**
- Full-text search via SEAL + Loupe (SQLite-based, zero infrastructure)
- Covers title, content, summary, source name, category, and extracted keywords
- Inline dashboard filter for instant client-side search-as-you-type
- Auto-reindex on article creation, daily full reindex as safety net

**Operations**
- Health check endpoint (`/health`) for container orchestration, no auth required
- Settings UI for runtime configuration without container restart
- Dynamic paid model routing -- automatic acceleration when enrichment queue is deep
- AI model quality tracking with circuit breaker (3 failures = 24h fallback)
- Blocked model list via environment variable

## Competitive Advantage

| Feature | Miniflux | FreshRSS | Inoreader | Feedly | Readwise Reader | **News Aggregator** |
|---------|----------|----------|-----------|--------|-----------------|---------------------|
| Self-hosted | Yes | Yes | No | No | No | **Yes** |
| AI categorization | No | No | Paid | Paid | No | **Free (OpenRouter)** |
| AI summarization | No | No | Paid | Paid (Leo) | No | **Free** |
| Keyword extraction | No | No | No | No | No | **Yes** |
| Multi-language translation | No | No | No | No | No | **Yes** |
| Smart alerts with AI | No | No | Paid | Paid | No | **Free** |
| AI editorial digests | No | No | No | No | No | **Yes** |
| Full-text fetch (Readability) | Yes | Partial | Yes | Yes | Yes | **Yes** |
| Sentiment-based ranking | No | No | No | No | No | **AI + rule-based, slider UI** |
| Content deduplication | Basic | No | No | No | No | **URL + title + fingerprint** |
| Real-time push (SSE) | No | No | No | No | No | **Mercure SSE** |
| Rule-based fallback | N/A | N/A | N/A | N/A | N/A | **Always active** |
| Cost | Free | Free | $10-15/mo | $8-18/mo | $8/mo | **Free** |

The core differentiator: this is the only self-hosted aggregator with free AI enrichment. Every AI feature has a rule-based fallback, so the system works without an API key. When AI is available, it enhances -- it never blocks.

## Architecture

- **Domain-driven design** with 6 bounded contexts + Shared (Article, Enrichment, Source, Notification, Digest, User)
- **Interface-first** -- all service and repository boundaries defined by interfaces
- **Three-phase enrichment pipeline** -- articles appear instantly with rule-based data, upgrade in-place when AI completes
- **ModelFailoverPlatform** -- decorator chains free models with automatic fallback (openrouter/free -> minimax -> glm -> gpt-oss -> qwen -> nemotron)
- **Mercure SSE** -- real-time push via FrankenPHP/Caddy built-in hub, zero additional infrastructure
- **htmx** -- declarative partial page updates without a JavaScript framework
- **Symfony Messenger** -- async enrichment via Doctrine transport (no Redis required)
- **SEAL + Loupe** -- zero-infrastructure full-text search backed by SQLite

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Runtime | FrankenPHP + Caddy (automatic HTTPS, HTTP/3) |
| Framework | Symfony 8.0, PHP 8.4 |
| Database | PostgreSQL 17 + PgBouncer |
| Search | SEAL + Loupe (SQLite-based) |
| AI | Symfony AI Bundle + OpenRouter free models + ModelFailoverPlatform |
| Frontend | Twig + DaisyUI + htmx + TypeScript (Bun + AssetMapper) |
| Real-time | Mercure SSE (built into FrankenPHP/Caddy) |
| Async | Symfony Messenger (Doctrine transport) |
| Notifications | Symfony Notifier (Pushover, Telegram, Slack, Discord, email) |
| Monitoring | Ember (Caddy/FrankenPHP metrics TUI) |

## Quality Standards

- **PHPStan level max** with 10 extensions, zero `ignoreErrors` entries
- **Mutation testing** via Infection -- 80% MSI minimum, 90% covered code MSI
- **879+ test methods** across 92 test classes (unit + integration)
- **Xdebug path coverage** for accurate branch coverage
- **PHPat architecture tests** enforcing bounded context boundaries
- **ECS + Rector** auto-enforcement of coding standards
- **Pre-commit hook** runs ECS + PHPStan; pre-push hook runs full test suite
- **CI pipeline** runs quality checks + tests on every PR in Docker (same containers as local dev)

## Status

Production-ready. 88 merged PRs, 73 issues resolved, all planned features implemented. Active maintenance with continuous improvements.

## License

MIT
