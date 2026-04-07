# CLAUDE.md

## Project

News Aggregator — self-hosted, AI-enhanced RSS/Atom aggregator. Symfony 8.0 + FrankenPHP + PostgreSQL.

See `PITCH.md` for full project overview.

## Planning Archive

Initial development planning files (task plan, progress log, research findings) are archived in `docs/archive/`. These document the full build-out from Phase 1-13 and are kept for historical reference.

## Quick Start

```bash
make up          # Start containers
make quality     # Run all quality checks
make test        # Run all tests
make hooks       # Install git hooks
```

## All Make Targets

### Docker
| Target | Description |
|--------|-------------|
| `make build` | Build Docker images (no cache) |
| `make up` | Start containers (detached, wait for healthy) |
| `make down` | Stop and remove containers |
| `make start` | Build + start |
| `make restart` | Down + up |
| `make logs` | Follow all container logs |
| `make sh` | Shell into PHP container |
| `make worker-logs` | Follow Messenger worker logs |

### Symfony
| Target | Description |
|--------|-------------|
| `make sf c="<cmd>"` | Run any bin/console command |
| `make cc` | Clear Symfony cache |
| `make sf-migrate` | Run Doctrine migrations |

### Code Quality
| Target | Description |
|--------|-------------|
| `make quality` | Run all quality checks (ECS + PHPStan + Rector) |
| `make phpstan` | PHPStan static analysis (level max) |
| `make ecs` | ECS coding standards check |
| `make ecs-fix` | Fix ECS coding standards issues |
| `make rector` | Rector dry-run |
| `make rector-fix` | Apply Rector fixes |

### Testing
| Target | Description |
|--------|-------------|
| `make test` | Run all PHPUnit tests |
| `make test-unit` | Run unit tests only |
| `make test-integration` | Run integration tests only |
| `make infection` | Mutation testing (unit suite, 80/90% MSI) |
| `make coverage` | Generate HTML coverage report |

### TypeScript
| Target | Description |
|--------|-------------|
| `make ts-build` | Compile TypeScript via Bun |
| `make ts-watch` | Watch and compile TypeScript |

### Database
| Target | Description |
|--------|-------------|
| `make db-create` | Create database |
| `make db-drop` | Drop database |
| `make db-reset` | Drop + create + migrate |
| `make export-postgres` | Dump PostgreSQL to `backup/postgres_backup.sql` |
| `make import-postgres` | Restore from backup |

### Git
| Target | Description |
|--------|-------------|
| `make hooks` | Install git hooks from `.githooks/` |

## Domain Overview

```
src/
├── Article/         # Core: articles, scoring, deduplication, content fingerprinting
│   ├── Repository/  # ArticleRepositoryInterface + Doctrine implementation
│   ├── Mercure/     # MercurePublisherService (real + null impl), ArticleCreatedMercureSubscriber
│   ├── Message/     # EnrichArticleMessage, RescoreArticlesMessage
│   └── MessageHandler/ # FetchSourceHandler (Phase 1), EnrichArticleHandler (Phase 2)
├── Enrichment/      # Rule-based + AI categorization/summarization/keywords/translation (decorator pattern)
├── Source/          # Feed management, fetching (laminas-feed), health tracking
│   └── Repository/  # SourceRepositoryInterface + Doctrine implementation
├── Notification/    # Unified alert rules (keyword/AI/both) + Notifier dispatch
│   └── Repository/  # AlertRuleRepositoryInterface, NotificationLogRepositoryInterface
├── Digest/          # Periodic AI-generated editorial summaries
│   └── Repository/  # DigestConfigRepositoryInterface, DigestLogRepositoryInterface
├── User/            # Auth (symfony/security-bundle), per-user read state (UserArticleRead)
│   └── Repository/  # UserRepositoryInterface, UserArticleReadRepositoryInterface
└── Shared/
    ├── AI/          # ModelFailoverPlatform, ModelDiscoveryService, ModelQualityTracker
    ├── Search/      # SEAL + Loupe full-text search (zero infrastructure)
    ├── Entity/      # Category (shared lookup)
    ├── Repository/  # CategoryRepositoryInterface + Doctrine implementation
    ├── ValueObject/ # EnrichmentMethod (cross-domain)
    ├── Scheduler/   # MaintenanceScheduleProvider (daily reindex + cleanup)
    ├── Command/     # app:cleanup, app:search-reindex, app:check-sources, app:process-digests
    ├── Controller/  # DashboardController, HealthController
    └── Twig/        # Extensions, NavigationExtension
```

