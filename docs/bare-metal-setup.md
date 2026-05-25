# Bare-metal Setup (Debian 13)

Install and run the news-aggregator on Debian 13 without Docker. For the Docker workflow, see [CONTRIBUTING.md](../CONTRIBUTING.md).

## Automated install (recommended)

Scripts live under `docs/bare-metal/scripts/`. They are **idempotent** and resolve systemd units, Caddyfile, and bootstrap assets relative to the script directory — not inside the application clone (which may be on a branch that does not yet contain `docs/bare-metal/`).

### One-shot bootstrap (`install.sh`)

On a **fresh Debian 13** host as **root**. Use two short commands (avoid pasting one long line — chat clients often break URLs and `&&` chains).

**1. Download the installer**

```bash
curl -fsSL https://raw.githubusercontent.com/tony-stark-eth/news-aggregator/main/docs/bare-metal/scripts/install.sh -o /root/install.sh
```

The bootstrap scripts must exist on the branch you fetch (typically `main` after this PR is merged). When testing from a feature branch, set `INSTALL_BRANCH` or clone that branch manually.

**2. Run the installer**

```bash
ADMIN_EMAIL=admin@local ADMIN_PASSWORD=changeme bash /root/install.sh
```

At the end, the script verifies that `news-web` listens on `:8000` and returns HTTP 200 or 302. Open `http://<host>:8000` and log in with the credentials above.

| Variable | Default | Description |
|----------|---------|-------------|
| `ADMIN_EMAIL` | *(required)* | Admin login email |
| `ADMIN_PASSWORD` | *(required)* | Admin login password (plaintext; hashed at seed time) |
| `REPO_URL` | `https://github.com/tony-stark-eth/news-aggregator.git` | Git URL for the **application** clone (into `/home/app/news-aggregator`) |
| `INSTALL_BRANCH` | `main` | Branch used to fetch bootstrap scripts when not run from a checkout |
| `CLONE_DIR` | `/tmp/news` | Where bootstrap scripts are cloned when using `curl -o install.sh` |
| `APP_USER` | `app` | Unix account created by `install-system.sh` |

**What `install.sh` does:**

1. Installs `git` if missing
2. Clones bootstrap scripts (unless already running from a checkout)
3. Runs `install-system.sh` — APT packages, FrankenPHP, PostgreSQL, `app` user, user systemd bus
4. Runs `install-project.sh` as `app` — clone app, `.env.local`, migrations, seed, assets, systemd user services, HTTP check

**After cloning this repository locally:**

```bash
sudo ADMIN_EMAIL=admin@local ADMIN_PASSWORD=changeme bash docs/bare-metal/scripts/install.sh
```

### Two-step install (alternative)

Use this when you only need one phase, or when debugging.

#### 1. System packages (as root)

```bash
sudo docs/bare-metal/scripts/install-system.sh
```

