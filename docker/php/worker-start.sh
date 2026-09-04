#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/var/www/html"
cd "$APP_DIR"

log() { echo "[worker-start] $*"; }

log "Aguardando MySQL (compartilhado com api)..."

env_get() {
    local key="$1" default="${2:-}" value
    value="$(grep -E "^${key}=" .env | tail -n1 | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true)"
    echo "${value:-$default}"
}

export DB_HOST="$(env_get DB_HOST mysql)"
export DB_PORT="$(env_get DB_PORT 3306)"
export DB_DATABASE="$(env_get DB_DATABASE nox_cms)"
export DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-root}"

tries=0
until php -r '
    try {
        new PDO("mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT"), "root", getenv("DB_ROOT_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
' 2>/dev/null; do
    tries=$((tries + 1))
    if [ "$tries" -ge 120 ]; then
        log "ERRO: MySQL indisponível após ${tries} tentativas"
        exit 1
    fi
    sleep 2
done
log "MySQL disponível. Iniciando queue worker..."

exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
