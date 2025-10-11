#!/bin/bash
# Nixpacks startup script for Coolify
# This runs BEFORE the app starts to fix permissions

echo "🔧 Setting up directory permissions..."

# Create directories if they don't exist
mkdir -p /app/data/backup
mkdir -p /app/uploads/covers
mkdir -p /app/logs

# Set correct ownership and permissions
chown -R www-data:www-data /app/data /app/uploads /app/logs
chmod -R 755 /app/data /app/uploads /app/logs

echo "✅ Permissions set successfully"
echo "📁 Directory structure:"
ls -la /app/data /app/uploads /app/logs

# Start Apache
exec apache2-foreground
