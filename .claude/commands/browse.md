---
description: Browse the running News Aggregator UI and return page text
argument-hint: "[--screenshot] <path>"
---

Run `BROWSE_PASSWORD=$BROWSE_PASSWORD bun run bin/browse.ts $ARGUMENTS` from project root.
If Playwright not installed, run `bun install && bunx playwright install chromium`.
If connection error, verify containers are running (`make up`).
Return the page text content to the conversation.
