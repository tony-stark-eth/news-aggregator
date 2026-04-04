# News Aggregator — Progress Log

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
