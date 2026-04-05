# News Aggregator — Findings

## Research & Discoveries

### dunglas/symfony-docker (2026-04-04)
- No tagged releases — always use latest `main` branch
- FrankenPHP + Caddy in a single container
- Automatic HTTPS (including dev), HTTP/3
- Mercure hub built-in (could use for live feed updates later)
- PostgreSQL preconfigured via DATABASE_URL
- Dev Container / Codespaces support
- Multi-stage Dockerfile: `frankenphp_base` → `frankenphp_dev` (Xdebug) → `frankenphp_prod` (rootless, worker mode)
- Setup: `docker compose build --no-cache && docker compose up --pull always -d --wait`
- compose.prod.yaml: switches to `frankenphp_prod` target, expects secrets via env vars

### Symfony 8.0 (2026-04-04)
- Latest stable: **v8.0.8**
- Requires **PHP 8.4** minimum
- Constructor extractor enabled by default
- Updated HTTP client requirements (amphp/http-client 5.3.2+)
- No 8.1 released yet

### Symfony AI Bundle v0.6.0 (2026-04-04)
- Package: `symfony/ai-bundle` (part of `symfony/ai` monorepo)
- Sub-packages: `symfony/ai-platform`, `symfony/ai-agent`, `symfony/ai-store`
- **Generic Platform bridge** for OpenRouter/LiteLLM (OpenAI-compatible API):
  ```yaml
  ai:
      platform:
          generic:
              openrouter:
                  base_url: '%env(OPENROUTER_BASE_URL)%'
                  api_key: '%env(OPENROUTER_API_KEY)%'
                  model_catalog: 'App\AI\OpenRouterModelCatalog'
  ```
- **FailoverPlatform** (`Symfony\AI\Platform\Bridge\Failover\FailoverPlatform`):
  - Chains multiple platforms, auto-fallback on failure
  - Integrates with Symfony RateLimiter component
  - Config: list platforms in order, attach rate limiter with sliding window policy
- ModelCatalog service defines available models + their capabilities (text I/O, streaming, structured output, etc.)

### OpenRouter Free Models (2026-04-04)
- Free tier models available (rate-limited but no cost):
  - google/gemma-3 (fast, decent quality)
  - meta-llama/llama-4-scout (good reasoning)
  - mistralai/mistral-small (efficient)
- All support OpenAI-compatible chat completions API
- API key required (free registration at openrouter.ai)
- Rate limits vary by model — FailoverPlatform handles this gracefully

### template-symfony-sveltekit backend patterns (2026-04-04)
- **Domain-based src/ structure**: `src/{Feature}/{Controller,Entity,Service,...}` + `src/Shared/`
- **PHPStan level max** with 10 extensions: strict-rules, shipmonk (~40 extra rules), cognitive-complexity (max 8/method, 50/class), type-coverage (100% all categories), phpat, symfony, doctrine, phpunit, deprecation-rules, voku
- **Bleeding edge PHPStan**: checkUninitializedProperties, checkImplicitMixed, checkBenevolentUnionTypes, reportPossiblyNonexistentGeneralArrayOffset. Zero ignoreErrors.
- **ShipMonk bans**: var_dump, dump, dd, print_r, DateTime::__construct (use DateTimeImmutable)
- **ECS**: PSR-12 + common + strict + cleanCode sets
- **Rector**: PHP 8.4, Symfony 8, Doctrine, PHPUnit, CodeQuality, DeadCode, EarlyReturn, TypeDeclaration. Carbon for date functions.
- **Infection**: 80% MSI, 90% covered MSI. Excludes Entity, Kernel, Controller, Command.
- **PHPat architecture tests**: LayerDependencyTest (services !→ controllers), NamingConventionTest (exceptions extend RuntimeException)
- **CaptainHook**: pre-commit (ECS + PHPStan on staged), commit-msg (conventional commits regex), pre-push (unit tests)
- **PHPUnit 13**: unit + integration suites, PCOV coverage, random order, #[CoversClass] required, createStub over createMock
- **PgBouncer**: transaction mode for web, direct connection for Messenger worker (LISTEN/NOTIFY)
- **Messenger**: doctrine transport, auto_setup, use_notify, max 3 retries, exponential backoff
- **Coding rules**: max 20 lines/method, max 3 params, max ~150 lines/class, max 5 deps. Immutable by default. Early returns. find* nullable, get* throws.

