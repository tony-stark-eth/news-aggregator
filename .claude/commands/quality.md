---
description: Run full quality pipeline and iteratively fix issues until green
---

Run the full quality pipeline and fix any issues found. Iterate until all checks pass.

## Steps

1. Run `make ecs-fix` to auto-fix coding standards
2. Run `make phpstan` — if errors, fix them and re-run
3. Run `make rector-fix` to apply Rector changes, then `make ecs-fix` again (Rector may introduce style issues)
4. Run `make test` — if failures, fix them and re-run
5. Repeat until all 4 checks pass clean

## Rules

- Fix PHPStan errors in the source code, not by adding `@phpstan-ignore`
- Never add `ignoreErrors` to phpstan.neon
- If a Rector change is wrong, revert it — do not suppress the rule
- If a test fails, read the test to understand intent before fixing
- Report final status: which checks passed, how many iterations needed
