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
├── Enrichment/      # Rule-based + AI categorization/summarization/keywords/translation (decorator pattern)
├── Source/          # Feed management, fetching (laminas-feed), health tracking
├── Notification/    # Unified alert rules (keyword/AI/both) + Notifier dispatch
├── Digest/          # Periodic AI-generated editorial summaries
├── User/            # Auth (symfony/security-bundle), per-user read state (UserArticleRead)
└── Shared/
    ├── AI/          # ModelFailoverPlatform, ModelDiscoveryService, ModelQualityTracker
    ├── Search/      # SEAL + Loupe full-text search (zero infrastructure)
    ├── Entity/      # Category (shared lookup)
    ├── ValueObject/ # EnrichmentMethod (cross-domain)
    ├── Scheduler/   # MaintenanceScheduleProvider (daily reindex + cleanup)
    ├── Command/     # app:cleanup, app:search-reindex, app:check-sources, app:process-digests
    ├── Controller/  # DashboardController, HealthController
    └── Twig/        # Extensions, NavigationExtension
```

## AI Integration

- **Primary model**: `openrouter/free` — auto-routes to best available free model, zero maintenance
- **Fallback chain**: `ModelFailoverPlatform` (PlatformInterface decorator) chains free → minimax → glm → gpt-oss → qwen → nemotron
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
| `NOTIFIER_CHATTER_DSN` | Notifier transport DSN | (optional — alerts disabled without it) |
| `FETCH_DEFAULT_INTERVAL_MINUTES` | Default fetch interval | `60` |
| `DISPLAY_LANGUAGES` | Comma-separated display languages (e.g. `en,de,fr`) | `en` |
| `RETENTION_ARTICLES` | Article retention period | `90` |
| `RETENTION_LOGS` | Notification/digest log retention | `30` |
| `DATABASE_URL` | PostgreSQL DSN | (set in compose) |

## Guidelines

- `.claude/coding-php.md` — PHP coding rules
- `.claude/coding-typescript.md` — TypeScript conventions
- `.claude/testing.md` — Testing & code quality
- `.claude/architecture.md` — Architecture reference

## Hard Rules

- No `DateTime` — use `DateTimeImmutable` via `ClockInterface` only
- No `var_dump` / `dump` / `dd` / `print_r`
- No `empty()` — use explicit checks
- No `ignoreErrors` in phpstan.neon
- No YAML for Symfony config — PHP format only
- No `time()` / `date()` / `strtotime()` — use `ClockInterface`
- Interface-first: all service boundaries defined by interface
- Conventional Commits: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
