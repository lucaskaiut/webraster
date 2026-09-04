#!/bin/sh
set -eu

APP_DIR="/app"
cd "$APP_DIR"

log() { echo "[web-start] $*"; }

DEV_PORT="${DEV_PORT:-5173}"
export HOST="${HOST:-0.0.0.0}"

# ---------------------------------------------------------------------------
# 0) Scaffold: cria o projeto React se ainda não existir
#    (equivalente a "npm create vite@latest . -- --template react-ts" - usa
#    um diretório temporário porque ./web já contém .env.docker/node_modules)
# ---------------------------------------------------------------------------
if [ ! -f package.json ]; then
    log "Projeto React não encontrado - executando npm create vite (react-ts)..."
    SCAFFOLD_DIR="/tmp/vite-scaffold"
    rm -rf "$SCAFFOLD_DIR"
    mkdir -p "$SCAFFOLD_DIR"
    cd "$SCAFFOLD_DIR"
    npm create vite@latest web --yes -- --template react-ts
    cd "$APP_DIR"
    cp -a "$SCAFFOLD_DIR/web/." "$APP_DIR/"
    rm -rf "$SCAFFOLD_DIR"
    log "Projeto React criado em ./web"
fi

# ---------------------------------------------------------------------------
# 1) .env (apenas se não existir)
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
    if [ -f .env.docker ]; then
        cp .env.docker .env
        log ".env criado a partir de .env.docker"
    fi
else
    log ".env já existe - mantendo"
fi

# ---------------------------------------------------------------------------
# 2) Detecta o gerenciador de pacotes
# ---------------------------------------------------------------------------
if [ -f pnpm-lock.yaml ]; then
    PM="pnpm"
elif [ -f yarn.lock ]; then
    PM="yarn"
else
    PM="npm"
fi
log "Gerenciador de pacotes detectado: ${PM}"

# ---------------------------------------------------------------------------
# 3) Instala dependências apenas quando necessário
#    (node_modules vazio ou lockfile alterado desde a última instalação)
# ---------------------------------------------------------------------------
current_hash() {
    if [ -f pnpm-lock.yaml ]; then
        md5sum pnpm-lock.yaml
    elif [ -f yarn.lock ]; then
        md5sum yarn.lock
    elif [ -f package-lock.json ]; then
        md5sum package-lock.json
    else
        md5sum package.json
    fi | awk '{print $1}'
}

STAMP="node_modules/.install-stamp"
HASH="$(current_hash)"

if [ ! -f "$STAMP" ] || [ "$(cat "$STAMP" 2>/dev/null)" != "$HASH" ]; then
    log "Instalando dependências com ${PM}..."
    case "$PM" in
        pnpm) pnpm install ;;
        yarn) yarn install ;;
        npm)
            if [ -f package-lock.json ]; then
                npm ci || npm install
            else
                npm install
            fi
            ;;
    esac
    mkdir -p node_modules
    current_hash > "$STAMP"
    log "Dependências instaladas"
else
    log "Dependências atualizadas - pulando instalação"
fi

# ---------------------------------------------------------------------------
# 4) Servidor de desenvolvimento (sem build, sem preview, escutando 0.0.0.0)
# ---------------------------------------------------------------------------
HAS_DEV="$(node -p "require('./package.json').scripts && require('./package.json').scripts.dev ? '1' : '0'" 2>/dev/null || echo 0)"

if [ "$HAS_DEV" = "1" ]; then
    log "Iniciando servidor de desenvolvimento (porta ${DEV_PORT})..."
    case "$PM" in
        pnpm) exec pnpm run dev --host 0.0.0.0 --port "$DEV_PORT" --strictPort ;;
        yarn) exec yarn dev --host 0.0.0.0 --port "$DEV_PORT" --strictPort ;;
        npm)  exec npm run dev -- --host 0.0.0.0 --port "$DEV_PORT" --strictPort ;;
    esac
else
    log "Script 'dev' não encontrado - usando 'start' (HOST=0.0.0.0 PORT=${DEV_PORT})..."
    export PORT="$DEV_PORT"
    case "$PM" in
        pnpm) exec pnpm start ;;
        yarn) exec yarn start ;;
        npm)  exec npm start ;;
    esac
fi
