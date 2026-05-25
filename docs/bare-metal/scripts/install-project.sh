#!/usr/bin/env bash
#
# Bare-metal project bootstrap for news-aggregator
# Run as the dedicated app user (not root).
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SYSTEMD_SRC_DIR="${SYSTEMD_SRC_DIR:-$SCRIPT_DIR/../systemd}"
CADDYFILE_SRC="${CADDYFILE_SRC:-$SCRIPT_DIR/../Caddyfile.example}"
CADDYFILE_DEST="${CADDYFILE_DEST:-$HOME/.config/news-aggregator/Caddyfile}"

REPO_URL="${REPO_URL:-}"
PROJECT_DIR="${PROJECT_DIR:-$HOME/news-aggregator}"
ADMIN_EMAIL="${ADMIN_EMAIL:-}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
PG_PASSWORD_FILE="${PG_PASSWORD_FILE:-$HOME/.news-aggregator-pg-password}"
SERVER_NAME="${SERVER_NAME:-:8000}"
MERCURE_BASE_URL="${MERCURE_BASE_URL:-http://127.0.0.1:8000}"
MERCURE_PUBLIC_URL="${MERCURE_PUBLIC_URL:-$MERCURE_BASE_URL}"
INSTALL_SYSTEMD="${INSTALL_SYSTEMD:-yes}"

log() { printf '\033[1;36m[%s]\033[0m %s\n' "$(date +%H:%M:%S)" "$*"; }
err() { printf '\033[1;31m[ERR]\033[0m %s\n' "$*" >&2; exit 1; }

