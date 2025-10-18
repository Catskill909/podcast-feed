/**
 * Custom Audio Player
 * Beautiful, functional audio player for podcast episodes
 */

class CustomAudioPlayer {
    constructor(container) {
        this.container = container;
        this.audio = container.querySelector('audio');
        this.playBtn = null;
        this.progressBar = null;
        this.progressFill = null;
        this.currentTimeEl = null;
        this.durationEl = null;
        this.volumeBtn = null;
        this.volumeSlider = null;
        this.volumeFill = null;
        this.speedBtn = null;
        
        this.isPlaying = false;
        this.currentSpeed = 1;
        this.speeds = [0.5, 0.75, 1, 1.25, 1.5, 2];
        
        this.init();
    }
    
    init() {
        this.createPlayerUI();
        this.attachEventListeners();
        // Show stored duration immediately if available
        this.updateDuration();
    }
    
    createPlayerUI() {
        const playerHTML = `
            <div class="custom-audio-player">
                <button class="audio-play-btn" title="Play/Pause">
                    <i class="fas fa-play"></i>
                </button>
                
                <div class="audio-progress-container">
                    <div class="audio-time-display">
                        <span class="audio-current-time">0:00</span>
                        <span class="audio-duration">0:00</span>
                    </div>
                    <div class="audio-progress-bar">
                        <div class="audio-progress-fill"></div>
                        <input type="range" class="audio-scrubber" min="0" max="100" value="0" step="0.1">
                    </div>
                </div>
                
                <div class="audio-volume-control">
                    <button class="audio-volume-btn" title="Mute/Unmute">
                        <i class="fas fa-volume-up"></i>
                    </button>
                    <input type="range" class="audio-volume-slider-input" min="0" max="100" value="100" step="1">
                </div>
                
                <button class="audio-speed-btn" title="Playback Speed">
                    1x
                </button>
            </div>
        `;
        
        this.container.insertAdjacentHTML('beforeend', playerHTML);
        
        // Get references
        this.playBtn = this.container.querySelector('.audio-play-btn');
        this.scrubber = this.container.querySelector('.audio-scrubber');
        this.progressFill = this.container.querySelector('.audio-progress-fill');
        this.currentTimeEl = this.container.querySelector('.audio-current-time');
        this.durationEl = this.container.querySelector('.audio-duration');
        this.volumeBtn = this.container.querySelector('.audio-volume-btn');
        this.volumeSlider = this.container.querySelector('.audio-volume-slider-input');
        this.speedBtn = this.container.querySelector('.audio-speed-btn');
    }
    
    attachEventListeners() {
        // Play/Pause
        this.playBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.togglePlay();
        });
        
        // Scrubber - EXACT copy from working audio-player.js
        this.scrubber.addEventListener('input', (e) => {
            if (!this.audio.duration) return;
            this.audio.currentTime = (e.target.value / 100) * this.audio.duration;
        });
        
        // Volume button
        this.volumeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleMute();
        });
        
        // Volume slider
        this.volumeSlider.addEventListener('input', (e) => {
            const volume = parseFloat(e.target.value) / 100;
            this.audio.volume = volume;
            this.audio.muted = false;
            this.updateVolumeIcon();
        });
        
        // Speed
        this.speedBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.cycleSpeed();
        });
        
        // Audio events - EXACT copy from working audio-player.js
        this.audio.addEventListener('loadedmetadata', () => this.updateDuration());
        this.audio.addEventListener('timeupdate', () => this.updateProgress());
        this.audio.addEventListener('ended', () => this.onEnded());
        this.audio.addEventListener('play', () => this.onPlay());
        this.audio.addEventListener('pause', () => this.onPause());
    }
    
    togglePlay() {
        if (this.isPlaying) {
            this.audio.pause();
        } else {
            this.audio.play();
        }
    }
    
    onPlay() {
        this.isPlaying = true;
        this.playBtn.innerHTML = '<i class="fas fa-pause"></i>';
    }
    
    onPause() {
        this.isPlaying = false;
        this.playBtn.innerHTML = '<i class="fas fa-play"></i>';
    }
    
    onEnded() {
        this.isPlaying = false;
        this.playBtn.innerHTML = '<i class="fas fa-play"></i>';
        this.audio.currentTime = 0;
    }
    
    updateDuration() {
        let duration = this.audio.duration;
        
        // Fallback: Use stored duration from data attribute if audio metadata not loaded
        if (!duration || isNaN(duration) || duration === 0) {
            const storedDuration = this.container.getAttribute('data-duration');
            if (storedDuration) {
                duration = parseInt(storedDuration, 10);
            }
        }
        
        this.durationEl.textContent = this.formatTime(duration);
    }
    
    updateProgress() {
        if (!this.audio.duration) return;
        
        const percent = (this.audio.currentTime / this.audio.duration) * 100;
        
        // EXACT copy from working audio-player.js
        this.progressFill.style.width = percent + '%';
        this.scrubber.value = percent;
        this.currentTimeEl.textContent = this.formatTime(this.audio.currentTime);
    }
    
    toggleMute() {
        this.audio.muted = !this.audio.muted;
        this.volumeSlider.value = this.audio.muted ? 0 : this.audio.volume * 100;
        this.updateVolumeIcon();
    }
    
    updateVolumeIcon() {
        const icon = this.volumeBtn.querySelector('i');
        if (this.audio.muted || this.audio.volume === 0) {
            icon.className = 'fas fa-volume-mute';
        } else if (this.audio.volume < 0.5) {
            icon.className = 'fas fa-volume-down';
        } else {
            icon.className = 'fas fa-volume-up';
        }
    }
    
    cycleSpeed() {
        const currentIndex = this.speeds.indexOf(this.currentSpeed);
        const nextIndex = (currentIndex + 1) % this.speeds.length;
        this.currentSpeed = this.speeds[nextIndex];
        this.audio.playbackRate = this.currentSpeed;
        this.speedBtn.textContent = this.currentSpeed + 'x';
    }
    
    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        
        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
}

// Auto-initialize all audio players on page
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.audio-player-container').forEach(container => {
        new CustomAudioPlayer(container);
    });
});

// Export for use
window.CustomAudioPlayer = CustomAudioPlayer;
