#!/usr/bin/env bash
# =============================================================================
# Webraster Backup (nuvemax)
# Dump do MySQL (bases webraster + traccar) + volumes de storage, compacta,
# envia para Cloudflare R2 e remove arquivos locais/R2 com mais de
# RETENTION_HOURS horas.
#
# Espelha o padrão das stacks do contabo (ex.: financeiro-borcath/backup.sh):
# mesmo bucket R2, prefixo por aplicação, arquivo único <app>_backup_<TS>.tar.gz.
#
# Uso: scripts/backup.sh [/caminho/para/.backup.env]
# Sem argumento, lê o .backup.env na raiz do repo (R2_*).
#
# Cron (root):
#   0 */8 * * * /opt/nuvemax/panel/scripts/backup.sh >> /opt/nuvemax/backups/cron.log 2>&1
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
TIMESTAMP="$(date -u +%Y%m%d_%H%M%S)"

export PATH="${HOME}/.local/bin:/usr/local/bin:${PATH}"

BACKUP_ROOT="/opt/nuvemax/backups"
RETENTION_HOURS=24

# ---------------------------------------------------------------------------
# Carrega .backup.env (primeiro argumento ou .backup.env na raiz do repo)
# ---------------------------------------------------------------------------
ENV_FILE="${1:-${REPO_DIR}/.backup.env}"
if [[ -f "$ENV_FILE" ]]; then
  set -a
  source "$ENV_FILE"
  set +a
fi

BACKUP_ROOT="${BACKUP_ROOT:-/opt/nuvemax/backups}"
RETENTION_HOURS="${RETENTION_HOURS:-24}"
BACKUP_DIR="${BACKUP_ROOT}/tmp_${TIMESTAMP}"
LOG_FILE="${BACKUP_ROOT}/backup.log"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG_FILE"; }
die() { log "[ERRO] $*"; exit 1; }

check_var() {
  local name="$1" val="${!1:-}"
  if [[ -z "$val" ]]; then
    echo "[ERRO] Variável obrigatória não definida: $name" | tee -a "$LOG_FILE"
    return 1
  fi
}

# ---------------------------------------------------------------------------
# Configuração (sobrescrevível via .backup.env)
# ---------------------------------------------------------------------------
MYSQL_CONTAINER="${MYSQL_CONTAINER:-$(docker ps --filter name=nuvemax-panel-mysql --format '{{.Names}}' 2>/dev/null | head -1)}"
STORAGE_VOLUME="${STORAGE_VOLUME:-nuvemax-panel_webraster_storage}"
PRIVATE_VOLUME="${PRIVATE_VOLUME:-nuvemax-panel_webraster_private}"

# R2 (validado sob demanda — opcional)
# R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_ENDPOINT, R2_BUCKET

# ---------------------------------------------------------------------------
# Pre-flight
# ---------------------------------------------------------------------------
mkdir -p "$BACKUP_DIR" "$BACKUP_ROOT"
log "Iniciando backup em $BACKUP_DIR"

MYSQL_PASSWORD="${MYSQL_PASSWORD:-$(grep -E '^MYSQL_ROOT_PASSWORD=' "${REPO_DIR}/.env.prod" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '"' | tr -d "'")}"
[[ -n "$MYSQL_PASSWORD" ]] || die "MYSQL_ROOT_PASSWORD não encontrada (defina no .backup.env ou no .env.prod)"
[[ -n "$MYSQL_CONTAINER" ]] || die "container do MySQL não encontrado"

# ===========================================================================
# 1. MySQL dumps
# ===========================================================================
log "1/5 MySQL dump (bases: webraster, traccar)..."

for db in webraster traccar; do
  docker exec -e MYSQL_PWD="$MYSQL_PASSWORD" "$MYSQL_CONTAINER" \
    mysqldump -uroot \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    "$db" \
    > "$BACKUP_DIR/mysql_${db}.sql" \
    || die "Falha no mysqldump de $db"
  log "   $db: $(du -h "$BACKUP_DIR/mysql_${db}.sql" | cut -f1)"
done

# ===========================================================================
# 2. Storage (volumes persistentes)
# ===========================================================================
log "2/5 Storage (volumes webraster_storage + webraster_private)..."

