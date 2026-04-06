# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

#### Post-Release Features
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
