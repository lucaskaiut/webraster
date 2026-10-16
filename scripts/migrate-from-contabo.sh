#!/usr/bin/env bash
#
# Migração de dados contabo -> nuvemax (bases MySQL + volumes de storage).
#
# Executa na SUA máquina, usando os aliases ssh "contabo" e "nuvemax".
#
#   bash scripts/migrate-from-contabo.sh
#
# IMPORTANTE: o contabo está em produção (inclusive com outras aplicações) e
# NUNCA é modificado por este script — a leitura é feita só por dumps
# consistentes (mysqldump --single-transaction, sem locks). Pode ser repetido
# quantas vezes quiser; cada execução substitui os dados da nuvemax pelos
# dados mais recentes do contabo.
#
# O que o script faz:
#   1. pré-checagem de conectividade e espaço
#   2. dumps do contabo (webraster + traccar) para arquivos locais + auditoria
#      de domínios antigos nos dados + cópia para a nuvemax
#   3. backup de segurança do estado atual da nuvemax (bases + volumes)
#   4. janela: para os serviços na nuvemax (nunca no contabo)
#   5. restore das bases + sincronização dos volumes de storage
#   6. re-sobe a aplicação e valida contagens de linhas tabela a tabela
#
# Worker e scheduler da nuvemax ficam PARADOS ao final (o contabo segue
# processando em produção — evita rotinas agendadas duplicadas). Ative-os
# quando decidir a virada (ver docs/migracao-dados-contabo.md).
#
# Artefatos locais ficam em /tmp/nuvemax-migration (sobrescreva com MIGRATION_TMP).

set -Eeuo pipefail

LOCAL_TMP="${MIGRATION_TMP:-/tmp/nuvemax-migration}"
PANEL_DIR=/opt/nuvemax/panel
BACKUP_DIR=/opt/nuvemax/backups
REMOTE_TMP=/tmp/nuvemax-migration
TS="$(date +%Y%m%d-%H%M%S)"

log()  { echo "[migracao] $*"; }
fail() { echo "[migracao] ERRO: $*" >&2; exit 1; }

mkdir -p "$LOCAL_TMP"
chmod 700 "$LOCAL_TMP"

# ---------------------------------------------------------------------------
# 1) Pré-checagem
# ---------------------------------------------------------------------------
log "Pré-checagem: conectividade..."
ssh -o ConnectTimeout=10 -o BatchMode=yes contabo true || fail "sem acesso ssh ao contabo"
ssh -o ConnectTimeout=10 -o BatchMode=yes nuvemax true || fail "sem acesso ssh à nuvemax"

log "Pré-checagem: espaço em disco na nuvemax..."
FREE_MB=$(ssh nuvemax "df -m / | awk 'NR==2{print \$4}'")
[ "${FREE_MB:-0}" -gt 8192 ] || fail "pouco espaço na nuvemax (${FREE_MB}MB livres; precisa de >8GB)"

log "Origem (contabo):"
ssh contabo 'bash -s' <<'EOS'
set -Eeuo pipefail
SHARED=$(docker ps -q -f name=infra-shared_mysql)
TRC=$(docker ps -q -f name=infra-app-traccar_database)
docker exec "$SHARED" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -e "select concat(\"  webraster: \", round(sum(data_length+index_length)/1024/1024,1),\" MB, \",count(*),\" tabelas\") from information_schema.tables where table_schema=\"webraster\""'
PW=$(docker service inspect infra-app-traccar_traccar --format '{{range .Spec.TaskTemplate.ContainerSpec.Env}}{{println .}}{{end}}' | grep '^DATABASE_PASSWORD=' | cut -d= -f2-)
docker exec "$TRC" sh -c "MYSQL_PWD='$PW' mysql -utraccar -N -e 'select concat(\"  traccar: \", round(sum(data_length+index_length)/1024/1024,1),\" MB, \",count(*),\" tabelas\") from information_schema.tables where table_schema=\"traccar\"'"
EOS

# ---------------------------------------------------------------------------
# 2) Dumps do contabo -> arquivos locais (somente leitura no contabo)
# ---------------------------------------------------------------------------
log "Dump webraster (contabo)..."
ssh contabo 'bash -s' <<'EOS' | gzip -1 > "$LOCAL_TMP/webraster-$TS.sql.gz"
set -Eeuo pipefail
SHARED=$(docker ps -q -f name=infra-shared_mysql)
docker exec "$SHARED" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -uroot --single-transaction --routines --triggers --events --default-character-set=utf8mb4 webraster'
EOS
ls -lh "$LOCAL_TMP/webraster-$TS.sql.gz"