Installs APT packages, optional Sury PHP fallback, Composer, FrankenPHP (with SHA256 verification), PostgreSQL bootstrap, the dedicated `app` user, and starts the `user@<uid>` systemd instance.

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_USER` | `app` | Unix account for the app and systemd user units |
| `PG_PASSWORD` | *(auto)* | PostgreSQL password for role `app`. If unset, a random password is generated once and stored in `/home/<APP_USER>/.news-aggregator-pg-password` |
| `SURY_FALLBACK` | `auto` | `auto` \| `yes` \| `no` — add Sury PHP repo when Debian PHP &lt; 8.4.19 |
| `FRANKENPHP_VERSION` | `latest` | GitHub release tag (e.g. `v1.12.3`) or `latest`. Checksum verified via `sha256sum -c`. On GitHub API rate limit (HTTP 403), pin this variable and retry. |

Re-running is safe: the PostgreSQL password is **not** rotated unless `PG_PASSWORD` is set explicitly to a new value.

**Password file rules:**

- Empty or whitespace-only secret files are treated as absent.
- If role `app` does **not** exist, any stale secret file is ignored and a new password is generated.
- If role `app` exists but the secret file is missing/empty and `PG_PASSWORD` is unset, the script exits with an error (manual intervention required).

Pre-existing databases `app` / `app_test` owned by another role cause a hard exit (no modification).

#### 2. Project bootstrap (as app user)

```bash
su - app
REPO_URL=https://github.com/YOUR_FORK/news-aggregator.git \
ADMIN_EMAIL=admin@local \
ADMIN_PASSWORD=changeme \
bash /path/to/docs/bare-metal/scripts/install-project.sh
```

Use the **absolute path** to `install-project.sh` from your bootstrap checkout (e.g. `/tmp/news/docs/bare-metal/scripts/install-project.sh`), not a path inside the application clone.

| Variable | Default | Description |
|----------|---------|-------------|
| `REPO_URL` | *(prompt)* | Git clone URL (required in non-interactive mode) |
| `PROJECT_DIR` | `$HOME/news-aggregator` | Application root |
| `ADMIN_EMAIL` | *(prompt)* | Admin login email |
| `ADMIN_PASSWORD` | *(prompt)* | Admin login password |
| `PG_PASSWORD_FILE` | `$HOME/.news-aggregator-pg-password` | File written by `install-system.sh` |
| `PG_PASSWORD` | *(unset)* | Override when the password file is missing |
| `SERVER_NAME` | `:8000` | Caddy listen address (high port for systemd user services) |
| `MERCURE_BASE_URL` | `http://127.0.0.1:8000` | Site base URL for Mercure (no path suffix) |
| `MERCURE_PUBLIC_URL` | same as `MERCURE_BASE_URL` | Override when browsers reach the host via another hostname/IP |
| `INSTALL_SYSTEMD` | `yes` | Install, enable, and verify user systemd units |
| `SYSTEMD_SRC_DIR` | `$SCRIPT_DIR/../systemd` | Override systemd unit templates path |
| `CADDYFILE_SRC` | `$SCRIPT_DIR/../Caddyfile.example` | Override Caddyfile template path |
| `CADDYFILE_DEST` | `$HOME/.config/news-aggregator/Caddyfile` | Installed Caddyfile path |

**Non-interactive mode:** export `REPO_URL`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` before running. Without a TTY, missing variables exit with an error.

**Regenerate `.env.local`:** `rm .env.local`, then re-run `install-project.sh`.

**Git updates:** the script does not run `git pull`. Update the checkout manually when needed.

**Clone guard:** if `PROJECT_DIR` exists, is not empty, and contains no `.git` directory, the script exits with an error.

**Systemd install:** unit templates use placeholders (`@@PROJECT_DIR@@`, `@@FRANKENPHP_BIN@@`, `@@CADDYFILE@@`) substituted at install time. Templates and `Caddyfile.example` are copied from `$SCRIPT_DIR/../`, not from the application clone. If systemd install is skipped, the script logs an explicit message (never silent).

## Prerequisites

| Component | Version / notes |
|-----------|-----------------|
| PHP | 8.4 (see [PHP version check](#php-version-check)) |
| PostgreSQL | 17 + pgvector |
| Bun | latest (TypeScript compilation) |
| Composer | 2.x |
| Symfony CLI | optional (dev only) |
| FrankenPHP | latest |

### PHP extensions

FrankenPHP embeds its own PHP SAPI — **php-fpm is not required**.

Install via APT (see below): `ctype`, `iconv`, `intl`, `opcache`, `pdo_pgsql`, `pdo_sqlite`, `zip`, `apcu`, `mbstring`, `xml`, `curl`.

`composer.json` requires `ext-ctype` and `ext-iconv`; Loupe/SEAL search needs `pdo_sqlite`.

## Native APT packages

Debian 13 (trixie) ships PHP 8.4 and PostgreSQL 17 natively.

```bash
sudo apt update
sudo apt install -y \
  git curl unzip \
  postgresql-17 postgresql-17-pgvector \
  php8.4-cli php8.4-common php8.4-intl php8.4-opcache \
  php8.4-pgsql php8.4-sqlite3 php8.4-zip php8.4-apcu \
  php8.4-mbstring php8.4-xml php8.4-curl
