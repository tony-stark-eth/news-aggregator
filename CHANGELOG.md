# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

#### Test Notification from Settings Page (#102)
- "Send Test Notification" button on the settings page (htmx-powered, inline success/failure feedback)
- Handles missing transport (`null://null` or empty DSN) with a warning badge
- CSRF-protected POST endpoint at `/settings/test-notification`

### Changed

#### Article Entity Domain Behavior (#154)
- `setEnrichmentStatus()` now enforces valid state transitions: null -> Pending -> Complete
- `setScore()` validates the 0.0-1.0 range, throws `\InvalidArgumentException` on out-of-range values
- New `resetEnrichmentStatus()` method for re-enrichment workflows (backfill commands)
- Extracted `AiTextCleanupServiceInterface` from concrete `AiTextCleanupService` — all 4 consumer services now depend on the interface

#### Dynamic Paid Model Usage During High Pipeline Load
- Queue-aware model routing in `ModelFailoverPlatform` — automatically skips free models when enrichment queue is deep (#157)
- Three routing modes: normal (queue < 20), accelerated (queue 20-49: primary free then paid), skip-free (queue >= 50: paid only)
- New `QueueDepthService` queries messenger_messages table for real-time enrich queue depth
- New env vars: `QUEUE_ACCELERATE_THRESHOLD` (default 20), `QUEUE_SKIP_FREE_THRESHOLD` (default 50)
- Fully backward compatible — when no paid model configured or queue service unavailable, behavior unchanged

#### Full-Text Article Fetch (Readability)
- Three-phase enrichment pipeline: Phase 1 (sync rule-based) -> Phase 1.5 (async full-text fetch) -> Phase 2 (async AI enrichment) (#151)
- `FetchFullTextHandler` fetches full article content from source websites using fivefilters/readability.php
- Per-domain rate limiting via Symfony RateLimiter (sliding window, configurable)
- HTML sanitization of extracted content via symfony/html-sanitizer
- Per-source `fullTextEnabled` toggle on create/edit forms
- Feed content auto-detection: "Full content detected" indicator when feed already provides full articles
- Dedicated `async_fulltext` Messenger transport with fulltext-worker container
- New env vars: `FULL_TEXT_FETCH_ENABLED`, `FULL_TEXT_FETCH_TIMEOUT`, `FULL_TEXT_RATE_LIMIT_REQUESTS`, `FULL_TEXT_RATE_LIMIT_INTERVAL`
- Failed full-text fetch never blocks the pipeline — always dispatches enrichment

#### Feed URL Validation
- Validate feed URL before saving — htmx-powered "Validate" button on create/edit source forms fetches and parses the feed, showing title, item count, and detected language inline (#97)
- Language auto-detection from feed content — checks RSS `<language>`, Atom `xml:lang`, `dc:language`, and character heuristic fallback (German/French/Spanish) (#97)
- Auto-fill language field when validation detects a language and the field is empty (#97)

### Fixed
- Full-text search returns no results after container rebuild — search index now rebuilds on container startup via entrypoint (#133)

### Added

#### Translation Backfill
- `app:backfill-translations` command — dispatches re-enrichment for legacy articles missing translations (#146)

#### Bookmarks
- Article bookmark / save for later — toggle bookmark via htmx button on article cards, filter dashboard to show bookmarked articles only, persisted per-user with unique constraint (#134)

#### Digest Improvements
- Digest failure diagnosis and retry — failed/skipped digest logs show "Retry" htmx button to re-trigger generation, plus status filter dropdown (All/Success/Failed) on digest history (#136)

#### Privacy
- Strip UTM and tracking query parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `fbclid`, `gclid`, `mc_cid`, `mc_eid`) from article URLs before storage (#138)

#### Article Card Enhancements
- Reading time estimate on article cards — "3 min read" based on `ceil(wordCount / 200)`, gracefully hidden when no content text (#135)
- Score explanation tooltip on score badge — DaisyUI tooltip showing Recency/Category/Source/Enrichment sub-score percentages (#137)
- Unread count badges on category tabs — per-category and total unread counts displayed on dashboard tabs for logged-in users (#139)

#### Post-Release Features
- Fetch All button on sources page — dispatches FetchSourceMessage for all enabled sources via htmx, shows "Queued N sources" badge (#129)
- Dashboard source filter — dropdown to filter articles by source, works alongside category and unread filters with htmx no-reload interaction (#130)
- Per-source fetch interval — optional override (5-1440 minutes) per source; falls back to category interval then global default; shown in sources table (#96)
- Manual per-source fetch trigger — htmx "Fetch" button on sources table dispatches FetchSourceMessage with CSRF protection (#76)
- Paid model overflow — `OPENROUTER_PAID_FALLBACK_MODEL` env var appends a low-cost paid model to the failover chain as last resort before rule-based (#115)
- htmx enhancements across dashboard and CRUD pages — no-reload category tabs, unread toggle, Mark All Read, live search, inline delete with confirmation, digest Run Now (#122)
- Dashboard stats row: added Alerts Today count and Last Fetch relative timestamp (#75)
- Async two-phase enrichment — Phase 1 (sync) applies rule-based categorization/summarization/keywords/scoring, Phase 2 (async via `async_enrich` transport) runs full AI enrichment + translation; articles appear instantly and upgrade in-place (#114)
- Mercure real-time updates — SSE push via built-in FrankenPHP/Caddy Mercure hub; "new articles" banner on dashboard, in-place article card updates when AI enrichment completes (#119)
- htmx integration — replaced custom infinite scroll TypeScript with declarative `hx-trigger="revealed"` pagination; htmx SSE extension available for future use
- Dedicated enrichment worker (`enrichment-worker` service) with 256M memory for AI responses
- `RuleBasedEnrichmentService` — lightweight Phase 1 enrichment calling only rule-based implementations
- `EnrichArticleMessage` + `EnrichArticleHandler` — async AI enrichment with idempotency guard
- `MercurePublisherService` — publishes article creation and enrichment completion events, with `NullMercurePublisherService` fallback
- Batch AI prompts — combine categorize+summarize+keywords into one call, batch translation per language; reduces AI calls from 9 to 3 per article (#113, #117)
- Edit existing feed sources — fix URLs, rename, change category without deleting (#72)
- Digest configuration CRUD — create, edit, and delete digest configs from the UI (#93)
- Manual digest trigger — "Run Now" button dispatches on-demand digest generation (#99)
- View digest history content — read past digest summaries and included article titles (#100)
- Alert matches always logged regardless of notification transport — `DeliveryStatus` enum (sent/skipped/failed), colored badges in notification log UI, `CleanupCommand` now purges old notification and digest logs (#37)
- Multi-language translation with EN/DE/FR language selector — articles translated to all configured display languages, client-side dropdown to switch, translations stored as JSON on article (#36)
- Keyword extraction from articles — AI extracts key entities (people, orgs, places), displayed as badges, searchable (#21)
- Inline search-as-you-type filter on dashboard — client-side article filtering with debounce (#22)
- Article translation by source language — auto-translates German (and other) titles/summaries to English, preserves originals (#23)
- Alert rule fixtures — YAML-based alert strategies loadable via `app:load-alert-rules` command with `--dry-run` and `--purge` (#25)
- Auto-reindex search on article persist/update via Doctrine event listener (#29)
- Maintenance scheduler — daily `app:search-reindex` + `app:cleanup` via `#[AsSchedule('maintenance')]`
- Source and alert rule CRUD forms in the UI
- CSRF protection on mark-all-read and delete forms
- Search bar visible on all screen sizes (mobile hamburger menu + medium+ navbar)

### Fixed
- Auth switched from in-memory provider to entity provider — fixes mark-as-read, dashboard read state
- Dashboard sort by publishedAt (not fetchedAt) to match displayed timestamps
- Worker now consumes `scheduler_fetch` transport for automatic periodic feed fetching
- TypeScript modules (theme toggle, timeago, mark-as-read) now loaded via app.js imports
- CI builds TypeScript assets before functional tests
- Untracked auto-generated `config/reference.php`

#### Infrastructure & Scaffolding
- Project scaffolding with dunglas/symfony-docker (FrankenPHP + Caddy)
- Docker Compose setup with PostgreSQL 17, PgBouncer (transaction pooling), and Messenger worker
- Production compose override (`compose.prod.yaml`) with GHCR image reference
- Dockerfile with multi-stage build: `frankenphp_dev` (Bun + Xdebug) and `frankenphp_prod`
- Ports: 8443 (HTTPS) / 8180 (HTTP) — avoids conflicts with other homeserver services

#### Code Quality Tooling
- PHPStan 2.1.x — level max, 10 extensions, bleeding edge mode, zero errors
- ECS 13.0.x — PSR-12 + common + strict + cleanCode rulesets
- Rector 2.4.x — PHP 8.4, Symfony 8, Doctrine, PHPUnit upgrade sets
- Infection 0.32.x — mutation testing, 80% MSI / 90% covered MSI thresholds (unit suite only)
- PHPAt 0.12.x — architecture tests via PHPStan extension
- PHPUnit 13.1.x — unit + integration + functional suites, random order, Xdebug path coverage
- Symfony Panther — browser-based E2E tests
- Custom git hooks: pre-commit (ECS + PHPStan + Rector + rebase guard), commit-msg (Conventional Commits)

#### Domain Model
- **Article** domain: `Article` entity with URL, fingerprint, score, enrichment method/model tracking; `DeduplicationService`, `ScoringService`
- **Source** domain: `Source` entity with health tracking (error_count, last_error, health_status enum); auto-disable after 5 consecutive failures; `FeedFetcherService` (laminas/laminas-feed), `FeedParserService`, `FetchScheduleProvider`
- **Enrichment** domain: `RuleBasedCategorizationService`, `RuleBasedSummarizationService`; AI decorator services (`AiCategorizationService`, `AiSummarizationService`); `AiQualityGateService` (confidence >= 0.7 + structure validation); `EnrichmentResult` DTO carrying value + method + modelUsed
- **Notification** domain: `AlertRule` entity (keyword/ai/both types, user_id FK); `ArticleMatcherService`, `AiAlertEvaluationService`, `NotificationDispatchService`; `NotificationLog`
- **Digest** domain: `DigestConfig` entity (cron expression, category filters, user_id FK); `DigestGeneratorService`, `DigestSummaryService`; `DigestLog`; `app:process-digests` command (runs every 5 min)
- **User** domain: `User` entity (email + password_hash); `UserArticleRead` join entity (per-user read state, no `read_at` on Article)
- **Shared/AI**: `ModelFailoverPlatform` (PlatformInterface decorator, chain: openrouter/free → minimax → glm → gpt-oss → qwen → nemotron); `ModelDiscoveryService` with circuit breaker (3 failures → 24h fallback to DB list); `ModelQualityTracker`
- **Shared/Search**: SEAL + Loupe full-text search (SQLite-based, zero infrastructure); article index synced on persist/update
- **Shared/Entity**: `Category` entity (name, slug, weight, color, fetchIntervalMinutes)
- Cross-domain typed collections and DTOs replacing untyped arrays at all service boundaries

#### AI Integration
- OpenRouter integration via Symfony AI Bundle 0.6.0 (Generic Platform, OpenAI-compatible)
- `openrouter/free` as primary model (auto-routes to best available free model)
- `ModelFailoverPlatform`: named fallback chain for resilience when free tier is unavailable
- Keyword-first alert matching: AI called only on keyword hits (~10-20 calls/day vs ~250)
- `OPENROUTER_BLOCKED_MODELS` env var for manual model blocking
- `app:ai-stats` console command for model quality visibility
- `app:ai-smoke-test` command for quick verification

#### Frontend
- Twig templates with DaisyUI 4.x (CDN, version-pinned)
- TypeScript modules via Bun + AssetMapper (no Node/npm/Webpack): timeago, infinite-scroll, theme-toggle, mark-as-read
- Dark/light theme toggle with localStorage persistence
- Infinite scroll on article feed
- Relative timestamps with `Intl.DateTimeFormat` browser-local display (UTC stored, local displayed)
- `NavigationExtension` Twig global (`nav.activeRoute`, `nav.searchQuery`) — no direct request access in templates
- Full-text search UI with SEAL + Loupe backend
- All forms use Symfony Form component (CSRF protection built-in)

#### Operations
- `app:cleanup` — data retention (configurable via `ARTICLE_RETENTION_DAYS` / `LOG_RETENTION_DAYS`, defaults: 90/30 days)
- `app:check-sources` — manual source health check
- `app:search-reindex` — rebuild Loupe full-text index
- PostgreSQL backup/restore Makefile targets
- Ember v1.0.1 (Docker container) for Caddy/FrankenPHP metrics dashboard

#### CI/CD & Open-Source Readiness
- GitHub Actions CI workflow (`ci.yml`): parallel quality jobs (ECS, PHPStan, Rector) + sequential test jobs (unit, integration, functional); all run inside Docker Compose
- Scheduled security pipeline (`security.yml`): weekly `composer audit` + Symfony security check
- Dependabot (`dependabot.yml`): Composer (weekly), Docker (weekly), GitHub Actions (monthly); minor/patch grouped
- GHCR publishing workflow (`publish.yml`): builds `frankenphp_prod` image on version tags, pushes to `ghcr.io/tony-stark-eth/news-aggregator`
- MIT license, CONTRIBUTING.md, SECURITY.md, `.env.example`, GitHub issue/PR templates
- Mermaid architecture and article lifecycle diagrams in `docs/`

### Architecture Decisions
- DDD bounded contexts: Article, Source, Enrichment, Notification, Digest, User, Shared
- Interface-first: all service boundaries defined by interface, concrete implementations wired via DI
- Multi-user-ready entity design: `user_id` FKs on `AlertRule`, `DigestConfig`, `UserArticleRead` from the start
- No YAML Symfony config — PHP format only
- `symfony/clock` (PSR-20 ClockInterface) — no `DateTime`, `time()`, `date()`, `strtotime()`
- UTC storage, browser-local display for all timestamps
- Doctrine Messenger transport for async jobs (no Redis dependency)
- PgBouncer: transaction pooling for web, direct connection for Messenger worker
