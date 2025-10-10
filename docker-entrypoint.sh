#!/bin/bash
set -e

# Fix permissions on startup
chown -R www-data:www-data /app/data /app/uploads /app/logs 2>/dev/null || true
chmod -R 777 /app/data /app/uploads /app/logs 2>/dev/null || true

# Start Apache
exec apache2-foreground
