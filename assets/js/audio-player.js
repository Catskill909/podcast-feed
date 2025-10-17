/**
 * Audio Player - Playback Controls & State Management
 * Handles audio playback, progress tracking, and player UI
 */

class AudioPlayer {
    constructor() {
        this.audio = null;
        this.currentEpisode = null;
        this.playlist = [];
        this.currentIndex = -1;
        this.isPlaying = false;
        this.isMuted = false;
        this.volume = 1.0;
        this.playbackSpeed = 1.0;
        this.init();
    }

    /**
     * Initialize the audio player
     */
    init() {
        this.audio = document.getElementById('audioPlayer');
        if (!this.audio) {
            console.error('Audio element not found');
            return;
        }

        this.setupEventListeners();
        this.restorePlaybackState();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        if (!this.audio) return;

        // Audio events
        this.audio.addEventListener('loadedmetadata', () => this.onLoadedMetadata());
        this.audio.addEventListener('timeupdate', () => this.onTimeUpdate());
        this.audio.addEventListener('ended', () => this.onEnded());
        this.audio.addEventListener('play', () => this.onPlay());
        this.audio.addEventListener('pause', () => this.onPause());
        this.audio.addEventListener('error', (e) => this.onError(e));
        this.audio.addEventListener('progress', () => this.onProgress());

        // Scrubber
        const scrubber = document.getElementById('audioScrubber');
        if (scrubber) {
            scrubber.addEventListener('input', (e) => this.seekTo(e.target.value));
        }

        // Volume
        const volumeSlider = document.getElementById('volumeSlider');
        if (volumeSlider) {
            volumeSlider.addEventListener('input', (e) => this.setVolume(e.target.value / 100));
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    }

    /**
     * Toggle playback for an episode
     */
    togglePlayback(episode, playlist = []) {
        if (this.currentEpisode?.id === episode.id) {
            // Same episode - toggle play/pause
            if (this.isPlaying) {
                this.pause();
            } else {
                this.play();
            }
        } else {
            // New episode - load and play
            this.loadEpisode(episode, playlist);
        }
    }

    /**
     * Load an episode
     */
    loadEpisode(episode, playlist = []) {
        this.currentEpisode = episode;
        this.playlist = playlist;
        this.currentIndex = playlist.findIndex(ep => ep.id === episode.id);

        // Set audio source
        this.audio.src = episode.audio_url;
        
        // Set title attribute as fallback for browsers without MediaSession support
        const podcastTitle = window.playerModal?.currentPodcast?.title || 'Podcast';
        this.audio.title = `${episode.title} - ${podcastTitle}`;
        
        this.audio.load();

        // Update UI
        this.updatePlayerUI();
        this.showPlayerBar();

        // Auto-play
        this.play();

        // Update media session metadata
        this.updateMediaSession();
        
        // Update document title as fallback
        this.updateDocumentTitle();

        // Save state
        this.savePlaybackState();
    }
    
    /**
     * Update document title (fallback for browsers without MediaSession)
     */
    updateDocumentTitle() {
        if (!this.currentEpisode) return;
        
        const podcastTitle = window.playerModal?.currentPodcast?.title || 'Podcast Browser';
        const episodeTitle = this.currentEpisode.title || 'Episode';
        
        // Store original title to restore later
        if (!this.originalTitle) {
            this.originalTitle = document.title;
        }
        
        document.title = `▶ ${episodeTitle} - ${podcastTitle}`;
    }
    
    /**
     * Restore original document title
     */
    restoreDocumentTitle() {
        if (this.originalTitle) {
            document.title = this.originalTitle;
        }
    }

    /**
     * Update MediaSession API for lock screen controls
     */
    updateMediaSession() {
        if (!('mediaSession' in navigator)) return;
        if (!this.currentEpisode) return;

        // Get podcast info from modal or current context
        const podcastTitle = window.playerModal?.currentPodcast?.title || 'Podcast Browser';
        const podcastImage = window.playerModal?.currentPodcast?.cover_url || '';

        // Set metadata
        navigator.mediaSession.metadata = new MediaMetadata({
            title: this.currentEpisode.title || 'Unknown Episode',
            artist: podcastTitle,
            album: podcastTitle,
            artwork: [
                { src: podcastImage, sizes: '96x96',   type: 'image/jpeg' },
                { src: podcastImage, sizes: '128x128', type: 'image/jpeg' },
                { src: podcastImage, sizes: '192x192', type: 'image/jpeg' },
                { src: podcastImage, sizes: '256x256', type: 'image/jpeg' },
                { src: podcastImage, sizes: '384x384', type: 'image/jpeg' },
                { src: podcastImage, sizes: '512x512', type: 'image/jpeg' }
            ]
        });

        // Set action handlers
        navigator.mediaSession.setActionHandler('play', () => this.play());
        navigator.mediaSession.setActionHandler('pause', () => this.pause());
        navigator.mediaSession.setActionHandler('previoustrack', () => this.previousEpisode());
        navigator.mediaSession.setActionHandler('nexttrack', () => this.nextEpisode());
        navigator.mediaSession.setActionHandler('seekbackward', () => this.skipBackward(15));
        navigator.mediaSession.setActionHandler('seekforward', () => this.skipForward(15));
        
        // Seek to specific position (if supported)
        try {
            navigator.mediaSession.setActionHandler('seekto', (details) => {
                if (details.seekTime) {
                    this.audio.currentTime = details.seekTime;
                }
            });
        } catch (error) {
            // seekto not supported
        }
    }

    /**
     * Play audio
     */
    play() {
        if (!this.audio.src) return;

        const playPromise = this.audio.play();
        
        if (playPromise !== undefined) {
            playPromise.then(() => {
                this.isPlaying = true;
                this.updatePlayPauseIcon();
            }).catch(error => {
                console.error('Playback failed:', error);
                this.showError('Playback failed. Please try again.');
            });
        }
    }

    /**
     * Pause audio
     */
    pause() {
        this.audio.pause();
        this.isPlaying = false;
        this.updatePlayPauseIcon();
    }

    /**
     * Stop playback and reset all settings
     */
    stopPlayback() {
        this.pause();
        this.audio.currentTime = 0;
        this.currentEpisode = null;
        
        // Reset playback speed to normal
        this.playbackSpeed = 1.0;
        this.audio.playbackRate = 1.0;
        this.updateSpeedLabel();
        
        // Restore original document title
        this.restoreDocumentTitle();
        
        this.hidePlayerBar();
        this.hideMiniPlayer();
        this.clearPlaybackState();
        
        // Update episode cards
        if (window.playerModal) {
            window.playerModal.renderEpisodes();
        }
    }

    /**
     * Previous episode
     */
    previousEpisode() {
        if (this.currentIndex > 0) {
            const prevEpisode = this.playlist[this.currentIndex - 1];
            this.loadEpisode(prevEpisode, this.playlist);
        }
    }

    /**
     * Next episode
     */
    nextEpisode() {
        if (this.currentIndex < this.playlist.length - 1) {
            const nextEpisode = this.playlist[this.currentIndex + 1];
            this.loadEpisode(nextEpisode, this.playlist);
        }
    }

    /**
     * Skip forward (default 15 seconds)
     */
    skipForward(seconds = 15) {
        this.audio.currentTime = Math.min(this.audio.currentTime + seconds, this.audio.duration);
    }

    /**
     * Skip backward (default 15 seconds)
     */
    skipBackward(seconds = 15) {
        this.audio.currentTime = Math.max(this.audio.currentTime - seconds, 0);
    }

    /**
     * Seek to specific time
     */
    seekTo(percentage) {
        if (!this.audio.duration) return;
        this.audio.currentTime = (percentage / 100) * this.audio.duration;
    }

    /**
     * Set volume
     */
    setVolume(level) {
        this.volume = Math.max(0, Math.min(1, level));
        this.audio.volume = this.volume;
        this.updateVolumeIcon();
        this.savePlaybackState();
    }

    /**
     * Toggle mute
     */
    toggleMute() {
        this.isMuted = !this.isMuted;
        this.audio.muted = this.isMuted;
        this.updateVolumeIcon();
    }

    /**
     * Set playback speed
     */
    setPlaybackSpeed(speed) {
        this.playbackSpeed = speed;
        this.audio.playbackRate = speed;
        this.updateSpeedLabel();
        this.savePlaybackState();
    }

    /**
     * Cycle through playback speeds
     */
    cyclePlaybackSpeed() {
        const speeds = [0.5, 0.75, 1.0, 1.25, 1.5, 2.0];
        const currentIndex = speeds.indexOf(this.playbackSpeed);
        const nextIndex = (currentIndex + 1) % speeds.length;
        this.setPlaybackSpeed(speeds[nextIndex]);
    }

    /**
     * Audio event: Loaded metadata
     */
    onLoadedMetadata() {
        const totalDuration = document.getElementById('totalDuration');
        if (totalDuration) {
            totalDuration.textContent = this.formatTime(this.audio.duration);
        }
    }

    /**
     * Audio event: Time update
     */
    onTimeUpdate() {
        const currentTime = document.getElementById('currentTime');
        const audioProgress = document.getElementById('audioProgress');
        const audioScrubber = document.getElementById('audioScrubber');

        if (currentTime) {
            currentTime.textContent = this.formatTime(this.audio.currentTime);
        }

        if (this.audio.duration) {
            const percentage = (this.audio.currentTime / this.audio.duration) * 100;
            
            if (audioProgress) {
                audioProgress.style.width = percentage + '%';
            }
            
            if (audioScrubber) {
                audioScrubber.value = percentage;
            }
        }

        // Update MediaSession position state
        this.updatePositionState();

        // Save progress periodically
        if (Math.floor(this.audio.currentTime) % 10 === 0) {
            this.savePlaybackState();
        }
    }

    /**
     * Update MediaSession position state
     */
    updatePositionState() {
        if (!('mediaSession' in navigator)) return;
        if (!this.audio.duration) return;

        try {
            navigator.mediaSession.setPositionState({
                duration: this.audio.duration,
                playbackRate: this.audio.playbackRate,
                position: this.audio.currentTime
            });
        } catch (error) {
            // Position state not supported
        }
    }

    /**
     * Audio event: Progress (buffering)
     */
    onProgress() {
        if (!this.audio.buffered.length) return;

        const buffered = this.audio.buffered.end(this.audio.buffered.length - 1);
        const duration = this.audio.duration;
        
        if (duration > 0) {
            const percentage = (buffered / duration) * 100;
            const audioBuffered = document.getElementById('audioBuffered');
            if (audioBuffered) {
                audioBuffered.style.width = percentage + '%';
            }
        }
    }

    /**
     * Audio event: Ended
     */
    onEnded() {
        this.isPlaying = false;
        this.updatePlayPauseIcon();
        
        // Auto-play next episode
        if (this.currentIndex < this.playlist.length - 1) {
            setTimeout(() => this.nextEpisode(), 1000);
        }
    }

    /**
     * Audio event: Play
     */
    onPlay() {
        this.isPlaying = true;
        this.updatePlayPauseIcon();
        
        // Update episode card state
        if (window.playerModal && this.currentEpisode) {
            window.playerModal.updateEpisodePlayingState(this.currentEpisode.id, true);
        }
    }

    /**
     * Audio event: Pause
     */
    onPause() {
        this.isPlaying = false;
        this.updatePlayPauseIcon();
        
        // Update episode card state
        if (window.playerModal && this.currentEpisode) {
            window.playerModal.updateEpisodePlayingState(this.currentEpisode.id, false);
        }
    }

    /**
     * Audio event: Error
     */
    onError(e) {
        console.error('Audio error:', e);
        this.showError('Failed to load audio. The file may be unavailable.');
        this.isPlaying = false;
        this.updatePlayPauseIcon();
    }

    /**
     * Handle keyboard shortcuts
     */
    handleKeyboard(e) {
        // Only handle if player is active and not typing in input
        if (!this.currentEpisode || e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }

        switch (e.key) {
            case ' ':
                e.preventDefault();
                this.isPlaying ? this.pause() : this.play();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                this.skipBackward(5);
                break;
            case 'ArrowRight':
                e.preventDefault();
                this.skipForward(5);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.setVolume(Math.min(this.volume + 0.1, 1));
                break;
            case 'ArrowDown':
                e.preventDefault();
                this.setVolume(Math.max(this.volume - 0.1, 0));
                break;
            case 'm':
            case 'M':
                e.preventDefault();
                this.toggleMute();
                break;
        }
    }

    /**
     * Update player UI
     */
    updatePlayerUI() {
        if (!this.currentEpisode) return;

        const titleEl = document.getElementById('currentEpisodeTitle');
        if (titleEl) {
            titleEl.textContent = this.currentEpisode.title;
        }

        // Update mini player if visible
        this.updateMiniPlayer();
    }

    /**
     * Update play/pause icon
     */
    updatePlayPauseIcon() {
        const icon = document.getElementById('playPauseIcon');
        if (icon) {
            icon.innerHTML = this.isPlaying ? '<i class="fa-solid fa-pause"></i>' : '<i class="fa-solid fa-play"></i>';
        }
    }

    /**
     * Update volume icon
     */
    updateVolumeIcon() {
        const icon = document.getElementById('volumeIcon');
        if (!icon) return;

        if (this.isMuted || this.volume === 0) {
            icon.innerHTML = '<i class="fa-solid fa-volume-xmark"></i>';
        } else if (this.volume < 0.5) {
            icon.innerHTML = '<i class="fa-solid fa-volume-low"></i>';
        } else {
            icon.innerHTML = '<i class="fa-solid fa-volume-high"></i>';
        }

        const volumeSlider = document.getElementById('volumeSlider');
        if (volumeSlider) {
            volumeSlider.value = this.volume * 100;
        }
    }

    /**
     * Update speed label
     */
    updateSpeedLabel() {
        const label = document.getElementById('speedLabel');
        if (label) {
            label.textContent = this.playbackSpeed + 'x';
        }
    }

    /**
     * Show player bar
     */
    showPlayerBar() {
        const playerBar = document.getElementById('audioPlayerBar');
        if (playerBar) {
            playerBar.style.display = 'block';
        }
    }

    /**
     * Hide player bar
     */
    hidePlayerBar() {
        const playerBar = document.getElementById('audioPlayerBar');
        if (playerBar) {
            playerBar.style.display = 'none';
        }
    }

    /**
     * Show mini player (when modal is closed but audio playing)
     */
    showMiniPlayer() {
        let miniPlayer = document.getElementById('miniPlayer');
        
        if (!miniPlayer) {
            miniPlayer = this.createMiniPlayer();
            document.body.appendChild(miniPlayer);
        }

        this.updateMiniPlayer();
        miniPlayer.style.display = 'block';
    }

    /**
     * Hide mini player
     */
    hideMiniPlayer() {
        const miniPlayer = document.getElementById('miniPlayer');
        if (miniPlayer) {
            miniPlayer.remove();
        }
    }

    /**
     * Create mini player element
     */
    createMiniPlayer() {
        const div = document.createElement('div');
        div.id = 'miniPlayer';
        div.className = 'mini-player';
        div.onclick = () => {
            if (window.playerModal && window.playerModal.currentPodcastId) {
                window.playerModal.show(window.playerModal.currentPodcastId);
            }
        };
        
        div.innerHTML = `
            <button class="mini-player-close" onclick="event.stopPropagation(); audioPlayer.stopPlayback();">&times;</button>
            <div class="mini-player-content">
                <div class="mini-player-cover">
                    <img id="miniPlayerCover" src="" alt="Cover">
                </div>
                <div class="mini-player-info">
                    <div class="mini-player-title" id="miniPlayerTitle"></div>
                    <div class="mini-player-podcast" id="miniPlayerPodcast"></div>
                </div>
                <div class="mini-player-controls">
                    <button class="mini-player-btn" onclick="event.stopPropagation(); audioPlayer.isPlaying ? audioPlayer.pause() : audioPlayer.play();">
                        <i class="fa-solid fa-${this.isPlaying ? 'pause' : 'play'}"></i>
                    </button>
                </div>
            </div>
        `;
        
        return div;
    }

    /**
     * Update mini player content
     */
    updateMiniPlayer() {
        if (!this.currentEpisode) return;

        const cover = document.getElementById('miniPlayerCover');
        const title = document.getElementById('miniPlayerTitle');
        const podcast = document.getElementById('miniPlayerPodcast');

        if (cover) {
            cover.src = this.currentEpisode.podcast_cover || this.currentEpisode.image_url || '';
        }

        if (title) {
            title.textContent = this.currentEpisode.title;
        }

        if (podcast) {
            podcast.textContent = this.currentEpisode.podcast_title || 'Podcast';
        }
    }

    /**
     * Format time in MM:SS or HH:MM:SS
     */
    formatTime(seconds) {
        if (!seconds || isNaN(seconds)) return '0:00';

        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }

    /**
     * Save playback state to localStorage
     */
    savePlaybackState() {
        if (!this.currentEpisode) return;

        const state = {
            episode: this.currentEpisode,
            currentTime: this.audio.currentTime,
            volume: this.volume,
            playbackSpeed: this.playbackSpeed,
            timestamp: Date.now()
        };

        localStorage.setItem('podcast_player_state', JSON.stringify(state));
    }

    /**
     * Restore playback state from localStorage
     */
    restorePlaybackState() {
        try {
            const stateJson = localStorage.getItem('podcast_player_state');
            if (!stateJson) return;

            const state = JSON.parse(stateJson);
            
            // Only restore if less than 24 hours old
            if (Date.now() - state.timestamp > 24 * 60 * 60 * 1000) {
                this.clearPlaybackState();
                return;
            }

            // Restore volume and speed
            if (state.volume !== undefined) {
                this.setVolume(state.volume);
            }
            
            if (state.playbackSpeed !== undefined) {
                this.setPlaybackSpeed(state.playbackSpeed);
            }

            // Note: We don't auto-resume playback, just restore settings
        } catch (e) {
            console.error('Failed to restore playback state:', e);
            this.clearPlaybackState();
        }
    }

    /**
     * Clear playback state
     */
    clearPlaybackState() {
        localStorage.removeItem('podcast_player_state');
    }

    /**
     * Show error message
     */
    showError(message) {
        if (window.playerModal) {
            window.playerModal.showToast(message, 'danger');
        } else {
            console.error(message);
        }
    }
}

// Global functions for inline event handlers
function togglePlayback() {
    if (window.audioPlayer) {
        window.audioPlayer.isPlaying ? window.audioPlayer.pause() : window.audioPlayer.play();
    }
}

function stopPlayback() {
    if (window.audioPlayer) {
        window.audioPlayer.stopPlayback();
    }
}

function previousEpisode() {
    if (window.audioPlayer) {
        window.audioPlayer.previousEpisode();
    }
}

function nextEpisode() {
    if (window.audioPlayer) {
        window.audioPlayer.nextEpisode();
    }
}

function skipForward() {
    if (window.audioPlayer) {
        window.audioPlayer.skipForward(15);
    }
}

function skipBackward() {
    if (window.audioPlayer) {
        window.audioPlayer.skipBackward(15);
    }
}

function toggleMute() {
    if (window.audioPlayer) {
        window.audioPlayer.toggleMute();
    }
}

function cyclePlaybackSpeed() {
    if (window.audioPlayer) {
        window.audioPlayer.cyclePlaybackSpeed();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.audioPlayer = new AudioPlayer();
});
