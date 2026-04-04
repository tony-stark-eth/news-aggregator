# News Aggregator — Task Plan

## Project Summary

Build a Symfony 8.0 news aggregator with configurable sources, deduplication, AI-powered categorization/summarization, scoring, and a simple Twig + DaisyUI frontend. DDD architecture, TDD workflow, based on dunglas/symfony-docker with best practices from template-symfony-sveltekit.

**Long-term goals**: open-source release, potential feature monetization. Architecture must be multi-user-ready even though MVP is single-user.

## Key Decisions

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | dunglas/symfony-docker as base (latest main) | FrankenPHP + Caddy + PostgreSQL, production-ready, no tagged releases (rolling) |
| D2 | OpenRouter free models via Symfony AI v0.6.0 | Generic Platform bridge (OpenAI-compatible) + FailoverPlatform across free models |
| D3 | Twig + DaisyUI (CDN, version-pinned) + plain TypeScript | Simple SSR. No JS framework, no Stimulus/Turbo. TS compiled via Bun, served by AssetMapper |
| D4 | Monolog for error tracking | Built-in, sufficient for single-user homeserver |
| D5 | Ember v1.0.1 (Docker container) | Caddy/FrankenPHP metrics dashboard |
| D6 | RSS/Atom feeds only (publicly accessible) | No paywalled or authenticated feeds |
| D7 | PostgreSQL only, no MongoDB | Simpler than tcg-scanner; no vector/document store needed |
| D8 | Domain-based folder structure | Replicate template-symfony-sveltekit DDD pattern |
| D9 | Doctrine Messenger transport | Async jobs without Redis dependency |
| D10 | PgBouncer for web, direct for worker | Template pattern: transaction pooling for web, LISTEN/NOTIFY for worker |
| D11 | Ports: 8443 (HTTPS) / 8180 (HTTP) | Free on host — avoids conflicts with tcg-scanner (443/80), HA (8123), Plex, etc. |
| D12 | Custom git hooks (.githooks/) | Shell scripts + `git config core.hooksPath`, no CaptainHook dependency |
| D13 | Xdebug path coverage (not PCOV) | Combined with Infection mutation testing for comprehensive coverage |
| D14 | PHP config files only | No YAML for Symfony config — use .php config format everywhere |
| D15 | laminas/laminas-feed 2.26.x for feed parsing | Active (Mar 2026), PHP 8.4 support, Laminas-backed — no Symfony component exists, debril/* is dead/archived |
| D16 | Symfony Notifier (transport-agnostic) | Any Notifier transport via channel DSN env vars. Pushover recommended for Android as sensible default but not hardcoded — user installs desired transport package at deploy time |
| D17 | Unified AlertRule system | Single entity with type enum (keyword/ai/both). Keyword matching is always step 1. AI evaluation runs only on keyword matches (step 2). One pipeline, no duplicate notification paths |
| D18 | Rule-based fallback for categorization/summarization | OpenRouter free models are unreliable. Rule-based logic ensures the system always functions. AI becomes an enhancement layer, not a dependency |
| D19 | Private repo, open-source ready | MIT license, README, CONTRIBUTING.md, CHANGELOG.md, SECURITY.md, .env.example, GitHub templates. Flip to public when ready |
| D20 | Low-maintenance by design | Dependabot for dependency updates, scheduled security pipeline, E2E tests for critical paths |
| D21 | CI uses Docker Compose for parity | GitHub Actions runs tests inside the same Docker containers as local dev. Jobs parallelized where independent |
| D22 | symfony/clock for time (not Carbon) | PSR-20 ClockInterface, MockClock for tests, returns DateTimeImmutable. Symfony-native, no extra dependency |
| D23 | UTC storage, browser-local display | All DB timestamps in UTC. Frontend: `<time datetime="ISO">` + TypeScript `Intl.DateTimeFormat` for browser timezone conversion |
| D24 | `openrouter/free` as primary model | Auto-routes to best available free model. Zero maintenance. Dynamic discovery as secondary fallback |
| D25 | AI quality gates | Structured output validation, confidence threshold (>= 0.7), summary length heuristic. Stats command for visibility. Manual `OPENROUTER_BLOCKED_MODELS` env var for persistently bad models |
| D26 | Periodic AI digest (configurable schedule) | Periodic command (`app:process-digests` every 5 min) checks cron + last_run. Avoids Symfony Scheduler compile-time limitation |
| D28 | ModelDiscovery circuit breaker | 3 consecutive failures → fall back to DB-persisted model list for 24h. Fresh containers with down endpoint still work |
| D29 | TypeScript modules via Bun + AssetMapper | `assets/ts/` compiled to JS via `bun build`. No Node/npm, no Webpack/Encore, no Stimulus. Modules: timeago, infinite-scroll, theme-toggle, mark-as-read |
| D30 | SEAL + Loupe for full-text search | Search abstraction layer (swap to Meilisearch later), Loupe adapter (SQLite-based, zero infrastructure). `cmsig/seal-symfony-bundle` + `cmsig/seal-loupe-adapter` |
| D31 | Interface-first architecture | All domain services depend on interfaces, not concrete implementations. Reduces coupling, enables testing with stubs, allows swapping implementations |
| D32 | Multi-user-ready entity design | MVP is single-user with basic auth. But entities use `user_id` FKs where needed (read state, alert rules, digest configs). No `read_at` on Article — use `UserArticleRead` join entity. Minimal migration pain when full multi-user is added |
| D33 | Basic auth via symfony/security-bundle | Single admin user configured via env vars (`ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`). Login page, session-based auth. Required even for personal use (app exposes API keys, notification config) |
| D34 | Data retention + cleanup | Configurable retention period via env vars. Scheduled `app:cleanup` command prunes old articles, notification logs, digest logs. Default: 90 days articles, 30 days logs |
| D35 | Feed error handling + health | Source entity tracks error_count, last_error, health status. Auto-disable after 5 consecutive failures. Health indicator in UI |
| D36 | Symfony Form component for all forms | Handles CSRF automatically, validation, type safety. No manual form HTML |
| D37 | Store article content (description/summary from feed) | Full RSS `<description>` or `<content:encoded>` stored, HTML-stripped for search/dedup. Original HTML preserved for display. No full-page scraping |
| D38 | Template extraction goal | Phase 1+2 designed with clean separation: framework setup (extractable) vs project-specific config (news-aggregator only). Future: extract opinionated Symfony+Docker+Claude Code template |
| D39 | EnrichmentMethod in Shared/ValueObject | Cross-domain value object — used by Article entity and Enrichment services. Avoids circular dependency |
| D40 | Per-category fetch intervals (not per-source) | Fetch urgency is a property of content type: politics=5min, science=60min. Category.fetchIntervalMinutes (nullable) with FETCH_DEFAULT_INTERVAL_MINUTES env fallback. Avoids 16+ individual source configs |

## Pinned Versions

| Package | Version | Notes |
|---------|---------|-------|
| Symfony | 8.0.x (latest via `symfony new`) | PHP 8.4 required |
| symfony/ai-bundle | 0.6.0 | Generic Platform + FailoverPlatform |
| PHPStan | 2.1.x | Level max + 10 extensions |
| ECS (easy-coding-standard) | 13.0.x | PSR-12 + strict + cleanCode |
| Rector | 2.4.x | PHP 8.4, Symfony 8, Doctrine sets |
| Infection | 0.32.x | 80% MSI, 90% covered MSI |
| PHPat | 0.12.x | Architecture tests via PHPStan |
| PHPUnit | 13.1.x | Unit + integration suites, Xdebug path coverage |
| laminas/laminas-feed | 2.26.x | RSS/Atom parsing, PHP 8.4 support, active (Mar 2026) |
| symfony/notifier | 8.0.x | Transport-agnostic notifications |
| symfony/panther | latest | E2E browser tests |
| cmsig/seal-symfony-bundle | 0.12.x | Search abstraction layer, Symfony integration |
| cmsig/seal-loupe-adapter | 0.12.x | SQLite-based full-text search, zero infrastructure |
| DaisyUI | 4.x (CDN, pin exact version) | Prevent breaking changes from unpinned CDN |
| Ember | 1.0.1 | Docker container |

## Port Map (host)

Occupied ports: 80, 443, 631, 1883, 3000, 3389, 4000, 4431, 4444, 5173, 5432, 7575, 8000, 8025, 8080, 8088, 8090, 8123, 9443, 18554, 18555, 27017, 32400

**News Aggregator: 8443 (HTTPS) / 8180 (HTTP)** — both confirmed free.

## Architecture

**Core rules**:
1. All domain services depend on interfaces, not concrete implementations. Every service boundary is defined by an interface. Concrete implementations wired via Symfony DI.
2. Entities that will need per-user scoping in multi-user mode are designed with `user_id` FK from the start (AlertRule, DigestConfig, UserArticleRead). Article itself is shared across users.
3. Cross-cutting AI infrastructure lives in `Shared/AI/`, not inside a specific domain.
4. **Article deduplication is global, not per-user.** Same article from the same feed is stored once. Read state is per-user (UserArticleRead), but the article and its enrichment are shared. This is intentional — dedup at the content level, personalization at the view level.

```
src/
├── Article/              # Core — articles, scoring, dedup
│   ├── Controller/       # ArticleController (list, detail, filter)
│   ├── Entity/           # Article (NO read_at — read state is per-user via UserArticleRead)
│   ├── Repository/       # ArticleRepository
│   ├── Service/          # ScoringService, DeduplicationService
│   ├── ValueObject/      # ArticleFingerprint, Score, Url
│   └── Exception/
├── Enrichment/           # Content enrichment (rule-based + AI decorators)
│   ├── Service/          # RuleBasedCategorizationService, RuleBasedSummarizationService
│   │                     # AiCategorizationService, AiSummarizationService (decorators over rule-based)
│   │                     # AiQualityGateService
│   ├── ValueObject/      # EnrichmentResult
│   └── Exception/
├── Source/               # Feed management + fetching
│   ├── Controller/       # SourceController (CRUD)
│   ├── Entity/           # Source (+ error_count, last_error_message, health_status)
│   ├── Repository/       # SourceRepository
│   ├── Service/          # FeedFetcherService, FeedParserService
│   ├── ValueObject/      # FeedUrl, SourceHealth (enum: healthy/degraded/failing/disabled)
│   ├── Message/          # FetchSourceMessage
│   ├── MessageHandler/   # FetchSourceHandler
│   ├── Scheduler/        # FetchScheduleProvider
│   └── Exception/
├── Notification/         # Unified alert rules + notification dispatch
│   ├── Controller/       # AlertRuleController (CRUD), NotificationLogController
│   ├── Entity/           # AlertRule (+ user_id FK), NotificationLog
│   ├── Repository/       # AlertRuleRepository, NotificationLogRepository
│   ├── Service/          # ArticleMatcherService, AiAlertEvaluationService, NotificationDispatchService
│   ├── Message/          # SendNotificationMessage
│   ├── MessageHandler/   # SendNotificationHandler
│   ├── ValueObject/      # MatchResult, AlertRuleType (enum), AlertUrgency (enum), EvaluationResult
│   └── Exception/
├── Digest/               # Periodic AI-generated editorial summaries
│   ├── Controller/       # DigestConfigController (CRUD), DigestHistoryController
│   ├── Entity/           # DigestConfig (+ user_id FK), DigestLog
│   ├── Repository/       # DigestConfigRepository, DigestLogRepository
│   ├── Service/          # DigestGeneratorService, DigestSummaryService (uses Shared/AI)
│   ├── Message/          # GenerateDigestMessage
│   ├── MessageHandler/   # GenerateDigestHandler
│   └── Exception/
├── User/                 # Auth + read state
│   ├── Controller/       # LoginController
│   ├── Entity/           # User (email, password_hash), UserArticleRead (user_id, article_id, read_at)
│   ├── Repository/       # UserRepository, UserArticleReadRepository
│   └── Exception/
└── Shared/
    ├── AI/               # Cross-cutting AI infrastructure
    │   ├── Service/      # ModelDiscoveryService, ModelQualityTracker
    │   └── Platform/     # FailoverPlatform config, OpenRouter bridge
    ├── Search/           # SEAL integration (infrastructure, not a domain)
    │   ├── Service/      # ArticleSearchServiceInterface + SealArticleSearchService
    │   ├── Index/        # SEAL Article index definition
    │   └── EventListener/ # Sync article to search index on persist/update
    ├── Entity/           # Category (name, slug, weight, color) — shared lookup, not a bounded context
    ├── Repository/       # CategoryRepository
    ├── ValueObject/      # EnrichmentMethod (enum: Ai/RuleBased) — cross-domain, used by Article + Enrichment
    ├── Command/          # app:cleanup, app:search-reindex, app:check-sources
    ├── Controller/       # DashboardController, HealthController
    └── Twig/             # Extensions, components
```

## PR Strategy

Incremental delivery — one PR per phase (or logical group), each reviewable and testable in isolation.

| PR | Phases | Scope | Why grouped / separate |
|----|--------|-------|----------------------|
| #1 | 1 + 2 | Scaffold + quality tooling + basic auth | Bundled: quality tools + auth must guard from commit 1 |
| #2 | 3 | Domain entities + DB migrations | First domain code, entities only |
| #3 | 4 | Feed fetching + parsing | Core feature: RSS ingestion pipeline |
| #4 | 5 | Dedup + rule-based enrichment | System fully functional without AI after this |
| #5 | 6 | AI enrichment layer (OpenRouter) | Enhancement layer on top of rule-based |
| #6 | 7 | Scoring & ranking | Article prioritization |
| #7 | 8 | Scheduler + background jobs | Automated fetching |
| #8 | 9 | Unified alert system + Notifier | Keyword + AI alerts |
| #9 | 10 | Periodic digest | Scheduled editorial summaries |
| #10 | 11A-11B | Frontend: layout, theme, dashboard, CRUD pages | Core UI |
| #11 | 11C | Search integration (SEAL + Loupe) | Search infra + UI |
| #12 | 11D-11E | Read state, logs/stats pages, E2E tests | Remaining UI + all E2E |
| #13 | 12 | Logging + monitoring (Ember) | Observability |
| #14 | 13 | CI/CD, security, GHCR publishing, final polish | Dependabot, pipelines, README, Docker image publishing |

All PRs target `main`. Each PR should pass all quality checks (`make quality`) before merge.

---

## Default Sources (seed data — all publicly accessible)

| Category | Source | Feed URL |
|----------|--------|----------|
| Politics | Tagesschau | https://www.tagesschau.de/xml/rss2 |
| Politics | ZDF heute | https://www.zdf.de/rss/zdf/nachrichten |
| Politics | BBC News | https://feeds.bbci.co.uk/news/rss.xml |
| Politics | Der Spiegel | https://www.spiegel.de/schlagzeilen/tops/index.rss |
| Business | Handelsblatt | https://www.handelsblatt.com/contentexport/feed/top |
| Business | MarketWatch | https://feeds.content.dowjones.io/public/rss/mw_topstories |
| Business | CNBC | https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=100003114 |
| Business | Reuters Business | https://www.reutersagency.com/feed/?best-topics=business-finance |
| Tech | Heise | https://www.heise.de/rss/heise-atom.xml |
| Tech | Ars Technica | https://feeds.arstechnica.com/arstechnica/index |
| Tech | The Verge | https://www.theverge.com/rss/index.xml |
| Tech | Hacker News | https://hnrss.org/frontpage |
| Science | Nature News | https://www.nature.com/nature.rss |
| Science | Ars Technica Science | https://feeds.arstechnica.com/arstechnica/science |
| Sports | Kicker | https://rss.kicker.de/news/aktuell |
| Sports | ESPN | https://www.espn.com/espn/rss/news |

---

## Phases

### Phase 1: Project Scaffolding & Infrastructure
> **Sequence**: Clone dunglas/symfony-docker → `docker compose build && up` → verify latest Symfony 8.0.x is installed → THEN proceed to Phase 2 for quality tooling.

- [x] 1.1 Clone dunglas/symfony-docker (latest main) into news-aggregator/
- [x] 1.2 `docker compose build --no-cache` + `docker compose up --pull always -d --wait` to scaffold Symfony 8.0.x
- [x] 1.3 Verify Symfony version (`bin/console --version`) is 8.0.x — confirmed 8.0.8
- [x] 1.4 Create private GitHub repo (`gh repo create tony-stark-eth/news-aggregator --private`)
- [x] 1.5 Initialize git repo, create .gitignore (ensure no secrets, .env.local, vendor/ committed)
- [x] 1.6 Open-source preparation (ready to flip to public):
  - MIT LICENSE file
  - README.md (project description, features, setup, configuration, architecture overview)
  - CONTRIBUTING.md (dev setup, code quality expectations, PR process, conventional commits)
  - CHANGELOG.md (Keep a Changelog format, initial entry)
  - SECURITY.md (responsible disclosure instructions)
  - .env.example (all env vars with placeholder values, no real secrets)
  - GitHub issue templates (.github/ISSUE_TEMPLATE/bug_report.md, feature_request.md)
  - GitHub PR template (.github/pull_request_template.md)
- [x] 1.7 Adapt Dockerfile: add Bun installation to `frankenphp_dev` stage (not in dunglas/symfony-docker by default)
- [x] 1.8 Adapt Docker setup: add PgBouncer service, Messenger worker service (Ember deferred to Phase 12)
- [x] 1.9 Map ports: 8443 (HTTPS) / 8180 (HTTP) in compose.yaml
- [x] 1.10 Create docker/postgres/init.sql (app_test DB for integration tests)
- [x] 1.11 Create Makefile (docker, quality, test, db, backup/restore targets — modeled on template)
- [x] 1.12 Add `make export-postgres` / `make import-postgres` backup/restore targets
- [x] 1.13 Create CLAUDE.md (project root) with:
  - Quick start commands (`make up`, `make quality`, `make test`)
  - Project type and stack summary
  - Links to `.claude/` guideline files
  - Hard rules: no `DateTime`, no `var_dump`/`dump`/`dd`, no `empty()`, no `ignoreErrors`, no YAML config, conventional commits, interface-first
- [x] 1.14 Create `.claude/` guideline files:
  - `.claude/coding-php.md` — PHP coding guidelines:
    - `declare(strict_types=1)`, `final readonly class` default, constructor injection only
    - Interface-first: all service boundaries defined by interface
    - ClockInterface for all time access, `time()`/`date()`/`strtotime()` forbidden
    - Max 20 lines/method, max 3 params, max ~150 lines/class, max 5 constructor deps
    - Cognitive complexity max 8/method, max 50/class
    - find* nullable / get* throws, value objects, enums over magic values
    - Early returns, max nesting 2, immutability by default
    - Naming: `{Feature}Controller`, `{Action}Service`, `{What}Exception`, `{ClassUnderTest}Test`
    - Domain-based folders, not framework-based (`src/{Domain}/` not `src/Entity/`)
  - `.claude/coding-typescript.md` — TypeScript coding guidelines:
    - Strict mode, no `any`, `noUncheckedIndexedAccess: true`
    - Small focused modules (one concern per file), no framework
    - DaisyUI class conventions, `data-theme` for theming
    - DOM queries typed, `fetch()` for async, no jQuery
  - `.claude/testing.md` — Testing & code quality:
    - PHPStan level max + 10 extensions (table with what each checks)
    - Enforcement matrix (what tool, threshold, blocks CI?)
    - PHPUnit: Xdebug path coverage, unit + integration suites, #[CoversClass], createStub over createMock
    - Infection: unit suite only, MSI >= 80%, covered MSI >= 90%
    - ECS, Rector sets, architecture tests (PHPat)
    - CI pipeline order (parallel static analysis → sequential tests → E2E)
  - `.claude/architecture.md` — Architecture reference:
    - Docker services (FrankenPHP, PostgreSQL, PgBouncer, Messenger worker, Ember)
    - Domain boundaries (Article, Enrichment, Source, Notification, Digest, User, Shared)
    - Dual DB connections (PgBouncer for web, direct for worker)
    - Global dedup assumption (articles shared, read state per-user)
    - Multi-user readiness notes (which entities have user_id, migration path)
    - Makefile targets overview
    - ENV variables reference
- [x] 1.15 Verify `make up` boots cleanly with Symfony welcome page on https://localhost:8443

### Phase 2: Code Quality Tooling + Auth + TypeScript
> **Purpose**: Install ALL quality tools + auth + TypeScript pipeline before writing any domain code, so every line is guarded from the start. Config modeled on template-symfony-sveltekit.

- [x] 2.1 Install & configure PHPStan 2.1.x (level max + 10 extensions):
  - phpstan-strict-rules, shipmonk/phpstan-rules, phpstan-deprecation-rules, voku/phpstan-rules
  - tomasvotruba/cognitive-complexity (max 8/method, 50/class)
  - tomasvotruba/type-coverage (100% return, param, property, constant, declare)
  - phpat/phpat, phpstan-symfony, phpstan-doctrine, phpstan-phpunit
  - Bleeding edge: checkUninitializedProperties, checkImplicitMixed, checkBenevolentUnionTypes
  - Zero ignoreErrors
- [x] 2.2 Install & configure ECS 13.0.x (PSR-12 + common + strict + cleanCode)
- [x] 2.3 Install & configure Rector 2.4.x (PHP 8.4, Symfony 8, Doctrine, PHPUnit, CodeQuality, DeadCode, EarlyReturn, TypeDeclaration)
- [x] 2.4 Install & configure Infection 0.32.x:
  - 80% MSI, 90% covered MSI
  - **Unit test suite only** (not integration)
  - Exclude Entity/Kernel/Controller/Command from mutation
  - Uses Xdebug path coverage (shared with PHPUnit)
- [x] 2.5 Install & configure PHPat 0.12.x (architecture tests via PHPStan)
- [x] 2.6 Create custom git hooks in `.githooks/`:
  - `pre-commit`: run ECS check, PHPStan, Rector --dry-run on staged PHP files + rebase guard + patch backup
  - `commit-msg`: conventional commits regex validation
  - Install via `git config core.hooksPath .githooks` (Makefile target: `make hooks`)
- [x] 2.7 Configure PHPUnit 13.1.x:
  - Unit + integration suites
  - **Xdebug path coverage** (XDEBUG_MODE=coverage, not PCOV)
  - Random execution order
  - #[CoversClass] required on every test class
- [x] 2.8 Install & verify Symfony Panther (early — catch FrankenPHP/Caddy compat issues now, not in Phase 11)
- [x] 2.9 Add Makefile targets: `make quality`, `make phpstan`, `make ecs`, `make rector`, `make test`, `make test-unit`, `make test-integration`, `make infection`, `make coverage`, `make hooks`
- [x] 2.10 Install & configure symfony/clock:
  - Inject `ClockInterface` everywhere time is needed (never `new DateTimeImmutable()` or `time()`)
  - Use `MockClock` in test bootstrap for deterministic time
  - ShipMonk ban: add `time`, `date`, `strtotime` to forbidden functions list
- [x] 2.11 Configure Doctrine to store all timestamps as UTC (`datetime_immutable` type, server default UTC)
- [x] 2.12 Install symfony/security-bundle, configure basic auth:
  - Single admin user via env vars (`ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`)
  - Memory provider with form_login authenticator
  - Session-based auth, firewall on all routes except login + dev tools
  - (User entity deferred to Phase 3 — memory provider sufficient for now)
- [x] 2.13 Setup TypeScript + Bun + AssetMapper pipeline:
  - `tsconfig.json` with strict mode
  - Bun compile step: `bun build assets/ts/*.ts --outdir=assets/js/`
  - AssetMapper serves compiled JS via importmap
  - Makefile targets: `make ts-build`, `make ts-watch` (using `bun --watch`)
  - No Node/npm, no Webpack, no Encore, no Stimulus
  - Bun added to Dockerfile in Phase 1.7
- [x] 2.14 Convert any YAML Symfony config files to PHP format
- [x] 2.15 Verify all tools pass on scaffolded project (zero errors)

### Phase 3: Domain Entities & Database (TDD)
- [x] 3.1 Write Category entity tests → implement in `Shared/Entity/Category` (name, slug, weight, color)
- [x] 3.2 Write Source entity tests → implement Source entity + ValueObjects (FeedUrl, SourceHealth enum: healthy/degraded/failing/disabled) + error tracking fields (error_count, last_error_message)
- [x] 3.3 Write Article entity tests → implement Article entity + ValueObjects (Url, ArticleFingerprint, Score):
  - Store `content_raw` (original HTML from feed) and `content_text` (stripped, for search/dedup)
  - `enrichment_method` (EnrichmentMethod enum from Shared/ValueObject: Ai/RuleBased, nullable)
  - `ai_model_used` (string, nullable)
  - NO `read_at` on Article — read state is per-user
- [x] 3.4 Write UserArticleRead entity (user_id, article_id, read_at) — per-user read tracking
- [x] 3.5 Create & run migrations
- [x] 3.6 Write architecture tests (PHPat): layer deps, naming conventions, interface-first enforcement
- [x] 3.7 Write repository integration tests
- [x] 3.8 Create data fixtures / seed command for default sources + categories

### Phase 4: Feed Fetching & Parsing (TDD)
- [x] 4.1 Install laminas/laminas-feed 2.26.x (framework-agnostic, register as Symfony service behind interface)
- [x] 4.2 Write FeedParserServiceInterface + implementation unit tests → implement parser:
  - Parse RSS `<description>` and `<content:encoded>` — store both raw HTML and stripped text
  - Handle encoding edge cases (UTF-8, HTML entities)
- [x] 4.3 Write FeedFetcherServiceInterface + implementation unit tests → implement fetcher (Symfony HttpClient)
- [x] 4.4 Write FetchSourceMessage + FetchSourceHandler tests → implement async handler
- [x] 4.5 Feed error handling:
  - Increment Source.error_count on fetch failure (HTTP error, malformed XML, timeout)
  - Store last_error_message on Source entity
  - Update Source.health_status (healthy → degraded after 2 failures → failing after 4 → auto-disable after 5)
  - Reset error_count on successful fetch
  - Log all errors with source name and URL
- [ ] 4.6 Write integration test: fetch RSS feed (using recorded fixture via Symfony HttpClient MockResponse) → parse → persist articles with content. Add separate `make smoke` target that hits real feeds (optional, not in CI)
- [x] 4.7 Configure Messenger transport (doctrine, retry strategy: max 3, exponential backoff) — already done in Phase 2

### Phase 5: Deduplication & Rule-Based Enrichment (TDD)
> **Note**: Rule-based services are implemented here (before AI in Phase 6) so the system is fully functional without any external API dependency. Phase 6 layers AI on top as an enhancement.

- [x] 5.1 Write DeduplicationServiceInterface + implementation unit tests:
  - URL exact match
  - Title similarity (similar_text, 85% threshold)
  - Content fingerprint (xxh128 hash match)
- [x] 5.2 Implement DeduplicationService
- [x] 5.3 Integrate dedup into FetchSourceHandler (check before persisting)
- [x] 5.4 Add DB indexes for fingerprint + URL lookups (done in Phase 3 migration)
- [x] 5.5 Write RuleBasedCategorizationService unit tests → implement (behind CategorizationServiceInterface):
  - Keyword matching against article title + content (5 categories, German + English keywords)
  - Case-insensitive substring matching, minimum 2 keyword matches
  - Returns category slug or null
- [x] 5.6 Write RuleBasedSummarizationService unit tests → implement (behind SummarizationServiceInterface):
  - Extract first two sentences of article content_text as fallback summary
  - Handle edge cases (short content, encoding, truncation at 500 chars)
- [x] 5.7 Integrate rule-based services into FetchSourceHandler pipeline

### Phase 6: AI Enrichment Layer — Symfony AI + OpenRouter (TDD)
> **Note**: AI services are decorators over rule-based services from Phase 5. Same interfaces. Article is always saved regardless of AI availability.
>
> **Model strategy**: `openrouter/free` auto-router as primary. Dynamic model discovery as secondary. Rule-based as last resort.
>
> **Quality gates**: All AI output validated before acceptance. Bad output rejected → next model or rule-based.

- [x] 6.1 Install symfony/ai-bundle 0.6.x + symfony/ai-open-router-platform + symfony/ai-failover-platform
- [x] 6.2 Configure OpenRouter via dedicated platform bridge (PHP config):
  - API key via `%env(OPENROUTER_API_KEY)%`
  - Model: `openrouter/auto` (auto-routes to best available model)
- [x] 6.3 Write ModelDiscoveryService tests → implement (in `Shared/AI/Service/`):
  - Query `GET /api/v1/models` (public, no auth needed)
  - Filter: free pricing, context_length >= 8192
  - Cache results (TTL: 1 hour) via Symfony Cache
  - Circuit breaker: 3 consecutive failures → stop retrying for 24h
  - Filter out models in `OPENROUTER_BLOCKED_MODELS` env var
- [x] 6.4 Configure FailoverPlatform chain via ai-failover-platform bundle:
  - Platform chain: openrouter → failover
  - Rate limiter: sliding window, 20 req/min
  - Rule-based fallback handled in AI service decorators
- [x] 6.5 Write AiQualityGateService tests → implement (in `Enrichment/Service/`):
  - Summary heuristics: reject if < 20 chars, > 500 chars, or title-repeat detection
  - Category validation: only accept known slugs
- [x] 6.6 Write AiCategorizationService tests → implement as decorator over RuleBasedCategorizationService:
  - Try AI → validate through AiQualityGateService
  - On rejection or failure: delegate to RuleBasedCategorizationService
- [x] 6.7 Write AiSummarizationService tests → implement as decorator over RuleBasedSummarizationService:
  - Try AI summarization → validate length
  - On failure: delegate to RuleBasedSummarizationService
- [x] 6.8 Write AiDeduplicationService tests → implement (semantic similarity available via isSemanticallyDuplicate, delegates to rule-based by default)
- [x] 6.9 Write ModelQualityTracker tests → implement (in `Shared/AI/Service/`):
  - Track per-model acceptance/rejection rate (cache-based, 7-day TTL)
  - Console command `app:ai-model-stats` — report model quality metrics + discovered free models
- [x] 6.10 services.php aliases AI implementations as defaults for all interfaces (CategorizationServiceInterface, SummarizationServiceInterface, DeduplicationServiceInterface)
- [x] 6.11 OPENROUTER_API_KEY in .env.example and .env, OPENROUTER_BLOCKED_MODELS wired via services.php
- [x] 6.12 EnrichmentMethod::RuleBased set on articles (AI method tracking ready in Article entity)

### Phase 7: Scoring & Ranking (TDD)
- [x] 7.1 Write ScoringServiceInterface + implementation unit tests:
  - Category weight scoring (0.3 weight, normalized to max 10)
  - Recency decay scoring (0.4 weight, 12h half-life, 7d max)
  - Source reliability weight (0.2 weight, health-based)
  - AI enrichment boost (0.1 weight, AI > RuleBased > none)
  - Combined score 0.0-1.0
- [x] 7.2 Implement ScoringService
- [x] 7.3 Integrate scoring into article persistence (FetchSourceHandler calls score on buildArticle)
- [x] 7.4 Write rescore command/message for bulk updates (app:rescore-articles + RescoreArticlesMessage/Handler)

### Phase 8: Scheduler & Background Jobs
- [x] 8.1 Create FetchScheduleProvider (Symfony Scheduler, `#[AsSchedule('fetch')]`)
- [x] 8.2 Configure per-category fetch intervals (D40):
  - Category.fetchIntervalMinutes (nullable, falls back to FETCH_DEFAULT_INTERVAL_MINUTES env var, default 15)
  - Seed defaults: politics=5min, business=10min, tech=15min, sports=30min, science=60min
  - Rationale: fetch interval is a property of content type, not individual sources
- [x] 8.3 Create console commands: `app:fetch-sources` (dispatch all), `app:check-sources` (health table), `app:rescore-articles` (from Phase 7)
- [x] 8.4 Create `app:cleanup` command:
  - Delete articles older than RETENTION_ARTICLES (default: 90 days)
  - Delete orphaned user_article_read entries
  - Retention periods configurable via env vars
- [x] 8.5 Test scheduler integration (FetchScheduleProvider + CleanupCommand unit tests)

### Phase 9: Unified Alert System + Symfony Notifier (TDD)
> **Unified AlertRule** — one entity, one pipeline:
> - `type: keyword` — keyword match only (fast, free, rule-based)
> - `type: ai` — keyword match first, then AI evaluation on matches only (cuts API calls ~90%)
> - `type: both` — keyword match triggers instant notify, AI evaluation adds severity context
>
> Keyword matching is always step 1. AI is always step 2, only on matches.

- [x] 9.1 Install symfony/notifier 8.0.x (no specific transport package — user's choice at deploy time)
- [x] 9.2 Configure Notifier with channel DSN env vars (NOTIFIER_CHATTER_DSN for chat transports)
- [x] 9.3 Write AlertRule entity tests → implement:
  - name (string), type (AlertRuleType enum: keyword / ai / both)
  - keywords (json array of strings), context_prompt (text, nullable — required when type includes ai)
  - urgency (AlertUrgency enum: low/medium/high)
  - severity_threshold (int 1-10, default 5) — only used when type includes ai
  - cooldown_minutes (int, default 60)
  - categories (json array, empty = all)
  - user_id (FK — multi-user ready)
  - enabled (bool), created_at, updated_at
- [ ] 9.4 Write NotificationLog entity tests → implement:
  - alert_rule reference, article reference, transport used, sent_at, success/failure
  - match_type (string: "keyword" or "ai"), ai_severity (int, nullable)
  - ai_explanation (text, nullable), ai_model_used (string, nullable)
- [x] 9.4 Write NotificationLog entity tests → implement
- [x] 9.5 Create migrations for AlertRule + NotificationLog
- [x] 9.6 Write ArticleMatcherService (+ interface) unit tests → implement:
  - Case-insensitive keyword matching against title + content + summary
  - Category filter on alert rule (empty = all)
  - Cooldown via NotificationLog query (configurable per rule)
  - Returns list of MatchResult (alert_rule, matched_keywords)
- [x] 9.7 Write AiAlertEvaluationService unit tests → implement:
  - AI prompt with context_prompt → parse SEVERITY + EXPLANATION
  - Rule-based fallback on AI failure (keyword overlap scoring)
  - Returns EvaluationResult (severity, explanation, model_used)
- [x] 9.8 Write NotificationDispatchService → implement:
  - Transport-agnostic via Symfony Notifier (urgency-mapped importance)
  - Logs to NotificationLog with AI metadata
- [x] 9.9 Create SendNotificationMessage + SendNotificationHandler:
  - Loads entities, optional AI evaluation, severity threshold check
  - Async via Messenger
- [x] 9.10 Integrate into FetchSourceHandler pipeline:
  - After flush → ArticleMatcherService → dispatch SendNotificationMessage per match
- [ ] 9.11 Write integration tests (deferred to E2E phase)
- [ ] 9.12 Add env vars to .env.example: NOTIFIER_CHATTER_DSN with Pushover example, commented-out examples for Telegram, Slack, Discord
- [ ] 9.13 Create seed/fixture data: example alert rules:
  - "Breaking News" (type: keyword, keywords: ["breaking", "eilmeldung"], urgency: high)
  - "Stock Portfolio" (type: ai, keywords: ["Tesla", "NVIDIA", "DAX", "S&P"], context_prompt: "I hold positions in Tesla, NVIDIA, and DAX ETFs.", severity_threshold: 6)
  - "Munich Safety" (type: both, keywords: ["München", "Munich", "Bayern"], context_prompt: "I live in Munich. Alert me about severe weather or security incidents.", severity_threshold: 4)

### Phase 10: Periodic Digest (TDD)
> Scheduled editorial summaries with AI-generated takeaways. Uses a periodic command (`app:process-digests`) instead of dynamic ScheduleProvider.

- [x] 10.1 Write DigestConfig entity tests → implement:
  - name (string), schedule (cron string)
  - categories (json array, empty = all), article_limit (int, default 10)
  - user_id (FK — multi-user ready)
  - enabled (bool), last_run_at (DateTimeImmutable, nullable), created_at, updated_at
- [x] 10.2 Write DigestLog entity tests → implement:
  - digest_config reference, generated_at, article_count, content snapshot (text), delivery status, transport used
- [x] 10.3 Create migrations for DigestConfig + DigestLog
- [x] 10.4 Write DigestGeneratorService tests → implement:
  - Query top-scored articles since last_run_at for configured categories
  - Group articles by category
  - Returns structured digest data (articles per category, metadata)
- [x] 10.5 Write DigestSummaryService tests → implement (AI via Shared/AI + rule-based fallback):
  - AI path: editorial summary per category + takeaways + risk flags
  - Quality gate: validate AI output structure
  - Rule-based fallback: article titles with first-sentence excerpts, grouped by category
- [x] 10.6 Create `app:process-digests` console command:
  - Runs every 5 minutes via Symfony Scheduler (fixed schedule, compile-time)
  - Queries enabled DigestConfig entities
  - For each: parse cron string, compare against last_run_at + current time
  - If due: dispatch GenerateDigestMessage, update last_run_at
- [x] 10.7 Create GenerateDigestMessage + GenerateDigestHandler (async via Messenger)
- [x] 10.8 Dispatch via Symfony Notifier in GenerateDigestHandler
- [x] 10.9 Write integration test: digest pipeline (collect → summarize → log → update lastRunAt) + skip when no articles
- [ ] 10.10 Create seed data: "Daily Tech Digest" daily 8am, "Weekly Summary" Monday 9am

### Phase 11: Frontend — Twig + DaisyUI + TypeScript

#### 11A: Layout & Theme
- [ ] 11A.1 Configure base layout with DaisyUI CDN (version-pinned) + Tailwind CDN
  - DaisyUI `night` theme (dark default) + `winter` theme (light) with toggle
  - Theme preference stored in `localStorage`, applied via `data-theme` attribute
- [ ] 11A.2 Navigation: top navbar (sticky) with:
  - Logo/title left, search bar center, dark mode toggle + user dropdown right
  - Mobile: hamburger menu → slide-out drawer with nav links
- [ ] 11A.3 Sidebar (desktop only, collapsible): category list with article counts, source health indicators, quick links to settings
- [ ] 11A.4 Reusable Twig components (using Symfony Form component for all forms — CSRF automatic):
  - `_article_card.html.twig` — card with title, source badge, category badge (colored), time ago, score indicator, AI summary, enrichment method icon, read/unread state, external link
  - `_stat_widget.html.twig` — stat card for dashboard
  - `_empty_state.html.twig` — illustration + message + CTA button
  - `_flash_messages.html.twig` — toast notifications (DaisyUI alert)
  - `_source_health_badge.html.twig` — green/yellow/red indicator based on SourceHealth enum
  - `_pagination_loader.html.twig` — infinite scroll sentinel + spinner
- [ ] 11A.5 Create `assets/ts/timeago.ts`:
  - Query all `<time datetime="...">` elements, format via `Intl.DateTimeFormat` with browser timezone
  - Relative time for recent ("5 min ago"), absolute for older ("> 24h")
  - `setInterval` (60s) to re-render. Handle cross-day boundary, DST transitions
- [ ] 11A.6 Create `assets/ts/infinite-scroll.ts`:
  - `IntersectionObserver` on sentinel element at bottom of article list
  - Fetch next page via `fetch()`, append to DOM
  - Show loading spinner during fetch, "No more articles" when exhausted
- [ ] 11A.7 Create `assets/ts/theme-toggle.ts`:
  - Toggle `data-theme` between `night`/`winter`, persist to `localStorage`
- [ ] 11A.8 Create `assets/ts/mark-as-read.ts`:
  - On article link click: fire-and-forget `POST /articles/{id}/read` via `fetch()`
  - Update card styling (add read class) without page reload

#### 11B: Pages
- [x] 11B.1 **Dashboard** (homepage `/`):
  - Top row: stat widgets (articles today, sources active, alerts triggered, last fetch time)
  - Category tabs (horizontal scrollable on mobile): All / Politics / Business / Tech / Science / Sports
  - Article feed: cards sorted by score, infinite scroll, read/unread styling
  - Cursor-based pagination on ArticleController: `GET /?cursor={lastArticleId}&category={slug}` returns HTML fragment for infinite scroll append
  - Click article title → opens original URL in new tab, marks as read
  - First-boot empty state: "No articles yet — configure your sources" → link to source management
- [x] 11B.2 **Search** (`/search?q=...`) — stub controller + template created
- [x] 11B.3 **Source management** (`/sources`) — SourceController + template (list view; forms deferred)
- [x] 11B.4 **Alert rule management** (`/alerts`) — stub controller + template created
- [x] 11B.5 **Digest configuration** (`/digests`) — stub controller + template created
- [x] 11B.6 **Notification log** (`/notifications`) — stub controller + template created
- [ ] 11B.7 **Digest history** (`/digests/history`):
  - List: digest name, generated at, article count, delivery status
  - Expandable: click to reveal generated content preview
- [x] 11B.8 **AI model stats** (`/stats/ai`) — stub controller + template created
- [x] 11B.9 **Settings** (`/settings`) — stub controller + template created

#### 11C: Search Integration
- [ ] 11C.1 Install `cmsig/seal-symfony-bundle` + `cmsig/seal-loupe-adapter`
- [ ] 11C.2 Define Article search index (title, content_text, summary, source name, category — all searchable)
- [ ] 11C.3 Create ArticleSearchServiceInterface + SEAL implementation
- [ ] 11C.4 Event listener: sync article to search index on persist/update (async via Messenger)
- [ ] 11C.5 Create `app:search-reindex` console command for initial/full reindex
- [ ] 11C.6 Write integration test: article persisted → indexed → searchable by title/content

#### 11D: Read State
- [ ] 11D.1 Mark as read: POST endpoint (`/articles/{id}/read`), creates UserArticleRead entry
- [ ] 11D.2 Mark all as read: button on dashboard, bulk insert
- [ ] 11D.3 Visual styling: read articles get reduced opacity + muted border, unread are bold
- [ ] 11D.4 Dashboard filter toggle: "Show unread only" (default on)

#### 11E: Testing
- [ ] 11E.1 Write functional tests for all controllers (WebTestCase)
- [ ] 11E.2 Write E2E tests (Panther, already installed in Phase 2) for critical paths:
  - Dashboard loads, articles display, category filter works, infinite scroll loads more
  - Search: query returns results, empty state shows
  - Source management: add source, toggle enabled, delete
  - Alert rule management: create keyword rule, create AI rule with context prompt, toggle
  - Digest config: create daily digest, verify in history
  - Notification log page renders, filters work
  - Dark mode toggle persists across navigation
- [ ] 11E.3 **Golden path end-to-end test**:
  - Seed sources → fetch feeds → dedup → enrich → score → alert rule matches → notification dispatched
  - Validates the entire pipeline works in one test

### Phase 12: Logging & Monitoring
- [ ] 12.1 Configure Monolog: stderr (docker) + rotating file handlers
- [ ] 12.2 Add structured logging to: feed fetcher (incl. errors + health transitions), dedup, scoring, AI calls (model used, quality gate result), notification dispatches, alert evaluations, digest generation
- [ ] 12.3 Configure Ember v1.0.1 Docker container in compose.yaml (needs access to Caddy admin API on php container)
- [ ] 12.4 Run `ember init` against Caddy, verify metrics endpoint
- [ ] 12.5 Verify Ember dashboard shows FrankenPHP worker metrics

### Phase 13: CI/CD, Security & Final Polish
> **Principle**: CI runs inside Docker Compose to match local dev exactly. Independent jobs parallelized. Scheduled pipelines for maintenance-free operation.

- [ ] 13.1 Create GitHub Actions CI workflow (`ci.yml`, on push/PR):
  - All jobs use Docker Compose (build from same Dockerfile as local dev)
  - **Parallel jobs** (no DB needed, independent):
    - Job 1: ECS check
    - Job 2: PHPStan
    - Job 3: Rector --dry-run
    - Job 4: Bun build (TypeScript compilation check)
  - **Sequential jobs** (need DB, depend on parallel passing):
    - Job 5: PHPUnit unit + integration (with PostgreSQL service via compose)
    - Job 6: Infection mutation testing (unit suite only, depends on Job 5 for coverage)
  - **E2E job** (depends on full stack):
    - Job 7: Symfony Panther E2E tests (full Docker Compose stack up)
  - Docker layer caching via `actions/cache` for fast rebuilds
- [ ] 13.2 Create scheduled security pipeline (`security.yml`, weekly cron):
  - `composer audit`, `symfony security:check`, `docker scout cves`
  - Notify on failure
- [ ] 13.3 Configure Dependabot (`.github/dependabot.yml`):
  - Composer dependencies: weekly, auto-open PRs
  - Docker base images: weekly
  - GitHub Actions versions: monthly
  - Group minor/patch updates, assign to repo owner
- [ ] 13.4 Create GHCR image publishing workflow (`publish.yml`, on tag/release):
  - Build production image, push to `ghcr.io/tony-stark-eth/news-aggregator`
  - Tag with semver + `latest`
  - Users can `docker pull` and run without building from source
- [ ] 13.5 Verify all quality tools pass in CI
- [ ] 13.6 Run acc skills to validate: `audit architecture`, `php-code-review`
- [ ] 13.7 Update CLAUDE.md (created in Phase 1.13) with final architecture, all commands, domain overview
- [ ] 13.8 Add entry to parent Projects/CLAUDE.md services table
- [ ] 13.9 Add to Homarr dashboard
- [ ] 13.10 Document in README:
  - Quickstart: `docker pull` + `docker compose up` + configure env vars
  - Notification setup: install transport package, set DSN, create alert rules. Popular transports with DSN examples
  - Alert rules: how to create, keyword vs AI vs both, example context prompts
  - Digest: how to configure schedules, category filters
  - AI: how it works, quality gates, model rotation, optional model pinning
  - Data retention: how to configure cleanup intervals
  - Contributing: link to CONTRIBUTING.md
- [ ] 13.11 Create Mermaid diagrams in `docs/`:
  - `docs/architecture.md` — system architecture: Docker services, domain boundaries, data flow between domains, external dependencies (OpenRouter, RSS feeds, Notifier transports, Loupe/SQLite)
  - `docs/article-lifecycle.md` — user flow: RSS feed → fetch → dedup → enrich (rule-based → AI) → score → alert match → notification. Covers the full article pipeline end-to-end
  - Embed both in README with links to full diagrams
- [ ] 13.12 Final README polish: badges (CI status, license, PHP version), screenshots, architecture diagram preview, quickstart guide
- [ ] 13.13 Update CHANGELOG.md with initial release notes

---

## Resolved Questions

| # | Question | Resolution |
|---|----------|------------|
| Q1 | Bloomberg RSS | No public feed → replaced with MarketWatch + CNBC |
| Q2 | Financial Times RSS | Paywalled → replaced with Reuters Business |
| Q3 | Port assignment | 8443 (HTTPS) / 8180 (HTTP) — confirmed free on host |
| Q4 | Ember deployment | Docker container (v1.0.1) |
| Q5 | Watchlists vs Alert Profiles | Merged into unified AlertRule (D17) — one entity, one pipeline |
| Q6 | Dynamic ScheduleProvider | Replaced with periodic `app:process-digests` command (D26) |
| Q7 | ModelQualityTracker auto-blocklist | Dropped — data too thin. Manual env var instead (D25) |
| Q8 | Category as bounded context | Too thin — moved to Shared/Entity (architecture review) |
| Q9 | Search as bounded context | Too thin — moved to Shared/Search (architecture review) |
| Q10 | AI infra placement | Cross-cutting — moved to Shared/AI (architecture review) |
| Q11 | Article dedup scope | Global (not per-user). Same article stored once. Read state is per-user via UserArticleRead. Documented in architecture core rules |

## Error Log

_(Track errors and blockers here during implementation)_
