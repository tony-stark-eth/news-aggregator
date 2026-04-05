# System Architecture

## Overview

News Aggregator is a self-hosted Symfony 8 application running inside Docker Compose. The system aggregates RSS/Atom feeds, enriches articles with AI, dispatches notifications, and generates periodic digests.

```mermaid
graph TB
    subgraph External
        FEEDS[RSS/Atom Feeds]
        OPENROUTER[OpenRouter API\nfree models]
        NOTIFIER[Notifier Transports\nPushover / Telegram / Slack / ...]
    end

    subgraph Docker Compose
        subgraph FrankenPHP
            WEB[Web Process\nHTTPS :8443]
            WORKER[Messenger Worker\nasync jobs]
        end

        subgraph PostgreSQL 17
            DB[(app database)]
            TESTDB[(app_test database)]
        end

        PGBOUNCER[PgBouncer\ntransaction pooling]
        LOUPE[(Loupe / SQLite\nfull-text search)]
        EMBER[Ember TUI\nmetrics dashboard]
    end

    subgraph Domains
        ARTICLE[Article\nscoring · dedup]
        SOURCE[Source\nfeed health]
        ENRICHMENT[Enrichment\ncategorize · summarize]
        NOTIFICATION[Notification\nalert rules]
        DIGEST[Digest\nperiodic summaries]
        USER[User\nauth · read state]
        SHARED_AI[Shared/AI\nModelFailoverPlatform\nModelDiscovery]
        SHARED_SEARCH[Shared/Search\nSEAL + Loupe]
    end

    FEEDS -->|HTTP fetch| SOURCE
    SOURCE -->|FetchSourceMessage| WORKER
    WORKER -->|persist| ARTICLE
    ARTICLE -->|EnrichmentMessage| ENRICHMENT
    ENRICHMENT -->|rule-based fallback| ARTICLE
    ENRICHMENT -->|AI call| SHARED_AI
    SHARED_AI -->|OpenAI-compat API| OPENROUTER
    SHARED_AI -->|fallback chain| OPENROUTER
    ARTICLE -->|index on persist| SHARED_SEARCH
    SHARED_SEARCH --- LOUPE
    ARTICLE -->|AlertMatchMessage| NOTIFICATION
    NOTIFICATION -->|keyword + AI eval| SHARED_AI
    NOTIFICATION -->|SendNotificationMessage| WORKER
    WORKER -->|dispatch| NOTIFIER
    DIGEST -->|GenerateDigestMessage| WORKER
    WORKER -->|AI summary| SHARED_AI
    WORKER -->|deliver| NOTIFIER
    WEB -->|transaction pool| PGBOUNCER
    PGBOUNCER --- DB
    WORKER -->|direct connection| DB
    TESTDB -. test suite .- WORKER
    WEB --- EMBER
```

## Domain Boundaries

| Domain | Responsibility | Key Entities |
|--------|---------------|--------------|
| **Article** | Core articles, scoring, deduplication | `Article`, `ArticleFingerprint` |
| **Source** | Feed management, health tracking, fetch scheduling | `Source`, `SourceHealth` |
| **Enrichment** | Rule-based + AI categorization/summarization | `EnrichmentResult`, `AiQualityGate` |
| **Notification** | Alert rules, keyword/AI matching, dispatch | `AlertRule`, `NotificationLog` |
| **Digest** | Periodic schedules, AI editorial summaries | `DigestConfig`, `DigestLog` |
| **User** | Auth, per-user read state | `User`, `UserArticleRead` |
| **Shared/AI** | ModelFailoverPlatform, discovery, quality tracking | `ModelId`, `ModelQualityStats` |
| **Shared/Search** | SEAL + Loupe integration, search index sync | `ArticleSearchServiceInterface` |
| **Shared/Entity** | Category (shared lookup across domains) | `Category` |

## External Dependencies

| Service | Purpose | Required |
|---------|---------|----------|
| OpenRouter (`openrouter/free`) | AI categorization, summarization, alert eval, digests | No (rule-based fallback) |
| Notifier transport (Pushover, Telegram, Slack, ...) | Notification delivery | No (alerts disabled without DSN) |
| RSS/Atom feeds | News sources | Yes (core feature) |

## Port Map

| Port | Service |
|------|---------|
| `8443` | HTTPS (FrankenPHP/Caddy) |
| `8180` | HTTP (FrankenPHP/Caddy) |
| `5432` | PostgreSQL (internal) |
| `6432` | PgBouncer (internal) |