```

## PHP version check

`composer.json` requires `php >=8.4.19`. Debian security packages may ship 8.4.16 — verify before `composer install`:

```bash
php -v
```

If the version is below 8.4.19, add the [Sury PHP repository](https://packages.sury.org/php/) (optional, not the default path):

```bash
sudo apt install -y lsb-release ca-certificates curl
sudo curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/php-sury.gpg
echo "deb [signed-by=/usr/share/keyrings/php-sury.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
  | sudo tee /etc/apt/sources.list.d/php-sury.list
sudo apt update
sudo apt install -y php8.4-cli php8.4-common php8.4-intl php8.4-opcache \
  php8.4-pgsql php8.4-sqlite3 php8.4-zip php8.4-apcu \
  php8.4-mbstring php8.4-xml php8.4-curl
```

Re-check with `php -v` (expect 8.4.21+ from Sury).

## Manual installations

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### FrankenPHP

Follow the [official install guide](https://frankenphp.dev/docs/install/). Typical path: `/usr/local/bin/frankenphp`. Adjust unit files if yours differs.

### Bun

```bash
curl -fsSL https://bun.sh/install | bash
```

Ensure `bun` is on `PATH` for your shell.

### Symfony CLI (optional)

```bash
curl -sS https://get.symfony.com/cli/installer | bash
```

Only needed for option C in [Running the application](#running-the-application). Not required for production-like bare-metal setups.

## Dedicated user

Create a non-root user for running the app and systemd user services:

```bash
sudo adduser --disabled-password --gecos "" app
sudo loginctl enable-linger app
```

Clone and operate the project as this user. With the automated scripts, `PROJECT_DIR` defaults to `/home/app/news-aggregator`.

## PostgreSQL

Create the application role and databases (aligned with project defaults):

```bash
sudo -u postgres psql <<'SQL'
CREATE USER app WITH PASSWORD 'CHANGE_ME';
CREATE DATABASE app OWNER app;
CREATE DATABASE app_test OWNER app;
GRANT ALL PRIVILEGES ON DATABASE app TO app;
GRANT ALL PRIVILEGES ON DATABASE app_test TO app;
\c app
CREATE EXTENSION IF NOT EXISTS vector;
\c app_test
CREATE EXTENSION IF NOT EXISTS vector;
GRANT ALL ON SCHEMA public TO app;
SQL
```

Doctrine appends `_test` automatically in `APP_ENV=test` (`config/packages/doctrine.php`), so `app` becomes `app_test` — the explicit `app_test` database matches integration tests.

## Project bootstrap

```bash
git clone https://github.com/YOUR_FORK/news-aggregator.git
cd news-aggregator
composer install
cp .env.example .env.local
```

### Secrets and `.env.local`

Generate values (do not commit real secrets):

```bash
# Symfony secret
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Mercure JWT (use the same value for all three Symfony/Caddy keys below)
openssl rand -hex 32
```

Edit `.env.local`:

```dotenv
APP_SECRET=<generated-secret>

DATABASE_URL="postgresql://app:CHANGE_ME@127.0.0.1:5432/app?serverVersion=17&charset=utf8"

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=CHANGE_ME

# Bare-metal paths (required for Caddyfile.example)
APP_ROOT=/home/app/news-aggregator
SERVER_NAME=:8000

# Mercure — Symfony publisher
MERCURE_URL=http://127.0.0.1:8000/.well-known/mercure
MERCURE_PUBLIC_URL=http://127.0.0.1:8000/.well-known/mercure
MERCURE_JWT_SECRET=<generated-hex>

