#!/bin/sh
# Fix permissions on startup
chown -R www-data:www-data /app/data /app/uploads /app/logs
chmod -R 777 /app/data /app/uploads /app/logs

# Execute the main command
exec "$@"
