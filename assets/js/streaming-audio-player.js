(function () {
    const DEFAULT_METADATA_INTERVAL = 15000;

    class StreamingAudioPlayer {
        constructor(root, options = {}) {
            this.root = root;
            this.options = options;
            this.audio = root.querySelector('#liveAudio');

            this.playPauseButton = root.querySelector('#playPauseButton');
            this.playPauseIcon = root.querySelector('#playPauseIcon');
            this.playPauseSpinner = root.querySelector('#playPauseSpinner');
            this.playPauseAssist = root.querySelector('#playPauseAssist');
            this.muteButton = root.querySelector('#muteButton');
            this.volumeSlider = root.querySelector('#volumeSlider');
            this.volumeIcon = root.querySelector('#volumeIcon');
            this.volumeControl = root.querySelector('#volumeControl');
            this.statusEl = root.querySelector('#playerStatus');
            this.lastUpdatedEl = root.querySelector('#lastUpdated');

            this.stationArtworkEl = root.querySelector('#stationArtwork');

            this.nowPlayingTitleEl = root.querySelector('#nowPlayingTitle');
            this.nowPlayingArtistEl = root.querySelector('#nowPlayingArtist');
            this.nowPlayingSummaryEl = root.querySelector('#nowPlayingSummary');
            this.nowPlayingSongEl = root.querySelector('#nowPlayingSong');
            this.nowPlayingSummaryArtistEl = root.querySelector('#nowPlayingSummaryArtist');
            this.nowPlayingSeparatorEl = this.nowPlayingSummaryEl?.querySelector('.player-nowplaying__separator') ?? null;

            this.showNameEl = root.querySelector('#showName');
            this.showHostsEl = root.querySelector('#showHosts');
            this.showScheduleEl = root.querySelector('#showSchedule');
            this.pledgeRowEl = root.querySelector('#pledgeRow');
            this.pledgeLinkEl = root.querySelector('#pledgeLink');

            this.nextShowNameEl = root.querySelector('#nextShowName');
            this.nextShowTimeEl = root.querySelector('#nextShowTime');

            this.streamEndpoint = root.dataset.streamEndpoint;
            this.metadataEndpoint = root.dataset.metadataEndpoint;
            this.metadataInterval = parseInt(root.dataset.metadataInterval, 10) || DEFAULT_METADATA_INTERVAL;
            this.placeholderArtwork = root.dataset.placeholderArtwork || null;

            this.streamUrl = '';
            this.metadataTimer = null;
            this.lastMetadataSignature = '';
            this.metadataInFlight = false;
            this.streamRequestInFlight = false;
            this.previousVolume = 0.8;
            this.userInitiatedPause = false;

            this.errorModalEl = root.querySelector('#playerErrorModal');
            this.errorMessageEl = root.querySelector('#playerErrorMessage');
            this.errorRetryBtn = root.querySelector('#playerErrorRetry');
            this.errorCloseBtn = root.querySelector('#playerErrorClose');

            this.init();
        }

        init() {
            if (!this.audio) {
                console.error('Streaming audio element missing');
                return;
            }

            this.attachEventListeners();
            this.audio.preload = 'auto';
            this.audio.volume = (parseInt(this.volumeSlider?.value ?? '80', 10) || 80) / 100;
            this.previousVolume = this.audio.volume;
            this.updateVolumeIcon();
            this.updateState('loading', 'Initialising stream…');

            if (this.stationArtworkEl && this.placeholderArtwork) {
                this.stationArtworkEl.src = this.placeholderArtwork;
                if (this.stationArtworkEl.complete) {
                    this.stationArtworkEl.classList.add('is-loaded');
                }
                this.stationArtworkEl.addEventListener('load', () => {
                    this.stationArtworkEl.classList.add('is-loaded');
                }, { once: true });
            }

            this.fetchStreamUrl().then(() => {
                this.updateState('ready', 'Stream ready. Press play to listen.');
                this.fetchMetadata();
                this.startMetadataPolling();
            }).catch((error) => {
                console.error(error);
                this.updateState('error', 'Unable to load stream URL. Please try again later.');
            });
        }

        attachEventListeners() {
            if (this.playPauseButton) {
                this.playPauseButton.addEventListener('click', () => {
                    if (!this.streamUrl) {
                        this.setLoadingIndicator(true);
                        this.fetchStreamUrl().then(() => {
                            this.play();
                        }).catch((error) => {
                            console.error(error);
                            this.setLoadingIndicator(false);
                            this.updateState('error', 'Unable to load stream URL. Please try again later.');
                        });
                        return;
                    }

                    if (this.audio.paused) {
                        this.play();
                    } else {
                        this.pause();
                    }
                });
            }

            if (this.volumeSlider) {
                this.volumeSlider.addEventListener('input', (event) => {
                    const level = Math.max(0, Math.min(1, parseInt(event.target.value, 10) / 100));
                    this.audio.volume = level;
                    if (level === 0) {
                        this.audio.muted = true;
                    } else {
                        this.audio.muted = false;
                        this.previousVolume = level;
                    }
                    this.updateVolumeIcon();
                });

                this.volumeSlider.addEventListener('blur', () => {
                    this.expandVolumeSlider(false);
                });
            }

            if (this.volumeControl) {
                this.volumeControl.addEventListener('mouseleave', () => {
                    this.expandVolumeSlider(false);
                });
            }

            if (this.muteButton) {
                this.muteButton.addEventListener('click', () => {
                    this.toggleVolumeExpansion();
                    const wasMuted = this.audio.muted;
                    if (wasMuted) {
                        this.audio.muted = false;
                        const restore = this.previousVolume > 0 ? this.previousVolume : 0.4;
                        this.audio.volume = restore;
                        if (this.volumeSlider) {
                            this.volumeSlider.value = String(Math.round(restore * 100));
                        }
                    } else {
                        this.audio.muted = true;
                        if (this.volumeSlider) {
                            this.volumeSlider.value = '0';
                        }
                    }
                    this.updateVolumeIcon();
                });
            }

            this.audio.addEventListener('playing', () => {
                this.setPlayButton(true);
                this.updateState('playing', 'Now playing live stream.');
                this.hideErrorModal();
            });

            this.audio.addEventListener('pause', () => {
                if (this.audio.currentTime === 0) {
                    this.updateState('ready', 'Stream paused. Press play to resume.');
                } else {
                    this.updateState('paused', 'Stream paused.');
                }
                this.setPlayButton(false);

                if (this.userInitiatedPause) {
                    this.teardownStream();
                    this.userInitiatedPause = false;
                }
            });

            this.audio.addEventListener('waiting', () => {
                if (!this.audio.paused) {
                    this.setLoadingIndicator(true);
                }
                this.updateStatus('Buffering stream…');
            });

            this.audio.addEventListener('error', () => {
                const mediaError = this.audio.error;
                const message = mediaError?.message || 'Playback error detected. Retrying…';
                console.error('Audio playback error:', mediaError);
                this.updateState('error', message);
                this.setPlayButton(false);
                this.showErrorModal('We lost the connection to the live stream. Please try again.');
            });

            this.audio.addEventListener('canplay', () => {
                if (!this.audio.paused) {
                    this.setLoadingIndicator(false);
                }
            });

            if (this.errorRetryBtn) {
                this.errorRetryBtn.addEventListener('click', () => {
                    this.hideErrorModal();
                    this.setLoadingIndicator(true);
                    this.fetchStreamUrl().then(() => {
                        this.play();
                    }).catch((error) => {
                        console.error(error);
                        this.setLoadingIndicator(false);
                        this.showErrorModal('Still unable to reach the stream. Please try again shortly.');
                    });
                });
            }

            if (this.errorCloseBtn) {
                this.errorCloseBtn.addEventListener('click', () => {
                    this.hideErrorModal();
                });
            }
        }

        decodeHtml(raw) {
            if (typeof raw !== 'string') {
                return raw;
            }

            const parser = new DOMParser();
            const doc = parser.parseFromString(raw, 'text/html');
            return doc.documentElement.textContent || raw;
        }

        async fetchStreamUrl() {
            if (!this.streamEndpoint) {
                throw new Error('Stream endpoint is not configured');
            }

            if (this.streamRequestInFlight) {
                return;
            }

            this.streamRequestInFlight = true;
            try {
                const response = await fetch(this.streamEndpoint, {
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) {
                    throw new Error(`Failed to fetch stream URL (HTTP ${response.status})`);
                }

                const payload = await response.json();

                if (!payload.success || !payload.stream_url) {
                    throw new Error(payload.error || 'Stream URL not found in response');
                }

                this.streamUrl = payload.stream_url;
                this.audio.src = this.streamUrl;
                try {
                    this.audio.load();
                } catch (error) {
                    console.warn('Audio load failed to initiate prebuffering:', error);
                }
            } finally {
                this.streamRequestInFlight = false;
            }
        }

        async retryStreamFetch() {
            try {
                await this.fetchStreamUrl();
                this.updateState('ready', 'Stream ready. Press play to listen.');
            } catch (error) {
                console.error('Retry stream fetch failed:', error);
                this.updateState('error', 'Still unable to load stream URL. Please try again later.');
            }
        }

        play() {
            if (!this.streamUrl) {
                this.retryStreamFetch();
                return;
            }

            this.updateState('loading', 'Connecting to stream…');
            this.setLoadingIndicator(true);
            this.audio.play().catch((error) => {
                console.error('Audio play() rejected:', error);
                this.updateState('error', 'Playback failed. Please ensure your browser allows audio autoplay.');
                this.setPlayButton(false);
                this.setLoadingIndicator(false);
                this.showErrorModal('Playback failed to start. Please check your connection and try again.');
            });
        }

        pause() {
            this.userInitiatedPause = true;
            this.audio.pause();
        }

        setPlayButton(isPlaying) {
            if (!this.playPauseIcon || !this.playPauseButton) return;

            this.setLoadingIndicator(false);

            if (isPlaying) {
                this.playPauseIcon.classList.remove('fa-play');
                this.playPauseIcon.classList.add('fa-pause');
                this.playPauseButton.setAttribute('aria-label', 'Pause stream');
                if (this.playPauseAssist) {
                    this.playPauseAssist.textContent = 'Pause';
                }
            } else {
                this.playPauseIcon.classList.remove('fa-pause');
                this.playPauseIcon.classList.add('fa-play');
                this.playPauseButton.setAttribute('aria-label', 'Play stream');
                if (this.playPauseAssist) {
                    this.playPauseAssist.textContent = 'Play';
                }
            }
        }

        updateVolumeIcon() {
            if (!this.volumeIcon) return;

            this.volumeIcon.classList.remove('fa-volume-high', 'fa-volume-low', 'fa-volume-xmark');
            if (this.audio.muted || this.audio.volume === 0) {
                this.volumeIcon.classList.add('fa-volume-xmark');
            } else if (this.audio.volume < 0.4) {
                this.volumeIcon.classList.add('fa-volume-low');
            } else {
                this.volumeIcon.classList.add('fa-volume-high');
            }
        }

        toggleVolumeExpansion() {
            if (!this.volumeControl) return;
            const expanded = this.volumeControl.classList.toggle('player-volume--expanded');
            if (expanded && this.volumeSlider) {
                this.volumeSlider.focus({ preventScroll: true });
            }
        }

        expandVolumeSlider(show) {
            if (!this.volumeControl) return;
            if (show) {
                this.volumeControl.classList.add('player-volume--expanded');
            } else {
                this.volumeControl.classList.remove('player-volume--expanded');
            }
        }

        startMetadataPolling() {
            this.stopMetadataPolling();
            this.metadataTimer = setInterval(() => {
                this.fetchMetadata().catch((error) => {
                    console.error('Metadata poll error:', error);
                });
            }, this.metadataInterval);
        }

        stopMetadataPolling() {
            if (this.metadataTimer) {
                clearInterval(this.metadataTimer);
                this.metadataTimer = null;
            }
        }

        async fetchMetadata() {
            if (!this.metadataEndpoint || this.metadataInFlight) {
                return;
            }

            this.metadataInFlight = true;
            try {
                const response = await fetch(this.metadataEndpoint, {
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) {
                    throw new Error(`Failed to fetch metadata (HTTP ${response.status})`);
                }

                const payload = await response.json();

                if (!payload.success || !payload.data) {
                    throw new Error(payload.error || 'Invalid metadata response payload');
                }

                this.updateMetadata(payload.data);
            } catch (error) {
                this.updateStatus('Unable to load metadata. Retrying…');
                throw error;
            } finally {
                this.metadataInFlight = false;
            }
        }

        updateMetadata(metadata) {
            const currentSignature = JSON.stringify(metadata.current ?? {});
            const hasChanged = currentSignature !== this.lastMetadataSignature;
            this.lastMetadataSignature = currentSignature;

            if (metadata.station && hasChanged) {
                this.updateStationInfo(metadata.station);
            }

            if (metadata.current) {
                if (hasChanged) {
                    this.updateCurrentInfo(metadata.current);
                }
            }

            if (metadata.next) {
                this.updateNextInfo(metadata.next);
            }

            if (this.lastUpdatedEl) {
                const now = new Date();
                this.lastUpdatedEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }

            if (hasChanged) {
                this.updateStatus('Metadata refreshed.');
            }
        }

        updateStationInfo(station) {
            if (this.stationArtworkEl && station.image) {
                if (this.stationArtworkEl.src !== station.image) {
                    this.stationArtworkEl.src = station.image;
                }
            }
        }

        updateCurrentInfo(current) {
            if (this.nowPlayingTitleEl) {
                this.nowPlayingTitleEl.textContent = this.decodeHtml(current.title || 'Live Broadcast');
            }

            if (this.nowPlayingArtistEl) {
                const artist = current.artist || current.hosts || '';
                this.nowPlayingArtistEl.textContent = this.decodeHtml(artist || '—');
            }

            const songSummaryRaw = (current.pl_song || '').trim();
            const artistSummaryRaw = (current.pl_artist || '').trim();
            const songSummary = this.decodeHtml(songSummaryRaw);
            const artistSummary = this.decodeHtml(artistSummaryRaw);
            const shouldShowSummary = Boolean(songSummary) || Boolean(artistSummary);

            if (this.nowPlayingSummaryEl) {
                if (!shouldShowSummary) {
                    this.nowPlayingSummaryEl.hidden = true;
                    if (this.nowPlayingSongEl) {
                        this.nowPlayingSongEl.textContent = '';
                    }
                    if (this.nowPlayingSummaryArtistEl) {
                        this.nowPlayingSummaryArtistEl.textContent = '';
                    }
                } else {
                    this.nowPlayingSummaryEl.hidden = false;
                    if (this.nowPlayingSongEl) {
                        this.setMarqueeText(this.nowPlayingSongEl, songSummary);
                    }
                    if (this.nowPlayingSummaryArtistEl) {
                        this.setMarqueeText(this.nowPlayingSummaryArtistEl, artistSummary);
                    }
                    if (this.nowPlayingSeparatorEl) {
                        this.nowPlayingSeparatorEl.hidden = !(songSummary && artistSummary);
                    }
                }
            }

            if (this.showNameEl) {
                this.showNameEl.textContent = this.decodeHtml(current.show || '—');
            }

            if (this.showHostsEl) {
                this.showHostsEl.textContent = this.decodeHtml(current.hosts || '—');
            }

            if (this.showScheduleEl) {
                this.showScheduleEl.textContent = this.formatSchedule(current.start, current.end);
            }

            if (this.pledgeRowEl && this.pledgeLinkEl) {
                if (current.pledge_url) {
                    this.pledgeLinkEl.href = current.pledge_url;
                    this.pledgeRowEl.hidden = false;
                } else {
                    this.pledgeRowEl.hidden = true;
                }
            }
        }

        updateNextInfo(next) {
            if (this.nextShowNameEl) {
                this.nextShowNameEl.textContent = this.decodeHtml(next.title || '—');
            }

            if (this.nextShowTimeEl) {
                const schedule = this.formatSchedule(next.start, next.end);
                this.nextShowTimeEl.textContent = schedule || '—';
            }
        }

        formatSchedule(start, end) {
            if (!start && !end) {
                return '—';
            }

            if (start && end) {
                return `${start} – ${end}`;
            }

            return start || end || '—';
        }

        updateState(state, message = null) {
            if (typeof state === 'string') {
                this.root?.setAttribute('data-state', state);
            }

            if (message) {
                this.updateStatus(message);
            }
        }

        updateStatus(message) {
            if (!this.statusEl || !message) {
                return;
            }

            this.statusEl.textContent = message;
        }

        setLoadingIndicator(isLoading) {
            if (!this.playPauseButton) {
                return;
            }

            if (isLoading) {
                this.playPauseButton.classList.add('player-control__button--loading');
                if (this.playPauseSpinner) {
                    this.playPauseSpinner.setAttribute('aria-hidden', 'false');
                }
            } else {
                this.playPauseButton.classList.remove('player-control__button--loading');
                if (this.playPauseSpinner) {
                    this.playPauseSpinner.setAttribute('aria-hidden', 'true');
                }
            }
        }

        teardownStream() {
            try {
                this.audio.pause();
                this.audio.removeAttribute('src');
                this.audio.load();
            } catch (error) {
                console.warn('Failed to teardown audio stream cleanly:', error);
            }

            this.streamUrl = '';
        }

        showErrorModal(message) {
            if (!this.errorModalEl) {
                return;
            }

            if (this.errorMessageEl && message) {
                this.errorMessageEl.textContent = message;
            }

            this.errorModalEl.hidden = false;
        }

        hideErrorModal() {
            if (!this.errorModalEl) {
                return;
            }

            this.errorModalEl.hidden = true;
        }

        setMarqueeText(container, text) {
            if (!container) {
                return;
            }

            const value = text || '—';
            let span = container.querySelector('span');
            if (!span) {
                span = document.createElement('span');
                container.textContent = '';
                container.appendChild(span);
            }

            span.textContent = value;
            requestAnimationFrame(() => {
                const available = container.clientWidth;
                const contentWidth = span.scrollWidth;
                const shouldScroll = contentWidth > available + 1;

                container.classList.remove('player-nowplaying__value--scroll');
                container.classList.remove('player-nowplaying__value--static');

                if (shouldScroll) {
                    const distance = contentWidth - available + 16; // small buffer for spacing
                    container.style.setProperty('--scroll-distance', `${distance}px`);
                    const duration = Math.min(Math.max(distance / 20, 12), 30); // clamp between 12s and 30s
                    container.style.setProperty('--marquee-duration', `${duration}s`);
                    container.classList.add('player-nowplaying__value--scroll');
                } else {
                    container.classList.add('player-nowplaying__value--static');
                    container.style.removeProperty('--scroll-distance');
                    container.style.removeProperty('--marquee-duration');
                }
            });
        }
    }

    function initPlayer(root = null, options = {}) {
        const element = root || document.getElementById('streamingPlayer');
        if (!element) {
            return null;
        }

        if (element.__streamingPlayerInstance) {
            return element.__streamingPlayerInstance;
        }

        const instance = new StreamingAudioPlayer(element, options);
        element.__streamingPlayerInstance = instance;
        return instance;
    }

    window.StreamingPlayer = {
        init: initPlayer,
        get instance() {
            const root = document.getElementById('streamingPlayer');
            return root ? root.__streamingPlayerInstance : null;
        },
    };

    document.addEventListener('DOMContentLoaded', () => {
        initPlayer();
    });

    window.addEventListener('message', (event) => {
        if (event.data?.type === 'STREAMING_MODAL_CLOSE') {
            const instance = window.StreamingPlayer?.instance;
            if (instance) {
                instance.pause();
            }
        }
    });
})();
