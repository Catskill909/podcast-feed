/**
 * Podcast Directory Manager - Main Application JavaScript
 * Handles all interactive functionality and UI behaviors
 */

class PodcastApp {
    constructor() {
        this.currentEditId = null;
        this.searchTimeout = null;
        this.init();
    }

    /**
     * Initialize the application
     */
    init() {
        this.setupEventListeners();
        this.setupFileUpload();
        this.setupSearch();
        this.setupModalHandlers();
        this.setupFormSubmission();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Close modal on overlay click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.hideModal();
                this.hideDeleteModal();
                this.hideStatusModal();
                this.hideStatsModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideModal();
                this.hideDeleteModal();
                this.hideStatusModal();
                this.hideFeedModal();
                this.hideStatsModal();
            }
        });

        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(alert => {
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 5000);
        });
    }

    /**
     * Setup file upload functionality
     */
    setupFileUpload() {
        const fileInput = document.getElementById('cover_image');
        const fileLabel = document.getElementById('fileLabel');
        const imagePreview = document.getElementById('imagePreview');
        const previewImage = document.getElementById('previewImage');
        const imageInfo = document.getElementById('imageInfo');

        if (!fileInput || !fileLabel) return;

        // Handle file selection
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            this.handleFileSelect(file);
        });

        // Handle drag and drop
        fileLabel.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileLabel.classList.add('dragover');
        });

        fileLabel.addEventListener('dragleave', (e) => {
            e.preventDefault();
            fileLabel.classList.remove('dragover');
        });

        fileLabel.addEventListener('drop', (e) => {
            e.preventDefault();
            fileLabel.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.type.startsWith('image/')) {
                    fileInput.files = files;
                    this.handleFileSelect(file);
                }
            }
        });
    }

    /**
     * Handle file selection and preview
     */
    async handleFileSelect(file) {
        const fileLabel = document.getElementById('fileLabel');
        const imagePreview = document.getElementById('imagePreview');
        const previewImage = document.getElementById('previewImage');
        const imageInfo = document.getElementById('imageInfo');

        if (!file) {
            this.resetFileInput();
            return;
        }

        // Update label
        fileLabel.querySelector('span:last-child').textContent = file.name;

        // Validate file
        const validation = await podcastValidator.validateImageDimensions(file);

        if (!validation.valid) {
            podcastValidator.showFieldError('cover_image', validation.message);
            return;
        }

        podcastValidator.clearFieldError('cover_image');

        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImage.src = e.target.result;

            const { width, height } = validation.dimensions;
            const fileSize = this.formatFileSize(file.size);

            imageInfo.innerHTML = `
                <div class="d-flex justify-content-between text-sm text-muted">
                    <span>Dimensions: ${width}×${height}px</span>
                    <span>Size: ${fileSize}</span>
                </div>
            `;

            imagePreview.style.display = 'block';
        };

        reader.readAsDataURL(file);
    }

    /**
     * Reset file input
     */
    resetFileInput() {
        const fileInput = document.getElementById('cover_image');
        const fileLabel = document.getElementById('fileLabel');
        const imagePreview = document.getElementById('imagePreview');

        if (fileInput) fileInput.value = '';
        if (fileLabel) {
            fileLabel.querySelector('span:last-child').textContent = 'Click to select cover image or drag & drop';
        }
        if (imagePreview) imagePreview.style.display = 'none';
    }

    /**
     * Setup search functionality
     */
    setupSearch() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.filterPodcasts(e.target.value);
            }, 300);
        });
    }

    /**
     * Filter podcasts table based on search query
     */
    filterPodcasts(query) {
        const table = document.getElementById('podcastsTable');
        if (!table) return;

        const rows = table.querySelectorAll('tbody tr');
        const searchTerm = query.toLowerCase().trim();

        let visibleCount = 0;

        rows.forEach(row => {
            const title = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const url = row.querySelector('td:nth-child(3)').textContent.toLowerCase();

            const matches = !searchTerm ||
                title.includes(searchTerm) ||
                url.includes(searchTerm);

            if (matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Show/hide empty state
        this.toggleEmptySearchState(visibleCount === 0 && searchTerm);
    }

    /**
     * Toggle empty search state
     */
    toggleEmptySearchState(show) {
        let emptySearchState = document.getElementById('emptySearchState');

        if (show && !emptySearchState) {
            const table = document.querySelector('.table-container');
            emptySearchState = document.createElement('div');
            emptySearchState.id = 'emptySearchState';
            emptySearchState.className = 'empty-state';
            emptySearchState.innerHTML = `
                <div class="empty-state-icon">🔍</div>
                <h3 class="empty-state-title">No Results Found</h3>
                <p class="empty-state-description">
                    No podcasts match your search criteria. Try adjusting your search terms.
                </p>
            `;
            table.parentElement.appendChild(emptySearchState);
        } else if (!show && emptySearchState) {
            emptySearchState.remove();
        }
    }

    /**
     * Clear search
     */
    clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            this.filterPodcasts('');
        }
    }

    /**
     * Setup modal handlers
     */
    setupModalHandlers() {
        // Form reset when modal is hidden
        const modal = document.getElementById('podcastModal');
        if (modal) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        if (!modal.classList.contains('show')) {
                            this.resetForm();
                        }
                    }
                });
            });
            observer.observe(modal, { attributes: true });
        }
    }

    /**
     * Setup form submission
     */
    setupFormSubmission() {
        const form = document.getElementById('podcastForm');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;

            try {
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="loading"></span> Processing...';

                // Validate form
                const formData = new FormData(form);
                const files = {
                    cover_image: document.getElementById('cover_image').files[0]
                };

                const validation = await podcastValidator.validateForm(formData, files);

                if (!validation.valid) {
                    // Show validation errors
                    Object.entries(validation.results).forEach(([field, result]) => {
                        if (!result.valid) {
                            podcastValidator.showFieldError(field, result.message);
                        }
                    });
                    // Reset button state immediately on validation failure
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    return;
                }

                // Submit form
                form.submit();

            } catch (error) {
                console.error('Form submission error:', error);
                this.showAlert('An error occurred while submitting the form. Please try again.', 'danger');
                // Reset button state on error
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    /**
     * Show add modal
     */
    showAddModal() {
        this.currentEditId = null;

        document.getElementById('modalTitle').textContent = 'Add New Podcast';
        document.getElementById('formAction').value = 'create';
        document.getElementById('podcastId').value = '';
        document.getElementById('submitIcon').textContent = '➕';
        document.getElementById('submitText').textContent = 'Add Podcast';

        this.resetForm();
        this.showModal();
    }

    /**
     * Show edit modal
     */
    async showEditModal(podcastId) {
        try {
            this.currentEditId = podcastId;

            // Get podcast data from the table row
            const row = document.querySelector(`tr[data-podcast-id="${podcastId}"]`);
            if (!row) {
                this.showAlert('Podcast not found', 'danger');
                return;
            }

            const title = row.querySelector('td:nth-child(2) strong').textContent;
            const feedUrl = row.querySelector('td:nth-child(3) a').textContent.trim();
            
            // Get description from data attribute if available
            const description = row.dataset.description || '';

            // Populate form
            document.getElementById('modalTitle').textContent = 'Edit Podcast';
            document.getElementById('formAction').value = 'update';
            document.getElementById('podcastId').value = podcastId;
            document.getElementById('title').value = title;
            document.getElementById('feed_url').value = feedUrl;
            document.getElementById('description').value = description;
            document.getElementById('submitIcon').textContent = '✏️';
            document.getElementById('submitText').textContent = 'Update Podcast';

            // Clear file input for edit mode
            this.resetFileInput();

            this.showModal();

        } catch (error) {
            console.error('Error loading podcast for edit:', error);
            this.showAlert('Error loading podcast data', 'danger');
        }
    }

    /**
     * Show modal
     */
    showModal() {
        const modal = document.getElementById('podcastModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Focus first input
            setTimeout(() => {
                const firstInput = modal.querySelector('input[type="text"]');
                if (firstInput) firstInput.focus();
            }, 100);
        }
    }

    /**
     * Hide modal
     */
    hideModal() {
        const modal = document.getElementById('podcastModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    /**
     * Show delete modal
     */
    showDeleteModal(podcastId, podcastTitle) {
        document.getElementById('deleteId').value = podcastId;
        document.getElementById('deletePodcastTitle').textContent = podcastTitle;

        const modal = document.getElementById('deleteModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Hide delete modal
     */
    hideDeleteModal() {
        const modal = document.getElementById('deleteModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    /**
     * Show status modal
     */
    showStatusModal(podcastId, podcastTitle, currentStatus) {
        document.getElementById('statusId').value = podcastId;
        document.getElementById('statusPodcastTitle').textContent = podcastTitle;
        
        // Highlight current status
        const activeOption = document.querySelector('.status-option-active');
        const inactiveOption = document.querySelector('.status-option-inactive');
        
        if (currentStatus === 'active') {
            activeOption.style.borderColor = 'var(--accent-primary)';
            activeOption.style.backgroundColor = 'rgba(35, 134, 54, 0.1)';
            inactiveOption.style.borderColor = 'var(--border-primary)';
            inactiveOption.style.backgroundColor = 'var(--bg-secondary)';
        } else {
            inactiveOption.style.borderColor = 'var(--accent-danger)';
            inactiveOption.style.backgroundColor = 'rgba(218, 54, 51, 0.1)';
            activeOption.style.borderColor = 'var(--border-primary)';
            activeOption.style.backgroundColor = 'var(--bg-secondary)';
        }

        const modal = document.getElementById('statusModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Hide status modal
     */
    hideStatusModal() {
        const modal = document.getElementById('statusModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    /**
     * Change podcast status
     */
    changeStatus(newStatus) {
        document.getElementById('statusValue').value = newStatus;
        document.getElementById('statusForm').submit();
    }

    /**
     * Show feed modal with main RSS feed
     */
    async showFeedModal() {
        const feedUrl = window.location.origin + '/feed.php';
        document.getElementById('feedModalTitle').textContent = 'RSS Feed - All Active Podcasts';
        document.getElementById('feedUrlInput').value = feedUrl;
        
        const modal = document.getElementById('feedModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Load feed content
        await this.loadFeedContent(feedUrl);
    }

    /**
     * Show feed modal for specific podcast
     */
    async showPodcastFeedModal(feedUrl, title) {
        document.getElementById('feedModalTitle').textContent = `RSS Feed - ${title}`;
        document.getElementById('feedUrlInput').value = feedUrl;
        
        const modal = document.getElementById('feedModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Load feed content
        await this.loadFeedContent(feedUrl);
    }

    /**
     * Load feed content via AJAX
     */
    async loadFeedContent(url) {
        const contentElement = document.getElementById('feedContent');
        contentElement.textContent = 'Loading feed...';

        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const xmlText = await response.text();
            
            // Format XML for display
            const formatted = this.formatXML(xmlText);
            contentElement.textContent = formatted;
        } catch (error) {
            contentElement.textContent = `Error loading feed: ${error.message}`;
            console.error('Feed load error:', error);
        }
    }

    /**
     * Format XML with indentation
     */
    formatXML(xml) {
        const PADDING = '  ';
        const reg = /(>)(<)(\/*)/g;
        let formatted = '';
        let pad = 0;

        xml = xml.replace(reg, '$1\n$2$3');
        
        xml.split('\n').forEach((node) => {
            let indent = 0;
            if (node.match(/.+<\/\w[^>]*>$/)) {
                indent = 0;
            } else if (node.match(/^<\/\w/)) {
                if (pad !== 0) {
                    pad -= 1;
                }
            } else if (node.match(/^<\w([^>]*[^\/])?>.*$/)) {
                indent = 1;
            } else {
                indent = 0;
            }

            formatted += PADDING.repeat(pad) + node + '\n';
            pad += indent;
        });

        return formatted.trim();
    }

    /**
     * Hide feed modal
     */
    hideFeedModal() {
        const modal = document.getElementById('feedModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    /**
     * Copy feed URL from modal
     */
    copyFeedUrlFromModal() {
        const input = document.getElementById('feedUrlInput');
        input.select();
        input.setSelectionRange(0, 99999); // For mobile devices

        try {
            navigator.clipboard.writeText(input.value).then(() => {
                // Show success feedback
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '✅ Copied!';
                btn.style.backgroundColor = 'var(--accent-success)';
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.backgroundColor = '';
                }, 2000);
            }).catch(() => {
                // Fallback for older browsers
                document.execCommand('copy');
                alert('Feed URL copied to clipboard!');
            });
        } catch (err) {
            alert('Failed to copy URL. Please copy manually.');
            console.error('Copy failed:', err);
        }
    }

    /**
     * Reset form
     */
    resetForm() {
        const form = document.getElementById('podcastForm');
        if (form) {
            form.reset();
            podcastValidator.clearAllErrors();
            this.resetFileInput();
        }
    }

    /**
     * Copy feed URL to clipboard
     */
    async copyFeedUrl() {
        const feedUrlInput = document.getElementById('feedUrl');
        if (!feedUrlInput) return;

        try {
            await navigator.clipboard.writeText(feedUrlInput.value);
            this.showAlert('Feed URL copied to clipboard!', 'success');
        } catch (error) {
            // Fallback for older browsers
            feedUrlInput.select();
            document.execCommand('copy');
            this.showAlert('Feed URL copied to clipboard!', 'success');
        }
    }

    /**
     * Show alert message
     */
    showAlert(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible">
                <div class="alert-icon">
                    ${type === 'success' ? '✅' : type === 'danger' ? '❌' : 'ℹ️'}
                </div>
                <div>
                    <strong>${type === 'success' ? 'Success!' : type === 'danger' ? 'Error!' : 'Info'}</strong>
                    ${message}
                </div>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        `;

        const container = document.querySelector('.container');
        if (container) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = alertHtml;
            const alert = tempDiv.firstElementChild;

            container.insertBefore(alert, container.firstElementChild);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 5000);
        }
    }

    /**
     * Show statistics modal
     */
    showStats() {
        const modal = document.getElementById('statsModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Hide statistics modal
     */
    hideStatsModal() {
        const modal = document.getElementById('statsModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' bytes';
        }
    }

    /**
     * Utility method to debounce function calls
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Global functions for inline event handlers
function showAddModal() {
    window.podcastApp.showAddModal();
}

function editPodcast(id) {
    window.podcastApp.showEditModal(id);
}

function deletePodcast(id, title) {
    window.podcastApp.showDeleteModal(id, title);
}

function hideModal() {
    window.podcastApp.hideModal();
}

function hideDeleteModal() {
    window.podcastApp.hideDeleteModal();
}

function clearSearch() {
    window.podcastApp.clearSearch();
}

function copyFeedUrl() {
    window.podcastApp.copyFeedUrl();
}

function showStats() {
    window.podcastApp.showStats();
}

function showStatusModal(id, title, status) {
    window.podcastApp.showStatusModal(id, title, status);
}

function hideStatusModal() {
    window.podcastApp.hideStatusModal();
}

function changeStatus(status) {
    window.podcastApp.changeStatus(status);
}

function showFeedModal() {
    window.podcastApp.showFeedModal();
}

function showPodcastFeedModal(feedUrl, title) {
    window.podcastApp.showPodcastFeedModal(feedUrl, title);
}

function hideFeedModal() {
    window.podcastApp.hideFeedModal();
}

function copyFeedUrl() {
    window.podcastApp.copyFeedUrlFromModal();
}

function hideStatsModal() {
    window.podcastApp.hideStatsModal();
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        localStorage.removeItem('podcast_auth');
        location.reload();
    }
}

// RSS Import Modal Functions
function showImportRssModal() {
    const modal = document.getElementById('importRssModal');
    if (modal) {
        resetRssImportModal();
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Focus on URL input
        setTimeout(() => {
            document.getElementById('rssFeedUrlInput').focus();
        }, 100);
    }
}

function hideImportRssModal() {
    const modal = document.getElementById('importRssModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        resetRssImportModal();
    }
}

function resetRssImportModal() {
    // Reset to step 1
    document.getElementById('rssImportStep1').style.display = 'block';
    document.getElementById('rssImportStep2').style.display = 'none';
    document.getElementById('rssImportError').style.display = 'none';
    document.getElementById('rssImportLoading').style.display = 'none';
    
    // Reset buttons
    document.getElementById('rssFetchButton').style.display = 'inline-block';
    document.getElementById('rssImportButton').style.display = 'none';
    document.getElementById('rssBackButton').style.display = 'none';
    
    // Clear inputs
    document.getElementById('rssFeedUrlInput').value = '';
    document.getElementById('rssTitle').value = '';
    document.getElementById('rssFeedUrl').value = '';
    document.getElementById('rssDescription').value = '';
    document.getElementById('rssImageUrl').value = '';
}

async function fetchRssFeedData() {
    const feedUrl = document.getElementById('rssFeedUrlInput').value.trim();
    
    // Validate URL
    if (!feedUrl) {
        showRssError('Please enter a feed URL');
        return;
    }
    
    // Show loading state
    document.getElementById('rssImportError').style.display = 'none';
    document.getElementById('rssImportLoading').style.display = 'block';
    document.getElementById('rssFetchButton').disabled = true;
    
    try {
        // Fetch feed data from API
        const formData = new FormData();
        formData.append('feed_url', feedUrl);
        
        const response = await fetch('api/import-rss.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show preview
            displayRssPreview(result.data);
        } else {
            showRssError(result.error || 'Failed to fetch feed');
        }
    } catch (error) {
        console.error('RSS Fetch Error:', error);
        showRssError('Network error. Please check your connection and try again.');
    } finally {
        document.getElementById('rssImportLoading').style.display = 'none';
        document.getElementById('rssFetchButton').disabled = false;
    }
}

function displayRssPreview(data) {
    // Hide step 1, show step 2
    document.getElementById('rssImportStep1').style.display = 'none';
    document.getElementById('rssImportStep2').style.display = 'block';
    
    // Update buttons
    document.getElementById('rssFetchButton').style.display = 'none';
    document.getElementById('rssImportButton').style.display = 'inline-block';
    document.getElementById('rssBackButton').style.display = 'inline-block';
    
    // Populate form fields
    document.getElementById('rssTitle').value = data.title || '';
    document.getElementById('rssFeedUrl').value = data.feed_url || '';
    document.getElementById('rssDescription').value = data.description || '';
    document.getElementById('rssImageUrl').value = data.image_url || '';
    
    // Display feed info
    document.getElementById('rssFeedType').value = data.feed_type || 'Unknown';
    document.getElementById('rssEpisodeCount').value = data.episode_count || '0';
    
    // Display image preview
    if (data.image_url) {
        const img = document.getElementById('rssPreviewImage');
        img.src = data.image_url;
        img.style.display = 'block';
        document.getElementById('rssNoImage').style.display = 'none';
        document.getElementById('rssImageInfo').textContent = 'Image will be downloaded on import';
        document.getElementById('rssImageInfo').style.display = 'block';
    } else {
        document.getElementById('rssPreviewImage').style.display = 'none';
        document.getElementById('rssNoImage').style.display = 'block';
        document.getElementById('rssImageInfo').style.display = 'none';
    }
}

function showRssError(message) {
    const errorDiv = document.getElementById('rssImportError');
    const errorMessage = document.getElementById('rssImportErrorMessage');
    errorMessage.textContent = message;
    errorDiv.style.display = 'flex';
}

async function importRssFeed() {
    const form = document.getElementById('rssImportForm');
    const title = document.getElementById('rssTitle').value.trim();
    const feedUrl = document.getElementById('rssFeedUrl').value.trim();
    
    // Validate required fields
    if (!title || !feedUrl) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Disable button during import
    const importBtn = document.getElementById('rssImportButton');
    importBtn.disabled = true;
    importBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing...';
    
    try {
        // Submit the form
        form.submit();
    } catch (error) {
        console.error('Import Error:', error);
        alert('Failed to import podcast. Please try again.');
        importBtn.disabled = false;
        importBtn.innerHTML = '<i class="fa-solid fa-check"></i> Import Podcast';
    }
}

// Health Check Modal Functions
let currentHealthCheckPodcastId = null;

function showHealthCheckModal() {
    const modal = document.getElementById('healthCheckModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function hideHealthCheckModal() {
    const modal = document.getElementById('healthCheckModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        currentHealthCheckPodcastId = null;
    }
}

async function checkPodcastHealth(podcastId, podcastTitle) {
    currentHealthCheckPodcastId = podcastId;
    
    // Update modal title
    document.getElementById('healthCheckTitle').textContent = podcastTitle;
    
    // Show modal and loading state
    showHealthCheckModal();
    document.getElementById('healthCheckLoading').style.display = 'block';
    document.getElementById('healthCheckResults').style.display = 'none';
    
    try {
        // Call health check API
        const formData = new FormData();
        formData.append('podcast_id', podcastId);
        
        const response = await fetch('api/health-check.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayHealthCheckResults(result.data);
        } else {
            alert('Health check failed: ' + (result.error || 'Unknown error'));
            hideHealthCheckModal();
        }
    } catch (error) {
        console.error('Health Check Error:', error);
        alert('Network error. Please check your connection and try again.');
        hideHealthCheckModal();
    }
}

function displayHealthCheckResults(data) {
    // Hide loading, show results
    document.getElementById('healthCheckLoading').style.display = 'none';
    document.getElementById('healthCheckResults').style.display = 'block';
    document.getElementById('healthCheckAgainButton').style.display = 'inline-block';
    
    // Update timestamp
    document.getElementById('healthCheckTimestamp').textContent = data.timestamp;
    
    // Update overall status
    const overallStatus = document.getElementById('healthOverallStatus');
    const overallIcon = document.getElementById('healthOverallIcon');
    const overallMessage = document.getElementById('healthOverallMessage');
    const overallDetails = document.getElementById('healthOverallDetails');
    
    // Remove old classes
    overallStatus.className = 'alert';
    
    if (data.overall_status === 'healthy') {
        overallStatus.classList.add('alert-success');
        overallIcon.textContent = '✅';
        overallMessage.textContent = 'All checks passed!';
        overallDetails.textContent = 'Your podcast feed is healthy and properly configured.';
    } else if (data.overall_status === 'warning') {
        overallStatus.classList.add('alert-warning');
        overallIcon.textContent = '⚠️';
        overallMessage.textContent = 'Some issues detected';
        overallDetails.textContent = 'Your feed is accessible but has some warnings that should be addressed.';
    } else {
        overallStatus.classList.add('alert-danger');
        overallIcon.textContent = '❌';
        overallMessage.textContent = 'Critical issues found';
        overallDetails.textContent = 'Your feed has critical issues that need immediate attention.';
    }
    
    // Update individual checks
    updateHealthCheckCard('feedUrl', data.checks.feed_url);
    updateHealthCheckCard('rssStructure', data.checks.rss_structure);
    updateHealthCheckCard('itunesNamespace', data.checks.itunes_namespace);
    updateHealthCheckCard('coverImage', data.checks.cover_image);
}

function updateHealthCheckCard(checkName, checkData) {
    const badge = document.getElementById(checkName + 'Badge');
    const message = document.getElementById(checkName + 'Message');
    const details = document.getElementById(checkName + 'Details');
    
    // Update badge
    badge.className = 'health-check-status-badge status-' + checkData.status;
    badge.textContent = checkData.status.toUpperCase();
    
    // Update message
    message.textContent = checkData.message;
    
    // Update details
    if (checkData.details) {
        details.textContent = checkData.details;
        details.style.display = 'block';
    } else {
        details.textContent = '';
        details.style.display = 'none';
    }
    
    // Add extra info if available
    if (checkData.http_code) {
        details.textContent += (details.textContent ? ' | ' : '') + 'HTTP ' + checkData.http_code;
    }
    if (checkData.response_time) {
        details.textContent += (details.textContent ? ' | ' : '') + checkData.response_time;
    }
    if (checkData.content_type) {
        details.textContent += (details.textContent ? ' | ' : '') + checkData.content_type;
    }
    if (checkData.size) {
        details.textContent += (details.textContent ? ' | ' : '') + checkData.size;
    }
}

function recheckPodcastHealth() {
    if (currentHealthCheckPodcastId) {
        const title = document.getElementById('healthCheckTitle').textContent;
        checkPodcastHealth(currentHealthCheckPodcastId, title);
    }
}

// Help Modal Functions
function showHelpModal() {
    const modal = document.getElementById('helpModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function hideHelpModal() {
    const modal = document.getElementById('helpModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.podcastApp = new PodcastApp();
    
    // Add Enter key handler for RSS URL input
    const rssUrlInput = document.getElementById('rssFeedUrlInput');
    if (rssUrlInput) {
        rssUrlInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                fetchRssFeedData();
            }
        });
    }
    
    // Close modals on overlay click
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-overlay')) {
            if (e.target.id === 'importRssModal') {
                hideImportRssModal();
            } else if (e.target.id === 'healthCheckModal') {
                hideHealthCheckModal();
            } else if (e.target.id === 'helpModal') {
                hideHelpModal();
            }
        }
    });
    
    // Close modals on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const importModal = document.getElementById('importRssModal');
            const healthModal = document.getElementById('healthCheckModal');
            const helpModal = document.getElementById('helpModal');
            
            if (importModal && importModal.classList.contains('show')) {
                hideImportRssModal();
            } else if (healthModal && healthModal.classList.contains('show')) {
                hideHealthCheckModal();
            } else if (helpModal && helpModal.classList.contains('show')) {
                hideHelpModal();
            }
        }
    });
});