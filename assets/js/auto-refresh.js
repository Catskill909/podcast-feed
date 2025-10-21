/**
 * Browser-Based Auto-Refresh
 * Automatically refreshes podcast feeds when users visit the site
 * No cron needed - works in local and production
 */

(function() {
    'use strict';
    
    // Only run on index.php and admin.php
    const currentPage = window.location.pathname.split('/').pop();
    if (!['index.php', 'admin.php', ''].includes(currentPage)) {
        return;
    }
    
    // Trigger auto-refresh in background
    function triggerAutoRefresh() {
        fetch('api/auto-refresh.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.refreshed) {
                console.log('✓ Auto-refresh completed:', data.stats);
                
                // If podcasts were updated, reload the page to show new data
                if (data.stats.updated > 0) {
                    console.log(`${data.stats.updated} podcast(s) updated - reloading...`);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } else if (data.success && !data.refreshed) {
                console.log('Auto-refresh skipped (recent refresh found)');
            }
        })
        .catch(error => {
            // Silent fail - don't disrupt user experience
            console.log('Auto-refresh check failed:', error);
        });
    }
    
    // Trigger on page load (after a short delay to not block page rendering)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(triggerAutoRefresh, 2000);
        });
    } else {
        setTimeout(triggerAutoRefresh, 2000);
    }
    
})();
