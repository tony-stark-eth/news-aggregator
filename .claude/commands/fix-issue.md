---
description: Fetch a GitHub issue and implement the fix end-to-end
argument-hint: "<issue-number>"
---

Implement the fix for GitHub issue $ARGUMENTS.

## Steps

1. Fetch the issue: `gh issue view $ARGUMENTS`
2. Create a branch: `git checkout -b fix/issue-$ARGUMENTS main` (or `feat/` / `refactor/` based on issue type)
3. Read the issue description and acceptance criteria
4. Research the relevant code — read files, check tests, understand the blast radius
5. Implement the fix following project conventions (`.claude/coding-php.md`)
6. Run `/quality` to ensure all checks pass
7. Commit with conventional commit message referencing the issue: `fix: description (#$ARGUMENTS)`
8. Push and create a PR: `gh pr create --title "..." --body "Closes #$ARGUMENTS ..."`
9. Wait for CI to pass — fix if needed

## Rules

- One issue per branch — do not bundle unrelated changes
- Update docs (CLAUDE.md, README) if the change affects conventions
- Write tests for new behavior
- Follow the scope lock principle: if you find unrelated issues, create new GitHub issues for them
