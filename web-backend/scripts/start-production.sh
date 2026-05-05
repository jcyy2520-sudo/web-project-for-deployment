#!/bin/sh
set -eu

APP_PORT="${APP_PORT:-10000}"
REVERB_BIND_HOST="${REVERB_BIND_HOST:-0.0.0.0}"
REVERB_BIND_PORT="${REVERB_SERVER_PORT:-${REVERB_PORT:-8080}}"
NGINX_TEMPLATE="/app/docker/nginx.conf.template"
NGINX_CONFIG="/etc/nginx/nginx.conf"

cleanup() {
  kill 0 >/dev/null 2>&1 || true
}

trap cleanup INT TERM EXIT

envsubst '${APP_PORT}' < "${NGINX_TEMPLATE}" > "${NGINX_CONFIG}"

php artisan config:clear >/dev/null 2>&1 || true

php-fpm -D
php artisan reverb:start --host="${REVERB_BIND_HOST}" --port="${REVERB_BIND_PORT}" &
php artisan queue:work --tries=1 --sleep=3 &
php artisan schedule:work &
nginx -g 'daemon off;' &

wait -n