### Ember v1.0.1 (2026-04-04)
- **NOT an error tracker** — terminal dashboard for Caddy/FrankenPHP metrics
- Shows: RPS, latency P50/P90/P95/P99, HTTP status distribution, CPU, memory
- FrankenPHP mode: per-thread monitoring, worker queue status, crash tracking
- Prometheus export + headless daemon mode available
- Install methods: brew, `go install`, **Docker image** (chosen)
- Setup: `ember init` (configures Caddy metrics endpoint) → `ember` (launches TUI)
- No database, no web UI — reads live from Caddy admin API

### Host Port Map (2026-04-04)
Occupied: 21, 22, 53, 80, 443, 631, 1883, 3000, 3389, 4000, 4431, 4444, 5173, 5432, 7575, 8000, 8025, 8080, 8088, 8090, 8123, 9443, 18554, 18555, 27017, 32400, 32401, 32600, 32768, 32769, 37425, 44425, 46383

**Selected for news-aggregator: 8443 (HTTPS) / 8180 (HTTP)**

### RSS Feed Availability (verified 2026-04-04)

**Confirmed public:**
- Tagesschau: `https://www.tagesschau.de/xml/rss2`
- ZDF heute: `https://www.zdf.de/rss/zdf/nachrichten`
- BBC News: `https://feeds.bbci.co.uk/news/rss.xml`
- Der Spiegel: `https://www.spiegel.de/schlagzeilen/tops/index.rss`
- Handelsblatt: `https://www.handelsblatt.com/contentexport/feed/top`
- Heise: `https://www.heise.de/rss/heise-atom.xml`
- Ars Technica: `https://feeds.arstechnica.com/arstechnica/index`
- The Verge: `https://www.theverge.com/rss/index.xml`
- Hacker News (hnrss): `https://hnrss.org/frontpage`
- Nature: `https://www.nature.com/nature.rss`
- Kicker: `https://rss.kicker.de/news/aktuell`
- ESPN: `https://www.espn.com/espn/rss/news`

**Replaced (paywalled/unavailable):**
- Bloomberg → MarketWatch: `https://feeds.content.dowjones.io/public/rss/mw_topstories`
- Financial Times → Reuters Business: `https://www.reutersagency.com/feed/?best-topics=business-finance`
- Added CNBC: `https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=100003114`

### Feed Parsing (2026-04-04, updated)
- **No Symfony component exists** for RSS/Atom feed parsing
- `symfony/dom-crawler` can parse XML but requires manual RSS/Atom handling — not a feed parser
- **Rejected**: `debril/rss-atom-bundle` (138 stars, dead 2 years), `debril/feed-io` (archived/EOL July 2025)
- **Rejected**: `simplepie` (1,573 stars but legacy API targeting PHP 7.2+, heavy, own HTTP/cache layer)
- **`laminas/laminas-feed 2.26.x`** (chosen):
  - Last release: v2.26.1 (March 3, 2026 — 1 month ago)
  - Explicit PHP 8.4 support (`~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0`)
  - Backed by Laminas org (not a single maintainer)
  - Framework-agnostic — register as Symfony service, no bundle needed
  - Supports RSS 1.0/2.0 + Atom reading and writing
  - Clean modern API

### Git Hooks (2026-04-04)
- tcg-scanner has NO git hooks installed — relies entirely on CI
- **Decision**: Create custom shell scripts in `.githooks/` directory
  - `pre-commit`: ECS check + PHPStan + Rector --dry-run (staged PHP files only)
  - `commit-msg`: conventional commits regex validation
  - Installed via `git config core.hooksPath .githooks`
  - No external dependency (no CaptainHook/Husky)

### Coverage & Mutation Testing Strategy (2026-04-04)
- **Xdebug path coverage** (not PCOV) — more thorough, tracks execution paths through branches
- Single coverage engine shared between PHPUnit and Infection
- Infection mutation testing runs against **unit test suite only** (integration tests too slow)
- 80% MSI minimum, 90% covered MSI minimum

### Symfony Config Format (2026-04-04)
- All Symfony config in PHP format (`.php` files in `config/`)
- No YAML except where absolutely required by third-party tools
- Uses `return static function (ContainerConfigurator $container): void {}` or Symfony 8 attribute-based config

### Notification System Design (2026-04-04)
- **Symfony Notifier** is transport-agnostic: application code uses `NotifierInterface`, transport configured via DSN env vars
- Channel-specific DSNs: `NOTIFIER_CHATTER_DSN` (chat: Slack, Discord, Telegram), `NOTIFIER_TEXTER_DSN` (SMS), `NOTIFIER_EMAIL_DSN` (email)
- Pushover: uses `symfony/pushover-notifier` package, DSN format: `pushover://USER_KEY:APP_TOKEN@default`
- User installs desired transport package at deploy time — app code stays transport-agnostic
- **Watchlists**: general-purpose keyword groups (not limited to any specific domain)
  - Entity: name, keywords (JSON array), urgency (enum: low/medium/high), enabled, timestamps
  - Matching: case-insensitive substring of any keyword against article title + content/summary
  - Single article can trigger multiple watchlists
  - Dispatched async via Messenger → NotificationLog tracks delivery

