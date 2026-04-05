# News Aggregator — Progress Log

## Session 15 — 2026-04-05

### Completed

#### Phase 13: CI/CD, Security & Final Polish

- [x] 13.5: CI fix — bypass PgBouncer for test DB (compose env DATABASE_URL overrides .env.test; pass direct database URL for all test steps)
- [x] 13.5: CI fix — remove manual `scheduler_fetch` transport from messenger.php (wrong DSN format `symfony://` → should be `schedule://`; `#[AsSchedule('fetch')]` auto-registers correctly)
- [x] 13.6: Architecture audit — created 7 missing service interfaces (ModelDiscoveryService, ModelQualityTracker, AiQualityGateService, DigestGeneratorService, DigestSummaryService, NotificationDispatchService, AiAlertEvaluationService)
- [x] 13.6: Architecture audit — updated all consumers to type-hint interfaces instead of concrete classes
- [x] 13.6: Architecture audit — added 3 PHPat rules (Article→Enrichment/Notification/Digest, Enrichment→Notification/Digest, Source→all-domains), with exclusions for FetchSourceHandler (orchestration) and SeedDataCommand (cross-domain seeder)
- [x] 13.6: DI/config audit — all clean: services.php aliases, Messenger routing, Doctrine mappings, security, Monolog, routes, env vars all correct

### Audit Findings (non-actionable / accepted trade-offs)

- FetchSourceHandler (Article/) imports Enrichment + Notification — accepted as orchestration pipeline. Excluded from PHPat rule.
- SeedDataCommand (Source/) seeds DigestConfig — accepted as cross-domain seeder. Excluded from PHPat rule.
- Digest → Article dependency — legitimate upstream/downstream relationship. Digests summarize articles.
- Value objects already `final readonly class` — properties implicitly readonly (PHP 8.2+).
- 9 PHPUnit notices pre-existing — not related to audit changes.

### Quality

- ECS: OK
- PHPStan: 0 errors (160 files, including 7 new interfaces)
- Rector: clean
- PHPUnit: 120 tests, 311 assertions, all pass

## Session 14 — 2026-04-04

### Completed

#### Phase 13: CI/CD, Security & Final Polish

- [x] 13.1: Created `.github/workflows/ci.yml` — parallel quality jobs (ECS, PHPStan, Rector via matrix strategy) + sequential test jobs (unit, integration, functional); all inside Docker Compose
- [x] 13.2: Created `.github/workflows/security.yml` — weekly Monday 6am cron; `composer audit` + `symfony security:check`; `workflow_dispatch` for manual runs
- [x] 13.3: Created `.github/dependabot.yml` — Composer (weekly, minor/patch grouped), Docker (weekly), GitHub Actions (monthly); all assigned to `tony-stark-eth`
- [x] 13.4: Created `.github/workflows/publish.yml` — triggers on `v*` tags; builds `frankenphp_prod` target; tags with semver + `latest`; pushes to `ghcr.io/tony-stark-eth/news-aggregator`
- [x] 13.7: Updated `CLAUDE.md` — full make target reference table, domain overview diagram, AI integration summary, key env vars table
- [x] 13.10: Updated `README.md` — GHCR quickstart (`docker pull`), notification setup with DSN examples for 5 transports, alert rules documentation (types, fields, example prompts), digest configuration, AI explanation, data retention config, CI/license/PHP badges
- [x] 13.11: Created `docs/architecture.md` — Mermaid graph diagram: Docker services, domain data flow, external dependencies (OpenRouter, RSS feeds, Notifier transports, Loupe/SQLite), port map, domain boundary table
- [x] 13.11: Created `docs/article-lifecycle.md` — Mermaid flowchart: full article pipeline (fetch → dedup → persist → score → enrich → search index → alert matching → notification → digest), key design decisions table
- [x] 13.12: README badges added (CI, license, PHP version); links to docs/ diagrams; GHCR quickstart as Option A; Option B (build from source) preserved
- [x] 13.13: Updated `CHANGELOG.md` — comprehensive initial release notes covering all 13 phases: infrastructure, quality tooling, all domains, AI integration, frontend, operations, CI/CD, architecture decisions

