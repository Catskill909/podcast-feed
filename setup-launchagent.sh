#!/bin/bash
# Setup LaunchAgent for Automated Feed Scanning (macOS)
# This is more reliable than cron on macOS

echo "=========================================="
echo "Podcast Feed Auto-Scanner Setup (LaunchAgent)"
echo "=========================================="
echo ""

PLIST_FILE="com.podcastfeed.autoscan.plist"
LAUNCH_AGENTS_DIR="$HOME/Library/LaunchAgents"
DEST_PLIST="$LAUNCH_AGENTS_DIR/$PLIST_FILE"

# Create LaunchAgents directory if it doesn't exist
if [ ! -d "$LAUNCH_AGENTS_DIR" ]; then
    echo "Creating LaunchAgents directory..."
    mkdir -p "$LAUNCH_AGENTS_DIR"
fi

# Check if LaunchAgent already exists
if [ -f "$DEST_PLIST" ]; then
    echo "⚠️  LaunchAgent already exists!"
    echo ""
    read -p "Unload and replace it? (y/n): " replace
    
    if [ "$replace" = "y" ] || [ "$replace" = "Y" ]; then
        echo "Unloading existing LaunchAgent..."
        launchctl unload "$DEST_PLIST" 2>/dev/null
        rm "$DEST_PLIST"
    else
        echo "Setup cancelled."
        exit 0
    fi
fi

# Copy plist file
echo "Installing LaunchAgent..."
cp "$PLIST_FILE" "$DEST_PLIST"

# Load the LaunchAgent
echo "Loading LaunchAgent..."
launchctl load "$DEST_PLIST"

# Check if it loaded successfully
if launchctl list | grep -q "com.podcastfeed.autoscan"; then
    echo ""
    echo "✓ LaunchAgent installed and loaded successfully!"
    echo ""
    echo "=========================================="
    echo "Setup Complete!"
    echo "=========================================="
    echo ""
    echo "Your podcasts will now be scanned every 30 minutes"
    echo ""
    echo "Useful commands:"
    echo "  Check status:     launchctl list | grep podcastfeed"
    echo "  Unload:           launchctl unload ~/Library/LaunchAgents/$PLIST_FILE"
    echo "  Reload:           launchctl unload ~/Library/LaunchAgents/$PLIST_FILE && launchctl load ~/Library/LaunchAgents/$PLIST_FILE"
    echo "  View logs:        tail -f logs/auto-scan.log"
    echo "  Manual scan:      php cron/auto-scan-feeds.php"
    echo ""
    echo "The first scan will run immediately, then every 30 minutes."
    echo ""
else
    echo ""
    echo "❌ Failed to load LaunchAgent"
    echo "Check the error output above for details"
    exit 1
fi