log "Dump traccar (contabo)..."
ssh contabo 'bash -s' <<'EOS' | gzip -1 > "$LOCAL_TMP/traccar-$TS.sql.gz"
set -Eeuo pipefail
TRC=$(docker ps -q -f name=infra-app-traccar_database)
PW=$(docker service inspect infra-app-traccar_traccar --format '{{range .Spec.TaskTemplate.ContainerSpec.Env}}{{println .}}{{end}}' | grep '^DATABASE_PASSWORD=' | cut -d= -f2-)
docker exec "$TRC" sh -c "MYSQL_PWD='$PW' mysqldump -utraccar --single-transaction --no-tablespaces --default-character-set=utf8mb4 traccar"
EOS
ls -lh "$LOCAL_TMP/traccar-$TS.sql.gz"

log "Auditoria: referências aos domínios antigos dentro dos dados (manter/revisar depois)..."
for f in webraster traccar; do
  {
    echo "  == $f: ocorrências =="
    zgrep -aoh -iE "noxtecnologias\.com\.br|dispositivos\.nuvemax|webraster\.nuvemax" "$LOCAL_TMP/$f-$TS.sql.gz" 2>/dev/null | sort | uniq -c | sort -rn | head -5 || echo "    (nenhuma)"
  } | sed 's/^/  /'
done

