/**
 * Sort Manager
 * Handles podcast directory sorting functionality
 */

class SortManager {
    constructor() {
        this.currentSort = this.loadSortPreference();
        this.sortOptions = this.defineSortOptions();
        this.isDropdownOpen = false;
        this.pollingInterval = null;
        this.init();
    }

    /**
     * Initialize the sort manager
     */
    init() {
        this.renderDropdown();
        this.attachEventListeners();
        this.applySortToTable(this.currentSort);
        this.updateButtonLabel();
        
        // Load preference from server and update if different
        this.syncWithServer();
        
        // Start polling for changes every 30 seconds
        this.startPolling();
    }

    /**
     * Sync with server preference on page load
     */
    async syncWithServer(showNotification = false) {
        const serverSort = await this.loadSortPreferenceFromServer();
        if (serverSort && serverSort !== this.currentSort) {
            // Server has different preference, use it
            this.currentSort = serverSort;
            this.updateButtonLabel();
            this.updateActiveOption();
            this.applySortToTable(serverSort);
            
            // Update localStorage to match server
            try {
                const data = {
                    sortKey: serverSort,
                    timestamp: Date.now()
                };
                localStorage.setItem('podcast_sort_preference', JSON.stringify(data));
            } catch (error) {
                console.error('Error updating localStorage:', error);
            }
            
            // Show notification if this was from polling (not initial load)
            if (showNotification && window.podcastApp) {
                const sortLabel = this.sortOptions[serverSort]?.label || serverSort;
                window.podcastApp.showAlert(`Sort order updated to: ${sortLabel}`, 'info');
            }
        }
    }

