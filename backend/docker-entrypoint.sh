#!/usr/bin/env bash
#
# One image, two roles.
#   app     prepares everything, then serves the API
#   worker  waits for the app, then consumes the queue
#
# Only the app role writes to the shared mount, so the two never race.
#
set -euo pipefail

APP_ROLE="${APP_ROLE:-app}"
READY_MARKER="storage/app/.pms-ready"

# Compose only passes connection details, and set -u aborts on anything unset.
RABBITMQ_EXCHANGE="${RABBITMQ_EXCHANGE:-production.events}"
RABBITMQ_QUEUE="${RABBITMQ_QUEUE:-production.events.processing}"
RABBITMQ_DLX="${RABBITMQ_DLX:-production.events.dlx}"
RABBITMQ_DLQ="${RABBITMQ_DLQ:-production.events.dlq}"

log() { printf '\033[0;36m[entrypoint:%s]\033[0m %s\n' "$APP_ROLE" "$1"; }

wait_for_mysql() {
    log "waiting for mysql at ${DB_HOST}:${DB_PORT} ..."
    until mysqladmin ping --host="${DB_HOST}" --port="${DB_PORT}" --silent 2>/dev/null; do
        sleep 2
    done
    log "mysql is up"
}

wait_for_rabbitmq() {
    log "waiting for rabbitmq at ${RABBITMQ_HOST}:${RABBITMQ_PORT} ..."
    until (echo > "/dev/tcp/${RABBITMQ_HOST}/${RABBITMQ_PORT}") 2>/dev/null; do
        sleep 2
    done
    log "rabbitmq is up"
}

prepare_application() {
    if [ ! -f .env ]; then
        log "no .env found, seeding it from .env.example"
        cp .env.example .env
    fi

    if [ ! -f vendor/autoload.php ]; then
        log "installing composer dependencies (first boot, this takes a minute)"
        composer install --no-interaction --prefer-dist --no-progress
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        log "generating application key"
        php artisan key:generate --force
    fi

    mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

    # This script runs as root, Apache runs as www-data. Without the chown, the
    # first error writes an unwritable laravel.log and returns a 500 that hides
    # the real exception.
    chown -R www-data:www-data storage bootstrap/cache || true
    chmod -R ug+rwX storage bootstrap/cache || true

    wait_for_mysql

    # Before the seeders, not after. They run real production orders, and a
    # message published to an unbound exchange is dropped.
    declare_rabbitmq_topology

    log "running migrations and seeders"
    php artisan migrate --seed --force

    php artisan config:clear
    php artisan route:clear

    touch "$READY_MARKER"
    log "application ready"
}

# The queue driver declares the exchange but skips the queue and bindings when
# an exchange is configured, so they are declared here instead.
declare_rabbitmq_topology() {
    wait_for_rabbitmq

    log "declaring rabbitmq topology"

    php artisan rabbitmq:exchange-declare "$RABBITMQ_EXCHANGE" rabbitmq --type=topic --durable=1 --silent || true
    php artisan rabbitmq:exchange-declare "$RABBITMQ_DLX" rabbitmq --type=topic --durable=1 --silent || true

    php artisan rabbitmq:queue-declare "$RABBITMQ_DLQ" rabbitmq --durable=1 --silent || true
    php artisan rabbitmq:queue-bind "$RABBITMQ_DLQ" "$RABBITMQ_DLX" rabbitmq --routing-key="production.failed" --silent || true

    php artisan rabbitmq:queue-declare "$RABBITMQ_QUEUE" rabbitmq --durable=1 --silent || true
    php artisan rabbitmq:queue-bind "$RABBITMQ_QUEUE" "$RABBITMQ_EXCHANGE" rabbitmq --routing-key="$RABBITMQ_QUEUE" --silent || true
}

wait_for_application() {
    log "waiting for the app container to finish provisioning ..."
    until [ -f "$READY_MARKER" ] && [ -f vendor/autoload.php ]; do
        sleep 2
    done
    log "app is provisioned"
}

case "$APP_ROLE" in
    app)
        rm -f "$READY_MARKER"
        prepare_application
        ;;
    worker)
        wait_for_application
        wait_for_rabbitmq
        ;;
    *)
        log "unknown APP_ROLE '$APP_ROLE', expected 'app' or 'worker'"
        exit 1
        ;;
esac

log "exec: $*"
exec "$@"
