# Architecture Reference

## Docker Services

| Service | Image | Purpose | Port |
|---------|-------|---------|------|
| php | frankenphp (dev) | Web server + PHP runtime + Mercure hub | 8443 (HTTPS), 8180 (HTTP) |
| database | postgres:17-alpine | Primary datastore | 5432 (internal) |
| pgbouncer | edoburu/pgbouncer | Connection pooling (transaction mode) | 6432 (internal) |
| worker | frankenphp (dev) | Messenger async consumer (default queue) | - |
| enrichment-worker | frankenphp (dev) | Messenger consumer (enrich queue) | - |
| fulltext-worker | frankenphp (dev) | Messenger consumer (fulltext queue) | - |

## Database Connections

- **Web requests** -> PgBouncer (port 6432, transaction pooling) via `DATABASE_URL`
- **Messenger workers** -> Direct PostgreSQL (port 5432) via `DATABASE_DIRECT_URL` (needs LISTEN/NOTIFY)

## Domain Boundaries

| Domain | Responsibility |
|--------|---------------|
| Article | Core articles, scoring, deduplication, full-text fetch, content fingerprinting |
| Source | Feed management, fetching (laminas-feed), health tracking |
| Enrichment | Rule-based + AI categorization/summarization/keywords/translation/sentiment (decorator pattern) |
| Chat | Conversational RAG assistant (symfony/ai-agent + pgvector), embeddings |
| Notification | Unified alert rules (keyword/AI/both) + Notifier dispatch |
| Digest | Periodic AI-generated editorial summaries with sentiment awareness |
| User | Authentication (symfony/security-bundle), per-user read state, bookmarks |
| Shared | AI infra (ModelFailoverPlatform), search (SEAL/Loupe), settings, scheduler, categories |

## Article Pipeline

Three-phase enrichment with real-time updates:

1. **Phase 1 (sync)**: Rule-based categorization, summarization, keywords, sentiment scoring → article appears instantly
2. **Phase 1.5 (async fulltext)**: Readability.php fetches full article content with per-domain rate limiting → failures never block pipeline
3. **Phase 2 (async enrich)**: AI enrichment (category + summary + keywords + sentiment + translation) via ModelFailoverPlatform → article upgrades in-place via Mercure SSE

See `docs/article-lifecycle.md` for the full Mermaid flowchart.

## Messenger Transports

| Transport | Queue | Messages |
|-----------|-------|----------|
| async | default | FetchSourceMessage, SendNotificationMessage, GenerateDigestMessage, RescoreArticlesMessage |
| async_fulltext | fulltext | FetchFullTextMessage |
| async_enrich | enrich | EnrichArticleMessage, GenerateEmbeddingMessage, ScoreSentimentMessage |

## Key Design Decisions

- **Global dedup**: Articles are shared across users. Same article = one record. Read state is per-user via `UserArticleRead`.
- **Multi-user ready**: MVP is single-user, but entities with user-scoped data have `user_id` FK from the start.
- **AI as decorator**: Rule-based services implement the interface. AI services wrap them and fall back to rule-based on failure.
- **Interface-first**: All service boundaries defined by interface. Concrete implementations wired via Symfony DI.
- **Sentiment slider**: -10 to +10 spectrum controls dashboard ranking/filtering, chat tone, and digest framing. Stored via SettingsService.
- **Queue-aware routing**: When enrichment queue is deep, ModelFailoverPlatform skips free models to accelerate processing.
- **Mercure SSE**: Real-time push via built-in FrankenPHP/Caddy Mercure hub for article creation and enrichment completion events.

## Cross-Context Entity FK Strategy (ADR)

**Decision**: Bounded contexts reference each other via direct Doctrine `ManyToOne` associations, not by ID-only references.

**Affected relationships**:
- `NotificationLog` → `Article` (Notification → Article context)
- `AlertRule` → `User` (Notification → User context)
- `DigestConfig` → `User` (Digest → User context)
- `UserArticleRead` → `Article` (User → Article context)
- `UserArticleBookmark` → `Article` (User → Article context)

**Rationale**: This is a modular monolith with a single PostgreSQL database. Direct FK associations provide referential integrity, cascade deletes, and efficient joins — all critical for a self-hosted single-DB application. The ORM coupling is an acceptable trade-off.

**When to revisit**: If bounded contexts are ever split into separate services (microservices), these FK relationships must be replaced with ID-only references + eventual consistency patterns (event-driven sync, API calls). This is not planned and would be a major architectural change.

**Status**: Accepted. No action needed.

## Environment Variables

| Variable | Purpose | Default |
|----------|---------|---------|
| `APP_ENV` | Symfony environment | `dev` |
| `APP_SECRET` | Symfony secret | (must set) |
| `DATABASE_URL` | PgBouncer connection | (auto from compose) |
| `DATABASE_DIRECT_URL` | Direct PostgreSQL | (auto from compose) |
| `ADMIN_EMAIL` | Admin login email | (required) |
| `ADMIN_PASSWORD_HASH` | Bcrypt/argon2 hash | (required) |
| `OPENROUTER_API_KEY` | OpenRouter API key | (optional — rule-based fallback works without it) |
| `OPENROUTER_BASE_URL` | OpenRouter base URL | `https://openrouter.ai/api/v1` |
| `OPENROUTER_BLOCKED_MODELS` | Comma-separated blocked model IDs | (empty) |
| `OPENROUTER_PAID_FALLBACK_MODEL` | Paid model appended to failover chain | (empty) |
| `NOTIFIER_CHATTER_DSN` | Notification transport DSN | `null://null` |
| `DISPLAY_LANGUAGES` | Comma-separated display languages | `en` |
| `MERCURE_URL` | Internal Mercure hub URL | `https://php/.well-known/mercure` |
| `MERCURE_PUBLIC_URL` | Public Mercure hub URL (browser SSE) | `https://localhost:8443/.well-known/mercure` |
| `MERCURE_JWT_SECRET` | JWT secret for Mercure publishing | (must set) |
| `FULL_TEXT_FETCH_ENABLED` | Enable full-text article fetching | `true` |
| `QUEUE_ACCELERATE_THRESHOLD` | Queue depth to start accelerating | `20` |
| `QUEUE_SKIP_FREE_THRESHOLD` | Queue depth to skip free models | `50` |
| `RETENTION_ARTICLES` | Article retention (days) | `90` |
| `RETENTION_LOGS` | Log retention (days) | `30` |

## Makefile Targets

Run `make help` for the full list. Key targets:

- `make up` / `make down` - Docker lifecycle
- `make quality` - All quality checks (ECS + PHPStan + Rector)
- `make test` / `make test-unit` / `make test-integration` - Tests
- `make infection` - Mutation testing (80/90% MSI thresholds)
- `make sf c="..."` - Symfony console commands
- `make ts-build` / `make ts-watch` - TypeScript compilation via Bun
- `make hooks` - Install git hooks