# Mercure — Caddy hub (same secret as MERCURE_JWT_SECRET)
MERCURE_PUBLISHER_JWT_KEY=<generated-hex>
MERCURE_SUBSCRIBER_JWT_KEY=<generated-hex>
```

`SERVER_NAME=:8000` binds a high port so systemd user services (non-root) can listen without capabilities. Option B (systemd) is the target bare-metal layout.

#### Admin password

- **Use `ADMIN_PASSWORD`** (plaintext). `SeedDataCommand` hashes it at runtime via `UserPasswordHasherInterface` (`config/services.php`).
- **`ADMIN_PASSWORD_HASH` in `.env.example` is unused** — auth loads the user from the database, not from env. Do not set it expecting login to work.
- If login fails after seeding, see [Troubleshooting](#troubleshooting).

#### Mercure

| Variable | Consumer |
|----------|----------|
| `MERCURE_URL` | Symfony (server-side publish) |
| `MERCURE_PUBLIC_URL` | Browser SSE (`EventSource`) |
| `MERCURE_JWT_SECRET` | Symfony Mercure bundle |
| `MERCURE_PUBLISHER_JWT_KEY` | Caddy Mercure hub |
| `MERCURE_SUBSCRIBER_JWT_KEY` | Caddy Mercure hub |

Generate one secret with `openssl rand -hex 32` and reuse it for all five Mercure-related values unless you have a reason to split them.

Adjust `MERCURE_PUBLIC_URL` to match how you reach the host (hostname, port, TLS). Keep `MERCURE_URL` (Symfony server-side) and `MERCURE_PUBLIC_URL` (browser SSE) as **full URLs including the `/.well-known/mercure` path** — they are independent of `SERVER_NAME`.

For a different listen port:

```dotenv
SERVER_NAME=:8080
MERCURE_URL=http://127.0.0.1:8080/.well-known/mercure
MERCURE_PUBLIC_URL=http://127.0.0.1:8080/.well-known/mercure
```

### Database migrations and seed

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-data
php bin/console app:search-reindex
php bin/console asset-map:compile
```

`asset-map:compile` mirrors the Docker image build step and validates AssetMapper imports.

`app:seed-data` creates categories, sources, the admin user, and digest configs. It **skips the admin user if one already exists** (`UserRepository::findFirst()` in `SeedDataCommand`) — it does not update the password.

**Reset admin credentials** (required when changing `ADMIN_EMAIL` / `ADMIN_PASSWORD` after the first seed):

```bash
php bin/console dbal:run-sql 'DELETE FROM "user"'
php bin/console app:seed-data
```

Ensure `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env.local` match what you expect before re-seeding. `install-project.sh` prints this procedure when it detects an existing admin row.

### TypeScript

Assets are plain TypeScript scripts (no ES modules). Running `tsc` on the whole tree fails with global scope collisions (`TS2451`, `TS2393`) — e.g. duplicate `DEBOUNCE_MS`, `STORAGE_KEY`, `init()`.

Compile per file with Bun (same as the Docker `Makefile` target):

```bash
bun build assets/ts/*.ts --outdir=assets/js/ --root=assets/ts
```

Watch mode:

```bash
bun build assets/ts/*.ts --outdir=assets/js/ --root=assets/ts --watch
```

Re-run after editing any `assets/ts/*.ts` file.

## Running the application

Five Messenger transports are defined in `config/packages/messenger.php`:

| Transport | Consumer |
|-----------|----------|
| `async` | `news-worker-async` |
| `async_enrich` | `news-worker-enrich` |
| `async_fulltext` | `news-worker-fulltext` |
| `scheduler_fetch` | `news-scheduler` |
| `scheduler_maintenance` | `news-scheduler` |

`scheduler_*` transports are auto-registered by `#[AsSchedule('fetch')]` and `#[AsSchedule('maintenance')]`.

### Option A — manual (tmux)

```bash
# Terminal 1 — web (use the installed Caddyfile, or docs/bare-metal/Caddyfile.example from a checkout)
frankenphp run --config ~/.config/news-aggregator/Caddyfile

# Terminal 2–5 — workers
php bin/console messenger:consume async --time-limit=3600
php bin/console messenger:consume async_enrich --time-limit=3600
php bin/console messenger:consume async_fulltext --time-limit=3600
php bin/console messenger:consume scheduler_fetch scheduler_maintenance --time-limit=3600
```

Set `WorkingDirectory` to the project root and load `.env.local` (Symfony reads it automatically; FrankenPHP/Caddy needs Mercure vars in the environment — `export $(grep -v '^#' .env.local | xargs)` or use direnv).

### Option B — systemd user services (recommended)

`install-project.sh` copies `Caddyfile.example` to `~/.config/news-aggregator/Caddyfile`, substitutes `@@PROJECT_DIR@@`, `@@FRANKENPHP_BIN@@`, and `@@CADDYFILE@@` in the unit templates, enables all five services, and verifies `:8000` responds before exiting.