escape_dotenv() {
  local value=$1
  value=${value//\\/\\\\}
  value=${value//\"/\\\"}
  printf '%s' "$value"
}

urlencode() {
  local s="$1" i c out=""
  for ((i = 0; i < ${#s}; i++)); do
    c="${s:i:1}"
    case "$c" in
      [a-zA-Z0-9.~_-]) out+="$c" ;;
      *) printf -v c '%%%02X' "'$c"; out+="$c" ;;
    esac
  done
  printf '%s' "$out"
}

sed_escape() {
  printf '%s' "$1" | sed 's/[&/\|]/\\&/g'
}

prompt_or_fail() {
  local var_name=$1
  local prompt_text=$2
  local current_value=${!var_name:-}

  if [ -n "$current_value" ]; then
    return 0
  fi

  if [[ -t 0 ]]; then
    if [ "$var_name" = "ADMIN_PASSWORD" ]; then
      read -rsp "$prompt_text" "$var_name"
      echo
    else
      read -rp "$prompt_text" "$var_name"
    fi
    return 0
  fi

  err "${var_name} is required in non-interactive mode (export ${var_name}=...)"
}

require_non_git_directory() {
  if [ -e "$PROJECT_DIR" ] && [ ! -d "$PROJECT_DIR/.git" ]; then
    if [ -n "$(ls -A "$PROJECT_DIR" 2>/dev/null)" ]; then
      err "${PROJECT_DIR} exists, is not empty, and is not a git repository. Remove it or set PROJECT_DIR."
    fi
  fi
}

load_pg_password() {
  local stored=""

  if [ -n "${PG_PASSWORD:-}" ]; then
    printf '%s' "$PG_PASSWORD"
    return 0
  fi

  if [ -r "$PG_PASSWORD_FILE" ]; then
    stored=$(tr -d '[:space:]' < "$PG_PASSWORD_FILE")
    if [ -n "$stored" ]; then
      printf '%s' "$stored"
      return 0
    fi
  fi

  return 1
}

require_pg_password() {
  local hint

  hint="Run as root: bash ${SCRIPT_DIR}/install-system.sh — or pass PG_PASSWORD=... in the environment."

  if load_pg_password >/dev/null; then
    return 0
  fi

  if [ -f "$PG_PASSWORD_FILE" ] && [ ! -r "$PG_PASSWORD_FILE" ]; then
    err "PostgreSQL password file ${PG_PASSWORD_FILE} exists but is not readable (expected owner ${USER}). ${hint}"
  fi

  if [ -f "$PG_PASSWORD_FILE" ]; then
    err "PostgreSQL password file ${PG_PASSWORD_FILE} is empty. Re-run install-system.sh to regenerate it. ${hint}"
  fi

  err "PostgreSQL password not found at ${PG_PASSWORD_FILE}. ${hint}"
}

append_env_local() {
  local app_secret mercure_secret pg_pass pg_pass_encoded mercure_url mercure_public

  app_secret=$(php -r 'echo bin2hex(random_bytes(32));')
  mercure_secret=$(openssl rand -hex 32)

  pg_pass=$(load_pg_password) || require_pg_password

  pg_pass_encoded=$(urlencode "$pg_pass")
  mercure_url="${MERCURE_BASE_URL%/}/.well-known/mercure"
  mercure_public="${MERCURE_PUBLIC_URL%/}/.well-known/mercure"

  {
    printf '\n'
    printf 'APP_SECRET=%s\n' "$app_secret"
    printf 'DATABASE_URL="postgresql://app:%s@127.0.0.1:5432/app?serverVersion=17&charset=utf8"\n' "$pg_pass_encoded"
    printf 'ADMIN_EMAIL=%s\n' "$(escape_dotenv "$ADMIN_EMAIL")"
    printf 'ADMIN_PASSWORD="%s"\n' "$(escape_dotenv "$ADMIN_PASSWORD")"
    printf 'APP_ROOT=%s\n' "$(escape_dotenv "$PROJECT_DIR")"
    printf 'SERVER_NAME=%s\n' "$(escape_dotenv "$SERVER_NAME")"
    printf 'MERCURE_URL=%s\n' "$mercure_url"
    printf 'MERCURE_PUBLIC_URL=%s\n' "$mercure_public"
    printf 'MERCURE_JWT_SECRET=%s\n' "$mercure_secret"
    printf 'MERCURE_PUBLISHER_JWT_KEY=%s\n' "$mercure_secret"
    printf 'MERCURE_SUBSCRIBER_JWT_KEY=%s\n' "$mercure_secret"
  } >> .env.local

  chmod 600 .env.local
}

warn_existing_admin() {
  local count

  count=$(php bin/console dbal:run-sql 'SELECT COUNT(*) FROM "user"' --no-ansi 2>/dev/null | awk 'NF && $1 ~ /^[0-9]+$/{print $1; exit}')
  count=${count:-0}

  if [ "$count" -gt 0 ]; then
    log "Admin user already exists in database (${count} row(s))."
    log "app:seed-data will NOT update ADMIN_EMAIL or ADMIN_PASSWORD."
    log "Reset procedure:"
    log "  php bin/console dbal:run-sql 'DELETE FROM \"user\"'"
    log "  php bin/console app:seed-data"
  fi
}

install_systemd_units() {
  local frankenphp_bin project_dir_escaped frankenphp_escaped caddyfile_escaped src dest

  frankenphp_bin=$(command -v frankenphp)
  [ -n "$frankenphp_bin" ] || err "frankenphp not found in PATH"
  [ -f "$CADDYFILE_SRC" ] || err "Caddyfile not found at ${CADDYFILE_SRC}"

  project_dir_escaped=$(sed_escape "$PROJECT_DIR")
  frankenphp_escaped=$(sed_escape "$frankenphp_bin")

  mkdir -p "$(dirname "$CADDYFILE_DEST")"
  cp "$CADDYFILE_SRC" "$CADDYFILE_DEST"
  chmod 644 "$CADDYFILE_DEST"
  log "Caddyfile installed at ${CADDYFILE_DEST}"

  caddyfile_escaped=$(sed_escape "$CADDYFILE_DEST")

  export XDG_RUNTIME_DIR="/run/user/$(id -u)"
  mkdir -p ~/.config/systemd/user

  for src in "$SYSTEMD_SRC_DIR"/news-*.service; do
    dest="$HOME/.config/systemd/user/$(basename "$src")"
    sed \
      -e "s|@@PROJECT_DIR@@|${project_dir_escaped}|g" \
      -e "s|@@FRANKENPHP_BIN@@|${frankenphp_escaped}|g" \
      -e "s|@@CADDYFILE@@|${caddyfile_escaped}|g" \
      "$src" >"$dest"
  done
}

start_systemd_services() {
  export XDG_RUNTIME_DIR="/run/user/$(id -u)"
  systemctl --user daemon-reload
  systemctl --user enable --now \
    news-web news-worker-async news-worker-enrich \
    news-worker-fulltext news-scheduler

  local i
  for i in $(seq 1 15); do
    if ss -tln 2>/dev/null | grep -q ':8000 '; then
      break
    fi
    sleep 1
  done
  if ! ss -tln 2>/dev/null | grep -q ':8000 '; then
    echo "FATAL: news-web did not start listening on :8000 within 15s" >&2
    systemctl --user status news-web --no-pager || true
    journalctl --user -u news-web -n 30 --no-pager || true
    exit 1
  fi
  log "news-web listening on :8000"

  local http_code
  http_code=$(curl -fsS -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/ || echo "000")
  if [ "$http_code" != "200" ] && [ "$http_code" != "302" ]; then
    echo "FATAL: unexpected HTTP ${http_code} from http://127.0.0.1:8000/" >&2
    exit 1
  fi
  log "HTTP check OK (status ${http_code})"
}

verify_post_install() {
  command -v bun >/dev/null 2>&1 || err "Post-install check failed: bun not found"
  bun --version >/dev/null 2>&1 || err "Post-install check failed: bun --version failed"
  log "Bun OK: $(bun --version)"

  php bin/console about >/dev/null 2>&1 || err "Post-install check failed: Symfony console unreachable"
  log "Symfony console OK"
}

[ "$EUID" -ne 0 ] || err "Do not run as root"
command -v composer >/dev/null || err "composer missing (run install-system.sh as root: bash ${SCRIPT_DIR}/install-system.sh)"
command -v frankenphp >/dev/null || err "frankenphp missing (run install-system.sh as root: bash ${SCRIPT_DIR}/install-system.sh)"

require_pg_password

export PATH="$HOME/.bun/bin:$PATH"

if ! command -v bun >/dev/null 2>&1; then
  log "Installing Bun"
  curl -fsSL https://bun.sh/install | bash
  export PATH="$HOME/.bun/bin:$PATH"
fi

require_non_git_directory

if [ ! -f "$PROJECT_DIR/.env.local" ]; then
  prompt_or_fail REPO_URL "Fork URL (https://github.com/USER/news-aggregator.git): "
  prompt_or_fail ADMIN_EMAIL "Admin email: "
  prompt_or_fail ADMIN_PASSWORD "Admin password: "
fi

if [ ! -d "$PROJECT_DIR/.git" ]; then
  log "Cloning ${REPO_URL} into ${PROJECT_DIR}"
  git clone "$REPO_URL" "$PROJECT_DIR"
fi
cd "$PROJECT_DIR"

if [ ! -f .env.local ]; then
  log "Generating .env.local"
  cp .env.example .env.local
  append_env_local
  log ".env.local created (mode 600). To regenerate: rm .env.local, then re-run this script."
fi

log "Installing PHP dependencies"
composer install --no-interaction --prefer-dist

log "Running database migrations"
php bin/console doctrine:migrations:migrate --no-interaction

warn_existing_admin

log "Seeding initial data"
php bin/console app:seed-data

log "Reindexing search"
php bin/console app:search-reindex

log "Compiling TypeScript"
mkdir -p assets/js
bun build assets/ts/*.ts --outdir=assets/js/ --root=assets/ts

log "Compiling Symfony assets"
php bin/console asset-map:compile

if [ "$INSTALL_SYSTEMD" = "yes" ] && [ -d "$SYSTEMD_SRC_DIR" ] && [ -f "$CADDYFILE_SRC" ]; then
  log "Installing systemd user units from $SYSTEMD_SRC_DIR"
  install_systemd_units
  start_systemd_services
else
  log "Skipping systemd installation (INSTALL_SYSTEMD=$INSTALL_SYSTEMD, systemd_dir=$SYSTEMD_SRC_DIR, caddyfile=$CADDYFILE_SRC)"
fi

verify_post_install

HOST="$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo 127.0.0.1)"
log "Done. Open http://${HOST}:8000 and log in with ${ADMIN_EMAIL}"
