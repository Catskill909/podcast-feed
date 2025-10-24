# THE REAL PROBLEM

After extensive analysis, I believe the issue is that **the podcast cards are being rendered DYNAMICALLY by JavaScript AFTER the page loads**, and the CSS might not be applying to dynamically-created elements properly on mobile browsers.

## Evidence:
1. `browse.js` line 213 - Cards are created via `innerHTML` 
2. Cards don't exist in initial HTML - they're injected by JavaScript
3. Mobile browsers may handle dynamically-created elements differently

## THE SOLUTION: Apply Styles via JavaScript

Since CSS isn't working, let's apply styles DIRECTLY via JavaScript after the cards are created.

### File to Modify: `/assets/js/browse.js`

Add this function after line 246 (after renderPodcastCard):

```javascript
/**
 * Apply mobile-specific styles to cards (for when CSS fails)
 */
applyMobileStyles() {
    // Only on mobile devices
    if (window.innerWidth <= 768) {
        const cards = document.querySelectorAll('.podcast-card');
        const badges = document.querySelectorAll('.podcast-card-badge, .podcast-card-new-badge');
        const overlays = document.querySelectorAll('.podcast-card-play-overlay');
        
        // Apply to each card
        cards.forEach(card => {
            // Disable text selection
            card.style.webkitUserSelect = 'none';
            card.style.userSelect = 'none';
            card.style.webkitTapHighlightColor = 'transparent';
            
            // Scale on phones only
            if (window.innerWidth <= 480) {
                card.style.transform = 'scale(0.88)';
            }
        });
        
        // Make badges larger
        badges.forEach(badge => {
            badge.style.fontSize = '18px';
            badge.style.padding = '12px 18px';
            badge.style.minHeight = '40px';
            badge.style.fontWeight = '700';
        });
        
        // Hide play overlays
        overlays.forEach(overlay => {
            overlay.style.display = 'none';
        });
    }
}
```

Then call it after rendering cards (around line 200):

```javascript
renderPodcasts(podcasts) {
    const container = document.getElementById('podcastsGrid');
    if (!container) return;

    if (!podcasts || podcasts.length === 0) {
        this.showEmptyState(container);
        return;
    }

    container.innerHTML = podcasts
        .map((podcast, index) => this.renderPodcastCard(podcast, index))
        .join('');
    
    // APPLY MOBILE STYLES AFTER RENDERING
    this.applyMobileStyles();
}
```

This bypasses CSS entirely and applies styles via JavaScript, which WILL work.
