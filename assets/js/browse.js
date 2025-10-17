/**
 * Public Podcast Browser
 * Handles loading, searching, filtering, and displaying podcasts
 */

class PodcastBrowser {
    constructor() {
        this.podcasts = [];
        this.filteredPodcasts = [];
        this.searchTimeout = null;
        this.currentSort = 'latest';
        this.init();
    }

    /**
     * Initialize the browser
     */
    async init() {
        this.setupEventListeners();
        await this.loadPodcasts();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Search input
        const searchInput = document.getElementById('browseSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.filterPodcasts(e.target.value);
                }, 300);
            });
        }

        // Custom sort dropdown
        this.setupSortDropdown();
    }

    /**
     * Setup custom sort dropdown
     */
    setupSortDropdown() {
        const sortButton = document.getElementById('browseSortButton');
        const sortDropdown = document.getElementById('browseSortDropdown');
        const sortOptions = sortDropdown?.querySelectorAll('.sort-option');

        if (!sortButton || !sortDropdown) return;

        // Toggle dropdown
        sortButton.addEventListener('click', (e) => {
            e.stopPropagation();
            const isExpanded = sortButton.getAttribute('aria-expanded') === 'true';
            sortButton.setAttribute('aria-expanded', !isExpanded);
            sortButton.classList.toggle('active');
            sortDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!sortButton.contains(e.target) && !sortDropdown.contains(e.target)) {
                sortButton.setAttribute('aria-expanded', 'false');
                sortButton.classList.remove('active');
                sortDropdown.classList.remove('show');
            }
        });

        // Handle sort option clicks
        sortOptions?.forEach(option => {
            option.addEventListener('click', () => {
                const sortValue = option.dataset.sort;
                const sortLabel = option.querySelector('.sort-option-label').textContent;

                // Update active state
                sortOptions.forEach(opt => opt.classList.remove('active'));
                option.classList.add('active');

                // Update button label
                document.getElementById('browseSortLabel').textContent = sortLabel;

                // Close dropdown
                sortButton.setAttribute('aria-expanded', 'false');
                sortButton.classList.remove('active');
                sortDropdown.classList.remove('show');

                // Apply sort
                this.currentSort = sortValue;
                this.sortPodcasts();
            });
        });
    }

    /**
     * Load podcasts from API
     */
    async loadPodcasts() {
        const container = document.getElementById('podcastsGrid');
        if (!container) return;

        // Show loading state
        this.showLoading();

        try {
            const response = await fetch('api/get-public-podcasts.php');
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to load podcasts');
            }

            this.podcasts = result.podcasts || [];
            this.filteredPodcasts = [...this.podcasts];
            
            // Update stats
            this.updateStats();
            
            // Sort and render
            this.sortPodcasts();

        } catch (error) {
            console.error('Error loading podcasts:', error);
            this.showError('Failed to load podcasts. Please try again later.');
        }
    }

    /**
     * Update hero stats
     */
    updateStats() {
        const countEl = document.getElementById('podcastCount');
        if (countEl) {
            countEl.textContent = this.podcasts.length;
        }

        // Calculate total episodes
        const totalEpisodes = this.podcasts.reduce((sum, p) => sum + (p.episode_count || 0), 0);
        const episodesEl = document.getElementById('totalEpisodes');
        if (episodesEl) {
            episodesEl.textContent = totalEpisodes;
        }
    }

    /**
     * Filter podcasts by search term
     */
    filterPodcasts(searchTerm) {
        const term = searchTerm.toLowerCase().trim();

        if (!term) {
            this.filteredPodcasts = [...this.podcasts];
        } else {
            this.filteredPodcasts = this.podcasts.filter(podcast => {
                return podcast.title.toLowerCase().includes(term) ||
                       (podcast.description && podcast.description.toLowerCase().includes(term));
            });
        }

        this.sortPodcasts();
    }

    /**
     * Sort podcasts
     */
    sortPodcasts() {
        switch (this.currentSort) {
            case 'latest':
                this.filteredPodcasts.sort((a, b) => {
                    const dateA = a.latest_episode_date ? new Date(a.latest_episode_date) : new Date(0);
                    const dateB = b.latest_episode_date ? new Date(b.latest_episode_date) : new Date(0);
                    return dateB - dateA;
                });
                break;
            case 'title':
                this.filteredPodcasts.sort((a, b) => a.title.localeCompare(b.title));
                break;
            case 'episodes':
                this.filteredPodcasts.sort((a, b) => (b.episode_count || 0) - (a.episode_count || 0));
                break;
        }

        this.renderPodcasts();
    }

    /**
     * Render podcasts grid
     */
    renderPodcasts() {
        const container = document.getElementById('podcastsGrid');
        if (!container) return;

        if (this.filteredPodcasts.length === 0) {
            this.showEmptyState();
            return;
        }

        container.innerHTML = this.filteredPodcasts
            .map((podcast, index) => this.renderPodcastCard(podcast, index))
            .join('');
    }

    /**
     * Render a single podcast card
     */
    renderPodcastCard(podcast, index) {
        const isNew = this.isNewEpisode(podcast.latest_episode_date);
        const coverUrl = podcast.cover_url || '';
        const episodeCount = podcast.episode_count || 0;
        const latestDate = this.formatDate(podcast.latest_episode_date);

        return `
            <div class="podcast-card stagger-fade-in" onclick="showPlayerModal('${this.escapeHtml(podcast.id)}')" style="animation-delay: ${index * 0.05}s">
                <div class="podcast-card-cover">
                    ${coverUrl ? 
                        `<img src="${this.escapeHtml(coverUrl)}" alt="${this.escapeHtml(podcast.title)}" loading="lazy">` :
                        `<div class="podcast-card-cover-placeholder">🎙️</div>`
                    }
                    <div class="podcast-card-play-overlay">
                        <div class="podcast-card-play-icon">
                            <i class="fa-solid fa-play"></i>
                        </div>
                    </div>
                    <div class="podcast-card-overlay">
                        <h3 class="podcast-card-title-overlay">${this.escapeHtml(podcast.title)}</h3>
                    </div>
                    ${isNew ? '<div class="podcast-card-new-badge">New</div>' : ''}
                    <div class="podcast-card-badge">
                        <i class="fa-solid fa-podcast"></i>
                        <span>${episodeCount} Episode${episodeCount !== 1 ? 's' : ''}</span>
                    </div>
                </div>
                <div class="podcast-card-info">
                    ${podcast.description ? 
                        `<p class="podcast-card-description">${this.escapeHtml(podcast.description)}</p>` : 
                        `<p class="podcast-card-description" style="color: var(--text-muted); font-style: italic;">No description available</p>`
                    }
                    <div class="podcast-card-meta">
                        <div class="podcast-card-meta-item">
                            <i class="fa-solid fa-clock"></i>
                            <span>${latestDate}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Check if episode is new (within last 7 days)
     */
    isNewEpisode(dateString) {
        if (!dateString) return false;
        
        try {
            const episodeDate = new Date(dateString);
            const now = new Date();
            const diffTime = now - episodeDate;
            const diffDays = diffTime / (1000 * 60 * 60 * 24);
            
            return diffDays <= 7;
        } catch (e) {
            return false;
        }
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
            if (diffDays < 7) return `${diffDays} days ago`;
            if (diffDays < 30) {
                const weeks = Math.floor(diffDays / 7);
                return `${weeks} week${weeks !== 1 ? 's' : ''} ago`;
            }
            
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
     * Show loading state
     */
    showLoading() {
        const container = document.getElementById('podcastsGrid');
        if (!container) return;

        const skeletonCount = 8;
        const skeletons = Array(skeletonCount).fill(0).map(() => `
            <div class="podcast-card-skeleton">
                <div class="skeleton-cover"></div>
                <div class="skeleton-info">
                    <div class="skeleton-line title"></div>
                    <div class="skeleton-line description"></div>
                    <div class="skeleton-line description"></div>
                    <div class="skeleton-line meta"></div>
                </div>
            </div>
        `).join('');

        container.innerHTML = skeletons;
    }

    /**
     * Show empty state
     */
    showEmptyState() {
        const container = document.getElementById('podcastsGrid');
        if (!container) return;

        const searchInput = document.getElementById('browseSearch');
        const hasSearch = searchInput && searchInput.value.trim();

        container.innerHTML = `
            <div class="browse-empty-state" style="grid-column: 1 / -1;">
                <div class="browse-empty-icon">🎙️</div>
                <h3 class="browse-empty-title">${hasSearch ? 'No Podcasts Found' : 'No Podcasts Available'}</h3>
                <p class="browse-empty-description">
                    ${hasSearch ? 
                        'Try adjusting your search terms or filters.' : 
                        'There are no podcasts available at the moment. Check back soon!'
                    }
                </p>
                ${hasSearch ? 
                    '<button class="btn btn-primary" onclick="podcastBrowser.clearSearch()">Clear Search</button>' : 
                    ''
                }
            </div>
        `;
    }

    /**
     * Show error state
     */
    showError(message) {
        const container = document.getElementById('podcastsGrid');
        if (!container) return;

        container.innerHTML = `
            <div class="browse-empty-state" style="grid-column: 1 / -1;">
                <div class="browse-empty-icon">⚠️</div>
                <h3 class="browse-empty-title">Error Loading Podcasts</h3>
                <p class="browse-empty-description">${this.escapeHtml(message)}</p>
                <button class="btn btn-primary" onclick="podcastBrowser.loadPodcasts()">
                    <i class="fa-solid fa-rotate"></i> Try Again
                </button>
            </div>
        `;
    }

    /**
     * Clear search
     */
    clearSearch() {
        const searchInput = document.getElementById('browseSearch');
        if (searchInput) {
            searchInput.value = '';
            this.filterPodcasts('');
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
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.podcastBrowser = new PodcastBrowser();
});
