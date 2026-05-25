#!/usr/bin/env bash
#
# Bare-metal system install for news-aggregator on Debian 13
# Run as root. Idempotent.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

APP_USER="${APP_USER:-app}"
SURY_FALLBACK="${SURY_FALLBACK:-auto}"  # auto|yes|no
FRANKENPHP_VERSION="${FRANKENPHP_VERSION:-latest}"
PG_PASSWORD="${PG_PASSWORD-}"
PG_PASSWORD_PROVIDED=0
PG_FORCE_PASSWORD_UPDATE=0
PG_PASSWORD_FILE="/home/${APP_USER}/.news-aggregator-pg-password"

if [ -n "${PG_PASSWORD:-}" ]; then
  PG_PASSWORD_PROVIDED=1
fi

log() { printf '\033[1;36m[%s]\033[0m %s\n' "$(date +%H:%M:%S)" "$*"; }
err() { printf '\033[1;31m[ERR]\033[0m %s\n' "$*" >&2; exit 1; }

sql_escape() {
  printf '%s' "$1" | sed "s/'/''/g"
}

validate_sury_fallback() {
  case "$SURY_FALLBACK" in
    auto|yes|no) ;;
    *) err "SURY_FALLBACK must be auto, yes, or no (got: $SURY_FALLBACK)" ;;
  esac
}

frankenphp_arch() {
  case "$(uname -m)" in
    x86_64) echo "x86_64" ;;
    aarch64|arm64) echo "aarch64" ;;
    *) err "Unsupported architecture for FrankenPHP: $(uname -m) (expected x86_64 or aarch64)" ;;
  esac
}

frankenphp_normalize_tag() {
  local tag=$1
  case "$tag" in
    latest) printf 'latest' ;;
    v*) printf '%s' "$tag" ;;
    *) printf 'v%s' "$tag" ;;
  esac
}

github_api_get() {
  local url=$1 out=$2
  curl -sSL -o "$out" -w '%{http_code}' \
    -H 'Accept: application/vnd.github+json' \
    -H 'User-Agent: news-aggregator-bare-metal-install' \
    "$url"
}

frankenphp_parse_digest() {
  local json_file=$1 asset=$2
  local segment digest

  segment=$(sed "s/.*\"name\":\"${asset}\"//" "$json_file" | sed 's/"name":"frankenphp.*//')
  digest=$(grep -oE 'sha256:[a-f0-9]{64}' <<<"$segment" | head -1 | cut -d: -f2)
  [ -n "$digest" ] || return 1
  printf '%s' "$digest"
}

frankenphp_resolve_tag() {
  local normalized api_file code tag

  normalized=$(frankenphp_normalize_tag "$FRANKENPHP_VERSION")
  if [ "$normalized" != "latest" ]; then
    printf '%s' "$normalized"
    return 0
  fi

  api_file=$(mktemp)
  code=$(github_api_get "https://api.github.com/repos/php/frankenphp/releases/latest" "$api_file")
  if [ "$code" = "200" ]; then
    tag=$(grep -oE '"tag_name"[[:space:]]*:[[:space:]]*"[^"]+"' "$api_file" | head -1 | sed 's/.*"\([^"]*\)"$/\1/')
    rm -f "$api_file"
    [ -n "$tag" ] || err "Could not parse latest FrankenPHP tag from GitHub API"
    printf '%s' "$tag"
    return 0
  fi

  rm -f "$api_file"
  if [ "$code" = "403" ]; then
    err "GitHub API rate-limited (HTTP 403). Pin FRANKENPHP_VERSION (e.g. FRANKENPHP_VERSION=v1.12.3) and retry."
  fi
  err "GitHub API error (HTTP ${code}) while resolving latest FrankenPHP release. Pin FRANKENPHP_VERSION and retry."
}

frankenphp_fetch_checksum() {
  local tag=$1 asset=$2
  local sha_url checksum_file tmp_api code digest

  sha_url="https://github.com/php/frankenphp/releases/download/${tag}/${asset}.sha256"
  checksum_file=$(mktemp)
  if curl -fsSL "$sha_url" -o "$checksum_file" 2>/dev/null && [ -s "$checksum_file" ]; then
    awk '{print $1; exit}' "$checksum_file"
    rm -f "$checksum_file"
    return 0
  fi
  rm -f "$checksum_file"

  tmp_api=$(mktemp)
  code=$(github_api_get "https://api.github.com/repos/php/frankenphp/releases/tags/${tag}" "$tmp_api")
  if [ "$code" = "200" ]; then
    digest=$(frankenphp_parse_digest "$tmp_api" "$asset") || true
    rm -f "$tmp_api"
    [ -n "$digest" ] || err "Could not parse FrankenPHP digest for ${asset} (release ${tag})"
    printf '%s' "$digest"
    return 0
  fi

  rm -f "$tmp_api"
  if [ "$code" = "403" ]; then
    err "GitHub API rate-limited (HTTP 403). Pin FRANKENPHP_VERSION=${tag} and retry later, or install FrankenPHP manually."
  fi
  err "GitHub API error (HTTP ${code}) while fetching FrankenPHP checksum for ${tag}. Pin FRANKENPHP_VERSION and retry."
}