### Rule-Based Fallback Strategy (2026-04-04)
- **Problem**: OpenRouter free models are unreliable (rate limits, outages, model deprecation)
- **Solution**: Rule-based services as the foundation, AI as a decorator/enhancement layer
- **RuleBasedCategorizationService**: keyword matching against title + content, terms derived from source category + domain-specific word lists
- **RuleBasedSummarizationService**: extract first 2 sentences of content (HTML stripped, normalized)
- **Processing cascade**: AI (Gemma → Llama → Mistral) → rule-based fallback → article always saved
- **EnrichmentMethod enum**: AiGemma, AiLlama, AiMistral, RuleBased — tracked on Article entity
- **Sequencing**: rule-based implemented in Phase 5 (before AI), so system works end-to-end without external APIs

### Time Handling Strategy (2026-04-04)
- **symfony/clock** chosen over Carbon: PSR-20 `ClockInterface`, ships with Symfony 8, no extra dependency
  - `$clock->now()` returns `DateTimeImmutable` (already enforced by ShipMonk `DateTime::__construct` ban)
  - `MockClock` in tests: `$clock = new MockClock('2026-04-04 12:00:00')`, `$clock->modify('+1 hour')`
  - Additional ShipMonk bans: `time()`, `date()`, `strtotime()` — forces all time access through ClockInterface
- **UTC storage**: Doctrine `datetime_immutable` type, all entities store UTC. No timezone ambiguity in DB.
- **Browser-local display**: SSR Twig renders `<time datetime="ISO8601">` tags. Tiny vanilla JS snippet uses `Intl.DateTimeFormat` with browser's timezone to localize on page load. Relative time for recent articles ("5 min ago"), absolute for older.
- Carbon rejected: adds Laravel-ecosystem dependency, Symfony already provides everything needed natively

### OpenRouter Model Strategy (2026-04-04)
- **`/api/v1/models` endpoint is public** — no API key needed to list models
- Free models identified by `pricing.prompt === "0" && pricing.completion === "0"`, ID ends with `:free`
- **`openrouter/free` auto-router**: send requests with model ID `openrouter/free`, OpenRouter picks best available free model automatically. Zero maintenance.
- Rate limits: 20 req/min, 50 req/day without credits; 1000/day with $10+ balance
- No server-side free filter — must filter client-side after fetching all models
- Query params available: `category`, `supported_parameters` (e.g. `tools`), `output_modalities`
- Key fields per model: `id`, `context_length`, `pricing.{prompt,completion}`, `supported_parameters`, `architecture.input_modalities`
- **Quality gates needed**: `openrouter/free` may route to weak models. Mitigated by:
  1. Structured output validation (JSON schema)
  2. Confidence threshold >= 0.7
  3. Summary length heuristics (20-500 chars, no title repeat)
  4. Model quality tracking over time (acceptance/rejection rates)
  5. Minimum context_length >= 8192 filter for dynamic discovery
  6. Optional model pinning via `OPENROUTER_PREFERRED_MODELS` env var
- Example free models (as of 2026-04-04): qwen/qwen3.6-plus:free (1M ctx), nvidia/nemotron-3-super-120b-a12b:free (256K ctx), stepfun/step-3.5-flash:free (256K ctx), minimax/minimax-m2.5:free (196K ctx)

### Reviewer Feedback & Design Changes (2026-04-04)

**Reviewer 1 — Merge Watchlists + Alert Profiles (accepted)**:
- Problem: Two parallel notification paths (Watchlists: keyword, Alert Profiles: AI) with identical output (SendNotificationMessage, cooldown, NotificationLog). Maintenance overhead.
- Solution: Unified `AlertRule` entity with `type` enum (keyword/ai/both). Keyword matching is always step 1. AI evaluation is step 2, only runs on keyword matches.
- Result: One entity, one pipeline, one UI page. AI evaluation becomes the enrichment layer of a keyword rule, not a separate system.

**Reviewer 1 — Dynamic Scheduler from DB (accepted)**:
- Problem: Symfony Scheduler works with fixed schedules known at compile time. Dynamic cron from DB entities requires custom ScheduleProvider — non-trivial.
- Solution: Fixed-schedule `app:process-digests` command runs every 5 minutes. Checks each DigestConfig's cron string against last_run_at. If due, dispatches GenerateDigestMessage. Simple, testable, no Scheduler edge cases.

