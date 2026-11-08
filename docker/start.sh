#!/bin/sh
set -e

# Writable folders live on Coolify volumes; make sure they exist and PHP
# (running as nobody) can write to them.
mkdir -p /app/data/backup /app/uploads/covers /app/uploads/audio /app/logs
chown -R nobody:nogroup /app/data /app/uploads /app/logs

php-fpm -D
exec nginx -g 'daemon off;'
