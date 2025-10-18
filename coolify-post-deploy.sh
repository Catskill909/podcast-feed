#!/bin/bash
# Coolify Post-Deploy Hook Script
# This runs AFTER deployment completes to fix permissions automatically

echo "================================================"
echo "🚀 Post-Deploy: Fixing File Permissions"
echo "================================================"

# Navigate to app directory
cd /app || exit 1

# Check current user
echo "Current user: $(whoami)"
echo "Current UID: $(id -u)"

# Create directories if they don't exist (shouldn't be needed but safe)
mkdir -p data/backup
mkdir -p uploads/covers
mkdir -p logs

# Fix ownership for nobody user (UID 65534 in Nixpacks)
echo "Setting ownership to nobody (UID 65534, GID 65534)..."
chown -R 65534:65534 data uploads logs

# Fix permissions
echo "Setting permissions (755 for directories)..."
chmod -R 755 data uploads logs

# Verify the fix
echo ""
echo "✅ Permissions fixed! Verification:"
echo "-----------------------------------"
ls -la data uploads logs | grep -E "^d"

# Check if files are writable by PHP user
echo ""
echo "🔍 Testing write permissions..."
if [ -w data ] && [ -w uploads ] && [ -w logs ]; then
    echo "✅ All directories are writable!"
else
    echo "❌ WARNING: Some directories may not be writable!"
    echo "   This could cause issues. Check permissions manually."
fi

echo ""
echo "================================================"
echo "🔧 Configuring Nginx for Large File Uploads"
echo "================================================"

# Add Nginx upload limit configuration
if [ -f /nginx.conf ]; then
    echo "Checking Nginx upload limits..."
    
    # Check if already configured
    if grep -q "client_max_body_size" /nginx.conf; then
        echo "✅ Nginx already configured for large uploads"
    else
        echo "Adding upload limits to Nginx..."
        # Create a temp file with the config
        cat > /tmp/nginx-upload.conf << 'EOF'
# Large file upload support for podcast audio files
client_max_body_size 500M;
client_body_timeout 600s;
client_header_timeout 600s;
EOF
        
        # Insert after the first 'http {' line
        awk '/http {/ && !found {print; system("cat /tmp/nginx-upload.conf"); found=1; next} 1' /nginx.conf > /tmp/nginx.conf.new
        
        # Replace the config
        mv /tmp/nginx.conf.new /nginx.conf
        rm -f /tmp/nginx-upload.conf
        
        # Reload nginx
        echo "Reloading Nginx..."
        nginx -s reload 2>&1 || echo "⚠️  Nginx reload failed, will restart on next request"
        echo "✅ Nginx configured for 500M uploads"
    fi
else
    echo "⚠️  Nginx config not found at /nginx.conf"
fi

echo ""
echo "================================================"
echo "✅ Post-Deploy Hook Complete!"
echo "================================================"
echo ""
echo "Next steps:"
echo "1. Visit https://your-domain.com/check-user.php"
echo "2. Verify all directories show 'Writable'"
echo "3. Test delete/add operations"
echo ""
