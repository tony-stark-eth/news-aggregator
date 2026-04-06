/**
 * bin/browse.ts -- Browse the running News Aggregator UI via Playwright
 *
 * Usage: BROWSE_PASSWORD=<pw> bun run bin/browse.ts [--screenshot] <path>
 *        path defaults to /
 *
 * Reads from environment / .env files:
 *   ADMIN_EMAIL   -- login email (from .env / .env.local)
 *   BROWSE_PASSWORD -- login password (env var only, required)
 *   SERVER_NAME   -- hostname (default: localhost)
 *   HTTPS_PORT    -- port (default: 8443)
 */

import { chromium } from "playwright";

interface BrowseConfig {
  baseUrl: string;
  adminEmail: string;
  password: string;
  targetPath: string;
  takeScreenshot: boolean;
}

function parseArgs(argv: string[]): { targetPath: string; takeScreenshot: boolean } {
  // Bun.argv: [bun, script.ts, ...args]
  const args = argv.slice(2);
  let takeScreenshot = false;
  let targetPath = "/";

  for (const arg of args) {
    if (arg === "--screenshot") {
      takeScreenshot = true;
    } else {
      targetPath = arg;
    }
  }

  return { targetPath, takeScreenshot };
}

function buildConfig(): BrowseConfig {
  const { targetPath, takeScreenshot } = parseArgs(Bun.argv);

  const password = process.env["BROWSE_PASSWORD"];
  if (!password) {
    console.error("ERROR: BROWSE_PASSWORD environment variable is required");
    process.exit(1);
  }

  const adminEmail = process.env["ADMIN_EMAIL"];
  if (!adminEmail) {
    console.error("ERROR: ADMIN_EMAIL not found in .env or .env.local");
    process.exit(1);
  }

  // SERVER_NAME may contain Caddy multi-hostname syntax (e.g. "kmauel-ubuntu, localhost")
  // Extract only the first hostname for the browser URL
  const rawServerName = process.env["SERVER_NAME"] ?? "localhost";
  const serverName = rawServerName.split(",")[0]?.trim() ?? "localhost";
  const httpsPort = process.env["HTTPS_PORT"] ?? "8443";
  const baseUrl = `https://${serverName}:${httpsPort}`;

  return { baseUrl, adminEmail, password, targetPath, takeScreenshot };
}

async function browse(config: BrowseConfig): Promise<void> {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  try {
    // Navigate to login page
    await page.goto(`${config.baseUrl}/login`, { waitUntil: "domcontentloaded" });

    // Fill login form
    await page.fill('input[name="_username"]', config.adminEmail);
    await page.fill('input[name="_password"]', config.password);

    // Submit and wait for navigation
    await Promise.all([
      page.waitForNavigation({ waitUntil: "domcontentloaded" }),
      page.click('button[type="submit"]'),
    ]);

    // Navigate to target path (skip if already on login)
    if (config.targetPath !== "/login") {
      await page.goto(`${config.baseUrl}${config.targetPath}`, {
        waitUntil: "domcontentloaded",
      });
    }

    // Extract page content
    const title = await page.title();
    const bodyText = await page.evaluate(() => document.body.innerText);

    console.log("=== Page Title ===");
    console.log(title);
    console.log("");
    console.log("=== Page Text ===");
    console.log(bodyText);

    // Optional screenshot
    if (config.takeScreenshot) {
      const timestamp = Date.now();
      const screenshotPath = `/tmp/browse-${timestamp}.png`;
      await page.screenshot({ path: screenshotPath, fullPage: true });
      console.log("");
      console.log("=== Screenshot ===");
      console.log(screenshotPath);
    }
  } finally {
    await browser.close();
  }
}

const config = buildConfig();
browse(config).catch((err: unknown) => {
  const message = err instanceof Error ? err.message : String(err);
  console.error("Browse failed:", message);
  process.exit(1);
});
