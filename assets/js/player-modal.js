/**
 * Podcast Player Modal - Episode Management
 * Handles modal display, episode loading, and user interactions
 */

class PodcastPlayerModal {
    constructor() {
        this.currentPodcastId = null;
        this.currentPodcast = null;
        this.episodes = [];
        this.filteredEpisodes = [];
        this.searchTimeout = null;
        this.init();
    }

    /**
     * Initialize the player modal
     */
    init() {
        this.setupEventListeners();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Close on overlay click
        const overlay = document.getElementById('playerModal');
        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.hide();
                }
            });
        }

        // Search episodes (unique ID to avoid conflicts with main app)
        const searchInput = document.getElementById('playerEpisodeSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.filterEpisodes(e.target.value);
                }, 300);
            });
        }

        // Sort episodes (unique ID to avoid conflicts with main app)
        const sortSelect = document.getElementById('playerEpisodeSort');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.sortEpisodes(e.target.value);
            });
        }
    }

    /**
     * Show the player modal
     */
    async show(podcastId) {
        // If opening a different podcast, stop current playback
        if (this.currentPodcastId && this.currentPodcastId !== podcastId) {
            if (window.audioPlayer) {
                window.audioPlayer.stopPlayback();
            }
        }
        
        this.currentPodcastId = podcastId;
        
        const modal = document.getElementById('playerModal');
        if (!modal) return;

        // Reset sort order to default (newest first)
        const sortSelect = document.getElementById('playerEpisodeSort');
        if (sortSelect) {
            sortSelect.value = 'newest';
        }

        // CRITICAL: Clear old content BEFORE showing modal to prevent ghost content
        this.clearModalContent();

        // Reset scroll position to top
        const modalBody = modal.querySelector('.player-modal-body');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }

        // Show modal
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Load podcast data
        await this.loadPodcastData(podcastId);
        
        // Load episodes
        await this.loadEpisodes(podcastId);
    }

    /**
     * Hide the player modal
     */
    hide() {
        const modal = document.getElementById('playerModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
            
            // Reset scroll position to top
            const modalBody = modal.querySelector('.player-modal-body');
            if (modalBody) {
                modalBody.scrollTop = 0;
            }
        }

        // STOP all audio when closing modal
        if (window.audioPlayer) {
            window.audioPlayer.stopPlayback();
        }
        
        // Reset sort order to default
        const sortSelect = document.getElementById('playerEpisodeSort');
        if (sortSelect) {
            sortSelect.value = 'newest';
        }
        
        // Clear current podcast
        this.currentPodcastId = null;
        this.currentPodcast = null;
        this.episodes = [];
        this.filteredEpisodes = [];
    }

    /**
     * Clear modal content to prevent ghost content from previous podcast
     */
    clearModalContent() {
        // Clear podcast info
        const titleEl = document.getElementById('playerModalPodcastTitle');
        if (titleEl) titleEl.textContent = 'Loading...';
        
        const coverEl = document.getElementById('playerPodcastCover');
        if (coverEl) {
            coverEl.src = '';
            coverEl.style.display = 'none';
        }
        
        const nameEl = document.getElementById('playerPodcastName');
        if (nameEl) nameEl.textContent = 'Loading...';
        
        const descEl = document.getElementById('playerPodcastDescription');
        if (descEl) descEl.textContent = '';
        
        const countEl = document.getElementById('playerEpisodeCount');
        if (countEl) countEl.textContent = '0 Episodes';
        
        const latestEl = document.getElementById('playerLatestEpisode');
        if (latestEl) latestEl.textContent = 'Unknown';
        
        // Clear episodes list
        const listEl = document.getElementById('playerEpisodesList');
        if (listEl) {
            listEl.innerHTML = `
                <div class="player-loading">
                    <div class="spinner"></div>
                    <p>Loading episodes...</p>
                </div>
            `;
        }
        
        // Clear search
        const searchInput = document.getElementById('playerEpisodeSearch');
        if (searchInput) searchInput.value = '';
    }

    /**
     * Load podcast data
     * ALWAYS fetches fresh data from API to ensure latest episode is current
     */
    async loadPodcastData(podcastId) {
        try {
            // Fetch FRESH data from API (not stale HTML attributes!)
            const response = await fetch(`api/get-podcast-preview.php?id=${podcastId}`);
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Failed to load podcast data');
            }

            const podcast = {
                id: podcastId,
                title: result.data.title,
                description: result.data.description,
                feed_url: result.data.feed_url,
                episode_count: result.data.episode_count,
                latest_episode: result.data.latest_episode_date,  // FRESH from RSS feed!
                status: result.data.status,
                cover_url: result.data.image_url
            };

            this.currentPodcast = podcast;
            this.displayPodcastInfo(podcast);

        } catch (error) {
            console.error('Error loading podcast data:', error);
            this.showError('Failed to load podcast information');
        }
    }

    /**
     * Display podcast information
     */
    displayPodcastInfo(podcast) {
        // Update modal title
        const titleEl = document.getElementById('playerModalPodcastTitle');
        if (titleEl) {
            titleEl.textContent = podcast.title;
        }

        // Update cover image
        const coverEl = document.getElementById('playerPodcastCover');
        if (coverEl) {
            coverEl.style.display = 'none';
            coverEl.onload = () => {
                coverEl.style.display = 'block';
            };
            coverEl.onerror = () => {
                coverEl.style.display = 'none';
            };

            if (podcast.cover_url) {
                if (coverEl.src !== podcast.cover_url) {
                    coverEl.src = podcast.cover_url;
                } else if (coverEl.complete) {
                    coverEl.style.display = 'block';
                }
                coverEl.alt = podcast.title;
            } else {
                coverEl.removeAttribute('src');
            }
        }

        // Update podcast name
        const nameEl = document.getElementById('playerPodcastName');
        if (nameEl) {
            nameEl.textContent = podcast.title;
        }

        // Update description
        const descEl = document.getElementById('playerPodcastDescription');
        if (descEl) {
            descEl.textContent = podcast.description || 'No description available';
        }

        // Update episode count
        const countEl = document.getElementById('playerEpisodeCount');
        if (countEl) {
            const count = parseInt(podcast.episode_count) || 0;
            countEl.textContent = `${count} Episode${count !== 1 ? 's' : ''}`;
        }

        // Update status
        const statusEl = document.getElementById('playerStatus');
        if (statusEl) {
            statusEl.textContent = podcast.status === 'active' ? 'Active' : 'Inactive';
            statusEl.className = `badge badge-${podcast.status === 'active' ? 'success' : 'danger'}`;
        }

        // Update latest episode with better formatting
        const latestEl = document.getElementById('playerLatestEpisode');
        if (latestEl) {
            if (podcast.latest_episode) {
                latestEl.textContent = this.formatDate(podcast.latest_episode);
            } else {
                latestEl.textContent = 'Unknown';
            }
        }
        
        // Update the badge text for Latest
        const latestBadgeEl = document.getElementById('playerLatestEpisodeBadge');
        if (latestBadgeEl && podcast.latest_episode) {
            latestBadgeEl.innerHTML = `Latest: <span id="playerLatestEpisode">${this.formatDate(podcast.latest_episode)}</span>`;
        }
    }

    /**
     * Load episodes from RSS feed (client-side parsing)
     */
    async loadEpisodes(podcastId) {
        const listEl = document.getElementById('playerEpisodesList');
        if (!listEl) return;

        // Show loading state
        listEl.innerHTML = `
            <div class="player-loading">
                <div class="spinner"></div>
                <p>Loading episodes...</p>
            </div>
        `;

        try {
            // Get feed URL from current podcast data
            const feedUrl = this.currentPodcast?.feed_url;
            if (!feedUrl) {
                throw new Error('Feed URL not found');
            }
            
            // Fetch RSS feed through proxy to avoid CORS
            const response = await fetch(`api/fetch-feed.php?url=${encodeURIComponent(feedUrl)}`);
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`Failed to fetch feed (HTTP ${response.status})`);
            }
            
            // Get XML content
            const xmlText = await response.text();
            
            if (!xmlText || xmlText.trim() === '') {
                throw new Error('Feed returned empty content');
            }
            
            // Parse XML
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
            
            // Check for parse errors
            const parseError = xmlDoc.querySelector('parsererror');
            if (parseError) {
                throw new Error('Invalid RSS feed format');
            }
            
            // Parse episodes from XML
            this.episodes = this.parseRSSFeed(xmlDoc);
            this.filteredEpisodes = [...this.episodes];
            
            if (this.episodes.length === 0) {
                this.showEmptyState('No episodes found in this podcast feed');
            } else {
                this.renderEpisodes();
            }

        } catch (error) {
            console.error('Error loading episodes:', error);
            
            // Better error message based on error type
            let errorMsg = error.message;
            let errorTitle = 'Failed to load episodes';
            
            if (error.message.includes('empty response')) {
                errorTitle = 'Feed Loading Timeout';
                errorMsg = 'The podcast feed is taking too long to respond. This feed may be slow or temporarily unavailable.';
            } else if (error.message.includes('invalid response')) {
                errorTitle = 'Invalid Feed Format';
                errorMsg = 'The podcast feed returned an invalid response. The feed may be malformed.';
            } else if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
                errorTitle = 'Network Error';
                errorMsg = 'Unable to connect to the server. Please check your internet connection.';
            }
            
            listEl.innerHTML = `
                <div class="player-empty-state">
                    <div class="player-empty-state-icon">⚠️</div>
                    <h4 style="margin: var(--spacing-sm) 0; color: var(--text-primary);">${errorTitle}</h4>
                    <p style="font-size: var(--font-size-sm); color: var(--text-muted); max-width: 400px; margin: 0 auto;">${errorMsg}</p>
                    <button class="btn btn-sm btn-outline" style="margin-top: var(--spacing-lg);" onclick="playerModal.loadEpisodes('${this.currentPodcastId}')">
                        <i class="fa-solid fa-rotate"></i> Try Again
                    </button>
                </div>
            `;
        }
    }

    /**
     * Parse RSS feed XML document
     */
    parseRSSFeed(xmlDoc) {
        const episodes = [];
        const maxEpisodes = 50;
        
        // Get all item elements
        const items = xmlDoc.querySelectorAll('item');
        
        for (let i = 0; i < items.length && episodes.length < maxEpisodes; i++) {
            const item = items[i];
            
            // Get enclosure (audio file)
            const enclosure = item.querySelector('enclosure');
            const audioUrl = enclosure ? enclosure.getAttribute('url') : null;
            
            // Skip if no audio URL
            if (!audioUrl) continue;
            
            // Get episode data
            const title = item.querySelector('title')?.textContent || 'Untitled Episode';
            const description = item.querySelector('description')?.textContent || '';
            const pubDate = item.querySelector('pubDate')?.textContent || '';
            
            // Get iTunes duration
            const itunesDuration = item.querySelector('duration')?.textContent || 
                                  Array.from(item.querySelectorAll('*')).find(el => el.localName === 'duration')?.textContent || '';
            
            // Get episode image (iTunes or media)
            let imageUrl = '';
            const itunesImage = item.querySelector('image');
            if (itunesImage) {
                imageUrl = itunesImage.getAttribute('href') || '';
            }
            
            // Create episode object
            episodes.push({
                id: 'ep_' + this.hashCode(audioUrl + i),
                title: this.stripHTML(title),
                description: this.stripHTML(description).substring(0, 300),
                pub_date: pubDate,
                audio_url: audioUrl,
                duration: this.formatDuration(itunesDuration),
                image_url: imageUrl || this.currentPodcast?.cover_url || '',
                episode_number: episodes.length + 1
            });
        }
        
        return episodes;
    }
    
    /**
     * Simple hash function for generating episode IDs
     */
    hashCode(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(36);
    }
    
    /**
     * Strip HTML tags
     */
    stripHTML(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }
    
    /**
     * Format duration (handles HH:MM:SS, MM:SS, or seconds)
     */
    formatDuration(duration) {
        if (!duration) return '';
        
        // If already formatted, return as is
        if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(duration)) {
            return duration;
        }
        
        // If it's just seconds
        const seconds = parseInt(duration);
        if (!isNaN(seconds)) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            if (hours > 0) {
                return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            } else {
                return `${minutes}:${secs.toString().padStart(2, '0')}`;
            }
        }
        
        return duration;
    }

    /**
     * Render episodes list
     */
    renderEpisodes() {
        const listEl = document.getElementById('playerEpisodesList');
        if (!listEl) return;

        if (this.filteredEpisodes.length === 0) {
            this.showEmptyState('No episodes match your search');
            return;
        }

        listEl.innerHTML = this.filteredEpisodes.map(episode => this.renderEpisodeCard(episode)).join('');
        
        // Check for truncated descriptions after rendering
        setTimeout(() => this.checkTruncatedDescriptions(), 0);
    }
    
    /**
     * Check which descriptions are truncated and add class
     */
    checkTruncatedDescriptions() {
        const descriptions = document.querySelectorAll('.player-episode-description');
        descriptions.forEach(desc => {
            // Check if text is truncated by comparing scroll height to client height
            if (desc.scrollHeight > desc.clientHeight) {
                desc.classList.add('truncated');
            }
        });
    }
    
    /**
     * Toggle description expanded state
     */
    toggleDescription(element) {
        element.classList.toggle('expanded');
    }

    /**
     * Render a single episode card
     */
    renderEpisodeCard(episode) {
        const isPlaying = window.audioPlayer && 
                         window.audioPlayer.currentEpisode?.id === episode.id && 
                         window.audioPlayer.isPlaying;
        const coverUrl = episode.image_url || this.currentPodcast?.cover_url || '';
        
        return `
            <div class="player-episode-card ${isPlaying ? 'playing' : ''}" data-episode-id="${episode.id}">
                <div class="player-episode-cover">
                    ${coverUrl ? 
                        `<img src="${this.escapeHtml(coverUrl)}" alt="${this.escapeHtml(episode.title)}">` :
                        `<div class="player-episode-cover-placeholder">🎙️</div>`
                    }
                </div>
                <div class="player-episode-info">
                    <h5 class="player-episode-title">
                        ${this.escapeHtml(episode.title)}
                        ${isPlaying ? `
                            <span class="player-equalizer">
                                <span class="player-equalizer-bar"></span>
                                <span class="player-equalizer-bar"></span>
                                <span class="player-equalizer-bar"></span>
                            </span>
                        ` : ''}
                    </h5>
                    <div class="player-episode-meta">
                        <span>${this.formatDate(episode.pub_date)}</span>
                        <span class="player-episode-meta-separator">•</span>
                        <span>${episode.duration || 'Unknown'}</span>
                    </div>
                    ${episode.description ? `
                        <p class="player-episode-description" data-full-text="${this.escapeHtml(episode.description)}" onclick="event.stopPropagation(); playerModal.toggleDescription(this);">${this.escapeHtml(episode.description)}</p>
                    ` : ''}
                </div>
                <div class="player-episode-actions">
                    <button class="player-episode-action-btn" 
                            onclick="playerModal.downloadEpisode('${this.escapeJs(episode.audio_url)}', '${this.escapeJs(episode.title)}')"
                            title="Download MP3">
                        <i class="fa-solid fa-download"></i>
                    </button>
                    <button class="player-episode-action-btn play-btn" 
                            onclick="playerModal.togglePlayback('${episode.id}')"
                            title="${isPlaying ? 'Pause' : 'Play'} episode">
                        <i class="fa-solid fa-${isPlaying ? 'pause' : 'play'}"></i>
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Show empty state
     */
    showEmptyState(message = 'No episodes found') {
        const listEl = document.getElementById('playerEpisodesList');
        if (!listEl) return;

        listEl.innerHTML = `
            <div class="player-empty-state">
                <div class="player-empty-state-icon">🎙️</div>
                <p>${message}</p>
            </div>
        `;
    }

    /**
     * Filter episodes by search term
     */
    filterEpisodes(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        
        if (!term) {
            this.filteredEpisodes = [...this.episodes];
        } else {
            this.filteredEpisodes = this.episodes.filter(episode => {
                return episode.title.toLowerCase().includes(term) ||
                       (episode.description && episode.description.toLowerCase().includes(term));
            });
        }

        this.renderEpisodes();
    }

    /**
     * Sort episodes
     */
    sortEpisodes(sortBy) {
        switch (sortBy) {
            case 'newest':
                this.filteredEpisodes.sort((a, b) => new Date(b.pub_date) - new Date(a.pub_date));
                break;
            case 'oldest':
                this.filteredEpisodes.sort((a, b) => new Date(a.pub_date) - new Date(b.pub_date));
                break;
            case 'title':
                this.filteredEpisodes.sort((a, b) => a.title.localeCompare(b.title));
                break;
        }

        this.renderEpisodes();
    }

    /**
     * Toggle playback for an episode
     */
    togglePlayback(episodeId) {
        const episode = this.episodes.find(ep => ep.id === episodeId);
        if (!episode) return;

        // Add podcast info to episode
        episode.podcast_title = this.currentPodcast?.title;
        episode.podcast_cover = this.currentPodcast?.cover_url;

        if (window.audioPlayer) {
            window.audioPlayer.togglePlayback(episode, this.episodes);
        }
    }

    /**
     * Download episode
     */
    downloadEpisode(audioUrl, title) {
        // Find the episode by audio URL
        const episode = this.episodes.find(ep => ep.audio_url === audioUrl);
        
        // Emit analytics event before download
        if (episode && this.currentPodcast) {
            const event = new CustomEvent('audio:episodeDownloaded', {
                detail: {
                    episode: episode,
                    podcast: {
                        id: this.currentPodcast.id,
                        title: this.currentPodcast.title
                    }
                }
            });
            window.dispatchEvent(event);
        }

        const link = document.createElement('a');
        link.href = audioUrl;
        link.download = `${title}.mp3`;
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        this.showToast('Download started...', 'success');
    }

    /**
     * Share podcast feed URL
     */
    sharePodcast() {
        if (!this.currentPodcast) return;

        const shareUrl = this.currentPodcast.feed_url;
        
        navigator.clipboard.writeText(shareUrl).then(() => {
            this.showToast('RSS feed URL copied! You can paste this into any podcast app.', 'success');
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = shareUrl;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                this.showToast('RSS feed URL copied! You can paste this into any podcast app.', 'success');
            } catch (err) {
                this.showToast('Feed URL: ' + shareUrl, 'info');
            }
            document.body.removeChild(textArea);
        });
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        // Reuse the existing alert system from app.js
        if (window.podcastApp && window.podcastApp.showAlert) {
            window.podcastApp.showAlert(message, type);
        } else {
            alert(message);
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        this.showToast(message, 'danger');
    }

    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return 'Unknown';

        try {
            const date = new Date(dateString);
            const now = new Date();
            
            // Reset time to midnight for accurate day comparison
            const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            const nowOnly = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            
            const diffTime = nowOnly - dateOnly;
            const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays === 0) return 'Today';
            if (diffDays === 1) return 'Yesterday';
            if (diffDays === -1) return 'Tomorrow'; // Future date
            if (diffDays < 0) return 'In the future'; // Future date
            if (diffDays < 7) return `${diffDays} days ago`;
            
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        } catch (e) {
            return dateString;
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Escape JavaScript strings for use in onclick attributes
     * Handles quotes, apostrophes, backslashes, and newlines
     */
    escapeJs(text) {
        if (!text) return '';
        return text
            .replace(/\\/g, '\\\\')   // Escape backslashes first
            .replace(/'/g, "\\'")     // Escape single quotes
            .replace(/"/g, '\\"')     // Escape double quotes
            .replace(/\n/g, '\\n')    // Escape newlines
            .replace(/\r/g, '\\r')    // Escape carriage returns
            .replace(/\t/g, '\\t');   // Escape tabs
    }

    /**
     * Update episode card playing state
     */
    updateEpisodePlayingState(episodeId, isPlaying) {
        const cards = document.querySelectorAll('.player-episode-card');
        cards.forEach(card => {
            const cardEpisodeId = card.dataset.episodeId;
            if (cardEpisodeId === episodeId) {
                if (isPlaying) {
                    card.classList.add('playing');
                } else {
                    card.classList.remove('playing');
                }
            } else {
                card.classList.remove('playing');
            }
        });

        // Re-render to update play/pause icons
        this.renderEpisodes();
    }
}

// Global functions for inline event handlers
function showPlayerModal(podcastId) {
    window.playerModal.show(podcastId);
}

function hidePlayerModal() {
    window.playerModal.hide();
}

function sharePodcast() {
    window.playerModal.sharePodcast();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.playerModal = new PodcastPlayerModal();
});
