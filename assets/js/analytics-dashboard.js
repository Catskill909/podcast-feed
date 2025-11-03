/**
 * Analytics Dashboard
 * Handles fetching and rendering analytics data in the Stats modal
 */

class AnalyticsDashboard {
    constructor() {
        this.currentRange = '7d';
        this.currentPodcastId = '';
        this.chart = null;
        this.allData = null;
        this.init();
    }

    /**
     * Initialize dashboard
     */
    init() {
        this.setupEventListeners();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Time range buttons
        const rangeButtons = document.querySelectorAll('.analytics-time-range-btn');
        rangeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Update active state
                rangeButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Load analytics for new range
                this.currentRange = btn.dataset.range;
                this.loadAnalytics();
            });
        });

        // Podcast filter dropdown
        const podcastFilter = document.getElementById('analyticsPodcastFilter');
        if (podcastFilter) {
            podcastFilter.addEventListener('change', (e) => {
                this.currentPodcastId = e.target.value;
                this.filterAndRenderData();
            });
        }

        // Listen for stats modal open
        const statsModal = document.getElementById('statsModal');
        if (statsModal) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === 'class') {
                        if (statsModal.classList.contains('show')) {
                            // Modal opened - load analytics
                            this.loadAnalytics();
                        }
                    }
                });
            });

            observer.observe(statsModal, { attributes: true });
        }
    }

    /**
     * Load analytics data from API
     */
    async loadAnalytics() {
        const container = document.getElementById('analyticsContent');
        if (!container) return;

        // Show loading state
        container.innerHTML = `
            <div class="analytics-loading">
                <div class="spinner"></div>
                <p>Loading analytics...</p>
            </div>
        `;

        try {
            const response = await fetch(`api/get-analytics-stats.php?range=${this.currentRange}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to load analytics');
            }

            // Store full data
            this.allData = result;

            // Populate podcast filter dropdown
            this.populatePodcastFilter(result);

            // Render analytics
            this.filterAndRenderData();

        } catch (error) {
            console.error('Error loading analytics:', error);
            this.showError(error.message);
        }
    }

    /**
     * Populate podcast filter dropdown
     */
    populatePodcastFilter(data) {
        const select = document.getElementById('analyticsPodcastFilter');
        if (!select) return;

        // Use ALL podcasts from directory (not just those with analytics data)
        // This ensures podcasts with 0 plays/downloads still appear in the dropdown
        const podcasts = window.ALL_PODCASTS_FOR_FILTER || [];
        
        // Build options HTML
        let optionsHTML = '<option value="">All Podcasts</option>';
        podcasts.forEach(podcast => {
            const selected = podcast.id === this.currentPodcastId ? 'selected' : '';
            const safeId = this.escapeHtml(String(podcast.id));
            const safeTitle = this.sanitizeText(podcast.title);
            optionsHTML += `<option value="${safeId}" ${selected}>${safeTitle}</option>`;
        });

        select.innerHTML = optionsHTML;
    }

    /**
     * Filter and render data based on selected podcast
     */
    filterAndRenderData() {
        if (!this.allData) return;

        let filteredData = { ...this.allData };

        // If a podcast is selected, filter the data
        if (this.currentPodcastId) {
            filteredData = this.filterDataByPodcast(this.allData, this.currentPodcastId);
        }

        this.renderAnalytics(filteredData);
    }

    /**
     * Filter analytics data by podcast ID
     */
    filterDataByPodcast(data, podcastId) {
        const filtered = {
            ...data,
            overview: {
                totalPlays: 0,
                totalDownloads: 0,
                uniqueListeners: 0,
                playToDownloadRate: 0
            },
            dailySeries: [],
            topEpisodes: [],
            topPodcasts: []
        };

        // Filter top episodes for this podcast
        const podcastEpisodes = data.topEpisodes.filter(ep => ep.podcastId === podcastId);
        filtered.topEpisodes = podcastEpisodes;

        // Calculate overview stats from episodes
        podcastEpisodes.forEach(ep => {
            filtered.overview.totalPlays += ep.plays;
            filtered.overview.totalDownloads += ep.downloads;
            filtered.overview.uniqueListeners = Math.max(filtered.overview.uniqueListeners, ep.uniqueListeners);
        });

        // Calculate play-to-download rate
        if (filtered.overview.totalPlays > 0) {
            filtered.overview.playToDownloadRate = filtered.overview.totalDownloads / filtered.overview.totalPlays;
        }

        // Filter top podcasts to show only selected one
        const selectedPodcast = data.topPodcasts.find(p => p.podcastId === podcastId);
        if (selectedPodcast) {
            filtered.topPodcasts = [selectedPodcast];
        }

        // Filter daily series (approximate - sum all episodes for this podcast per day)
        // Note: This is a simplified approach. For accurate per-podcast daily data,
        // the backend would need to track podcast_id in daily metrics
        filtered.dailySeries = data.dailySeries.map(day => ({
            date: day.date,
            plays: Math.round(day.plays * (filtered.overview.totalPlays / data.overview.totalPlays || 0)),
            downloads: Math.round(day.downloads * (filtered.overview.totalDownloads / data.overview.totalDownloads || 0))
        }));

        return filtered;
    }

    /**
     * Render analytics data
     */
    renderAnalytics(data) {
        const container = document.getElementById('analyticsContent');
        if (!container) return;

        // Check if we have any data
        const hasData = data.overview.totalPlays > 0 || data.overview.totalDownloads > 0;

        if (!hasData) {
            this.showEmptyState();
            return;
        }

        // Build HTML
        container.innerHTML = `
            <!-- Overview Cards -->
            <div class="analytics-cards-grid">
                <div class="analytics-card plays">
                    <div class="analytics-card-header">
                        <div class="analytics-card-icon">
                            <i class="fa-solid fa-play"></i>
                        </div>
                    </div>
                    <div class="analytics-card-value">${this.formatNumber(data.overview.totalPlays)}</div>
                    <div class="analytics-card-label">Total Plays</div>
                </div>

                <div class="analytics-card downloads">
                    <div class="analytics-card-header">
                        <div class="analytics-card-icon">
                            <i class="fa-solid fa-download"></i>
                        </div>
                    </div>
                    <div class="analytics-card-value">${this.formatNumber(data.overview.totalDownloads)}</div>
                    <div class="analytics-card-label">Downloads</div>
                </div>

                <div class="analytics-card listeners">
                    <div class="analytics-card-header">
                        <div class="analytics-card-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <div class="analytics-card-value">${this.formatNumber(data.overview.uniqueListeners)}</div>
                    <div class="analytics-card-label">Unique Listeners</div>
                </div>

                <div class="analytics-card rate">
                    <div class="analytics-card-header">
                        <div class="analytics-card-icon">
                            <i class="fa-solid fa-chart-simple"></i>
                        </div>
                    </div>
                    <div class="analytics-card-value">${(data.overview.playToDownloadRate * 100).toFixed(0)}%</div>
                    <div class="analytics-card-label">Download Rate</div>
                </div>
            </div>

            <!-- Trend Chart -->
            <div class="analytics-chart-container">
                <div class="analytics-chart-header">
                    <h5 class="analytics-chart-title">Engagement Trends</h5>
                </div>
                <div class="analytics-chart-wrapper">
                    <canvas id="analyticsChart"></canvas>
                </div>
            </div>

            <!-- Top Episodes Table -->
            ${data.topEpisodes.length > 0 ? this.renderTopEpisodesTable(data.topEpisodes.slice(0, 10)) : ''}

            <!-- Top Podcasts Table -->
            ${data.topPodcasts.length > 0 ? this.renderTopPodcastsTable(data.topPodcasts.slice(0, 10)) : ''}
        `;

        // Render chart
        this.renderChart(data.dailySeries);
    }

    /**
     * Render top episodes table
     */
    renderTopEpisodesTable(episodes) {
        const rows = episodes.map((ep, index) => {
            const safeEpisodeTitle = this.sanitizeText(ep.episodeTitle);
            const safePodcastTitle = this.sanitizeText(ep.podcastTitle);

            return `
                <tr>
                    <td class="analytics-table-rank">${index + 1}</td>
                    <td class="analytics-table-title-cell">
                        <div class="analytics-table-episode-title">${safeEpisodeTitle}</div>
                        <div class="analytics-table-podcast-title">${safePodcastTitle}</div>
                    </td>
                    <td class="analytics-table-stat plays">${this.formatNumber(ep.plays)}</td>
                    <td class="analytics-table-stat downloads">${this.formatNumber(ep.downloads)}</td>
                    <td class="analytics-table-stat listeners">${this.formatNumber(ep.uniqueListeners)}</td>
                </tr>
            `;
        }).join('');

        return `
            <div class="analytics-table-container">
                <div class="analytics-table-header">
                    <h5 class="analytics-table-title">Top Episodes</h5>
                </div>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Episode</th>
                            <th style="text-align: right;">Plays</th>
                            <th style="text-align: right;">Downloads</th>
                            <th style="text-align: right;">Listeners</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
            </div>
        `;
    }

    /**
     * Render top podcasts table
     */
    renderTopPodcastsTable(podcasts) {
        const rows = podcasts.map((podcast, index) => {
            const safePodcastTitle = this.sanitizeText(podcast.podcastTitle);

            return `
                <tr>
                    <td class="analytics-table-rank">${index + 1}</td>
                    <td class="analytics-table-title-cell">
                        <div class="analytics-table-episode-title">${safePodcastTitle}</div>
                    </td>
                    <td class="analytics-table-stat plays">${this.formatNumber(podcast.plays)}</td>
                    <td class="analytics-table-stat downloads">${this.formatNumber(podcast.downloads)}</td>
                    <td class="analytics-table-stat">${podcast.episodeCount}</td>
                </tr>
            `;
        }).join('');

        return `
            <div class="analytics-table-container">
                <div class="analytics-table-header">
                    <h5 class="analytics-table-title">Top Podcasts</h5>
                </div>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Podcast</th>
                            <th style="text-align: right;">Plays</th>
                            <th style="text-align: right;">Downloads</th>
                            <th style="text-align: right;">Episodes</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
            </div>
        `;
    }

    /**
     * Render chart with Chart.js
     */
    renderChart(dailySeries) {
        // Destroy existing chart
        if (this.chart) {
            this.chart.destroy();
        }

        const canvas = document.getElementById('analyticsChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Prepare data
        const labels = dailySeries.map(d => this.formatChartDate(d.date));
        const playsData = dailySeries.map(d => d.plays);
        const downloadsData = dailySeries.map(d => d.downloads);

        // Create chart
        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Plays',
                        data: playsData,
                        borderColor: '#4ade80',
                        backgroundColor: 'rgba(74, 222, 128, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Downloads',
                        data: downloadsData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#9ca3af',
                            font: {
                                family: 'Inter, sans-serif',
                                size: 12
                            },
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleColor: '#f3f4f6',
                        bodyColor: '#d1d5db',
                        borderColor: '#374151',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#9ca3af',
                            font: {
                                family: 'Inter, sans-serif',
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#9ca3af',
                            font: {
                                family: 'Inter, sans-serif',
                                size: 11
                            },
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Format chart date
     */
    formatChartDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    /**
     * Format number with commas
     */
    formatNumber(num) {
        return num.toLocaleString();
    }

    /**
     * Show empty state
     */
    showEmptyState() {
        const container = document.getElementById('analyticsContent');
        if (!container) return;

        container.innerHTML = `
            <div class="analytics-empty-state">
                <div class="analytics-empty-icon">📊</div>
                <h5 class="analytics-empty-title">No Analytics Data Yet</h5>
                <p class="analytics-empty-description">
                    Start playing or downloading episodes from the public player to see engagement analytics here.
                </p>
            </div>
        `;
    }

    /**
     * Show error state
     */
    showError(message) {
        const container = document.getElementById('analyticsContent');
        if (!container) return;

        container.innerHTML = `
            <div class="analytics-empty-state">
                <div class="analytics-empty-icon">⚠️</div>
                <h5 class="analytics-empty-title">Error Loading Analytics</h5>
                <p class="analytics-empty-description">${this.sanitizeText(message)}</p>
            </div>
        `;
    }

    /**
     * Decode HTML entities and escape result for safe rendering
     */
    sanitizeText(text) {
        return this.escapeHtml(this.decodeHtml(text ?? ''));
    }

    /**
     * Decode HTML entities
     */
    decodeHtml(text) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text ?? '';
        return textarea.value;
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
    window.analyticsDashboard = new AnalyticsDashboard();
});
