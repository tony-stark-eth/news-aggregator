# PHP Coding Guidelines

## General

- `declare(strict_types=1)` in every PHP file
- `final readonly class` by default — only remove `final` when extension is needed, `readonly` when mutable state is required
- Constructor injection only (no setter injection, no property injection)
- Interface-first: all service boundaries defined by interface
- One class per file, filename matches class name

## Time Handling

- `ClockInterface` for all time access — inject `Psr\Clock\ClockInterface`
- **Forbidden**: `new DateTimeImmutable()`, `new DateTime()`, `time()`, `date()`, `strtotime()`
- Use `$clock->now()` which returns `DateTimeImmutable`
- Tests: use `Symfony\Component\Clock\MockClock` for deterministic time

## Size Limits

- Max **20 lines** per method
- Max **3 parameters** per method/constructor
- Max **~150 lines** per class
- Max **5 constructor dependencies**
- Cognitive complexity max **8 per method**, **50 per class**

## Naming Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Controller | `{Feature}Controller` | `ArticleController` |
| Service | `{Action}Service` | `ScoringService` |
| Interface | `{Action}ServiceInterface` | `ScoringServiceInterface` |
| Repository | `{Entity}Repository` | `ArticleRepository` |
| Exception | `{What}Exception` | `ArticleNotFoundException` |
| Test | `{ClassUnderTest}Test` | `ScoringServiceTest` |
| Value Object | Descriptive noun | `ArticleFingerprint`, `Score` |
| Enum | Descriptive noun | `SourceHealth`, `AlertRuleType` |
| Message | `{Action}Message` | `FetchSourceMessage` |
| Handler | `{Action}Handler` | `FetchSourceHandler` |

## Code Style

- Early returns — reduce nesting, max 2 levels
- `find*` methods return nullable, `get*` methods throw on not found
- Value objects over primitives for domain concepts
- Enums over magic strings/numbers
- Immutability by default — use `readonly` properties
- No `empty()` — use explicit checks (`=== null`, `=== ''`, `=== []`)
- No `var_dump`, `dump`, `dd`, `print_r`

## Arrays

- **No untyped arrays** as return types or parameters at service boundaries
- Associative arrays (`array{key: type}`) → DTOs or value objects
- Collections of domain objects → typed `ArrayCollection` subclass with `@template-extends`
  ```php
  /** @template-extends ArrayCollection<int, FeedItem> */
  final class FeedItemCollection extends ArrayCollection {}
  ```
- Domain primitives → value objects (model IDs, URLs, fingerprints — not raw strings)
- `list<string>` only for truly generic scalars (HTML tag names, SQL columns)
  - Keywords, slugs, model IDs → value objects or typed collections
- Internal/private methods may use plain arrays if scope is small
- **FQCN**: always import via `use`, never `\App\...` inline — enforced by ECS `FullyQualifiedStrictTypesFixer`

## Domain Structure

```
src/{Domain}/
├── Controller/
├── Entity/
├── Repository/
├── Service/
├── ValueObject/
├── Message/
├── MessageHandler/
└── Exception/
```

Cross-cutting concerns go in `src/Shared/`.