    /**
     * Start polling server for preference changes
     */
    startPolling() {
        // Poll every 30 seconds
        this.pollingInterval = setInterval(() => {
            this.syncWithServer(true); // true = show notification on change
        }, 30000); // 30 seconds
        
        console.log('Sort preference polling started (30s interval)');
        
        // Also check when user returns to tab (Page Visibility API)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // User returned to tab, check for updates
                console.log('Tab became visible, checking for sort updates...');
                this.syncWithServer(true);
            }
        });
    }

    /**
     * Stop polling (cleanup)
     */
    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
            console.log('Sort preference polling stopped');
        }
    }

    /**
     * Define available sort options
     */
    defineSortOptions() {
        return {
            'date-newest': {
                label: 'Newest Episodes',
                icon: 'fa-calendar-plus',
                section: 'date',
                sectionLabel: 'Sort by Episode Date'
            },
            'date-oldest': {
                label: 'Oldest Episodes',
                icon: 'fa-calendar-minus',
                section: 'date',
                sectionLabel: 'Sort by Episode Date'
            },
            'title-az': {
                label: 'A-Z',
                icon: 'fa-arrow-down-a-z',
                section: 'title',
                sectionLabel: 'Sort by Title'
            },
            'title-za': {
                label: 'Z-A',
                icon: 'fa-arrow-up-z-a',
                section: 'title',
                sectionLabel: 'Sort by Title'
            },
            'status-active': {
                label: 'Active First',
                icon: 'fa-circle-check',
                section: 'status',
                sectionLabel: 'Sort by Status'
            },
            'status-inactive': {
                label: 'Inactive First',
                icon: 'fa-circle-xmark',
                section: 'status',
                sectionLabel: 'Sort by Status'
            }
        };
    }

    /**
     * Load sort preference from server (with localStorage fallback)
     */
    loadSortPreference() {
        // Try to load from server first (synchronous for constructor)
        // Will be updated async after page load
        try {
            const stored = localStorage.getItem('podcast_sort_preference');
            if (stored) {
                const data = JSON.parse(stored);
                return data.sortKey || 'date-newest';
            }
        } catch (error) {
            console.error('Error loading sort preference from localStorage:', error);
        }
        return 'date-newest'; // Default
    }

    /**
     * Load sort preference from server (async)
     */
    async loadSortPreferenceFromServer() {
        try {
            const response = await fetch('api/sort-preference.php');
            if (!response.ok) {
                throw new Error('Failed to fetch sort preference');
            }
            const data = await response.json();
            if (data.success && data.sortKey) {
                return data.sortKey;
            }
        } catch (error) {
            console.error('Error loading sort preference from server:', error);
        }
        return null;
    }

    /**
     * Save sort preference to server (and localStorage as backup)
     */
    async saveSortPreference(sortKey) {
        // Save to localStorage immediately for responsiveness
        try {
            const data = {
                sortKey: sortKey,
                timestamp: Date.now()
            };
            localStorage.setItem('podcast_sort_preference', JSON.stringify(data));
        } catch (error) {
            console.error('Error saving to localStorage:', error);
        }

        // Save to server (this controls feed.php output for external apps)
        try {
            const response = await fetch('api/sort-preference.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ sortKey: sortKey })
            });

            if (!response.ok) {
                throw new Error('Failed to save sort preference to server');
            }

            const result = await response.json();
            if (!result.success) {
                console.error('Server error saving sort preference:', result.error);
            } else {
                console.log('Sort preference saved to server:', sortKey);
            }
        } catch (error) {
            console.error('Error saving sort preference to server:', error);
            // Show user feedback that save failed
            if (window.podcastApp) {
                window.podcastApp.showAlert('Warning: Sort preference may not persist across browsers', 'warning');
            }
        }
    }

    /**
     * Render the dropdown menu
     */
    renderDropdown() {
        const dropdown = document.getElementById('sortDropdown');
        if (!dropdown) return;

        // Group options by section
        const sections = {};
        Object.entries(this.sortOptions).forEach(([key, option]) => {
            if (!sections[option.section]) {
                sections[option.section] = {
                    label: option.sectionLabel,
                    options: []
                };
            }
            sections[option.section].options.push({ key, ...option });
        });

        // Build HTML
        let html = '';
        Object.entries(sections).forEach(([sectionKey, section]) => {
            html += `
                <div class="sort-section">
                    <div class="sort-section-title">${section.label}</div>
                    ${section.options.map(option => `
                        <button type="button" 
                                class="sort-option ${this.currentSort === option.key ? 'active' : ''}" 
                                data-sort="${option.key}"
                                aria-label="${option.label}">
                            <i class="fa-solid ${option.icon}"></i>
                            <span>${option.label}</span>
                            <i class="fa-solid fa-check sort-option-check"></i>
                        </button>
                    `).join('')}
                </div>
            `;
        });

        dropdown.innerHTML = html;
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        const button = document.getElementById('sortButton');
        const dropdown = document.getElementById('sortDropdown');

        if (!button || !dropdown) return;

        // Toggle dropdown on button click
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });

        // Handle option selection
        dropdown.addEventListener('click', (e) => {
            const option = e.target.closest('.sort-option');
            if (option) {
                const sortKey = option.dataset.sort;
                this.selectSort(sortKey);
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (this.isDropdownOpen && !e.target.closest('.sort-controls')) {
                this.closeDropdown();
            }
        });

        // Keyboard navigation
        button.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.toggleDropdown();
            } else if (e.key === 'Escape') {
                this.closeDropdown();
            }
        });

        // Keyboard navigation in dropdown
        dropdown.addEventListener('keydown', (e) => {
            const options = Array.from(dropdown.querySelectorAll('.sort-option'));
            const currentIndex = options.findIndex(opt => opt === document.activeElement);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const nextIndex = (currentIndex + 1) % options.length;
                options[nextIndex].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prevIndex = currentIndex <= 0 ? options.length - 1 : currentIndex - 1;
                options[prevIndex].focus();
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (currentIndex >= 0) {
                    const sortKey = options[currentIndex].dataset.sort;
                    this.selectSort(sortKey);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.closeDropdown();
                button.focus();
            }
        });
    }

    /**
     * Toggle dropdown visibility
     */
    toggleDropdown() {
        if (this.isDropdownOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }

    /**
     * Open dropdown
     */
    openDropdown() {
        const button = document.getElementById('sortButton');
        const dropdown = document.getElementById('sortDropdown');

        if (!button || !dropdown) return;

        button.classList.add('active');
        dropdown.classList.add('show');
        this.isDropdownOpen = true;

        // Focus first option
        setTimeout(() => {
            const firstOption = dropdown.querySelector('.sort-option');
            if (firstOption) firstOption.focus();
        }, 100);
    }

    /**
     * Close dropdown
     */
    closeDropdown() {
        const button = document.getElementById('sortButton');
        const dropdown = document.getElementById('sortDropdown');

        if (!button || !dropdown) return;

        button.classList.remove('active');
        dropdown.classList.remove('show');
        this.isDropdownOpen = false;
    }

    /**
     * Select a sort option
     */
    selectSort(sortKey) {
        if (!this.sortOptions[sortKey]) return;

        this.currentSort = sortKey;
        this.saveSortPreference(sortKey);
        this.updateButtonLabel();
        this.updateActiveOption();
        this.applySortToTable(sortKey);
        this.closeDropdown();
    }

    /**
     * Update button label
     */
    updateButtonLabel() {
        const label = document.getElementById('currentSortLabel');
        if (label && this.sortOptions[this.currentSort]) {
            label.textContent = this.sortOptions[this.currentSort].label;
        }
    }

    /**
     * Update active option in dropdown
     */
    updateActiveOption() {
        const dropdown = document.getElementById('sortDropdown');
        if (!dropdown) return;

        dropdown.querySelectorAll('.sort-option').forEach(option => {
            if (option.dataset.sort === this.currentSort) {
                option.classList.add('active');
            } else {
                option.classList.remove('active');
            }
        });
    }

    /**
     * Apply sorting to the table
     */
    applySortToTable(sortKey) {
        const table = document.getElementById('podcastsTable');
        if (!table) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Get all rows
        const rows = Array.from(tbody.querySelectorAll('tr'));
        if (rows.length === 0) return;

        // Sort rows based on selected option
        const sortedRows = this.sortRows(rows, sortKey);

        // Re-append rows in sorted order
        sortedRows.forEach(row => tbody.appendChild(row));

        // Add visual feedback
        this.addSortFeedback();
    }

    /**
     * Sort rows based on sort key
     */
    sortRows(rows, sortKey) {
        return rows.sort((a, b) => {
            switch (sortKey) {
                case 'date-newest':
                    return this.sortByDateNewest(a, b);
                case 'date-oldest':
                    return this.sortByDateOldest(a, b);
                case 'title-az':
                    return this.sortByTitleAZ(a, b);
                case 'title-za':
                    return this.sortByTitleZA(a, b);
                case 'status-active':
                    return this.sortByStatusActive(a, b);
                case 'status-inactive':
                    return this.sortByStatusInactive(a, b);
                default:
                    return 0;
            }
        });
    }

    /**
     * Sort by date - newest first
     */
    sortByDateNewest(a, b) {
        const dateA = this.getRowDate(a);
        const dateB = this.getRowDate(b);
        return dateB - dateA; // Descending
    }

    /**
     * Sort by date - oldest first
     */
    sortByDateOldest(a, b) {
        const dateA = this.getRowDate(a);
        const dateB = this.getRowDate(b);
        return dateA - dateB; // Ascending
    }

    /**
     * Sort by title A-Z
     */
    sortByTitleAZ(a, b) {
        const titleA = this.getRowTitle(a);
        const titleB = this.getRowTitle(b);
        return titleA.localeCompare(titleB);
    }

    /**
     * Sort by title Z-A
     */
    sortByTitleZA(a, b) {
        const titleA = this.getRowTitle(a);
        const titleB = this.getRowTitle(b);
        return titleB.localeCompare(titleA);
    }

    /**
     * Sort by status - active first
     */
    sortByStatusActive(a, b) {
        const statusA = this.getRowStatus(a);
        const statusB = this.getRowStatus(b);
        
        if (statusA === statusB) {
            // Secondary sort by date if status is same
            return this.sortByDateNewest(a, b);
        }
        
        return statusA === 'active' ? -1 : 1;
    }

    /**
     * Sort by status - inactive first
     */
    sortByStatusInactive(a, b) {
        const statusA = this.getRowStatus(a);
        const statusB = this.getRowStatus(b);
        
        if (statusA === statusB) {
            // Secondary sort by date if status is same
            return this.sortByDateNewest(a, b);
        }
        
        return statusA === 'inactive' ? -1 : 1;
    }

    /**
     * Get date from row (uses latest episode date if available, falls back to created date)
     */
    getRowDate(row) {
        // Try to get latest episode date from data attribute
        const latestEpisode = row.dataset.latestEpisode;
        if (latestEpisode && latestEpisode.trim() !== '') {
            const episodeDate = new Date(latestEpisode);
            if (!isNaN(episodeDate.getTime())) {
                return episodeDate;
            }
        }
        
        // Fall back to created date
        const dateCell = row.querySelector('td:nth-child(6)'); // Created column (now 6th)
        if (!dateCell) return new Date(0);
        
        const dateText = dateCell.textContent.trim();
        return new Date(dateText);
    }

    /**
     * Get title from row
     */
    getRowTitle(row) {
        const titleCell = row.querySelector('td:nth-child(2) strong');
        if (!titleCell) return '';
        
        return titleCell.textContent.trim().toLowerCase();
    }

    /**
     * Get status from row
     */
    getRowStatus(row) {
        const statusCell = row.querySelector('td:nth-child(4) .badge');
        if (!statusCell) return 'unknown';
        
        return statusCell.classList.contains('badge-success') ? 'active' : 'inactive';
    }

    /**
     * Add visual feedback after sorting
     */
    addSortFeedback() {
        const table = document.getElementById('podcastsTable');
        if (!table) return;

        // Add a subtle flash effect
        table.style.opacity = '0.7';
        setTimeout(() => {
            table.style.opacity = '1';
        }, 150);
    }
}

// Global function to initialize sort manager
function initSortManager() {
    if (document.getElementById('sortButton')) {
        window.sortManager = new SortManager();
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSortManager);
} else {
    initSortManager();
}