## AI Integration

- **Two-phase enrichment**: Phase 1 (sync in FetchSourceHandler) applies rule-based categorization, summarization, keywords, scoring. Phase 2 (async via `async_enrich` transport) runs full AI enrichment + translation. Articles appear instantly with rule-based data and upgrade in-place when AI completes.
- **Mercure SSE**: Real-time push via built-in FrankenPHP/Caddy Mercure hub. `MercurePublisherService` publishes article creation and enrichment completion events. Frontend uses native `EventSource` API to update article cards in-place and show "new articles" banner.
- **Primary model**: `openrouter/free` — auto-routes to best available free model, zero maintenance
- **Fallback chain**: `ModelFailoverPlatform` (PlatformInterface decorator) chains free → minimax → glm → gpt-oss → qwen → nemotron → optional paid model
- **Paid fallback**: `OPENROUTER_PAID_FALLBACK_MODEL` env var appends a low-cost paid model (e.g. `google/gemini-2.5-flash-lite`) to the end of the failover chain. Empty by default — pure free tier unless configured.
- **Rule-based fallback**: Always active — AI is an enhancement layer, not a dependency
- **Quality gates**: `AiQualityGateService` — structured output validation, confidence >= 0.7, summary length heuristic
- **Circuit breaker**: `ModelDiscoveryService` — 3 consecutive failures → 24h fallback to DB-persisted model list
- **Keyword extraction**: AI extracts 3-5 entities per article (people, orgs, places), displayed as tags
- **Translation**: Multi-language — translates articles to all configured display languages (`DISPLAY_LANGUAGES`); client-side language selector (EN/DE/FR)
- **Alert fixtures**: YAML-based alert rule definitions, loadable via `app:load-alert-rules`
- **Keyword-first**: Alert rules always run keyword matching first; AI evaluation only on keyword matches (~10-20 calls/day)
- **Auto-reindex**: Doctrine listener indexes articles on persist/update; daily full reindex via maintenance scheduler
- **Model stats**: `app:ai-stats` command shows model quality metrics
- **Blocked models**: `OPENROUTER_BLOCKED_MODELS` env var (comma-separated) for persistent manual overrides

## Key Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `ADMIN_EMAIL` | Admin login email | (required) |
| `ADMIN_PASSWORD_HASH` | Bcrypt/argon2 hash | (required) |
| `OPENROUTER_API_KEY` | OpenRouter API key for AI | (optional — rule-based fallback works without it) |
| `OPENROUTER_BLOCKED_MODELS` | Comma-separated blocked model IDs | (empty) |
| `OPENROUTER_PAID_FALLBACK_MODEL` | Paid model appended to failover chain (e.g. `google/gemini-2.5-flash-lite`) | (empty) |
| `NOTIFIER_CHATTER_DSN` | Notifier transport DSN | (optional — matches logged as `skipped` without it) |
| `FETCH_DEFAULT_INTERVAL_MINUTES` | Default fetch interval | `60` |
| `DISPLAY_LANGUAGES` | Comma-separated display languages (e.g. `en,de,fr`) | `en` |
| `MERCURE_URL` | Internal Mercure hub URL (for publishing) | `https://php/.well-known/mercure` |
| `MERCURE_PUBLIC_URL` | Public Mercure hub URL (for browser SSE) | `https://localhost:8443/.well-known/mercure` |
| `MERCURE_JWT_SECRET` | JWT secret for Mercure publishing | `!ChangeThisMercureHubJWTSecretKey!` |
| `RETENTION_ARTICLES` | Article retention period | `90` |
| `RETENTION_LOGS` | Notification/digest log retention | `30` |
| `DATABASE_URL` | PostgreSQL DSN | (set in compose) |