### Notes
- 13.5 (verify CI passes), 13.6 (code review skills), 13.8 (parent CLAUDE.md), 13.9 (Homarr) are manual steps — skipped per instructions

## Session 13 — 2026-04-04

### Completed

#### Phase 12: Logging & Monitoring

- [x] 12.1: Updated `config/packages/monolog.php` — added `app` channel; dev now logs to `php://stderr` (primary) + rotating file (7-day retention); prod retains stderr JSON + separate `app` rotating log (30-day, JSON); test unchanged
- [x] 12.2: Enhanced structured logging context across all key services:
  - `AiAlertEvaluationService` — added `rule`, `rule_id`, `article`, `article_id`, `model` to warning
  - `AiCategorizationService` — added `model` to quality gate rejection + failure warning
  - `AiSummarizationService` — added `model` to rejection info + failure warning
  - `SendNotificationHandler` — added `rule_id` + `article_id` to severity-skip info + sent info
  - `GenerateDigestHandler` — added `digest_config_id` to skip/failure/success logs; added `delivery_success` to completion log
  - `FetchSourceHandler` + `ModelFailoverPlatform` — already had full context, verified OK
- [x] 12.3: Added `ember` service to `compose.override.yaml` (dev only) — `ghcr.io/hosmelq/ember:1.0.1`, depends on `php`, `EMBER_CADDY_ADMIN_URL=http://php:2019`, `tty: true`
- [x] 12.4-12.5: Added `make ember` target to Makefile (runs `ember` binary inside container)

### Quality
- ECS: OK (no issues)
- PHPStan: 0 errors (153 files)
- Rector: clean (no changes needed)
- PHPUnit unit: 95 tests pass
- PHPUnit integration: 11 tests pass

## Session 1 — 2026-04-04

### Completed
- [x] Created project directory `/home/kmauel/Projects/news-aggregator/`
- [x] Researched dunglas/symfony-docker (FrankenPHP + Caddy + PostgreSQL, no tagged releases)
- [x] Researched template-symfony-sveltekit backend patterns (DDD, tooling, testing)
- [x] Researched Ember v1.0.1 (Caddy/FrankenPHP monitoring TUI, not error tracker)
- [x] Researched Symfony AI bundle v0.6.0 (Generic Platform bridge for OpenRouter, FailoverPlatform)
- [x] Checked latest versions of all dependencies via GitHub API
- [x] Checked Symfony 8.0 details via Context7 (latest 8.0.8, PHP 8.4 required)
- [x] Scanned host ports — selected 8443/8180 (confirmed free)
- [x] Clarified with user: Monolog, Twig + DaisyUI, single user, Ember as Docker container
- [x] Replaced paywalled feeds: Bloomberg → MarketWatch, FT → Reuters Business, added CNBC + Der Spiegel
- [x] Updated plan: OpenRouter free models via Symfony AI FailoverPlatform
- [x] Updated plan: correct phase sequencing (scaffold first → quality tools → then domain code)
- [x] Created task_plan.md (11 phases, ~70 tasks, all questions resolved)
- [x] Created findings.md (all research documented with versions)
- [x] Created progress.md
- [x] Dropped CaptainHook → custom `.githooks/` shell scripts (pre-commit: ECS + PHPStan + Rector)
- [x] Switched PCOV → Xdebug path coverage (shared with Infection)
- [x] Infection scoped to unit test suite only
- [x] Replaced laminas/laminas-feed → debril/rss-atom-bundle (Symfony bundle, no pure Symfony component exists)
- [x] Added D12-D15 decisions: custom git hooks, Xdebug path coverage, PHP config only, debril/rss-atom-bundle

- [x] Added D19-D21: private repo (open-source ready), low-maintenance (Dependabot, security pipeline), Docker-based CI
- [x] Phase 1: added private GH repo creation, open-source prep (LICENSE, README, CONTRIBUTING, templates, SECURITY.md)
- [x] Phase 10: added Symfony Panther E2E tests for critical paths
- [x] Phase 12: rewritten — Docker Compose CI, parallelized jobs, scheduled security pipeline, Dependabot, README polish

