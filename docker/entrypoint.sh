#!/bin/sh
# Container start-up: resolve a persistent application key, prepare the database, then start Apache.
set -eu

cd /var/www/html

KEY_FILE=/var/app-data/app-key

if [ -n "${SUPPLIED_APP_KEY:-}" ]; then
    APP_KEY="$SUPPLIED_APP_KEY"
elif [ -s "$KEY_FILE" ]; then
    APP_KEY="$(cat "$KEY_FILE")"
else
    APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    (umask 077 && printf '%s' "$APP_KEY" > "$KEY_FILE")
    echo "Generated a demo application key and stored it in the app-data volume."
fi

# A minimal .env so that every process, including "docker compose exec" commands, sees the same key.
printf 'APP_KEY=%s\n' "$APP_KEY" > .env

# Waits for MySQL, runs migrations, and seeds sample data only when the database has never been seeded.
php artisan commission:setup

exec "$@"
