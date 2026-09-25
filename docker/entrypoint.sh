#!/usr/bin/env bash
# Straden container entrypoint. One image, several roles (STRADEN_ROLE):
#   app       – web server (FrankenPHP/Caddy); runs migrations + bootstrap
#   reverb    – websocket server
#   runner    – Horizon queue worker; executes k6 load tests
#   scheduler – Laravel scheduler (polls running tests)
# Any other arguments are executed as-is, e.g. `php artisan straden:admin you@example.com`.
set -euo pipefail

cd /app

ROLE="${STRADEN_ROLE:-app}"
SECRETS_FILE=/app/storage/app/.straden-secrets
READY_FILE=/tmp/straden-ready

log() { echo "[straden:${ROLE}] $*"; }

if [[ "${1:-}" == "healthcheck" ]]; then
    case "$ROLE" in
        app) [[ -f "$READY_FILE" ]] && curl -fsS -o /dev/null http://localhost:2019/config/ ;;
        *) [[ -f "$READY_FILE" ]] ;;
    esac
    exit $?
fi

rm -f "$READY_FILE"
mkdir -p storage/app/public storage/app/.home storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

# ---------------------------------------------------------------------------
# Secrets: use values from the environment when provided, otherwise generate
# them once into the shared storage volume so every service agrees (AI
# provider credentials are encrypted with APP_KEY — never lose this file).
# ---------------------------------------------------------------------------
random() { php -r "echo bin2hex(random_bytes($1));"; }

if [[ ! -f "$SECRETS_FILE" ]]; then
    if [[ "$ROLE" == "app" ]]; then
        log "Generating instance secrets in storage/app/.straden-secrets"
        umask 077
        {
            echo "APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));')"
            echo "REVERB_APP_ID=$(( (RANDOM << 15 | RANDOM) % 900000 + 100000 ))"
            echo "REVERB_APP_KEY=$(random 16)"
            echo "REVERB_APP_SECRET=$(random 24)"
        } > "$SECRETS_FILE.tmp"
        mv "$SECRETS_FILE.tmp" "$SECRETS_FILE"
    else
        log "Waiting for the app service to generate instance secrets..."
        for _ in $(seq 1 120); do [[ -f "$SECRETS_FILE" ]] && break; sleep 1; done
        [[ -f "$SECRETS_FILE" ]] || { log "Secrets file never appeared"; exit 1; }
    fi
fi

while IFS='=' read -r key value; do
    [[ -z "$key" ]] && continue
    if [[ -z "${!key:-}" ]]; then export "$key=$value"; fi
done < "$SECRETS_FILE"

# ---------------------------------------------------------------------------
# Boot
# ---------------------------------------------------------------------------
wait_for_database() {
    for i in $(seq 1 60); do
        if php artisan db:show --json >/dev/null 2>&1; then return 0; fi
        log "Waiting for the database ($i/60)..."
        sleep 2
    done
    log "Database is unreachable"; exit 1
}

php artisan optimize:clear >/dev/null
php artisan optimize >/dev/null

if [[ $# -gt 0 ]]; then
    exec "$@"
fi

case "$ROLE" in
    app)
        wait_for_database
        log "Running migrations"
        php artisan migrate --force --isolated
        php artisan straden:bootstrap
        touch "$READY_FILE"
        log "Serving on ${STRADEN_DOMAIN:-:80}"
        exec frankenphp run --config /etc/frankenphp/Caddyfile
        ;;
    reverb)
        touch "$READY_FILE"
        exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}"
        ;;
    runner)
        log "k6 $(k6 version 2>/dev/null | head -n1)"
        touch "$READY_FILE"
        exec php artisan horizon
        ;;
    scheduler)
        touch "$READY_FILE"
        exec php artisan schedule:work
        ;;
    *)
        log "Unknown STRADEN_ROLE '${ROLE}' (expected app, reverb, runner or scheduler)"
        exit 1
        ;;
esac
