# Architect Brief

---

## Feature — Async two-phase enrichment + Mercure real-time updates (#114 + #119)

### Context

Currently `FetchSourceHandler` calls `ArticleEnrichmentService::enrich()` synchronously for every article — AI enrichment blocks the entire fetch pipeline (~13.5s per article). This brief decouples AI enrichment from fetching and adds Mercure SSE push so articles appear instantly and upgrade in-place when AI completes.

### Branch: `feat/114-119-async-enrichment-mercure`

---

### Architecture

#### Two-phase enrichment model

**Phase 1 (synchronous, in FetchSourceHandler):**
- Rule-based categorization + summarization + keyword extraction
- Apply source fallback category if rule-based returns null
- Score the article
- Set `enrichmentMethod = RuleBased`, `enrichmentStatus = Pending`
- Flush to DB, dispatch `ArticleCreated` event
- Dispatch `EnrichArticleMessage` to `async_enrich` transport

**Phase 2 (async, in EnrichArticleHandler):**
- Load article by ID
- Skip if `enrichmentStatus` is already `Complete` (idempotency)
- Run full `ArticleEnrichmentService::enrich()` (AI combined + translation + re-score)
- Set `enrichmentStatus = Complete`
- Flush, publish Mercure update

### New Components

#### 1. EnrichmentStatus enum
`src/Article/ValueObject/EnrichmentStatus.php` — `Pending`, `Complete`
Add to Article entity as `?EnrichmentStatus` column (nullable for backward compat).

#### 2. RuleBasedEnrichmentService
New lightweight service for Phase 1. Calls rule-based categorization, summarization, keyword extraction, and scoring. Does NOT call AI or translation.

DI wiring: explicitly bind rule-based implementations (not AI decorators) via `config/services.php`.

#### 3. EnrichArticleMessage + EnrichArticleHandler
- Message: `readonly class EnrichArticleMessage { public int $articleId; }`
- Handler: loads article, checks idempotency, calls `ArticleEnrichmentService::enrich()`, sets status, publishes Mercure

#### 4. FetchSourceHandler changes
- Replace `ArticleEnrichmentServiceInterface` with `RuleBasedEnrichmentService`
- Add `MessageBusInterface`
- In `persistItem()`: rule-based enrich → set pending → save → dispatch EnrichArticleMessage

#### 5. Messenger transport: `async_enrich`
- DSN: `doctrine://default?queue_name=enrich`
- Retry: 2 retries, 5s base delay, 3x multiplier, 60s max
- Routing: `EnrichArticleMessage → async_enrich`

#### 6. Worker config
Add `enrichment-worker` service to compose.override.yaml and compose.prod.yaml:
- Consumes: `async_enrich`
- Needs: DATABASE_URL, OPENROUTER_API_KEY, MERCURE_URL, MERCURE_JWT_SECRET
- Memory: 256M (higher for AI responses)

### Mercure Integration

#### 7. Install symfony/mercure-bundle
`composer require symfony/mercure-bundle`
Config: `config/packages/mercure.php` with `MERCURE_URL` and `MERCURE_JWT_SECRET`

#### 8. MercurePublisherService
Interface + implementation:
- `publishArticleCreated(Article $article)` — topic `/articles`, JSON payload
- `publishEnrichmentComplete(Article $article)` — topic `/articles/{id}/enriched`, JSON payload
- `NullMercurePublisherService` — no-op for environments without Mercure

Both methods catch exceptions and log warnings — never break the pipeline.

#### 9. Where events are published
- `ArticleCreatedMercureSubscriber` — new EventSubscriber, listens to `ArticleCreated`, calls `publishArticleCreated()`
- `EnrichArticleHandler` — calls `publishEnrichmentComplete()` after flush

#### 10. Frontend: `assets/ts/mercure-updates.ts`
- Native `EventSource` API (no new JS deps)
- Mercure URL from `<meta name="mercure-url">` in base.html.twig
- Subscribe to `/articles` → show "X new articles" banner on dashboard
- Subscribe to `/articles/{id}/enriched` for visible articles → update card in-place
- Add `data-article-id` and `data-enrichment-status` to article cards

#### 11. Template changes
- `base.html.twig`: add `<meta name="mercure-url">`
- `_article_card.html.twig`: add data attributes for JS targeting
- `dashboard/index.html.twig`: add hidden "new articles" banner

### Build Order

1. Entity: EnrichmentStatus enum + Article field + migration
2. RuleBasedEnrichmentService + DI wiring + tests
3. EnrichArticleMessage + EnrichArticleHandler + transport config + FetchSourceHandler refactor + tests
4. Install mercure-bundle + config + MercurePublisherService + subscribers + tests
5. Frontend: meta tag + data attributes + TypeScript module + banner
6. Worker compose config
7. `make quality` + `make test` + update CLAUDE.md + CHANGELOG

### Flags

- No changes to existing `ArticleEnrichmentService` or AI services
- Existing articles: `enrichmentStatus = null` → treat as "legacy complete" in handler
- Mercure failures logged and swallowed — never break pipeline
- Anonymous Mercure subscriptions (matching Caddyfile config)
- Frontend uses native EventSource — no new JS dependencies
- EnrichArticleHandler reconstructs FeedItem from Article fields for the enrichment call

### Out of Scope

- htmx integration (evaluated separately — decision pending research)
- Paid model overflow (#115)
- Per-user Mercure topics (single-user app)
- Enrichment retry UI