log "Backup dos volumes de storage (contabo)..."
ssh contabo 'bash -s' <<'EOS' | gzip -1 > "$LOCAL_TMP/storage-$TS.tar.gz"
set -Eeuo pipefail
docker run --rm -v webraster_webraster_storage:/data:ro alpine tar cf - -C /data .
EOS
ssh contabo 'bash -s' <<'EOS' | gzip -1 > "$LOCAL_TMP/private-$TS.tar.gz"
set -Eeuo pipefail
docker run --rm -v webraster_webraster_private:/data:ro alpine tar cf - -C /data .
EOS
ls -lh "$LOCAL_TMP"/*.tar.gz

log "Enviando artefatos para a nuvemax..."
ssh nuvemax "mkdir -p $REMOTE_TMP && chmod 700 $REMOTE_TMP"
scp -q "$LOCAL_TMP/webraster-$TS.sql.gz" "$LOCAL_TMP/traccar-$TS.sql.gz" \
       "$LOCAL_TMP/storage-$TS.tar.gz" "$LOCAL_TMP/private-$TS.tar.gz" \
       "nuvemax:$REMOTE_TMP/"

# ---------------------------------------------------------------------------
# 3) Backup de segurança do estado atual da nuvemax
# ---------------------------------------------------------------------------
log "Backup de segurança (pré-migração) das bases e volumes da nuvemax..."
ssh nuvemax "TS='$TS' bash -s" <<'EOS'
set -Eeuo pipefail
cd /opt/nuvemax/panel
mkdir -p /opt/nuvemax/backups
PW=$(grep -E '^MYSQL_ROOT_PASSWORD=' .env.prod | tail -n1 | cut -d= -f2-)
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$PW" mysql sh -c 'mysqldump -uroot --single-transaction webraster' | gzip -1 > "/opt/nuvemax/backups/pre-migration-$TS-webraster.sql.gz"
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$PW" mysql sh -c 'mysqldump -uroot --single-transaction traccar' | gzip -1 > "/opt/nuvemax/backups/pre-migration-$TS-traccar.sql.gz"
docker run --rm -v nuvemax-panel_webraster_storage:/data:ro alpine tar czf - -C /data . > "/opt/nuvemax/backups/pre-migration-$TS-storage.tar.gz"
docker run --rm -v nuvemax-panel_webraster_private:/data:ro alpine tar czf - -C /data . > "/opt/nuvemax/backups/pre-migration-$TS-private.tar.gz"
ls -lh /opt/nuvemax/backups/ | grep "pre-migration-$TS"
EOS

# ---------------------------------------------------------------------------
# 4) Janela: para os serviços na nuvemax
# ---------------------------------------------------------------------------
ACTIVE=$(ssh nuvemax "cat $PANEL_DIR/.deploy-active 2>/dev/null || echo a")
log "Slot ativo na nuvemax: ${ACTIVE}. Parando serviços (janela de migração)..."
log "AVISO: o monitor (Uptime Kuma) vai acusar indisponibilidade durante a janela."
ssh nuvemax "cd $PANEL_DIR && \
  docker compose --env-file .env.prod -f docker-compose.prod.yml --profile $ACTIVE stop app-$ACTIVE worker scheduler reverb && \
  docker compose --env-file docker/traccar/.env -f docker/traccar/docker-compose.yml stop traccar"

# ---------------------------------------------------------------------------
# 5) Restore das bases na nuvemax
# ---------------------------------------------------------------------------
log "Restaurando base webraster na nuvemax..."
ssh nuvemax "TS='$TS' bash -s" <<'EOS'
set -Eeuo pipefail
cd /opt/nuvemax/panel
PW=$(grep -E '^MYSQL_ROOT_PASSWORD=' .env.prod | tail -n1 | cut -d= -f2-)
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$PW" mysql \
  mysql -uroot -e "DROP DATABASE IF EXISTS webraster; CREATE DATABASE webraster CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "/tmp/nuvemax-migration/webraster-$TS.sql.gz" | \
  docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$PW" mysql \
  mysql -uroot webraster
echo "  webraster restaurado."
EOS

log "Restaurando base traccar na nuvemax..."
ssh nuvemax "TS='$TS' bash -s" <<'EOS'
set -Eeuo pipefail
cd /opt/nuvemax/panel
PW=$(grep -E '^MYSQL_ROOT_PASSWORD=' .env.prod | tail -n1 | cut -d= -f2-)
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$PW" mysql \
  mysql -uroot -e "DROP DATABASE IF EXISTS traccar; CREATE DATABASE traccar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "/tmp/nuvemax-migration/traccar-$TS.sql.gz" | \
  docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$PW" mysql \
  mysql -uroot traccar
echo "  traccar restaurado."
EOS

# ---------------------------------------------------------------------------
# 6) Sincronização dos volumes de storage
# ---------------------------------------------------------------------------
log "Sincronizando volumes de storage (public + private)..."
ssh nuvemax "TS='$TS' bash -s" <<'EOS'
set -Eeuo pipefail
for pair in "nuvemax-panel_webraster_storage:storage" "nuvemax-panel_webraster_private:private"; do
  VOL="${pair%%:*}"; FILE="${pair##*:}"
  docker volume inspect "$VOL" >/dev/null 2>&1 || { echo "volume $VOL não existe" >&2; exit 1; }
  gunzip -c "/tmp/nuvemax-migration/$FILE-$TS.tar.gz" | \
    docker run --rm -i -v "$VOL:/data" alpine sh -c 'find /data -mindepth 1 -delete; tar xf - -C /data'
  echo "  $VOL sincronizado."
done
EOS

# ---------------------------------------------------------------------------
# 7) Sobe os serviços na nuvemax
# ---------------------------------------------------------------------------
log "Subindo aplicação e Traccar na nuvemax..."
ssh nuvemax "cd $PANEL_DIR && \
  docker compose --env-file docker/traccar/.env -f docker/traccar/docker-compose.yml start traccar && \
  docker compose --env-file .env.prod -f docker-compose.prod.yml --profile $ACTIVE start app-$ACTIVE reverb"
log "worker e scheduler ficaram PARADOS de propósito (o contabo continua"
log "processando em produção — evita rotinas agendadas duplicadas)."
ssh nuvemax "touch $PANEL_DIR/.workers-paused"

log "Aguardando o app e o traccar ficarem saudáveis..."
for _ in $(seq 1 60); do
  OK=1
  ssh nuvemax "docker inspect -f '{{.State.Health.Status}}' nuvemax-panel-app-$ACTIVE-1 nuvemax-traccar-traccar-1 2>/dev/null" | grep -qv healthy && OK=0
  [ "$OK" = 1 ] && break
  sleep 3
done

# ---------------------------------------------------------------------------
# 8) Validação: contagens por tabela (origem x destino)
# ---------------------------------------------------------------------------
log "Coletando contagens do contabo (origem)..."
ssh contabo 'bash -s' <<'EOS' > "$LOCAL_TMP/counts-$TS-source.txt"
set -Eeuo pipefail
SHARED=$(docker ps -q -f name=infra-shared_mysql)
TRC=$(docker ps -q -f name=infra-app-traccar_database)
RPW=$(docker exec "$SHARED" sh -c 'printenv MYSQL_ROOT_PASSWORD')
TPW=$(docker service inspect infra-app-traccar_traccar --format '{{range .Spec.TaskTemplate.ContainerSpec.Env}}{{println .}}{{end}}' | grep '^DATABASE_PASSWORD=' | cut -d= -f2-)
counts() { # $1=cid $2=user $3=pass $4=db
  local db="$4" query="" t
  local tables
  tables=$(docker exec "$1" sh -c "MYSQL_PWD='$3' mysql -u$2 -N -e 'SHOW FULL TABLES WHERE Table_type=\"BASE TABLE\"' $db" | cut -f1)
  for t in $tables; do
    [ -n "$query" ] && query="$query UNION ALL "
    query="$query SELECT '$db.$t', COUNT(*) FROM \`$t\`"
  done
  printf '%s\n' "$query" | docker exec -i "$1" sh -c "MYSQL_PWD='$3' mysql -u$2 -N $db"
}
counts "$SHARED" root "$RPW" webraster
counts "$TRC" traccar "$TPW" traccar
EOS
sort -o "$LOCAL_TMP/counts-$TS-source.txt" "$LOCAL_TMP/counts-$TS-source.txt"

log "Coletando contagens da nuvemax (destino)..."
ssh nuvemax 'bash -s' <<'EOS' > "$LOCAL_TMP/counts-$TS-target.txt"
set -Eeuo pipefail
cd /opt/nuvemax/panel
RPW=$(grep -E '^MYSQL_ROOT_PASSWORD=' .env.prod | tail -n1 | cut -d= -f2-)
counts() { # $1=db
  local db="$1" query="" t tables
  tables=$(docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$RPW" mysql \
    mysql -uroot -N -e "SHOW FULL TABLES WHERE Table_type=\"BASE TABLE\"" "$db" | cut -f1)
  for t in $tables; do
    [ -n "$query" ] && query="$query UNION ALL "
    query="$query SELECT '$db.$t', COUNT(*) FROM \`$t\`"
  done
  printf '%s\n' "$query" | docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$RPW" mysql \
    mysql -uroot -N "$db"
}
counts webraster
counts traccar
EOS
sort -o "$LOCAL_TMP/counts-$TS-target.txt" "$LOCAL_TMP/counts-$TS-target.txt"

log "Comparando contagens tabela a tabela..."
if diff -u "$LOCAL_TMP/counts-$TS-source.txt" "$LOCAL_TMP/counts-$TS-target.txt" > "$LOCAL_TMP/counts-$TS.diff"; then
  TABLES=$(wc -l < "$LOCAL_TMP/counts-$TS-source.txt")
  log "OK: contagens idênticas em $TABLES tabelas."
else
  log "ATENÇÃO: diferenças encontradas (revisar $LOCAL_TMP/counts-$TS.diff):"
  head -30 "$LOCAL_TMP/counts-$TS.diff"
fi

log "Checando /up na nuvemax..."
ssh nuvemax 'curl -sk -o /dev/null -m 10 -w "  /up -> %{http_code}\n" --resolve new-webraster-api.nuvemax.srv.br:443:127.0.0.1 https://new-webraster-api.nuvemax.srv.br/up'

# ---------------------------------------------------------------------------
# 9) Resumo + próximos passos
# ---------------------------------------------------------------------------
cat <<SUMMARY

[migracao] ==================== RESUMO ====================
Dumps locais:      $LOCAL_TMP/*-$TS.*
Backups pré:       nuvemax:$BACKUP_DIR/pre-migration-$TS-*
Estado nuvemax:    app-$ACTIVE + reverb + traccar rodando; worker/scheduler PARADOS
Contabo:           intocado (somente dumps de leitura, produção segue normal)

Próximos passos (quando decidir a virada — ver docs/migracao-dados-contabo.md):
  1. Rodar este script de novo para levar os dados mais recentes.
  2. Validar painel/traccar na nuvemax (login, mapa, posições, arquivos).
  3. Virar o DNS: webraster-api, webraster-ws e dispositivos -> 185.194.219.192.
  4. Ajustar domínios no .env.prod e no docker/traccar/.env e rodar: bash scripts/deploy.sh
  5. Atualizar os VITE_* na Vercel e rebuildar o frontend.
  6. Ativar o processamento na nuvemax (só quando o contabo deixar de processar
     estes dados):
       ssh nuvemax 'cd /opt/nuvemax/panel && rm -f .workers-paused && docker compose --env-file .env.prod -f docker-compose.prod.yml start worker scheduler'
     (o arquivo .workers-paused também segura os deploys automáticos — enquanto
     ele existir, o deploy recria worker/scheduler sem iniciar.)
  7. O contabo permanece rodando (nunca é desligado por este script).
======================================================
SUMMARY
