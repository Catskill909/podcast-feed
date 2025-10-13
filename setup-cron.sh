#!/bin/bash
# Setup Cron Job for Automated Feed Scanning
# This script helps you set up automated scanning

echo "=========================================="
echo "Podcast Feed Auto-Scanner Setup"
echo "=========================================="
echo ""

# Get current directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo "Project directory: $SCRIPT_DIR"
echo ""

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "❌ Error: PHP is not installed or not in PATH"
    exit 1
fi

echo "✓ PHP found: $(php -v | head -n 1)"
echo ""

# Test the scanner
echo "Testing the scanner..."
php "$SCRIPT_DIR/cron/auto-scan-feeds.php"
TEST_RESULT=$?

if [ $TEST_RESULT -eq 0 ]; then
    echo ""
    echo "✓ Scanner test successful!"
else
    echo ""
    echo "❌ Scanner test failed. Please check the output above."
    exit 1
fi

echo ""
echo "=========================================="
echo "Cron Job Setup Options"
echo "=========================================="
echo ""
echo "Choose your scan interval:"
echo "  1) Every 15 minutes (aggressive)"
echo "  2) Every 30 minutes (recommended)"
echo "  3) Every hour"
echo "  4) Every 6 hours"
echo "  5) Manual setup (show cron line only)"
echo ""
read -p "Enter your choice (1-5): " choice

case $choice in
    1)
        CRON_SCHEDULE="*/15 * * * *"
        INTERVAL="15 minutes"
        ;;
    2)
        CRON_SCHEDULE="*/30 * * * *"
        INTERVAL="30 minutes"
        ;;
    3)
        CRON_SCHEDULE="0 * * * *"
        INTERVAL="1 hour"
        ;;
    4)
        CRON_SCHEDULE="0 */6 * * *"
        INTERVAL="6 hours"
        ;;
    5)
        CRON_SCHEDULE="*/30 * * * *"
        INTERVAL="30 minutes (example)"
        echo ""
        echo "Manual setup selected."
        echo ""
        echo "Add this line to your crontab (crontab -e):"
        echo ""
        echo "$CRON_SCHEDULE cd $SCRIPT_DIR && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1"
        echo ""
        exit 0
        ;;
    *)
        echo "Invalid choice. Exiting."
        exit 1
        ;;
esac

echo ""
echo "Selected interval: $INTERVAL"
echo ""
echo "Cron line to be added:"
echo "$CRON_SCHEDULE cd $SCRIPT_DIR && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1"
echo ""
read -p "Add this to your crontab? (y/n): " confirm

if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "Setup cancelled."
    exit 0
fi

# Add to crontab
CRON_LINE="$CRON_SCHEDULE cd $SCRIPT_DIR && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "auto-scan-feeds.php"; then
    echo ""
    echo "⚠️  A cron job for auto-scan-feeds.php already exists!"
    echo ""
    echo "Current crontab:"
    crontab -l | grep "auto-scan-feeds.php"
    echo ""
    read -p "Replace it? (y/n): " replace
    
    if [ "$replace" = "y" ] || [ "$replace" = "Y" ]; then
        # Remove old entry and add new one
        (crontab -l 2>/dev/null | grep -v "auto-scan-feeds.php"; echo "$CRON_LINE") | crontab -
        echo "✓ Cron job updated!"
    else
        echo "Setup cancelled."
        exit 0
    fi
else
    # Add new entry
    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
    echo "✓ Cron job added!"
fi

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Your podcasts will now be scanned every $INTERVAL"
echo ""
echo "Useful commands:"
echo "  View crontab:    crontab -l"
echo "  Edit crontab:    crontab -e"
echo "  Remove crontab:  crontab -r"
echo "  View logs:       tail -f $SCRIPT_DIR/logs/auto-scan.log"
echo "  Manual scan:     php $SCRIPT_DIR/cron/auto-scan-feeds.php"
echo ""
echo "Next scan will run at the scheduled time."
echo "Check logs/auto-scan.log for scan results."
echo ""