## Guidelines

- `.claude/coding-php.md` — PHP coding rules (includes scaffolding commands)
- `.claude/coding-typescript.md` — TypeScript conventions
- `.claude/testing.md` — Testing & code quality
- `.claude/architecture.md` — Architecture reference
- `docs/common-patterns.md` — Common patterns and custom make commands

## Specialized Agents

| Agent | File | Purpose |
|-------|------|---------|
| `architect` | `.claude/agents/architect.md` | Architecture decisions, pattern selection, bounded contexts |
| `product-owner` | `.claude/agents/product-owner.md` | Feature prioritization, requirements, user stories |
| `senior-developer` | `.claude/agents/senior-developer.md` | Implementation, PHP+TypeScript, Symfony expertise |
| `qa-specialist` | `.claude/agents/qa-specialist.md` | Testing, code review, quality gates |

## Slash Commands

| Command | Description |
|---------|-------------|
| `/quality` | Run full quality pipeline, iteratively fix until green |
| `/fix-issue <number>` | Fetch GitHub issue, implement, test, PR — end to end |
| `/review` | Review current branch using QA Specialist agent |
| `/browse [--screenshot] <path>` | Browse the running UI via Playwright, return page text |

## Browser Tools (Playwright MCP)

PO and QA agents have access to interactive browser tools via the Playwright MCP server (`.mcp.json`). These support persistent sessions, clicking, scrolling, form filling, and screenshots — unlike `/browse` which is a single-shot text dump.

Key tools: `browser_navigate`, `browser_snapshot`, `browser_click`, `browser_take_screenshot`, `browser_evaluate`, `browser_wait_for`, `browser_fill_form`, `browser_run_code`.

Login required: navigate to the app URL first, then use `browser_fill_form` to log in. Session persists across tool calls.

## Agent Handoff Workflow

For multi-step features, agents communicate through structured files in `.claude/handoff/`:

| File | Writer | Reader | Purpose |
|------|--------|--------|---------|
| `ARCHITECT-BRIEF.md` | architect | senior-developer, qa-specialist | What to build |
| `REVIEW-REQUEST.md` | senior-developer | qa-specialist | What was built |
| `REVIEW-FEEDBACK.md` | qa-specialist | senior-developer, architect | Review verdict |
| `BUILD-LOG.md` | architect | all | Step history + known gaps |
| `SESSION-CHECKPOINT.md` | architect | all | Cross-session resume state |

Flow: architect writes brief → senior-developer builds → qa-specialist reviews → architect deploys.

## Token Discipline — Always Active

```
Is this in a skill or memory?   → Trust it. Skip the file read.
Is this speculative?            → Kill the tool call.
Can calls run in parallel?      → Parallelize them.
Output > 20 lines you won't use → Route to subagent.
About to restate what user said → Delete it.
```

Grep before Read. Never read a whole file to find one thing.

## Workflow Expectations

- Run `make quality` after every code change — do not consider a task complete until it passes
- Run `make test-unit` during development — fast feedback, low token cost
- Run `make test` once before final commit — includes integration/functional tests
- Run `make infection` before submitting PR — catches mutation MSI failures early (saves ~20K tokens per CI fix round)
- One issue per branch — do not bundle unrelated changes
- Scope lock: when you find unrelated issues during work, log them in `docs/todo/` or create a GitHub issue — do not fix inline
- Use `make sf c="make:entity"` to scaffold entities — never write entity + repository files manually
- Always spawn implementation agents in foreground, never background (they need tool approval)

## Hard Rules

- No `DateTime` — use `DateTimeImmutable` via `ClockInterface` only
- No `var_dump` / `dump` / `dd` / `print_r`
- No `empty()` — use explicit checks
- No `ignoreErrors` in phpstan.neon
- No YAML for Symfony config — PHP format only
- No `time()` / `date()` / `strtotime()` — use `ClockInterface`
- Interface-first: all service and repository boundaries defined by interface
- No direct `EntityManagerInterface` in services/handlers — use repository interfaces
- Conventional Commits: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
