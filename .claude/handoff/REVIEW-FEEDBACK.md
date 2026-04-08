# Review Feedback -- Full-Text Article Fetch + C1/C2 Audit Fixes (#151/#152)

*Written by qa-specialist. Read by senior-developer and architect.*

Date: 2026-04-08
Verdict: **APPROVED WITH CONDITIONS**

---

## Quality Gates

| Gate | Result |
|------|--------|
| ECS | Clean |
| PHPStan (level max) | 0 errors |
| Rector (dry-run) | No changes |
| Unit tests | 732 pass, 1946 assertions |
| Integration tests | 1 error (pre-existing SourceRepositoryTest unique constraint, unrelated) |
| Mutation testing | 90% covered MSI (threshold met) |

## Browser Verification

| Test | Result |
|------|--------|
| Dashboard loads, 40 article cards render | PASS |
| Source edit form shows "Full-text fetch" checkbox, checked by default | PASS |
| Toggle off, save, reload -- persists as unchecked | PASS |
| Toggle back on, save -- persists as checked | PASS |
| New source form shows "Full-text fetch" checkbox with helper text | PASS |
| Feed preview template has "Full content detected" indicator | PASS (in template source) |
| Console errors are pre-existing Mercure hostname resolution, not related to this PR | PASS |
| fulltext-worker container defined in compose but not yet started | EXPECTED (needs `docker compose up -d`) |

## Code Review

### Architecture

- Three-phase pipeline is well-structured: Phase 1 (sync rule-based) -> Phase 1.5 (async fulltext) -> Phase 2 (async AI). Each phase is a separate handler with clear responsibility.
- New services follow interface-first pattern: `ArticleContentFetcherServiceInterface`, `ReadabilityExtractorServiceInterface`, `DomainRateLimiterServiceInterface`, `FeedContentAnalyzerServiceInterface`.
- HTML sanitizer config correctly blocks dangerous elements (script, style, iframe, object, embed, form). Sanitized HTML is stored but not rendered raw in any template currently.
- `FetchFullTextHandler` always dispatches `EnrichArticleMessage` regardless of fetch success/failure -- pipeline never blocks.

### C1 Fix (EntityManagerInterface removal)

Verified. `FetchSourceHandler` no longer imports or depends on `EntityManagerInterface`. The `isOpen()` check is abstracted to `ArticleRepositoryInterface::isConnectionHealthy()`.

### C2 Fix (Correlation IDs)

Verified. Correlation IDs are:
- Generated in `FetchSourceHandler::dispatchEnrichMessage` via `bin2hex(random_bytes(16))`
- Propagated through `FetchFullTextMessage` -> `FetchFullTextHandler` -> `EnrichArticleMessage` -> `EnrichArticleHandler`
- Included in all log contexts across all three handlers

### Security

- HTML sanitization via Symfony HtmlSanitizer before storage -- XSS mitigated.
- `contentFullHtml` is stored but never rendered with `|raw` in templates -- no current XSS surface.
- HTTP client uses configurable timeout (default 15s) and custom User-Agent.
- No URL validation on article URLs before fetching (SSRF concern). Mitigated by: self-hosted app, URLs come from curated RSS feeds, not user input. Acceptable risk.

### Test Coverage

- `FetchFullTextHandlerTest`: 8 tests covering success, not found, not pending, global disable, source disable, extraction failure, fetch exception, always-enrich-on-failure.
- `FetchSourceHandlerTest`: Updated with fulltext dispatch tests (11 total).
- `ReadabilityExtractorServiceTest`: 11 tests covering word count boundaries, sanitization, parse failures.
- `ArticleContentFetcherServiceTest`: 5 tests covering success, HTTP errors, timeout config.
- `DomainRateLimiterServiceTest`: 3 tests covering domain extraction, token consumption.
- `FeedContentAnalyzerServiceTest`: 12 tests covering full content detection, truncation markers, empty/null content.

---

## Conditions

### CONDITION 1: Flush before dispatch in FetchSourceHandler

**File**: `src/Article/MessageHandler/FetchSourceHandler.php:169-170`

**Problem**: `dispatchEnrichMessage` sets `FullTextStatus::Pending` on line 169 and dispatches `FetchFullTextMessage` on line 170, but the status is not flushed until line 71 of `__invoke`. With Doctrine transport, the message is immediately visible via direct INSERT. If the fulltext-worker picks up the message before the final flush on line 71 completes, `FetchFullTextHandler` will see `fullTextStatus = null` and skip the article (line 43 guard: `!== FullTextStatus::Pending`).

In practice this race is unlikely with a single worker, but it is a correctness bug under load or if processing is fast.

**Fix**: Move `$article->setFullTextStatus(FullTextStatus::Pending)` to before the `save($article, flush: true)` call on line 137, so it is persisted as part of the initial flush. Or add a `$this->articleRepository->flush()` after setting the status in `dispatchEnrichMessage`.

### CONDITION 2: DomainRateLimiterService naming vs behavior mismatch

**File**: `src/Article/Service/DomainRateLimiterService.php:22`

**Problem**: Method `waitForDomain` does not wait. It calls `ensureAccepted()` which throws `RateLimitExceededException` immediately when the limit is exceeded. The exception is caught by `FetchFullTextHandler`'s `catch (\Throwable)` on line 96, marking the article as permanently `Failed`.

With default config (2 req/5s per domain), rapid sequential processing of same-domain articles will cause failures rather than throttling.

**Fix**: Either rename to `consumeForDomain` to match the actual behavior, or change to `$limiter->reserve()->wait()` to actually wait for a token (which is what the interface contract and method name imply). If using `reserve()->wait()`, remove the `@throws` from the interface.

---

## Escalate to Architect

None. Both conditions are implementation fixes within the developer's scope.
