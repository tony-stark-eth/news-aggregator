# Architecture Reference

## Docker Services

| Service | Image | Purpose | Port |
|---------|-------|---------|------|
| php | frankenphp (dev) | Web server + PHP runtime | 8443 (HTTPS), 8180 (HTTP) |
| database | postgres:17-alpine | Primary datastore | 5432 (internal) |
| pgbouncer | edoburu/pgbouncer | Connection pooling (transaction mode) | 6432 (internal) |
| worker | frankenphp (dev) | Messenger async consumer | - |

## Database Connections

- **Web requests** -> PgBouncer (port 6432, transaction pooling) via `DATABASE_URL`
- **Messenger worker** -> Direct PostgreSQL (port 5432) via `DATABASE_DIRECT_URL` (needs LISTEN/NOTIFY)

## Domain Boundaries

| Domain | Responsibility |
|--------|---------------|
| Article | Core articles, scoring, deduplication |
| Source | Feed management, fetching, health tracking |
| Enrichment | Rule-based + AI categorization/summarization |
| Notification | Unified alert rules, notification dispatch |
| Digest | Periodic AI-generated editorial summaries |
| User | Authentication, per-user read state |
| Shared | AI infra, search, categories, cross-cutting commands |

## Key Design Decisions

- **Global dedup**: Articles are shared across users. Same article = one record. Read state is per-user via `UserArticleRead`.
- **Multi-user ready**: MVP is single-user, but entities with user-scoped data have `user_id` FK from the start.
- **AI as decorator**: Rule-based services implement the interface. AI services wrap them and fall back to rule-based on failure.
- **Interface-first**: All service boundaries defined by interface. Concrete implementations wired via Symfony DI.

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
| `ADMIN_EMAIL` | Admin login email | `admin@example.com` |
| `ADMIN_PASSWORD_HASH` | Bcrypt hash | (must set) |
| `OPENROUTER_API_KEY` | OpenRouter API key | (optional) |
| `OPENROUTER_BASE_URL` | OpenRouter base URL | `https://openrouter.ai/api/v1` |
| `OPENROUTER_BLOCKED_MODELS` | Comma-separated blocked model IDs | (empty) |
| `NOTIFIER_CHATTER_DSN` | Notification transport DSN | `null://null` |
| `RETENTION_ARTICLES` | Article retention (days) | `90` |
| `RETENTION_LOGS` | Log retention (days) | `30` |

## Makefile Targets

Run `make help` for the full list. Key targets:

- `make up` / `make down` - Docker lifecycle
- `make quality` - All quality checks
- `make test` - All tests
- `make sf c="..."` - Symfony console commands
- `make hooks` - Install git hooks