**Reviewer 1 — ModelQualityTracker auto-blocklist (accepted)**:
- Problem: Single-user server, free models rotate fast. Data basis too thin for statistical decisions (model sees ~20 requests before disappearing).
- Solution: Keep stats command for visibility (`app:ai-model-stats`). Drop auto-blocklist. Manual `OPENROUTER_BLOCKED_MODELS` env var for persistently bad models.

**Reviewer 2 — AI call volume optimization (accepted)**:
- Problem: 16 feeds × ~50 articles/day × 5 alert profiles = 250 AI calls/day. Free model rate limits (20 req/min, 50/day) will throttle.
- Solution: Keyword match first (step 1, free), AI evaluation only on matches (step 2, ~10-20 calls/day). Aligns with unified AlertRule design.

**Reviewer 2 — ModelDiscovery circuit breaker (accepted)**:
- Problem: `/api/v1/models` can fail. 1h cache helps but doesn't survive container restarts or prolonged outages.
- Solution: Circuit breaker (3 failures → stop for 24h). Persist last successful model list to DB. Fresh container with down endpoint still has fallback models.

**Reviewer 2 — timeago.js dedicated module (accepted)**:
- Problem: Inline JS snippet won't auto-update relative times, gets messy with DST/cross-day edge cases.
- Solution: `assets/js/timeago.js` module with `setInterval` (60s), `Intl.DateTimeFormat` + `Intl.RelativeTimeFormat`, handles DST transitions and cross-day boundaries.

### Digest & Alert Design (2026-04-04, updated after review)
- **Unified AlertRule system** (replaces separate Watchlists + Alert Profiles):
  - Single entity with type enum: keyword / ai / both
  - keyword: keyword match → instant notification (fast, free)
  - ai: keyword match first → AI evaluation on matches only → notify if severity >= threshold (~10-20 AI calls/day vs 250)
  - both: keyword match → instant notify + AI evaluation → enriched follow-up if significant
  - Fields: name, type, keywords[], context_prompt (for AI), urgency, severity_threshold (for AI), cooldown_minutes, categories, enabled
- **Periodic Digest** (separate domain):
  - DigestConfig: name, cron schedule, categories, article limit, last_run_at, enabled
  - `app:process-digests` command runs every 5 min (fixed Scheduler schedule), checks cron + last_run_at for each config
  - AI generates per-category editorial summary + takeaways + risk flags
  - Rule-based fallback: article titles with first-sentence excerpts
  - Delivered via existing Notifier infrastructure
- **Two notification systems** (down from three):
  1. AlertRules — real-time, keyword + optional AI evaluation (Phase 9)
  2. Digests — scheduled editorial summary (Phase 10)

### Full-Text Search — SEAL + Loupe (2026-04-04)
- **SEAL** (Search Engine Abstraction Layer): `cmsig/seal-symfony-bundle` v0.12.x
  - Unified API across 9 backends (Elasticsearch, Meilisearch, Loupe, Algolia, etc.)
  - Symfony bundle with autowiring
  - Active: v0.12.9 released 2026-03-01, commits through 2026-03-26, 433 stars
  - No native PostgreSQL full-text adapter — requires dedicated search engine
- **Loupe adapter**: `cmsig/seal-loupe-adapter` v0.12.x
  - SQLite-based, zero-infrastructure, runs in-process
  - Perfect for single-user homeserver (no extra container/daemon)
  - Can swap to Meilisearch later if scaling needed (just change adapter + config)
- **Interface-first**: ArticleSearchService interface → SEAL implementation. Decoupled from specific backend.

### Frontend Design Decisions (2026-04-04, updated after review)
- **Layout**: top navbar (sticky) + collapsible sidebar (desktop). Mobile: hamburger → drawer
- **Theme**: DaisyUI `night` (dark, default) / `winter` (light) with toggle, `localStorage` persistence. DaisyUI version-pinned on CDN.
- **TypeScript**: plain TS compiled via Bun (`bun build`), served by AssetMapper. No Node/npm, no Webpack/Encore, no Stimulus/Turbo
- **Infinite scroll**: `IntersectionObserver` on sentinel element, `fetch()` for next page
- **Search**: SEAL/Loupe full-text, search bar in navbar, results as article cards
- **Read state**: `UserArticleRead` join entity (per-user, multi-user ready), fire-and-forget POST on link click, visual dimming, "unread only" filter default
- **Empty states**: first-boot onboarding → "Configure sources to get started"
- **TypeScript modules**: `timeago.ts`, `infinite-scroll.ts`, `theme-toggle.ts`, `mark-as-read.ts`
- **Forms**: Symfony Form component for all forms (automatic CSRF, validation, type safety)
- **Source health**: visual indicator (green/yellow/red) based on SourceHealth enum

