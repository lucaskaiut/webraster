#!/usr/bin/env bash
#
# Deploy do painel na VPS nuvemax: git pull + build da imagem + up (com escala
# do worker) + garantia das bases no MySQL.
#
# Uso (no servidor, na raiz do clone do repo):
#   bash scripts/deploy.sh

set -Eeuo pipefail
cd "$(dirname "$0")/.."

COMPOSE=(docker compose --env-file .env.prod -f docker-compose.prod.yml)

env_get() {
  grep -E "^$1=" .env.prod | tail -n1 | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true
}

env_get_file() {
  grep -E "^$2=" "$1" | tail -n1 | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true
}

log() { echo "[deploy] $*"; }

log "Atualizando código (git pull --ff-only)..."
git pull --ff-only

log "Build da imagem do painel..."
"${COMPOSE[@]}" build app

log "Subindo MySQL..."
"${COMPOSE[@]}" up -d --wait mysql

log "Garantindo base + usuário do Traccar no MySQL..."
MYSQL_ROOT_PASSWORD="$(env_get MYSQL_ROOT_PASSWORD)"
TRACCAR_DB_PASSWORD=""
if [ -f docker/traccar/.env ]; then
  TRACCAR_DB_PASSWORD="$(env_get_file docker/traccar/.env DATABASE_PASSWORD)"
fi
if [ -n "${TRACCAR_DB_PASSWORD:-}" ]; then
  "${COMPOSE[@]}" exec -T -e MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql mysql -uroot -e "
    CREATE DATABASE IF NOT EXISTS traccar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'traccar'@'%' IDENTIFIED BY '${TRACCAR_DB_PASSWORD}';
    ALTER USER 'traccar'@'%' IDENTIFIED BY '${TRACCAR_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON traccar.* TO 'traccar'@'%';
    FLUSH PRIVILEGES;"
else
  log "AVISO: DATABASE_PASSWORD do Traccar não encontrado — pulando criação da base."
fi

log "Subindo aplicação (worker x${WORKER_REPLICAS:-2})..."
"${COMPOSE[@]}" up -d --scale worker="${WORKER_REPLICAS:-2}"

log "Status:"
"${COMPOSE[@]}" ps