- [x] Researched OpenRouter `/api/v1/models` endpoint (public, no auth) and `openrouter/free` auto-router
- [x] Added D24-D27: openrouter/free, AI quality gates, periodic digest, smart alert profiles
- [x] Phase 6 rewritten: openrouter/free primary, dynamic model discovery secondary, quality gates, model quality tracking
- [x] Phase 10 added (split 10A/10B): Periodic Digest + Smart Alert Profiles with AI evaluation
- [x] Phase 11 (Frontend): added alert profile CRUD, digest config/history pages, AI model stats page
- [x] Renumbered to 13 phases, ~120 tasks
- [x] Applied reviewer feedback (2 reviewers):
  - Merged Watchlists + Alert Profiles → unified AlertRule (keyword/ai/both), one entity, one pipeline
  - Digest: periodic command every 5 min instead of dynamic ScheduleProvider (Symfony Scheduler compile-time limitation)
  - ModelQualityTracker: dropped auto-blocklist, kept stats command + manual OPENROUTER_BLOCKED_MODELS env var
  - AI call volume: keyword match first → AI only on matches (~10-20 calls/day instead of ~250)
  - ModelDiscovery: added circuit breaker (3 failures → 24h fallback to DB-persisted list)
  - timeago.js: dedicated module with setInterval, DST handling, not inline snippet

- [x] Self-review (gewissenhaft): 20 issues identified and resolved:
  - Architecture: Article domain split (Article + Enrichment), Category/Search demoted to Shared, AI infra to Shared/AI
  - Multi-user readiness: User entity + UserArticleRead (no read_at on Article), user_id FKs on AlertRule/DigestConfig
  - Auth: symfony/security-bundle, single admin user via env vars
  - Data retention: app:cleanup command, configurable retention periods
  - Feed error handling: Source health tracking, auto-disable after 5 failures
  - CSRF: Symfony Form component for all forms
  - Content storage: RSS description/content:encoded stored (raw HTML + stripped text)
  - CHANGELOG.md, GHCR publishing, backup/restore targets
  - Panther setup moved to Phase 2 (early compat check)
  - PR #10 split into 3 PRs (#10, #11, #12)
  - Golden path end-to-end test added
  - D27 strikethrough cleaned up
  - Frontend: plain TypeScript via Bun + AssetMapper, DaisyUI version-pinned
  - Added D32-D37 decisions

- [x] Initialized git repo, created .gitignore (Phase 1.5)
- [x] Created private GitHub repo tony-stark-eth/news-aggregator (Phase 1.4)
- [x] Created CLAUDE.md with planning discipline rules (Phase 1.13)
- [x] Created PITCH.md for open-source readiness
- [x] Committed and pushed initial planning files
- [x] Saved feedback memory for planning file update discipline

## Session 2 — 2026-04-04

### Completed
- [x] Phase 1.1-1.3: Cloned dunglas/symfony-docker, built Docker images, scaffolded Symfony 8.0.8 (PHP 8.4.19)
- [x] Phase 1.6: Open-source files (LICENSE, README, CONTRIBUTING, CHANGELOG, SECURITY, .env.example, GitHub templates)
- [x] Phase 1.7: Dockerfile adapted — Bun added to frankenphp_dev stage
- [x] Phase 1.8: Docker Compose setup — PostgreSQL 17, PgBouncer (edoburu/pgbouncer, transaction mode), Messenger worker
- [x] Phase 1.9: Ports mapped — 8443 (HTTPS) / 8180 (HTTP)
- [x] Phase 1.10: docker/postgres/init.sql — app_test DB for integration tests
- [x] Phase 1.11-1.12: Makefile with all targets (docker, quality, test, db, backup/restore, ts, hooks)
- [x] Phase 1.14: .claude/ guideline files (coding-php, coding-typescript, testing, architecture)
- [x] Phase 1.15: Verified — Symfony welcome page on https://localhost:8443
- [x] Phase 2.1: PHPStan 2.1.x installed — level max, 10 extensions, bleeding edge, zero errors
- [x] Phase 2.2: ECS 13.0.x installed — PSR-12 + common + strict + cleanCode
- [x] Phase 2.3: Rector 2.4.x installed — PHP 8.4, Symfony 8, Doctrine, PHPUnit sets
- [x] Phase 2.4: Infection 0.32.x installed — unit-only, 80/90% MSI thresholds
- [x] Phase 2.5: PHPat 0.12.x installed via PHPStan extension
- [x] Phase 2.6: Git hooks — pre-commit (ECS+PHPStan+Rector with rebase guard + patch backup), commit-msg (conventional commits)
- [x] Phase 2.7: PHPUnit 13.1.x — unit + integration suites, random order, requireCoverageMetadata
- [x] Phase 2.8: Symfony Panther installed (ServerExtension in phpunit.dist.xml)
- [x] Phase 2.9: All Makefile targets in place
- [x] Phase 2.10: symfony/clock installed, ShipMonk bans time/date/strtotime via forbidCustomFunctions
- [x] Phase 2.11: Doctrine configured for UTC (datetime_immutable, server_version 17)
- [x] Phase 2.12: Security configured — memory provider, form_login, ADMIN_EMAIL env var
- [x] Phase 2.13: TypeScript pipeline — tsconfig.json (strict), Bun in Dockerfile, AssetMapper configured
- [x] Phase 2.14: All YAML configs converted to PHP (ContainerConfigurator::extension() pattern)
- [x] Phase 2.15: All quality tools pass — ECS OK, PHPStan OK (0 errors), Rector OK

