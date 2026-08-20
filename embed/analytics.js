/**
 * Embed Player Analytics
 *
 * The embed player previously logged nothing, so every play and download that
 * happened inside an iframe was invisible to the stats dashboard - which is
 * most real listening. This posts the same events, to the same endpoint, in the
 * same shape as assets/js/analytics-tracker.js on the public browser page.
 *
 * IDs must match the main player's or the two sources will not aggregate:
 * podcast IDs come from the master feed's <guid>, episode IDs are derived with
 * the same hash of (audioUrl + feed position) used by player-modal.js.
 */
class EmbedAnalytics {
    constructor() {
        // The endpoint is resolved against this document rather than written as
        // a relative path: the embed lives at /embed/, where a bare
        // "api/log-analytics-event.php" resolves to /embed/api/... and 404s.
        this.endpoint = new URL('../api/log-analytics-event.php', window.location.href).href;

        this.sessionId = this.getSessionId();
        this.loggedPlays = new Set();
        this.loggedDownloads = new Set();
    }

    getSessionId() {
        const KEY = 'podfeed-embed-session';
        let id = null;

        // Storage is unavailable in some embedding contexts (third-party cookie
        // blocking). Fall back to a per-page-load id rather than failing.
        try {
            id = sessionStorage.getItem(KEY);
            if (!id) {
                id = this.newId();
                sessionStorage.setItem(KEY, id);
            }
        } catch (error) {
            id = null;
        }

        return id || this.newId();
    }

    newId() {
        return self.crypto?.randomUUID
            ? self.crypto.randomUUID()
            : 'anon-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }

    trackPlay(episode, podcast) {
        this.send('play', episode, podcast, this.loggedPlays);
    }

    trackDownload(episode, podcast) {
        this.send('download', episode, podcast, this.loggedDownloads);
    }

    async send(type, episode, podcast, logged) {
        // Without real IDs the event cannot be joined to anything, so drop it
        // rather than writing a row that pollutes the dashboard.
        if (!episode?.analyticsId || !podcast?.analyticsId) return;

        // One event per episode per session, matching the main tracker.
        if (logged.has(episode.analyticsId)) return;
        logged.add(episode.analyticsId);

        const payload = {
            type,
            podcastId: podcast.analyticsId,
            episodeId: episode.analyticsId,
            sessionId: this.sessionId,
            episodeTitle: episode.title,
            podcastTitle: podcast.title,
            audioUrl: episode.audioUrl,
            timestamp: new Date().toISOString()
        };

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (!result.success) {
                // Allow a retry later in this session.
                logged.delete(episode.analyticsId);
                console.warn('Embed analytics rejected:', result.error);
            }
        } catch (error) {
            logged.delete(episode.analyticsId);
            console.warn('Embed analytics failed:', error.message);
        }
    }
}

window.embedAnalytics = new EmbedAnalytics();
