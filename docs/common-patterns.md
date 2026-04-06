# Common PHP Patterns

Patterns identified across news-aggregator, tcg-scanner, and smart-habit-app.

## Scaffolding Commands

| Command | Generates | Example |
|---------|-----------|---------|
| `make:entity` | Entity + Repository (Symfony built-in) | `make:entity` |
| `make:value-object` | Self-validating immutable VO | `make:value-object Article/Url` |
| `make:service-interface` | Interface + implementation pair | `make:service-interface Enrichment/Categorization` |
| `make:domain-message` | Message DTO + handler | `make:domain-message Notification/SendAlert` |
| `make:domain-exception` | Exception with named constructor | `make:domain-exception Source/FeedFetch` |
| `make:enum` | String-backed enum | `make:enum Notification/AlertUrgency` |
| `make:dto` | Readonly DTO | `make:dto Article/ArticleInfo` |

All commands use the format `<Context>/<Name>` and generate files into `src/<Context>/`.

## Pattern Conventions

### Entity
- `#[ORM\Entity]` + `#[ORM\Table]` attributes
- `?int $id = null` with auto-increment (or UUID v7)
- Constructor takes required fields, validates via Value Objects
- `DateTimeImmutable` for timestamps, never `DateTime`
- Void-return setters (not fluent)

### Value Object
- `final readonly class` implementing `\Stringable`
- Constructor validates and throws `\InvalidArgumentException`
- `value()`, `__toString()`, `equals(self $other)` methods

### Repository
- Interface in domain: `findById()`, `save()`, `flush()`
- Implementation extends `ServiceEntityRepository`
- No direct `EntityManagerInterface` in services

### Service
- Interface declares contract
- Implementation is `final readonly class`
- Constructor injection only

### Message / Handler
- Message: `final readonly class` with promoted scalar properties
- Handler: `#[AsMessageHandler]`, `final readonly class`, `__invoke()`

### Enum
- String-backed (`enum Foo: string`)
- Lives in `<Context>/Enum/`

### Exception
- `final class` extending `\RuntimeException`
- Named constructors: `static function because(): self`

### DTO
- `final readonly class` with promoted constructor properties
- No behavior, just data transfer

## Module Structure

```
src/<Context>/
├── Command/           # Console commands
├── Controller/        # Invokable HTTP controllers
├── Dto/               # Data Transfer Objects
├── Entity/            # Doctrine entities
├── Enum/              # String-backed enums
├── Event/             # Domain events
├── Exception/         # Domain exceptions
├── Message/           # Messenger messages
├── MessageHandler/    # Messenger handlers
├── Repository/        # Interface + Doctrine implementation
├── Service/           # Interface + implementation
└── ValueObject/       # Self-validating immutable objects
```