### Key Decisions Made
- Used `edoburu/pgbouncer` instead of `bitnami/pgbouncer` (bitnami:latest not found on Docker Hub)
- PHP config uses `ContainerConfigurator::extension()` pattern, not typed config classes (chicken-and-egg issue with cache warmup)
- Security uses memory provider instead of User entity for now (entity deferred to Phase 3)
- Ember container deferred to Phase 12 (monitoring)
- ShipMonk forbidden functions configured via services override (not via `list` config param)

## Session 3 — 2026-04-04

### Completed
- [x] Phase 3.1: Category entity + tests (name, slug, weight, color)
- [x] Phase 3.2: Source entity + SourceHealth enum + FeedUrl VO + InvalidFeedUrlException + tests (health state machine: recordSuccess/recordFailure)
- [x] Phase 3.3: Article entity + ArticleFingerprint VO + Url VO + tests (content_raw/content_text, enrichment tracking)
- [x] Phase 3.4: User entity (UserInterface + PasswordAuthenticatedUserInterface) + UserArticleRead entity
- [x] Phase 3.5: Migration generated and applied, schema validated
- [x] Added pdo_pgsql to Dockerfile (was missing from dunglas template)
- [x] Updated Doctrine mapping to domain-based structure (6 domains: Shared, Article, Source, User, Notification, Digest)
- [x] 23 unit tests passing, all quality checks clean

- [x] Phase 3.6: PHPat architecture tests — LayerDependencyTest + NamingConventionTest in tests/Architecture/
  - Tests registered in phpstan.neon with phpat.test tag
  - PHPat 0.12 API: shouldNot().dependOn() and should().extend/implement() (not deprecated shorthand)
  - Tests immediately caught SeedDataCommand in wrong namespace (Shared instead of Source domain)
  - Moved SeedDataCommand to App\Source\Command + fixed PHPStan type annotations
- [x] Phase 3.8: SeedDataCommand (app:seed-data) — 5 categories + 16 sources, idempotent (skip existing by slug/URL)

### Current Status
Phase 3 complete (7 of 8 tasks). 3.7 (repository integration tests) deferred — needs test DB config. Ready for Phase 4.

### Blockers
- None

### Next Steps
- Phase 4: Feed fetching + parsing

## Session 4 — 2026-04-04

### Completed
- [x] Phase 4.2: FeedParserServiceInterface, FeedItem DTO, LaminasFeedParserService (laminas/laminas-feed), unit test (RSS 2.0, Atom, skip missing title/link, HTML entity decoding)
- [x] Phase 4.3: FeedFetcherServiceInterface + HttpFeedFetcherService (Symfony HttpClient, 15s timeout, RSS Accept header) + FeedFetchException + unit tests (success, HTTP 4xx, network error)
- [x] Phase 4.4: FetchSourceMessage + FetchSourceHandler (async handler: fetch → parse → persist articles → update source health) + unit tests (persist articles, failure recording, skip disabled)
  - FetchSourceHandler placed in App\Article\MessageHandler (not App\Source) to respect PHPat architecture rule: App\Source must not depend on App\Article
  - FeedFetchException.fromUrl() accepts optional $previous throwable for proper exception chaining
  - Test uses createMock() with @var MockObject intersection types (PHPStan-compatible)
  - 42 tests, 126 assertions — all passing

