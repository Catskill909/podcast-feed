/**
 * Analytics Tracker
 * Handles client-side analytics event tracking with deduplication
 */

class AnalyticsTracker {
    constructor() {
        this.sessionId = this.getOrCreateSessionId();
        this.loggedPlays = new Set();
        this.loggedDownloads = new Set();
        this.init();
    }

    /**
     * Initialize tracker
     */
    init() {
        // Load previously logged events from sessionStorage
        this.loadSessionCache();

        // Listen for audio player events
        this.setupEventListeners();
    }

    /**
     * Get or create session ID
     * Stored in localStorage and rotated every 24 hours
     */
    getOrCreateSessionId() {
        const storageKey = 'pf_analytics_session';
        const expiryKey = 'pf_analytics_session_expiry';
        
        const now = Date.now();
        const expiry = localStorage.getItem(expiryKey);

        // Check if session expired (24 hours)
        if (expiry && now > parseInt(expiry)) {
            localStorage.removeItem(storageKey);
            localStorage.removeItem(expiryKey);
        }

        let sessionId = localStorage.getItem(storageKey);
        
        if (!sessionId) {
            // Generate new UUID
            sessionId = this.generateUUID();
            localStorage.setItem(storageKey, sessionId);
            
            // Set expiry to 24 hours from now
            const newExpiry = now + (24 * 60 * 60 * 1000);
            localStorage.setItem(expiryKey, newExpiry.toString());
        }

        return sessionId;
    }

    /**
     * Generate UUID v4
     */
    generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Load session cache from sessionStorage
     */
    loadSessionCache() {
        try {
            const playsCache = sessionStorage.getItem('analytics_plays');
            const downloadsCache = sessionStorage.getItem('analytics_downloads');

            if (playsCache) {
                this.loggedPlays = new Set(JSON.parse(playsCache));
            }

            if (downloadsCache) {
                this.loggedDownloads = new Set(JSON.parse(downloadsCache));
            }
        } catch (e) {
            console.warn('Failed to load analytics cache:', e);
        }
    }

    /**
     * Save session cache to sessionStorage
     */
    saveSessionCache() {
        try {
            sessionStorage.setItem('analytics_plays', JSON.stringify([...this.loggedPlays]));
            sessionStorage.setItem('analytics_downloads', JSON.stringify([...this.loggedDownloads]));
        } catch (e) {
            console.warn('Failed to save analytics cache:', e);
        }
    }

    /**
     * Setup event listeners for audio player
     */
    setupEventListeners() {
        // Listen for custom events from audio player
        window.addEventListener('audio:episodeStarted', (e) => {
            this.handleEpisodeStarted(e.detail);
        });

        window.addEventListener('audio:episodeDownloaded', (e) => {
            this.handleEpisodeDownloaded(e.detail);
        });
    }

    /**
     * Handle episode started event
     */
    handleEpisodeStarted(detail) {
        if (!detail || !detail.episode || !detail.podcast) {
            console.warn('Invalid episode started event data');
            return;
        }

        this.logPlay(detail.episode, detail.podcast);
    }

    /**
     * Handle episode downloaded event
     */
    handleEpisodeDownloaded(detail) {
        if (!detail || !detail.episode || !detail.podcast) {
            console.warn('Invalid episode downloaded event data');
            return;
        }

        this.logDownload(detail.episode, detail.podcast);
    }

    /**
     * Log a play event
     * 
     * @param {Object} episode Episode object with id, title, audio_url
     * @param {Object} podcast Podcast object with id, title
     */
    logPlay(episode, podcast) {
        const episodeId = episode.id;

        // Check if already logged in this session
        if (this.loggedPlays.has(episodeId)) {
            return; // Already logged, skip
        }

        // Mark as logged
        this.loggedPlays.add(episodeId);
        this.saveSessionCache();

        // Send to API
        this.sendEvent('play', episode, podcast);
    }

    /**
     * Log a download event
     * 
     * @param {Object} episode Episode object with id, title, audio_url
     * @param {Object} podcast Podcast object with id, title
     */
    logDownload(episode, podcast) {
        const episodeId = episode.id;

        // Check if already logged in this session
        if (this.loggedDownloads.has(episodeId)) {
            return; // Already logged, skip
        }

        // Mark as logged
        this.loggedDownloads.add(episodeId);
        this.saveSessionCache();

        // Send to API
        this.sendEvent('download', episode, podcast);
    }

    /**
     * Send event to API
     * 
     * @param {string} type Event type (play or download)
     * @param {Object} episode Episode object
     * @param {Object} podcast Podcast object
     */
    async sendEvent(type, episode, podcast) {
        const payload = {
            type: type,
            podcastId: podcast.id,
            episodeId: episode.id,
            sessionId: this.sessionId,
            episodeTitle: episode.title,
            podcastTitle: podcast.title,
            audioUrl: episode.audio_url,
            timestamp: new Date().toISOString()
        };

        try {
            const response = await fetch('api/log-analytics-event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!result.success) {
                console.warn('Analytics event failed:', result.error);
                
                // If failed, remove from logged set so it can be retried
                if (type === 'play') {
                    this.loggedPlays.delete(episode.id);
                } else if (type === 'download') {
                    this.loggedDownloads.delete(episode.id);
                }
                this.saveSessionCache();
            }

        } catch (error) {
            console.error('Failed to send analytics event:', error);
            
            // If network error, remove from logged set for retry
            if (type === 'play') {
                this.loggedPlays.delete(episode.id);
            } else if (type === 'download') {
                this.loggedDownloads.delete(episode.id);
            }
            this.saveSessionCache();
        }
    }

    /**
     * Clear session cache (for testing)
     */
    clearCache() {
        this.loggedPlays.clear();
        this.loggedDownloads.clear();
        sessionStorage.removeItem('analytics_plays');
        sessionStorage.removeItem('analytics_downloads');
    }
}

// Initialize analytics tracker on page load
document.addEventListener('DOMContentLoaded', () => {
    window.analyticsTracker = new AnalyticsTracker();
});
