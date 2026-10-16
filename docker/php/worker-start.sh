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
export DB_DATABASE="$(env_get DB_DATABASE webraster)"
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
log "MySQL disponível. Iniciando scheduler + queue worker..."

CHAT_QUEUE="$(env_get CHAT_QUEUE chat)"
TRACKING_QUEUE="$(env_get TRACCAR_QUEUE tracking)"
SYNC_QUEUE="$(env_get TRACCAR_SYNC_QUEUE sync)"
QUEUE_LIST="${TRACKING_QUEUE},${CHAT_QUEUE},broadcasts,default,${SYNC_QUEUE}"

php artisan schedule:work &
exec php artisan queue:work --queue="${QUEUE_LIST}" --sleep=1 --tries=3 --timeout=900 --max-time=3600