## Session 5 — 2026-04-04

### Completed
- [x] Phase 5.5: CategorizationServiceInterface + RuleBasedCategorizationService (5 categories, keyword scoring, requires >=2 matches)
  - `src/Enrichment/Service/CategorizationServiceInterface.php`
  - `src/Enrichment/Service/RuleBasedCategorizationService.php`
  - `tests/Unit/Enrichment/Service/RuleBasedCategorizationServiceTest.php` (5 test cases)
- [x] Phase 5.6: SummarizationServiceInterface + RuleBasedSummarizationService (first 2 sentences, 500-char cap, short-fragment filter)
  - `src/Enrichment/Service/SummarizationServiceInterface.php`
  - `src/Enrichment/Service/RuleBasedSummarizationService.php`
  - `tests/Unit/Enrichment/Service/RuleBasedSummarizationServiceTest.php` (6 test cases)

### Next Steps
- Phase 5.7: Integrate rule-based services into FetchSourceHandler pipeline

## Session 6 — 2026-04-04

### Completed
- [x] Phase 10.1: DigestConfig entity + unit test (name, schedule, categories, articleLimit, user FK, enabled, lastRunAt, createdAt, updatedAt)
- [x] Phase 10.2: DigestLog entity + unit test (digestConfig FK, generatedAt, articleCount, content, deliverySuccess, transport)
- [x] Phase 10.3: Doctrine migration generated and applied (digest_config + digest_log tables, 6 SQL queries)
- [x] Phase 10.4: DigestGeneratorService — queries top-scored articles since lastRunAt, filtered by category slugs, grouped by category
- [x] Phase 10.5: DigestSummaryService — AI via PlatformInterface (openrouter/auto) with rule-based fallback; wired in services.php
- [x] Phase 10.6: ProcessDigestsCommand (app:process-digests) — checks enabled configs against cron schedule via CronExpressionTrigger, dispatches GenerateDigestMessage when due
- [x] Phase 10.7: GenerateDigestMessage + GenerateDigestHandler — async Messenger handler, collects articles, generates summary, sends Notifier notification, persists DigestLog, updates lastRunAt
- [x] Deleted src/Digest/Entity/.gitkeep
- [x] ECS, PHPStan, Rector all pass (fixed: null-safe comparison for getNextRunDate(), FlipTypeControlToUseExclusiveTypeRector applied)

### Key Findings
- CronExpressionTrigger::fromSpec() + getNextRunDate() works correctly (symfony/scheduler v8.0.8, dragonmantank/cron-expression v3.x)
- getNextRunDate() returns ?DateTimeImmutable — null check required before comparison
- Integration test failures (ArticleRepositoryTest) are pre-existing schema drift (fetch_interval_minutes column), not caused by Phase 10

## Session 7 — 2026-04-04

### Completed
- [x] Phase 11B.1: DashboardController + templates/dashboard/index.html.twig + templates/dashboard/_article_list.html.twig
  - Stats row (articles today, active sources), category tabs, article feed with infinite scroll support
  - AJAX fragment support via isXmlHttpRequest() returning _article_list partial
- [x] Phase 11B.3: SourceController + templates/source/index.html.twig (list view; forms deferred to next iteration)
- [x] Stub controllers: AlertRuleController, NotificationLogController, DigestController, SearchController, SettingsController, AiStatsController, LoginController, ReadStateController
- [x] Stub templates: alert/index, notification/index, digest/index, search/index, settings/index, stats/ai, security/login
- [x] LoginController includes logout route (firewall-intercepted); ReadStateController handles POST /articles/{id}/read with UserArticleRead persistence

## Session 8 — 2026-04-04

