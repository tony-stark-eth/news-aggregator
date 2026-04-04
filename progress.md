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

### Current Status
Phase 1 in progress. 3 of 15 tasks complete (1.4, 1.5, 1.13). Next: clone dunglas/symfony-docker (1.1).

### Blockers
- None

### Next Steps
- Phase 1.1: Clone dunglas/symfony-docker into news-aggregator/
- Phase 1.2: Build & up to scaffold Symfony 8.0.x
- Phase 1.3: Verify Symfony version