install_frankenphp() {
  if command -v frankenphp >/dev/null 2>&1; then
    log "FrankenPHP already installed at $(command -v frankenphp)"
    return
  fi

  local arch tag asset download_url expected_sha tmp_bin checksum_file
  arch=$(frankenphp_arch)
  tag=$(frankenphp_resolve_tag)
  asset="frankenphp-linux-${arch}"
  download_url="https://github.com/php/frankenphp/releases/download/${tag}/${asset}"

  log "Installing FrankenPHP (${asset}, ${tag})"
  log "Download URL: ${download_url}"

  expected_sha=$(frankenphp_fetch_checksum "$tag" "$asset")

  tmp_bin=$(mktemp)
  checksum_file=$(mktemp)
  chmod 600 "$tmp_bin" "$checksum_file"
  curl -fsSL "$download_url" -o "$tmp_bin"
  printf '%s  %s\n' "$expected_sha" "$tmp_bin" >"$checksum_file"
  sha256sum -c "$checksum_file" >/dev/null || {
    rm -f "$tmp_bin" "$checksum_file"
    err "FrankenPHP checksum verification failed for ${asset} (${tag})"
  }

  install -m 755 "$tmp_bin" /usr/local/bin/frankenphp
  rm -f "$tmp_bin" "$checksum_file"
  log "FrankenPHP installed at /usr/local/bin/frankenphp"
}

resolve_pg_password() {
  local force_update=0 stored="" has_valid_file=0 role_exists=0 write_file=0

  if pg_role_exists; then
    role_exists=1
  fi

  stored=""
  if [ -r "$PG_PASSWORD_FILE" ]; then
    stored=$(tr -d '[:space:]' < "$PG_PASSWORD_FILE")
  fi
  if [ -n "$stored" ]; then
    has_valid_file=1
  fi

  if [ "$role_exists" -eq 0 ]; then
    if [ -z "$PG_PASSWORD" ]; then
      PG_PASSWORD=$(openssl rand -hex 24)
    fi
    force_update=1
    write_file=1
  elif [ "$has_valid_file" -eq 1 ]; then
    if [ "$PG_PASSWORD_PROVIDED" -eq 1 ]; then
      if [ "$PG_PASSWORD" = "$stored" ]; then
        force_update=0
      else
        force_update=1
        write_file=1
      fi
    else
      PG_PASSWORD=$stored
      force_update=0
    fi
  elif [ "$PG_PASSWORD_PROVIDED" -eq 1 ]; then
    force_update=1
    write_file=1
  else
    log "WARNING: PostgreSQL role 'app' exists but ${PG_PASSWORD_FILE} is missing or empty. Set PG_PASSWORD or recreate the secret file manually."
    exit 1
  fi

  if [ "$write_file" -eq 1 ]; then
    if [ -z "$PG_PASSWORD" ]; then
      PG_PASSWORD=$(openssl rand -hex 24)
    fi
    if [ -z "$PG_PASSWORD" ]; then
      echo "FATAL: PG_PASSWORD empty before writing secret file" >&2
      exit 1
    fi
    umask 077
    printf '%s' "$PG_PASSWORD" >"$PG_PASSWORD_FILE"
    chown "${APP_USER}:${APP_USER}" "$PG_PASSWORD_FILE"
    chmod 600 "$PG_PASSWORD_FILE"
    log "PostgreSQL password stored in ${PG_PASSWORD_FILE}"
  fi

  PG_FORCE_PASSWORD_UPDATE=$force_update
}

pg_role_exists() {
  [ "$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname = 'app'")" = "1" ]
}

pg_database_owner() {
  local db=$1
  sudo -u postgres psql -tAc \
    "SELECT pg_catalog.pg_get_userbyid(d.datdba) FROM pg_catalog.pg_database d WHERE d.datname = '${db}'"
}

assert_database_owner() {
  local db=$1
  local owner

  owner=$(pg_database_owner "$db")
  if [ -n "$owner" ] && [ "$owner" != "app" ]; then
    err "Database '${db}' exists but is owned by '${owner}' (expected 'app'). Refusing to modify."
  fi
}

bootstrap_postgresql() {
  local force_password_update=$1
  local pass_escaped setup_sql role_exists=0

  assert_database_owner "app"
  assert_database_owner "app_test"

  if pg_role_exists; then
    role_exists=1
  fi

  setup_sql=$(mktemp)
  chmod 600 "$setup_sql"
  # shellcheck disable=SC2064
  trap "rm -f '$setup_sql'" EXIT

  if [ -n "$PG_PASSWORD" ]; then
    pass_escaped=$(sql_escape "$PG_PASSWORD")
  fi

  {
    if [ -n "$PG_PASSWORD" ]; then
      if [ "$role_exists" -eq 0 ]; then
        printf "CREATE ROLE app WITH LOGIN PASSWORD '%s';\n" "$pass_escaped"
      elif [ "$force_password_update" -eq 1 ]; then
        printf "ALTER ROLE app WITH PASSWORD '%s';\n" "$pass_escaped"
      fi
    fi
    printf "SELECT format('CREATE DATABASE %%I OWNER app', 'app')\n"
    printf "WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'app')\\gexec\n"
    printf "SELECT format('CREATE DATABASE %%I OWNER app', 'app_test')\n"
    printf "WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'app_test')\\gexec\n"
  } >"$setup_sql"

  chown postgres:postgres "$setup_sql"
  sudo -u postgres psql -v ON_ERROR_STOP=1 -f "$setup_sql"
  rm -f "$setup_sql"
  trap - EXIT

  sudo -u postgres psql -d app -c "CREATE EXTENSION IF NOT EXISTS vector;"
  sudo -u postgres psql -d app_test -c "CREATE EXTENSION IF NOT EXISTS vector;"
}