### Completed
- [x] Updated enrichment service return types from `?string` to `EnrichmentResult`:
  - `RuleBasedCategorizationService::categorize()` — wraps result in `EnrichmentResult(value, EnrichmentMethod::RuleBased)`
  - `RuleBasedSummarizationService::summarize()` — wraps result in `EnrichmentResult(value, EnrichmentMethod::RuleBased)`
  - `AiCategorizationService::categorize()` — returns `EnrichmentResult(slug, EnrichmentMethod::Ai, MODEL)` on success; delegates to rule-based fallback on failure
  - `AiSummarizationService::summarize()` — returns `EnrichmentResult(summary, EnrichmentMethod::Ai, MODEL)` on success; delegates to rule-based fallback on failure
- [x] Updated `FetchSourceHandler::buildArticle()` to use `EnrichmentResult`:
  - `applyCategory()` extracted method — uses `$catResult->value`
  - `applyEnrichment()` extracted method — sets `EnrichmentMethod` from actual AI/RuleBased result, sets `aiModelUsed` from AI result
  - `processItems()` + `persistItem()` extracted to reduce `__invoke` complexity under 8
- [x] Updated all unit tests for enrichment services to assert on `$result->value` and `$result->method`
- [x] All quality tools pass: ECS, PHPStan, Rector
- [x] All 105 tests pass

## Session 9 — 2026-04-04

### Completed
- [x] Replaced `list<X>` PHPDoc array annotations with typed `ArrayCollection` subclasses throughout the codebase:
  - `FeedParserServiceInterface::parse()` + `LaminasFeedParserService::parse()` — return `FeedItemCollection`
  - `ArticleMatcherServiceInterface::match()` + `ArticleMatcherService::match()` — return `MatchResultCollection`
  - `FetchResult::$newArticles` — type changed from `array` to `ArticleCollection`
  - `FetchSourceHandler::processItems()` — param changed to `FeedItemCollection`, builds `ArticleCollection` for `FetchResult`
  - `FetchSourceHandler::dispatchAlerts()` — param changed to `ArticleCollection`
  - `GroupedArticles::$byCategory` — type changed to `array<string, ArticleCollection>`
  - `DigestGeneratorService::collectArticles()` — builds `ArticleCollection` per category group
  - `DigestSummaryService::formatArticles()` + `ruleBasedFallback()` — param annotations updated to `array<string, ArticleCollection>`
- [x] Updated tests to use `->toArray()` before array-index access (PHPStan: `offsetGet()` returns `?T`)
- [x] All quality tools pass: ECS clean, Rector clean, PHPStan 0 errors
- [x] All 95 unit tests pass

## Session 10 — 2026-04-04

### Completed

#### Phase 11C: Search Integration (SEAL + Loupe)
- [x] Created `config/packages/seal.php` — SEAL bundle config (loupe engine, `config/seal/` schema dir)
- [x] Created `config/seal/articles.php` — PHP schema file with Index, IdentifierField, TextField, FloatField, DateTimeField
- [x] `src/Shared/Search/Service/ArticleSearchServiceInterface.php` — index/remove/search API
- [x] `src/Shared/Search/Service/SealArticleSearchService.php` — SEAL engine implementation (saveDocument, deleteDocument, createSearchBuilder + Condition::search + Condition::equal)
- [x] `src/Shared/Search/EventListener/ArticleIndexListener.php` — Doctrine postPersist/postUpdate listener via `#[AsEntityListener]`
- [x] `src/Shared/Search/Command/SearchReindexCommand.php` — app:search-reindex with batch processing
- [x] Updated `SearchController` to use `ArticleSearchServiceInterface` (IDs → load by IDs, category filter)
- [x] Updated `services.php`: `ArticleSearchServiceInterface` alias to `SealArticleSearchService`
- [x] Updated PHPat architecture test: `App\Shared\Search` excluded from "Shared must not depend on domains" rule (variadic `excluding()`)

#### Phase 11D: Read State
- [x] Verified `ReadStateController` at `src/User/Controller/ReadStateController.php` — complete
- [x] Updated `DashboardController` to query `UserArticleRead` for current user, pass `readArticleIds` map to templates (both full-page and AJAX fragment)

#### Phase 10.10: Digest Seed Data
- [x] Added `User::setPassword()` method
- [x] Updated `SeedDataCommand` with `UserPasswordHasherInterface` dependency
- [x] Added `seedDemoUser()` — creates `demo@example.com` user if none exists (idempotent)
- [x] Added `seedDigestConfigs()` — creates "Daily Tech Digest" (`0 8 * * *`, tech, limit 10) and "Weekly Summary" (`0 9 * * 1`, all categories, limit 20), both idempotent

