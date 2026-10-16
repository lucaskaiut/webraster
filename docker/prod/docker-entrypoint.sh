#!/bin/sh
set -eu
cd /var/www/html

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache

role="${CONTAINER_ROLE:-web}"

wait_mysql() {
  echo "Waiting for MySQL..."
  i=0
  while [ "$i" -lt 90 ]; do
    if php -r "
      try {
        new PDO(
          'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . getenv('DB_DATABASE'),
          getenv('DB_USERNAME'),
          getenv('DB_PASSWORD')
        );
        exit(0);
      } catch (Throwable \$e) {
        exit(1);
      }
    " 2>/dev/null; then
      return 0
    fi
    i=$((i + 1))
    sleep 2
  done

  echo "MySQL not reachable" >&2
  exit 1
}

if [ "$role" = "reverb" ]; then
  echo "Starting Reverb..."
  wait_mysql
  exec su-exec www-data php artisan reverb:start --host=0.0.0.0 --port=8080
fi

if [ "$role" = "worker" ]; then
  wait_mysql
  tracking_queue="${TRACCAR_QUEUE:-tracking}"
  chat_queue="${CHAT_QUEUE:-chat}"
  sync_queue="${TRACCAR_SYNC_QUEUE:-sync}"
  queue_list="${tracking_queue},${chat_queue},broadcasts,default,${sync_queue}"
  exec su-exec www-data php artisan queue:work \
    --queue="${queue_list}" \
    --sleep=1 \
    --tries=3 \
    --timeout=900 \
    --max-time=3600
fi

if [ "$role" = "scheduler" ]; then
  exec su-exec www-data sh -c 'while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done'
fi

wait_mysql

echo "Running migrations..."
php artisan migrate --force || echo "WARNING: migrations failed — container will start with current schema" >&2

php artisan storage:link --no-interaction >/dev/null 2>&1 || true
php artisan optimize:clear --no-interaction >/dev/null 2>&1 || true

# Flag de saúde servida pelo nginx em /healthz (dreno do blue-green remove este
# arquivo para o Traefik parar de enviar tráfego novo antes da troca de slot).
printf 'ok' > public/healthz.txt
chown www-data:www-data public/healthz.txt

exec /usr/bin/supervisord -c /etc/supervisord.conf