verify_post_install() {
  local php_version

  php_version=$(php -r 'echo PHP_VERSION;')
  if dpkg --compare-versions "$php_version" lt "8.4.19"; then
    err "Post-install check failed: PHP ${php_version} < 8.4.19"
  fi
  log "PHP version OK: ${php_version}"

  command -v frankenphp >/dev/null 2>&1 || err "Post-install check failed: frankenphp not found"
  frankenphp version >/dev/null 2>&1 || err "Post-install check failed: frankenphp version command failed"
  log "FrankenPHP OK: $(frankenphp version 2>&1 | head -1)"

  sudo -u postgres psql -tAc "SELECT 1" >/dev/null 2>&1 || err "Post-install check failed: postgres psql unreachable"
  pg_role_exists || err "Post-install check failed: PostgreSQL role 'app' missing"
  [ "$(pg_database_owner app)" = "app" ] || err "Post-install check failed: database 'app' missing or wrong owner"
  log "PostgreSQL OK: role app, database app"
}

[ "$EUID" -eq 0 ] || err "Run as root"
. /etc/os-release
[ "${ID:-}" = "debian" ] && [ "${VERSION_ID:-}" = "13" ] || err "Debian 13 required"

validate_sury_fallback

log "Updating APT cache"
apt update -qq

log "Installing base tooling"
apt install -y -qq \
  git curl unzip ca-certificates lsb-release gnupg sudo \
  postgresql-17 postgresql-17-pgvector \
  php8.4-cli php8.4-common php8.4-intl php8.4-opcache \
  php8.4-pgsql php8.4-sqlite3 php8.4-zip php8.4-apcu \
  php8.4-mbstring php8.4-xml php8.4-curl

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
log "PHP version installed: $PHP_VERSION"

needs_sury=0
if dpkg --compare-versions "$PHP_VERSION" lt "8.4.19"; then
  case "$SURY_FALLBACK" in
    yes|auto) needs_sury=1 ;;
    no) err "PHP $PHP_VERSION < 8.4.19 and SURY_FALLBACK=no" ;;
  esac
fi

if [ "$needs_sury" -eq 1 ]; then
  log "Adding Sury repository for PHP >= 8.4.19"
  curl -fsSL https://packages.sury.org/php/apt.gpg \
    -o /usr/share/keyrings/php-sury.gpg
  echo "deb [signed-by=/usr/share/keyrings/php-sury.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
    > /etc/apt/sources.list.d/php-sury.list
  apt update -qq
  apt install -y -qq \
    php8.4-cli php8.4-common php8.4-intl php8.4-opcache \
    php8.4-pgsql php8.4-sqlite3 php8.4-zip php8.4-apcu \
    php8.4-mbstring php8.4-xml php8.4-curl
  log "PHP upgraded to $(php -r 'echo PHP_VERSION;')"
fi

if ! command -v composer >/dev/null 2>&1; then
  log "Installing Composer"
  curl -fsSL https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer
  chmod +x /usr/local/bin/composer
fi

install_frankenphp

if ! id "$APP_USER" >/dev/null 2>&1; then
  log "Creating user $APP_USER"
  adduser --disabled-password --gecos "" "$APP_USER"
fi
loginctl enable-linger "$APP_USER"
systemctl start "user@$(id -u "$APP_USER").service"
for _ in 1 2 3 4 5; do
  [ -d "/run/user/$(id -u "$APP_USER")" ] && break
  sleep 1
done
if [ ! -d "/run/user/$(id -u "$APP_USER")" ]; then
  echo "FATAL: user systemd instance for ${APP_USER} did not start" >&2
  exit 1
fi

log "PostgreSQL bootstrap"
systemctl enable --now postgresql

resolve_pg_password
bootstrap_postgresql "$PG_FORCE_PASSWORD_UPDATE"

verify_post_install

log "System install complete"
log "PostgreSQL password: ${PG_PASSWORD_FILE}"
log "Next: ADMIN_EMAIL=... ADMIN_PASSWORD=... bash ${SCRIPT_DIR}/install-project.sh"
log "Or full bootstrap: ADMIN_EMAIL=... ADMIN_PASSWORD=... bash ${SCRIPT_DIR}/install.sh"
if [ -r "$PG_PASSWORD_FILE" ]; then
  log "Secret file ready at ${PG_PASSWORD_FILE}"
fi