#### Phase 11B.7: Digest history
- [x] Verified `templates/digest/index.html.twig` — expandable content preview already present (DaisyUI `collapse` component with `<pre>{{ log.content }}</pre>`)

### Quality
- ECS: OK (2 fixes applied: `!` spacing)
- PHPStan: 0 errors
- Rector: clean
- PHPUnit unit suite: 95 tests pass

## Session 12 — 2026-04-04

### Completed

#### ControllerHelper Refactor (all controllers)
- [x] Replaced `AbstractController` with `ControllerHelper` injection in all 11 controllers: DashboardController, SearchController, SettingsController, AiStatsController, SourceController, AlertRuleController, NotificationLogController, DigestController, LoginController, LogoutController, ReadStateController
- [x] Fixed: `SealBundle` missing from `config/bundles.php` (was causing PHP container crash loop)
- [x] Fixed: SEAL Loupe marshaller requires string for DateTime — `$article->getFetchedAt()->format(DateTimeInterface::ATOM)`

#### Phase 11D.2: Mark All Read
- [x] Created `src/User/Controller/MarkAllReadController.php` — POST `/articles/read-all`, DQL subquery to find unread articles, bulk insert UserArticleRead

#### Phase 11D.4: Unread Filter
- [x] Added `#[MapQueryParameter] bool $unreadOnly = false` to DashboardController
- [x] Unread filter uses NOT EXISTS DQL subquery against UserArticleRead
- [x] `unreadOnly` passed to template
- [x] Template: toggle link (Show All / Unread Only) + "Mark All Read" form button before article feed

#### Phase 11E.1: Functional Tests
- [x] Created `tests/Functional/Controller/PageSmokeTest.php` — `#[DataProvider]` tests 8 protected routes redirect to `/login`, login returns 200

#### Phase 11E.2: E2E Tests (Panther)
- [x] Created `tests/E2E/DashboardE2ETest.php` — login + dashboard, category filter, theme toggle
- [x] Created `tests/E2E/SearchE2ETest.php` — search with no results
- [x] Created `tests/E2E/NavigationE2ETest.php` — all nav links return pages with `.navbar`

#### Phase 11E.3: Golden Path Integration Test
- [x] Created `tests/Integration/GoldenPathTest.php` — KernelTestCase, transactional rollback: seed → article → enrich → score → alert match

#### phpunit.dist.xml
- [x] Added `functional` and `e2e` testsuites

### Quality
- ECS: OK (auto-fixed import order, array formatting)
- Rector: clean
- PHPStan: 0 errors
- PHPUnit unit suite: 95 tests pass
- PHPUnit integration suite: 11 tests pass
- PHPUnit functional suite: 9 tests pass (8 data-provider cases + login page)

## Session 11 — 2026-04-04

### Completed

#### Invokable Controller Refactor
- [x] `DashboardController`: removed `Request`, replaced with `#[MapQueryParameter]` for `$category`, `$page`, `$fragment`; AJAX detection via `$fragment !== null`; renamed `index()` → `__invoke()`
- [x] `SearchController`: removed `Request`, replaced with `#[MapQueryParameter]` for `$q` and `$category`; renamed `index()` → `__invoke()`
- [x] `LoginController`: split — extracted logout action; renamed `login()` → `__invoke()`
- [x] `LogoutController` (new): single `__invoke(): never` with `#[Route('/logout', name: 'app_logout')]`
- [x] `ReadStateController`: renamed `markAsRead()` → `__invoke()`
- [x] `DigestController`, `AlertRuleController`, `NotificationLogController`, `AiStatsController`, `SettingsController`, `SourceController`: renamed `index()` → `__invoke()`
- [x] Deleted `config/routes/security.php` (was importing `security.route_loader.logout`; logout route now defined via attribute)
- [x] Verified no templates use `controller()` Twig function

### Quality
- ECS: OK (auto-fixed attribute formatting on `#[MapQueryParameter]` args)
- Rector: clean
- PHPStan: 0 errors
- PHPUnit unit suite: 95 tests pass
