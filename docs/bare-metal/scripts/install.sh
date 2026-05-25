#!/usr/bin/env bash
#
# One-shot bare-metal bootstrap for news-aggregator on Debian 13.
# Run as root.
#
# Recommended (avoids broken copy/paste from chat clients):
#   curl -fsSL https://raw.githubusercontent.com/tony-stark-eth/news-aggregator/main/docs/bare-metal/scripts/install.sh -o /root/install.sh
#   ADMIN_EMAIL=admin@local ADMIN_PASSWORD=changeme bash /root/install.sh
#
# Or after cloning this repository:
#   ADMIN_EMAIL=admin@local ADMIN_PASSWORD=changeme bash docs/bare-metal/scripts/install.sh
#
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/tony-stark-eth/news-aggregator.git}"
INSTALL_BRANCH="${INSTALL_BRANCH:-main}"
CLONE_DIR="${CLONE_DIR:-/tmp/news}"
APP_USER="${APP_USER:-app}"
ADMIN_EMAIL="${ADMIN_EMAIL:-}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"

log() { printf '\033[1;36m[%s]\033[0m %s\n' "$(date +%H:%M:%S)" "$*"; }
err() { printf '\033[1;31m[ERR]\033[0m %s\n' "$*" >&2; exit 1; }

[ "$EUID" -eq 0 ] || err "Run as root"

if [ -z "$ADMIN_EMAIL" ] || [ -z "$ADMIN_PASSWORD" ]; then
  err "ADMIN_EMAIL and ADMIN_PASSWORD are required (export ADMIN_EMAIL=... ADMIN_PASSWORD=...)"
fi

ensure_git() {
  if command -v git >/dev/null 2>&1; then
    return 0
  fi
  log "Installing git"
  apt-get update
  apt-get install -y git
}

resolve_script_dir() {
  local src script_dir

  src=${BASH_SOURCE[0]}
  if [ ! -f "$src" ]; then
    return 1
  fi
  script_dir=$(cd "$(dirname "$src")" && pwd)
  if [ -f "${script_dir}/install-system.sh" ] && [ -f "${script_dir}/install-project.sh" ]; then
    printf '%s' "$script_dir"
    return 0
  fi
  return 1
}

SCRIPT_DIR=$(resolve_script_dir || true)

if [ -z "$SCRIPT_DIR" ]; then
  ensure_git
  if [ ! -d "${CLONE_DIR}/.git" ]; then
    log "Cloning ${INSTALL_BRANCH} from ${REPO_URL} into ${CLONE_DIR}"
    git clone -b "$INSTALL_BRANCH" --depth 1 "$REPO_URL" "$CLONE_DIR"
  fi
  SCRIPT_DIR="${CLONE_DIR}/docs/bare-metal/scripts"
fi

[ -f "${SCRIPT_DIR}/install-system.sh" ] || err "install-system.sh not found in ${SCRIPT_DIR}"
[ -f "${SCRIPT_DIR}/install-project.sh" ] || err "install-project.sh not found in ${SCRIPT_DIR}"

log "Using scripts from ${SCRIPT_DIR}"

bash "${SCRIPT_DIR}/install-system.sh"

log "Running project bootstrap as ${APP_USER}"
su - "$APP_USER" -c "REPO_URL=$(printf '%q' "$REPO_URL") ADMIN_EMAIL=$(printf '%q' "$ADMIN_EMAIL") ADMIN_PASSWORD=$(printf '%q' "$ADMIN_PASSWORD") bash $(printf '%q' "${SCRIPT_DIR}/install-project.sh")"

log "Bootstrap complete"
