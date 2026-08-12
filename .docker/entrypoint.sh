#!/bin/sh

set -eu

mkdir -p \
    bootstrap/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

should_optimize=false

if [ "${1:-}" = "frankenphp" ]; then
    should_optimize=true
fi

if [ "${1:-}" = "php" ] && [ "${2:-}" = "artisan" ]; then
    case "${3:-}" in
        queue:work|schedule:work)
            should_optimize=true
            ;;
    esac
fi

if [ "$should_optimize" = true ] && [ "${LARAVEL_OPTIMIZE:-true}" = true ]; then
    php artisan optimize --ansi
fi

exec "$@"
