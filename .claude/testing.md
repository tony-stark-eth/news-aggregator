# Testing & Code Quality

## Quality Tools

| Tool | Version | Purpose | Blocks CI? |
|------|---------|---------|------------|
| PHPStan | 2.1.x | Static analysis (level max) | Yes |
| ECS | 13.0.x | Coding standards (PSR-12 + strict) | Yes |
| Rector | 2.4.x | Automated refactoring (dry-run in CI) | Yes |
| Infection | 0.32.x | Mutation testing | Yes (MSI thresholds) |
| PHPat | 0.12.x | Architecture tests (via PHPStan) | Yes |
| PHPUnit | 13.1.x | Unit + integration tests | Yes |

## PHPStan Extensions

| Extension | What it checks |
|-----------|---------------|
| phpstan-strict-rules | Strict type comparisons, no mixed |
| shipmonk/phpstan-rules | ~40 extra rules, bans `var_dump`/`DateTime`/`time()` |
| tomasvotruba/cognitive-complexity | Max 8/method, 50/class |
| tomasvotruba/type-coverage | 100% return, param, property types |
| phpstan-deprecation-rules | Flags deprecated API usage |
| voku/phpstan-rules | Additional strictness rules |
| phpat/phpat | Architecture dependency rules |
| phpstan-symfony | Symfony container/service analysis |
| phpstan-doctrine | Doctrine type/mapping analysis |
| phpstan-phpunit | PHPUnit best practices |

## PHPUnit Conventions

- **Suites**: `unit` (fast, no I/O) and `integration` (database, filesystem)
- **Coverage**: Xdebug path coverage (`XDEBUG_MODE=coverage`)
- **Execution**: Random order, fail on risky/warning
- **Attributes**: `#[CoversClass(Foo::class)]` required on every test class
- **Stubs vs mocks**: `createStub()` when call count doesn't matter, `createMock()` when it does
- **No mocking finals**: Use `BypassFinals::enable()` in `setUp()` when needed
- **Test naming**: `test{MethodName}{Scenario}{ExpectedBehavior}` or descriptive

## Infection (Mutation Testing)

- Runs against **unit test suite only** (integration tests too slow)
- Thresholds: MSI >= 80%, covered MSI >= 90%
- Excluded from mutation: Entity, Kernel, Controller, Command

### Writing mutation-resistant tests

Infection mutates code and checks if tests still pass. Common escaped mutants and how to kill them:

| Mutation | What Infection does | How to kill it |
|----------|-------------------|----------------|
| **MethodCallRemoval** | Removes `$logger->warning(...)` or `$tracker->record(...)` | Use `createMock()` + `expects(self::once())` on side-effect methods |
| **ArrayItemRemoval** | Removes a key from logger context array | Assert exact context via `self::callback(fn(array $ctx) => ...)` |
| **ArrayItem** | Swaps context keys/values | Same — verify specific keys exist with correct values |
| **MBString** | Replaces `mb_strtolower()` with `strtolower()` | Test with multibyte input (e.g. `'ÜBER'` where results differ) |
| **UnwrapTrim** | Removes `trim()` calls | Test with whitespace-padded input that affects logic |
| **Coalesce** | Removes `??` operator | Test both null and non-null paths for nullable parameters |
| **TrueValue/FalseValue** | Flips boolean returns | Assert the exact boolean value, not just truthiness |
| **ReturnRemoval** | Removes `return` statements | Ensure tests check the return value or downstream side effects |

### Rules for the senior developer

1. **Mock all injected side-effect services** (loggers, trackers, notifiers) — use `createMock()` with `expects()`, never `NullLogger` or `createStub()` for these
2. **Verify logger calls with context** — assert the message string AND use `self::callback()` to check context array keys/values
3. **Test null vs non-null paths** for every nullable parameter and `??` coalesce
4. **Test with multibyte strings** when code uses `mb_*` functions — choose input where `strlen` vs `mb_strlen` or `strtolower` vs `mb_strtolower` give different results
5. **Test boundary values** for numeric comparisons — if code checks `$percent > 90`, test with exactly 90 (boundary) and 91 (above)
6. **Verify return values from every code path** — if a method returns early on failure, assert that specific return
7. **Closure-based `InMemoryPlatform`** — when you need to verify prompt content, use a closure that captures the input and asserts on it

## CI Pipeline Order

1. **Parallel**: ECS check + PHPStan + Rector dry-run (no DB needed)
2. **Sequential**: PHPUnit (needs DB) -> Infection (needs test results)
3. **E2E**: Symfony Panther (full Docker stack)

## Running Quality Checks

```bash
make quality          # ECS + PHPStan + Rector
make test             # All tests
make test-unit        # Unit tests only
make test-integration # Integration tests only
make infection        # Mutation testing
make coverage         # HTML coverage report
```