Manual equivalent:

```bash
mkdir -p ~/.config/news-aggregator ~/.config/systemd/user
cp /path/to/bootstrap/docs/bare-metal/Caddyfile.example ~/.config/news-aggregator/Caddyfile
CADDYFILE=$HOME/.config/news-aggregator/Caddyfile
for unit in /path/to/bootstrap/docs/bare-metal/systemd/news-*.service; do
  sed -e "s|@@PROJECT_DIR@@|${PWD}|g" \
      -e "s|@@FRANKENPHP_BIN@@|$(command -v frankenphp)|g" \
      -e "s|@@CADDYFILE@@|${CADDYFILE}|g" \
      "$unit" > ~/.config/systemd/user/$(basename "$unit")
done
systemctl --user daemon-reload
systemctl --user enable --now news-web news-worker-async news-worker-enrich news-worker-fulltext news-scheduler
```

Check status:

```bash
systemctl --user status news-web
journalctl --user -u news-web -f
```

The web unit loads `EnvironmentFile=<project>/.env.local` for Mercure and `APP_ROOT`.

If `systemctl --user` fails with a missing runtime dir, `install-system.sh` already starts `user@<uid>.service` and waits for `/run/user/<uid>`. On manual setups:

```bash
export XDG_RUNTIME_DIR=/run/user/$(id -u)
loginctl enable-linger "$USER"
sudo systemctl start "user@$(id -u).service"
```

### Option C — Symfony CLI (not recommended)

```bash
symfony server:start
```

Observed issue: `--listen-ip` does not bind as expected in some environments. Prefer option B for a stable local setup.

## Troubleshooting

| Symptom | Cause / fix |
|---------|-------------|
| `git: command not found` / `switch 'b' requires a value` | Fresh LXC without git, or a long install command split across lines when copy-pasting. Use `install.sh` in two steps (curl, then bash). |
| `PostgreSQL password not found` | Run `install-system.sh` first (as root). Check `/home/app/.news-aggregator-pg-password` exists and is non-empty. |
| `Skipping systemd installation` in logs | Bootstrap assets missing at `$SYSTEMD_SRC_DIR` or `$CADDYFILE_SRC`. Run scripts from the bootstrap checkout, not only from the app clone. |
| `Caddyfile.example: no such file` in `news-web` journal | Old units pointed at the app clone. Re-run `install-project.sh` or regenerate units with `@@CADDYFILE@@` → `~/.config/news-aggregator/Caddyfile`. |
| `news-web did not start listening on :8000` | Check `journalctl --user -u news-web -n 50`. Common causes: missing Caddyfile, bad `.env.local`, FrankenPHP crash. |
| `TS2451` / `TS2393` on `tsc` | Global const/function collisions across `assets/ts/*.ts`. Use Bun per-file build (see above). |
| Invalid credentials after seed | (1) Set `ADMIN_PASSWORD`, not `ADMIN_PASSWORD_HASH`. (2) User already existed — seed skipped password update. Run `DELETE FROM "user"` then re-seed. (3) `ADMIN_EMAIL` mismatch. |
| `systemctl --user` fails | Set `XDG_RUNTIME_DIR=/run/user/$(id -u)`; enable lingering; start `user@<uid>.service` as root. |
| Symfony server bind errors | Use FrankenPHP + systemd (option B) instead of `symfony server:start`. |
| Mercure/SSE not working | Verify all Mercure env vars; web must use `frankenphp run` + Caddyfile, not `frankenphp php-server`. |
| Search returns nothing | Run `php bin/console app:search-reindex`. |

## Development workflow

Restart a single worker after code changes:

```bash
systemctl --user restart news-worker-enrich
```

Clear cache:

```bash
php bin/console cache:clear
```

Tail logs:

```bash
journalctl --user -u news-worker-async -f
```

AI features work without `OPENROUTER_API_KEY` — services fall back to rule-based categorization, summarization, and keyword extraction (`config/services.php`).

## Production hardening

Out of scope for this document. Use TLS termination, firewall rules, secret rotation, and non-default credentials before exposing the instance to a network.
