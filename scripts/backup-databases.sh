#!/usr/bin/env bash
#
# Backup diário (mysqldump) das bases "webraster" e "traccar" na VPS nuvemax.
#
# Instalação do cron (como root):
#   crontab -e
#   30 3 * * * /opt/nuvemax/panel/scripts/backup-databases.sh >> /var/log/nuvemax-backup.log 2>&1
#
# Variáveis opcionais: BACKUP_DIR (padrão /opt/nuvemax/backups), RETENTION_DAYS (padrão 14).

set -Eeuo pipefail
cd "$(dirname "$0")/.."

BACKUP_DIR="${BACKUP_DIR:-/opt/nuvemax/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"

env_get() {
  grep -E "^$1=" .env.prod | tail -n1 | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true
}

MYSQL_ROOT_PASSWORD="$(env_get MYSQL_ROOT_PASSWORD)"
DB_DATABASE="$(env_get DB_DATABASE)"; DB_DATABASE="${DB_DATABASE:-webraster}"

COMPOSE=(docker compose --env-file .env.prod -f docker-compose.prod.yml)

mkdir -p "$BACKUP_DIR"

echo "[backup] Dump ${DB_DATABASE}..."
"${COMPOSE[@]}" exec -T -e MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql \
  mysqldump -uroot --single-transaction --routines --triggers "${DB_DATABASE}" \
  | gzip > "${BACKUP_DIR}/${DB_DATABASE}-${STAMP}.sql.gz"

echo "[backup] Dump traccar..."
"${COMPOSE[@]}" exec -T -e MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql \
  mysqldump -uroot --single-transaction --routines --triggers traccar \
  | gzip > "${BACKUP_DIR}/traccar-${STAMP}.sql.gz"

find "$BACKUP_DIR" -name '*.sql.gz' -mtime +"${RETENTION_DAYS}" -delete

echo "[backup] Concluído: ${BACKUP_DIR}"
