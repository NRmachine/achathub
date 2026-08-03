#!/bin/sh

set -eu

mkdir -p \
    /tmp/achathub/framework/cache/data \
    /tmp/achathub/framework/sessions \
    /tmp/achathub/framework/views \
    /tmp/achathub/logs

# Vercel attend un port ouvert rapidement. The storefront can render from its
# bundled cold-start snapshot, so database maintenance is deliberately delayed
# until the first visitor has received the page without CPU or Neon contention.
(
    cd public
    exec php -S "0.0.0.0:${PORT:-80}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
) &
server_pid=$!

(
    sleep 10

    if php artisan achathub:vercel-boot; then
        # Prime the persistent Neon connection and replace the bundled fallback
        # with a live price/stock snapshot inside this HTTP process.
        php -r '
            $port = getenv("PORT") ?: "80";
            $context = stream_context_create(["http" => [
                "timeout" => 10,
                "ignore_errors" => true,
                "header" => "Host: www.achathub.com\r\nUser-Agent: AchatHub-Warmup/1.0\r\n",
            ]]);
            @file_get_contents("http://127.0.0.1:{$port}/?warmup=1", false, $context);
        ' || true
    else
        echo "AchatHub background boot failed; the storefront fallback remains available." >&2
    fi
) &

wait "$server_pid"
