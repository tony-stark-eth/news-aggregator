---
description: Review the current branch against main using the QA Specialist agent
---

Review all changes on the current branch compared to main.

## Steps

1. Run `git diff main...HEAD --stat` to see what files changed
2. Run `git log main..HEAD --oneline` to see commit messages
3. Spawn the `qa-specialist` agent with this prompt:

> Review the changes on the current branch. Start from `git diff main...HEAD` as your primary source of truth. Check for: spec compliance, scope drift, security issues, logic correctness, coding standards violations, test coverage gaps, and boundary conditions. Provide a verdict: APPROVED, APPROVED WITH CONDITIONS, or REJECTED.

4. Report the agent's findings
5. If APPROVED WITH CONDITIONS, list the specific fixes needed
