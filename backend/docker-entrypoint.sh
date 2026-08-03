#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Container entrypoint for both roles of the backend image.
#
#   APP_ROLE=app     — prepares the application (deps, .env, key, migrations,
#                      seeders), publishes a readiness marker, then runs Apache.
#   APP_ROLE=worker  — waits for the marker, then runs the RabbitMQ consumer.
#
# Only the "app" role mutates shared state, so two containers sharing the same
# bind mount never race on `composer install` or `migrate`.
# ---------------------------------------------------------------------------
set -euo pipefail

APP_ROLE="${APP_ROLE:-app}"
READY_MARKER="storage/app/.pms-ready"

# Topology names. Defaulted here because compose only passes the connection
# details, and `set -u` would abort on an unset variable below.
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
        log "no .env found — seeding it from .env.example"
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

    # Apache runs as www-data, but this script (and every `docker compose exec
    # ... artisan` call) runs as root — so anything root creates here, notably
    # storage/logs/laravel.log, becomes unwritable to the web server. Without
    # this chown, the first runtime error turns into an unrelated "could not be
    # opened in append mode" 500 that masks the actual exception.
    chown -R www-data:www-data storage bootstrap/cache || true
    chmod -R ug+rwX storage bootstrap/cache || true

    wait_for_mysql

    log "running migrations and seeders"
    php artisan migrate --seed --force

    php artisan config:clear
    php artisan route:clear

    declare_rabbitmq_topology

    touch "$READY_MARKER"
    log "application ready"
}

# The queue driver declares the exchange on first publish but deliberately
# leaves the queue and its bindings alone whenever an exchange is configured
# (see RabbitMQQueue::declareDestination). Declaring them here keeps a fresh
# `docker compose up` reproducible: topic exchange, one bound work queue, and a
# dead-letter queue for messages the worker gives up on.
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
        log "unknown APP_ROLE '$APP_ROLE' — expected 'app' or 'worker'"
        exit 1
        ;;
esac

log "exec: $*"
exec "$@"
