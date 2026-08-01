#!/bin/sh

set -eu

mkdir -p \
    /tmp/achathub/framework/cache/data \
    /tmp/achathub/framework/sessions \
    /tmp/achathub/framework/views \
    /tmp/achathub/logs

# Vercel attend un port ouvert en moins de 15 secondes. Le serveur démarre donc
# immédiatement, puis les migrations protégées par verrou se terminent pendant
# que le conteneur reste disponible.
php artisan serve --host=0.0.0.0 --port="${PORT:-80}" &
server_pid=$!

if ! php artisan achathub:vercel-boot; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
    exit 1
fi

wait "$server_pid"