### Architecture Review Outcomes (2026-04-04)
- **Category** demoted from bounded context → `Shared/Entity/` (too thin: just entity + repository)
- **Search** demoted from bounded context → `Shared/Search/` (infrastructure wrapper, no domain logic)
- **AI infrastructure** extracted from Article → `Shared/AI/` (cross-cutting: used by Enrichment, Notification, Digest)
- **Enrichment** extracted from Article → own bounded context (rule-based + AI decorator services)
- **User** domain added: User entity (basic auth), UserArticleRead (per-user read state, multi-user ready)
- **Article.read_at removed** → replaced by UserArticleRead join entity (won't break on multi-user)
- **Source entity** extended: error_count, last_error_message, health_status (SourceHealth enum)
- **Data retention**: `app:cleanup` command with configurable retention periods via env vars
- **Feed error handling**: auto-disable after 5 consecutive failures, health dashboard
- **Auth**: symfony/security-bundle, single admin user via env vars, session-based
- **CSRF**: Symfony Form component for all forms (automatic)
- **GHCR publishing**: Docker image published on release for `docker pull` installation

### Pinned Dependency Versions (2026-04-04)
| Package | Latest | Source |
|---------|--------|--------|
| symfony/symfony | 8.0.8 | gh releases |
| symfony/ai-bundle | 0.6.0 | gh releases |
| phpstan/phpstan | 2.1.46 | gh releases |
| symplify/easy-coding-standard | 13.0.0 | gh releases |
| rector/rector | 2.4.0 | gh releases |
| infection/infection | 0.32.6 | gh releases |
| phpat/phpat | 0.12.4 | gh releases |
| phpunit/phpunit | 13.1.0 | gh releases |
| laminas/laminas-feed | 2.26.1 | gh releases |
| cmsig/seal-symfony-bundle | 0.12.9 | gh releases |
| cmsig/seal-loupe-adapter | 0.12.x | gh releases |
| symfony/notifier | 8.0.x | ships with Symfony |
| symfony/panther | latest | E2E browser tests |
| ember | 1.0.1 | gh releases |

### Symfony AI Platform API (2026-04-04)
- `Platform::invoke(model, input)` requires `MessageBag` as input, NOT raw string
- Correct: `$platform->invoke('openrouter/auto', new MessageBag(Message::ofUser($prompt)))`
- Incorrect: `$platform->invoke('openrouter/auto', $prompt)` → "Payload must be an array"
- `->asText()` on the DeferredResult to get the response string
- Model `openrouter/auto` works and routes to free models (Gemini via BYOK)
- Rate: ~0.5-1s per call, 20 req/min free tier limit
- Smoke test verified: 127/127 articles AI-enriched with fresh cache

### AI Enrichment Performance (2026-04-04)
- **100% AI enrichment rate** when OpenRouter is available and cache is fresh
- Per-article: 2 AI calls (categorize + summarize) × ~0.5-1s = ~1-2s per article
- Per-source (~15 articles): ~15-30s total
- 16 sources sequentially via Messenger: ~4-8 minutes total
- Free model routing confirmed: `openrouter/auto` → no credits consumed
- Fallback to rule-based is seamless when AI fails (rate limits, timeouts)

### CI/CD & Maintenance Strategy (2026-04-04)
- **Docker-based CI**: All GitHub Actions jobs run inside Docker Compose (same containers as local dev). No "works on CI but not locally" drift.
- **Parallelized jobs**: Static analysis (ECS, PHPStan, Rector) run in parallel — no DB needed, independent. Tests run sequentially after (need DB).
- **E2E via Symfony Panther**: Browser tests for critical user flows (dashboard, source CRUD, watchlist CRUD, notification log). Runs against full Docker Compose stack in CI.
- **Scheduled security pipeline** (weekly cron): `composer audit`, `symfony security:check`, `docker scout cves`. Notify on failure.
- **Dependabot**: Composer (weekly), Docker base images (weekly), GitHub Actions (monthly). Group minor/patch into single PRs.
- **Open-source readiness**: MIT license, README with badges, CONTRIBUTING.md, .env.example, issue/PR templates, SECURITY.md. Repo starts private, ready to flip public.