docker run --rm -v "${STORAGE_VOLUME}":/data:ro alpine tar czf - -C /data . \
  > "$BACKUP_DIR/storage.tar.gz" \
  || die "Falha ao copiar storage"
docker run --rm -v "${PRIVATE_VOLUME}":/data:ro alpine tar czf - -C /data . \
  > "$BACKUP_DIR/private.tar.gz" \
  || die "Falha ao copiar private"

log "   storage: $(du -h "$BACKUP_DIR/storage.tar.gz" | cut -f1) | private: $(du -h "$BACKUP_DIR/private.tar.gz" | cut -f1)"

# ===========================================================================
# 3. Compactar
# ===========================================================================
log "3/5 Compactando..."

ARCHIVE_NAME="webraster_backup_${TIMESTAMP}.tar.gz"
ARCHIVE_PATH="${BACKUP_ROOT}/${ARCHIVE_NAME}"

tar czf "$ARCHIVE_PATH" -C "$BACKUP_DIR" . \
  || die "Falha ao criar arquivo compactado"

log "   Arquivo: $ARCHIVE_NAME ($(du -h "$ARCHIVE_PATH" | cut -f1))"

# ===========================================================================
# 4. Upload para R2 + limpeza de arquivos antigos no bucket
# ===========================================================================
R2_OK=true
for var in R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY R2_ENDPOINT R2_BUCKET; do
  if ! check_var "$var"; then R2_OK=false; fi
done

if $R2_OK && command -v rclone &>/dev/null; then
  log "4/5 Enviando para R2..."

  export RCLONE_CONFIG_S3_TYPE=s3
  export RCLONE_CONFIG_S3_PROVIDER=Cloudflare
  export RCLONE_CONFIG_S3_ACCESS_KEY_ID="$R2_ACCESS_KEY_ID"
  export RCLONE_CONFIG_S3_SECRET_ACCESS_KEY="$R2_SECRET_ACCESS_KEY"
  export RCLONE_CONFIG_S3_ENDPOINT="$R2_ENDPOINT"
  export RCLONE_CONFIG_S3_ACL=private

  if rclone copyto "$ARCHIVE_PATH" "s3:${R2_BUCKET}/webraster/${ARCHIVE_NAME}" \
      --s3-no-check-bucket 2>&1 | tail -3; then
    log "   Upload R2: OK"
  else
    die "Falha no upload para R2"
  fi

  log "5/5 Limpando arquivos antigos no R2 (>${RETENTION_HOURS}h)..."

  # Trailing slash é obrigatório: sem ele o rclone trata como single file e rejeita --min-age
  if rclone lsf "s3:${R2_BUCKET}/webraster/" --s3-no-check-bucket --min-age "${RETENTION_HOURS}h" 2>/dev/null | grep -q .; then
    rclone delete "s3:${R2_BUCKET}/webraster/" \
      --s3-no-check-bucket \
      --min-age "${RETENTION_HOURS}h" 2>&1 \
      | while read -r f; do [[ -n "$f" ]] && log "   Removido R2: $f"; done
    log "   Limpeza R2: concluída"
  else
    log "   Limpeza R2: nada a remover"
  fi
else
  log "4/5 Upload R2: pulado (credenciais não configuradas ou rclone indisponível)"
  log "5/5 Limpeza R2: pulada"
fi

# ===========================================================================
# Cleanup: remove diretório temporário e backups locais > RETENTION_HOURS
# ===========================================================================
rm -rf "$BACKUP_DIR"
log "Limpando backups locais com mais de ${RETENTION_HOURS}h..."

find "$BACKUP_ROOT" -maxdepth 1 -name 'webraster_backup_*.tar.gz' -mmin "+$((RETENTION_HOURS * 60))" -print -delete \
  | while read -r f; do log "   Removido: $f"; done

# ===========================================================================
# Done
# ===========================================================================
log "Backup concluído com sucesso: $ARCHIVE_NAME"

LOCAL_COUNT=$(find "$BACKUP_ROOT" -maxdepth 1 -name 'webraster_backup_*.tar.gz' | wc -l)
LOCAL_SIZE=$(du -sh "$BACKUP_ROOT" 2>/dev/null | cut -f1)
log "Backups locais: ${LOCAL_COUNT} arquivos, ${LOCAL_SIZE}"
