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
(
    cd public
    exec php -S "0.0.0.0:${PORT:-80}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
) &
server_pid=$!

if ! php artisan achathub:vercel-boot; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
    exit 1
fi

# Prime Laravel, the persistent Neon connection and the local storefront cache
# inside the HTTP process before the container receives regular traffic.
php -r '
    $port = getenv("PORT") ?: "80";
    $context = stream_context_create(["http" => [
        "timeout" => 10,
        "ignore_errors" => true,
        "header" => "Host: www.achathub.com\r\nUser-Agent: AchatHub-Warmup/1.0\r\n",
    ]]);
    @file_get_contents("http://127.0.0.1:{$port}/?warmup=1", false, $context);
' || true

wait "$server_pid"
