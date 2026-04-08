# Review Request -- Async Two-Phase Enrichment + Mercure Real-Time Updates (#114 + #119)

*Written by senior-developer. Read by qa-specialist.*

Ready for Review: YES

---

## What Was Built

Two-phase enrichment pipeline that decouples AI enrichment from article fetching. Phase 1 (synchronous in FetchSourceHandler) applies rule-based categorization, summarization, keyword extraction, and scoring -- articles appear instantly. Phase 2 (async via `async_enrich` Messenger transport) runs full AI enrichment + translation via the existing ArticleEnrichmentService. Mercure SSE push updates article cards in-place when AI completes and shows a "new articles" banner on the dashboard.

## Files Changed

### New Files
| File | Change |
|------|--------|
| `src/Article/ValueObject/EnrichmentStatus.php` | `Pending`/`Complete` string-backed enum |
| `src/Enrichment/Service/RuleBasedEnrichmentServiceInterface.php` | Interface for Phase 1 enrichment |
| `src/Enrichment/Service/RuleBasedEnrichmentService.php` | Rule-based categorization + summarization + keywords + scoring (no AI, no translation) |
| `src/Article/Message/EnrichArticleMessage.php` | Async message DTO with `articleId` |
| `src/Article/MessageHandler/EnrichArticleHandler.php` | Phase 2 handler: idempotency check, AI enrichment, Mercure publish |
| `src/Article/Mercure/MercurePublisherServiceInterface.php` | Interface for Mercure publishing |
| `src/Article/Mercure/MercurePublisherService.php` | Real impl wrapping `HubInterface`, catches + logs exceptions |
| `src/Article/Mercure/NullMercurePublisherService.php` | No-op fallback |
| `src/Article/Mercure/ArticleCreatedMercureSubscriber.php` | EventSubscriber on `ArticleCreated` |
| `assets/ts/mercure-updates.ts` | Native EventSource SSE client, in-place card updates, "new articles" banner |
| `config/packages/mercure.php` | Mercure bundle config (PHP format, replaces recipe YAML) |
| `migrations/Version20260406202725.php` | `enrichment_status VARCHAR(20) DEFAULT NULL` on `article` |

### Modified Files
| File | Change |
|------|--------|
| `src/Article/Entity/Article.php` | Added `enrichmentStatus` nullable field + getter/setter |
| `src/Article/MessageHandler/FetchSourceHandler.php` | `ArticleEnrichmentServiceInterface` -> `RuleBasedEnrichmentServiceInterface` + `MessageBusInterface`; sets `Pending` status; dispatches `EnrichArticleMessage` |
| `config/packages/messenger.php` | `async_enrich` transport (doctrine queue, 2 retries, 5s/3x/60s max); routes `EnrichArticleMessage` |
| `config/services.php` | DI wiring for RuleBasedEnrichmentService + MercurePublisherServiceInterface |
| `config/bundles.php` | Added `MercureBundle` + `declare(strict_types=1)` |
| `compose.yaml` | Removed standalone Mercure container (recipe artifact) |
| `compose.override.yaml` | Added `enrichment-worker` service, removed recipe Mercure service |
| `compose.prod.yaml` | Added `enrichment-worker` service |
| `.env` | Updated Mercure defaults for built-in Caddy hub |
| `templates/base.html.twig` | `<meta name="mercure-url">` tag |
| `templates/components/_article_card.html.twig` | `data-enrichment-status` attribute |
| `templates/dashboard/index.html.twig` | Hidden "new articles" banner |
| `assets/app.js` | Imported `mercure-updates.js` |
| `tests/Architecture/LayerDependencyTest.php` | Excluded `EnrichArticleHandler` from arch rule |
| `tests/Unit/Article/MessageHandler/FetchSourceHandlerTest.php` | Updated constructor, added dispatch test |

### Test Files
| File | Tests |
|------|-------|
| `tests/Unit/Enrichment/Service/RuleBasedEnrichmentServiceTest.php` | 6 tests: category, fallback, summary, skip null, keywords, empty keywords |
| `tests/Unit/Article/MessageHandler/EnrichArticleHandlerTest.php` | 5 tests: enrich+complete, not found, already complete, null legacy, original title |
| `tests/Unit/Article/Mercure/MercurePublisherServiceTest.php` | 6 tests: created, enriched, null ID x2, exception logging, topic verification |
| `tests/Unit/Article/Mercure/ArticleCreatedMercureSubscriberTest.php` | 2 tests: subscribed events, publish call |
| `tests/Unit/Article/Mercure/NullMercurePublisherServiceTest.php` | 2 tests: no-op methods |

### Doc Files
| File | Change |
|------|--------|
| `CLAUDE.md` | Domain overview, Mercure env vars, two-phase enrichment docs |
| `CHANGELOG.md` | Feature entries |

## Quality Gates

- [x] `make ecs` -- clean
- [x] `make phpstan` -- clean (0 errors)
- [x] `make rector` -- clean (dry-run no changes)
- [x] `make test-unit` -- 583 tests, 1496 assertions, all pass
- [ ] `make test-integration` -- NOT RUN (pre-existing pgbouncer/app_test infrastructure issue)

## Design Decisions

1. **Mercure in Article domain**: Architecture rules prevent Shared from depending on Article. Publisher lives at `App\Article\Mercure`.
2. **Null enrichmentStatus = legacy complete**: Existing articles skip Phase 2 processing.
3. **Removed standalone Mercure container**: FrankenPHP/Caddy already has built-in Mercure hub.
4. **ArticleEnrichmentService unchanged**: As specified in brief -- Phase 2 calls the existing service exactly as before.

## Open Questions

1. Integration test database issue (pgbouncer doesn't proxy app_test) is pre-existing -- should I create a separate issue?
2. EnrichArticleHandler depends on Enrichment namespace (excluded from arch rule) -- acceptable pattern?
3. Mercure JWT in dev is effectively unused due to `demo` mode -- acceptable for dev?

## Known Gaps

- Integration tests not verified due to pre-existing infrastructure issue
- No htmx integration (out of scope per brief)
- No per-user Mercure topics (single-user app, out of scope per brief)
