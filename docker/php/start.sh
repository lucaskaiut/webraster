#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/var/www/html"
cd "$APP_DIR"

log() { echo "[api-start] $*"; }

# ---------------------------------------------------------------------------
# 0) Scaffold: cria o projeto Laravel se ainda não existir
#    (equivalente a "composer create-project laravel/laravel ." - usa um
#    diretório temporário porque ./api já contém .env.docker)
# ---------------------------------------------------------------------------
if [ ! -f composer.json ]; then
    log "Projeto Laravel não encontrado - executando composer create-project..."
    TMP_DIR="$(mktemp -d)"
    composer create-project laravel/laravel "$TMP_DIR/app" --no-interaction --prefer-dist --no-scripts
    cp -a "$TMP_DIR/app/." "$APP_DIR/"
    rm -rf "$TMP_DIR"
    log "Projeto Laravel criado em ./api"
fi

# ---------------------------------------------------------------------------
# 1) .env (apenas se não existir)
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
    if [ -f .env.docker ]; then
        cp .env.docker .env
        log ".env criado a partir de .env.docker"
    elif [ -f .env.example ]; then
        cp .env.example .env
        log ".env criado a partir de .env.example"
    else
        log "ERRO: nenhum .env, .env.docker ou .env.example encontrado"
        exit 1
    fi
else
    log ".env já existe - mantendo"
fi

# Helper: lê valor do .env (sem sobrescrever dados existentes)
env_get() {
    local key="$1" default="${2:-}" value
    value="$(grep -E "^${key}=" .env | tail -n1 | cut -d '=' -f2- | tr -d '"' | tr -d "'" || true)"
    echo "${value:-$default}"
}

export DB_HOST="$(env_get DB_HOST mysql)"
export DB_PORT="$(env_get DB_PORT 3306)"
export DB_DATABASE="$(env_get DB_DATABASE nox_cms)"
export DB_USERNAME="$(env_get DB_USERNAME nox)"
export DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-root}"

# ---------------------------------------------------------------------------
# 2) Dependências (apenas se vendor/ não existir)
# ---------------------------------------------------------------------------
if [ ! -f vendor/autoload.php ]; then
    log "Instalando dependências do Composer..."
    composer install --no-interaction --prefer-dist --no-progress
else
    log "vendor/ já existe - pulando composer install"
fi

# ---------------------------------------------------------------------------
# 3) Aguarda o MySQL ficar disponível
# ---------------------------------------------------------------------------
log "Aguardando MySQL em ${DB_HOST}:${DB_PORT}..."
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
    if [ "$tries" -ge 90 ]; then
        log "ERRO: MySQL indisponível após ${tries} tentativas"
        exit 1
    fi
    sleep 2
done
log "MySQL disponível"

# ---------------------------------------------------------------------------
# 4) Garante o banco de dados (idempotente, não recria nem apaga)
# ---------------------------------------------------------------------------
php -r '
    $pdo = new PDO("mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT"), "root", getenv("DB_ROOT_PASSWORD"));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db = str_replace("`", "", getenv("DB_DATABASE"));
    $user = getenv("DB_USERNAME");
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if ($user !== false && $user !== "" && $user !== "root") {
        try {
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$db}`.* TO \"{$user}\"@\"%\"");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (Throwable $e) {
            fwrite(STDERR, "[api-start] AVISO: não foi possível conceder privilégios: " . $e->getMessage() . PHP_EOL);
        }
    }
'
log "Banco de dados '${DB_DATABASE}' garantido"

# ---------------------------------------------------------------------------
# 5) APP_KEY (apenas se vazia)
# ---------------------------------------------------------------------------
if [ -z "$(env_get APP_KEY)" ]; then
    log "Gerando APP_KEY..."
    php artisan key:generate --force --no-interaction
else
    log "APP_KEY já definida - pulando"
fi

# ---------------------------------------------------------------------------
# 6) Storage link (apenas se não existir)
# ---------------------------------------------------------------------------
if [ ! -e public/storage ]; then
    php artisan storage:link --no-interaction || log "AVISO: falha ao criar storage link"
else
    log "public/storage já existe - pulando storage:link"
fi

# Limpa caches para evitar configuração obsoleta em dev
php artisan optimize:clear --no-interaction >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------
# 7) Migrations + seeders (seed apenas em banco novo)
# ---------------------------------------------------------------------------
FRESH_DB="$(php -r '
    $pdo = new PDO("mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT"), "root", getenv("DB_ROOT_PASSWORD"));
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $q->execute([getenv("DB_DATABASE"), "migrations"]);
    echo ((int) $q->fetchColumn()) === 0 ? "1" : "0";
')"

log "Executando migrations..."
php artisan migrate --force --no-interaction

if [ "$FRESH_DB" = "1" ]; then
    log "Banco novo detectado - executando seeders..."
    php artisan db:seed --force --no-interaction || log "AVISO: seeders falharam (verifique os logs)"
else
    log "Banco já inicializado - pulando seeders"
fi

# ---------------------------------------------------------------------------
# 8) Permissões
# ---------------------------------------------------------------------------
HOST_UID=$(stat -c '%u' "$APP_DIR")
if [ "$HOST_UID" != "0" ] && [ "$HOST_UID" != "$(id -u www-data)" ]; then
    usermod -u "$HOST_UID" www-data 2>/dev/null || true
    log "UID do www-data ajustado para $HOST_UID (match com host)"
fi

mkdir -p storage/framework/{cache/data,sessions,testing,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
log "Permissões ajustadas (storage, bootstrap/cache)"

# ---------------------------------------------------------------------------
# 9) PHP-FPM em foreground
# ---------------------------------------------------------------------------
log "Inicialização concluída. Iniciando PHP-FPM..."
exec php-fpm
