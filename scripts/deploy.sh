#!/usr/bin/env bash
#
# Deploy do painel na VPS nuvemax — BLUE-GREEN (sem downtime de HTTP).
#
# Fluxo:
#   1. git pull + build da imagem (tag :latest + :<sha>)
#   2. garante MySQL e a base do Traccar
#   3. sobe o slot INATIVO (app-a/app-b) e espera ficar healthy
#   4. flip atômico da rota da API no Traefik (arquivo dinâmico) para o slot
#      novo + validação de ponta a ponta; em caso de falha, a rota volta para
#      o slot antigo e o deploy aborta
#   5. após 10s de graça (requisições em andamento), encerra o slot antigo
#   6. recria worker/scheduler/reverb com a imagem nova
#
# O roteamento da API NÃO usa labels nos containers: vive em um arquivo
# dinâmico do Traefik gerado aqui (docker/gateway/traefik/dynamic/webraster-api.yml).
# Isso evita o erro "Service defined multiple times with different
# configurations" quando containers antigo/novo coexistem com labels diferentes.
#
# Uso (no servidor, na raiz do clone do repo):
#   bash scripts/deploy.sh

set -Eeuo pipefail
cd "$(dirname "$0")/.."

# Exclusão mútua: nunca dois deploys ao mesmo tempo.
exec 9>/var/lock/nuvemax-panel-deploy.lock
if ! flock -n 9; then
  echo "[deploy] Já existe um deploy em andamento — abortando."
  exit 1
fi

COMPOSE=(docker compose --env-file .env.prod -f docker-compose.prod.yml)
STATE_FILE=.deploy-active
ROUTE_FILE=docker/gateway/traefik/dynamic/webraster-api.yml
WORKER_REPLICAS="${WORKER_REPLICAS:-2}"

log() { echo "[deploy] $*"; }

env_get() {
  grep -E "^$1=" .env.prod | tail -n1 | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true
}

env_get_file() {
  grep -E "^$2=" "$1" | tail -n1 | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true
}

slot_status() { # $1 = a|b → healthy|starting|unhealthy|missing|…
  local cid status
  cid="$("${COMPOSE[@]}" --profile "$1" ps -q "app-$1" 2>/dev/null || true)"
  if [ -z "$cid" ]; then
    echo "missing"
    return
  fi
  status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$cid" 2>/dev/null || echo missing)"
  echo "$status"
}

render_route() { # $1 = a|b — aponta a rota da API para o slot (escrita atômica)
  local domain tmp
  domain="$(env_get API_DOMAIN)"
  tmp="${ROUTE_FILE}.tmp"
  cat > "$tmp" <<YAML
# GERADO POR scripts/deploy.sh — não editar à mão.
http:
  routers:
    webraster-api:
      rule: Host(\`${domain}\`)
      entryPoints:
        - websecure
      service: webraster-api
      priority: 1000
      tls:
        certResolver: letsencrypt
  services:
    webraster-api:
      loadBalancer:
        healthCheck:
          path: /healthz
          interval: 5s
          timeout: 4s
        servers:
          - url: http://app-$1:80
YAML
  mv "$tmp" "$ROUTE_FILE"
}

check_up() {
  local domain code
  domain="$(env_get API_DOMAIN)"
  code="$(curl -sk -o /dev/null -m 10 -w '%{http_code}' --resolve "${domain}:443:127.0.0.1" "https://${domain}/up" || true)"
  echo "$code"
}

# ---------------------------------------------------------------------------
# 1) Código + imagem
# ---------------------------------------------------------------------------
log "Atualizando código (git pull --ff-only)..."
git pull --ff-only

SHA="$(git rev-parse --short HEAD)"
log "Build da imagem (commit ${SHA})..."
"${COMPOSE[@]}" build app-a
docker tag local/webraster-api:latest "local/webraster-api:${SHA}"
log "Imagem: local/webraster-api:latest (também tagueada como :${SHA})"

# ---------------------------------------------------------------------------
# 2) MySQL + base do Traccar
# ---------------------------------------------------------------------------
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

# ---------------------------------------------------------------------------
# 3) Qual slot está ativo?
# ---------------------------------------------------------------------------
ACTIVE=""
if [ -f "$STATE_FILE" ]; then
  ACTIVE="$(cat "$STATE_FILE" 2>/dev/null || true)"
fi
if [ "$ACTIVE" != "a" ] && [ "$ACTIVE" != "b" ]; then
  ACTIVE=""
  for slot in a b; do
    if [ "$(slot_status "$slot")" != "missing" ]; then
      ACTIVE="$slot"
    fi
  done
fi
if [ "$ACTIVE" = "a" ]; then TARGET="b"; else TARGET="a"; fi

log "Slot ativo: ${ACTIVE:-nenhum} → deploy no slot ${TARGET}"

# ---------------------------------------------------------------------------
# 4) Sobe o slot novo (a rota atual continua atendendo)
# ---------------------------------------------------------------------------
"${COMPOSE[@]}" --profile "$TARGET" rm -sf "app-${TARGET}" >/dev/null 2>&1 || true
"${COMPOSE[@]}" --profile "$TARGET" up -d "app-${TARGET}"

log "Aguardando app-${TARGET} ficar healthy..."
status=""
for _ in $(seq 1 90); do
  status="$(slot_status "$TARGET")"
  case "$status" in
    healthy) break ;;
    unhealthy)
      log "ERRO: app-${TARGET} ficou unhealthy — deploy abortado (nada foi alterado)."
      exit 1
      ;;
  esac
  sleep 2
done
if [ "$status" != "healthy" ]; then
  log "ERRO: timeout aguardando app-${TARGET} (status=${status}) — deploy abortado."
  exit 1
fi
log "app-${TARGET} healthy."

# ---------------------------------------------------------------------------
# 5) Flip atômico da rota + validação (com rollback)
# ---------------------------------------------------------------------------
log "Apontando a rota da API para app-${TARGET}..."
render_route "$TARGET"
sleep 2

HTTP_CODE="$(check_up)"
if [ "$HTTP_CODE" != "200" ]; then
  if [ -n "$ACTIVE" ] && [ "$ACTIVE" != "$TARGET" ]; then
    log "ERRO: /up retornou ${HTTP_CODE} — revertendo a rota para app-${ACTIVE}."
    render_route "$ACTIVE"
  fi
  log "Deploy abortado."
  exit 1
fi
log "Checagem HTTP via Traefik OK (${HTTP_CODE}) após a troca de rota."

# ---------------------------------------------------------------------------
# 6) Encerra o slot antigo (após graça para requisições em andamento)
# ---------------------------------------------------------------------------
if [ -n "$ACTIVE" ] && [ "$ACTIVE" != "$TARGET" ]; then
  log "Aguardando 10s antes de encerrar app-${ACTIVE}..."
  sleep 10
  "${COMPOSE[@]}" --profile "$ACTIVE" stop -t 30 "app-${ACTIVE}" >/dev/null 2>&1 || true
  "${COMPOSE[@]}" --profile "$ACTIVE" rm -sf "app-${ACTIVE}" >/dev/null 2>&1 || true
fi
echo "$TARGET" > "$STATE_FILE"

# ---------------------------------------------------------------------------
# 7) Worker / scheduler / reverb com a imagem nova
# ---------------------------------------------------------------------------
log "Recriando worker (x${WORKER_REPLICAS}), scheduler e reverb..."
"${COMPOSE[@]}" up -d --force-recreate --scale worker="${WORKER_REPLICAS}" worker scheduler reverb

log "Status:"
"${COMPOSE[@]}" ps
