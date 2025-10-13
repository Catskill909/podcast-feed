#!/bin/bash
# Nixpacks startup script for Coolify
# This runs BEFORE the app starts to fix permissions

echo "🔧 Setting up directory permissions..."

# Create directories if they don't exist
mkdir -p /app/data/backup
mkdir -p /app/uploads/covers
mkdir -p /app/logs

# IMPORTANT: Nixpacks PHP runs as 'nobody' (UID 65534, GID 65534)
# NOT www-data! This is the critical fix.
echo "Setting ownership to nobody (UID 65534)..."
chown -R 65534:65534 /app/data /app/uploads /app/logs
chmod -R 755 /app/data /app/uploads /app/logs

echo "✅ Permissions set successfully"
echo "📁 Directory ownership:"
ls -la /app/data /app/uploads /app/logs

echo "🔍 PHP User Check:"
php -r "echo 'PHP runs as: ' . exec('whoami') . ' (UID: ' . posix_getuid() . ')' . PHP_EOL;"

# Start Apache
exec apache2-foreground
