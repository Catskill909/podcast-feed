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
            // Get feed URL from button's onclick attribute
            const feedButton = row.querySelector('td:nth-child(3) button');
            const onclickAttr = feedButton.getAttribute('onclick');
            const feedUrl = onclickAttr.match(/showPodcastFeedModal\('([^']+)'/)[1];
            
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
        // Get current sort from SortManager if available
        let feedUrl = window.location.origin + '/feed.php';
        
        if (window.sortManager && window.sortManager.currentSort) {
            const sortKey = window.sortManager.currentSort;
            const sortMap = {
                'date-newest': { sort: 'episodes', order: 'desc' },
                'date-oldest': { sort: 'episodes', order: 'asc' },
                'title-az': { sort: 'title', order: 'asc' },
                'title-za': { sort: 'title', order: 'desc' },
                'status-active': { sort: 'status', order: 'desc' },
                'status-inactive': { sort: 'status', order: 'asc' }
            };
            
            const sortParams = sortMap[sortKey] || { sort: 'episodes', order: 'desc' };
            feedUrl += `?sort=${sortParams.sort}&order=${sortParams.order}`;
        }
        
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
            // Check if this is a local feed (our own feed.php)
            const isLocalFeed = url.includes(window.location.origin) || url.startsWith('/feed.php');
            
            let response;
            if (isLocalFeed) {
                // Fetch local feed directly
                response = await fetch(url);
            } else {
                // Use proxy for external feeds to avoid CORS issues
                const proxyUrl = `api/fetch-feed.php?url=${encodeURIComponent(url)}`;
                response = await fetch(proxyUrl);
            }
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const xmlText = await response.text();
            
            // Check if response is an error XML
            if (xmlText.includes('<error>')) {
                const parser = new DOMParser();
                const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
                const errorMsg = xmlDoc.querySelector('error')?.textContent || 'Unknown error';
                throw new Error(errorMsg);
            }
            
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
    
    // Hide previous errors and validation results
    document.getElementById('rssImportError').style.display = 'none';
    document.getElementById('rssValidationPanel').style.display = 'none';
    
    // Show loading state
    document.getElementById('rssImportLoading').style.display = 'block';
    updateRssLoadingMessage('Validating feed...');
    document.getElementById('rssFetchButton').disabled = true;
    
    try {
        // STEP 1: VALIDATE FEED (NEW)
        const validationResult = await validateRssFeedBeforeImport(feedUrl);
        
        if (!validationResult.success) {
            showRssError(validationResult.error || 'Validation failed');
            return;
        }
        
        const validation = validationResult.validation;
        
        // Check if feed can be imported
        if (!validation.can_import) {
            // Show blocking errors
            showValidationErrors(validation);
            return;
        }
        
        // Check for warnings
        if (validation.warning_messages && validation.warning_messages.length > 0) {
            // Show warnings and wait for user decision
            const shouldContinue = await showValidationWarnings(validation);
            if (!shouldContinue) {
                return; // User cancelled
            }
        } else {
            // Show brief success message
            showValidationSuccess(validation);
            await new Promise(resolve => setTimeout(resolve, 1000)); // Show for 1 second
        }
        
        // STEP 2: FETCH FULL DATA (EXISTING)
        updateRssLoadingMessage('Fetching full feed data...');
        
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

// NEW: Validate RSS feed before import
async function validateRssFeedBeforeImport(feedUrl) {
    try {
        const response = await fetch('api/validate-rss-import.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ feed_url: feedUrl })
        });
        
        return await response.json();
    } catch (error) {
        console.error('Validation Error:', error);
        return {
            success: false,
            error: 'Unable to validate feed. Please try again.'
        };
    }
}

// NEW: Update loading message
function updateRssLoadingMessage(message) {
    const loadingMsg = document.getElementById('rssLoadingMessage');
    if (loadingMsg) {
        loadingMsg.textContent = message;
    }
}

// NEW: Show validation success (brief)
function showValidationSuccess(validation) {
    const panel = document.getElementById('rssValidationPanel');
    const feedInfo = validation.feed_info || {};
    
    panel.innerHTML = `
        <div class="alert alert-success">
            <div class="alert-icon">✅</div>
            <div>
                <strong>Feed validated successfully!</strong>
                <ul class="validation-check-list">
                    <li>Valid ${feedInfo.feed_type || 'RSS 2.0'} structure</li>
                    <li>Cover image: ${feedInfo.image_url ? 'Found' : 'N/A'}</li>
                    <li>${feedInfo.episode_count || 0} episodes found</li>
                </ul>
            </div>
        </div>
    `;
    panel.style.display = 'block';
}

// NEW: Show validation warnings (requires user action)
function showValidationWarnings(validation) {
    return new Promise((resolve) => {
        const panel = document.getElementById('rssValidationPanel');
        const warnings = validation.warning_messages || [];
        
        panel.innerHTML = `
            <div class="alert alert-warning">
                <div class="alert-icon">⚠️</div>
                <div>
                    <strong>Feed has ${warnings.length} warning(s)</strong>
                    <ul class="validation-check-list">
                        ${warnings.map(w => `<li>${w.message || w}</li>`).join('')}
                    </ul>
                    <p style="margin-top: var(--spacing-sm); font-size: var(--font-size-sm); color: var(--text-secondary);">
                        These issues won't prevent import, but may affect compatibility with some podcast apps.
                    </p>
                    <div class="validation-buttons">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelRssValidation()">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="continueRssImportWithWarnings()">
                            Continue Anyway
                        </button>
                    </div>
                </div>
            </div>
        `;
        panel.style.display = 'block';
        
        // Set up global callbacks
        window.cancelRssValidation = () => {
            panel.style.display = 'none';
            document.getElementById('rssImportLoading').style.display = 'none';
            document.getElementById('rssFetchButton').disabled = false;
            resolve(false);
        };
        
        window.continueRssImportWithWarnings = () => {
            panel.style.display = 'none';
            resolve(true);
        };
    });
}

// NEW: Show validation errors (blocking)
function showValidationErrors(validation) {
    const panel = document.getElementById('rssValidationPanel');
    const errors = validation.errors || [];
    
    panel.innerHTML = `
        <div class="alert alert-danger">
            <div class="alert-icon">❌</div>
            <div>
                <strong>Cannot import feed - ${errors.length} critical issue(s) found</strong>
                <div style="margin-top: var(--spacing-md);">
                    ${errors.map(error => `
                        <div class="validation-error-detail">
                            <strong>✗ ${error.message || 'Unknown error'}</strong>
                            ${error.details ? `<p style="margin: var(--spacing-xs) 0 0 0; color: var(--text-secondary);">${error.details}</p>` : ''}
                            ${error.suggestion ? `<p class="validation-suggestion">💡 ${error.suggestion}</p>` : ''}
                        </div>
                    `).join('')}
                </div>
                <div style="margin-top: var(--spacing-md);">
                    <a href="https://validator.w3.org/feed/" target="_blank" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-external-link"></i> Validate Feed Externally
                    </a>
                </div>
            </div>
        </div>
    `;
    panel.style.display = 'block';
    
    // Hide loading, re-enable button
    document.getElementById('rssImportLoading').style.display = 'none';
    document.getElementById('rssFetchButton').disabled = false;
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
        // NOTE: latest_episode_date will be populated by cron job or manual refresh
        // We don't pass it during import to avoid stale data
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

// Refresh Feed Metadata Function
async function refreshFeedMetadata(podcastId) {
    const button = event.target.closest('button');
    const originalIcon = button.innerHTML;
    
    try {
        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        
        const formData = new FormData();
        formData.append('podcast_id', podcastId);
        
        const response = await fetch('api/refresh-feed-metadata.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update the row's data attributes
            const row = button.closest('tr');
            if (row && result.data) {
                row.dataset.latestEpisode = result.data.latest_episode_date || '';
                row.dataset.episodeCount = result.data.episode_count || '0';
                
                // CRITICAL: Update the displayed date immediately!
                const dateCell = row.querySelector('.latest-episode-cell');
                if (dateCell && result.data.latest_episode_date) {
                    const formattedDate = formatLatestEpisodeDate(result.data.latest_episode_date);
                    dateCell.innerHTML = formattedDate;
                }
            }
            
            // Show success message
            window.podcastApp.showAlert(
                `Feed data refreshed! Latest episode: ${result.data.latest_episode_date_formatted || 'Unknown'}`,
                'success'
            );
            
            // Re-apply current sort to reflect changes
            if (window.sortManager) {
                window.sortManager.applySortToTable(window.sortManager.currentSort);
            }
        } else {
            window.podcastApp.showAlert(
                `Failed to refresh feed data: ${result.error}`,
                'danger'
            );
        }
    } catch (error) {
        console.error('Refresh error:', error);
        window.podcastApp.showAlert(
            'Network error while refreshing feed data',
            'danger'
        );
    } finally {
        // Restore button
        button.disabled = false;
        button.innerHTML = originalIcon;
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
            } else if (e.target.id === 'previewModal') {
                hidePreviewModal();
            }
        }
    });
    
    // Close modals on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const importModal = document.getElementById('importRssModal');
            const healthModal = document.getElementById('healthCheckModal');
            const helpModal = document.getElementById('helpModal');
            const previewModal = document.getElementById('previewModal');
            
            if (importModal && importModal.classList.contains('show')) {
                hideImportRssModal();
            } else if (healthModal && healthModal.classList.contains('show')) {
                hideHealthCheckModal();
            } else if (helpModal && helpModal.classList.contains('show')) {
                hideHelpModal();
            } else if (previewModal && previewModal.classList.contains('show')) {
                hidePreviewModal();
            }
        }
    });
});

// Podcast Preview Modal Functions
let currentPreviewPodcastId = null;

async function showPodcastPreview(podcastId) {
    currentPreviewPodcastId = podcastId;
    
    const modal = document.getElementById('previewModal');
    const loadingEl = document.getElementById('previewLoading');
    const errorEl = document.getElementById('previewError');
    const contentEl = document.getElementById('previewContent');
    
    // Show modal with loading state
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    loadingEl.style.display = 'flex';
    errorEl.style.display = 'none';
    contentEl.style.display = 'none';
    
    try {
        // Fetch podcast preview data
        const response = await fetch(`api/get-podcast-preview.php?id=${encodeURIComponent(podcastId)}`);
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Invalid response type:', contentType);
            const text = await response.text();
            console.error('Response body:', text.substring(0, 500));
            showPreviewError('Server configuration error. Please check the API endpoint.');
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            displayPodcastPreview(result.data);
        } else {
            showPreviewError(result.error || 'Failed to load podcast details');
        }
    } catch (error) {
        console.error('Preview fetch error:', error);
        showPreviewError('Unable to load podcast details. Please try again.');
    }
}

function displayPodcastPreview(data) {
    // Hide loading, show content
    document.getElementById('previewLoading').style.display = 'none';
    document.getElementById('previewContent').style.display = 'grid';
    
    // Update title
    document.getElementById('previewTitle').textContent = data.title;
    
    // Update description
    const descEl = document.getElementById('previewDescription');
    if (data.description && data.description !== 'No description available') {
        descEl.textContent = data.description;
        descEl.style.display = 'block';
    } else {
        descEl.textContent = 'No description available';
        descEl.style.display = 'block';
    }
    
    // Update image
    const imageEl = document.getElementById('podcastPreviewImage');
    const placeholderEl = document.getElementById('previewImagePlaceholder');
    const dimensionsEl = document.getElementById('previewImageDimensions');
    
    if (data.image_url && data.image_url !== 'null' && data.image_url !== null && data.image_url.trim() !== '') {
        imageEl.src = data.image_url;
        imageEl.style.cssText = 'display: block !important; width: 240px !important; height: 240px !important;';
        placeholderEl.style.display = 'none';
        
        if (data.image_width && data.image_height) {
            dimensionsEl.textContent = `${data.image_width} × ${data.image_height} px`;
            dimensionsEl.style.display = 'block';
        } else {
            dimensionsEl.style.display = 'none';
        }
    } else {
        imageEl.style.display = 'none';
        placeholderEl.style.display = 'flex';
        dimensionsEl.style.display = 'none';
    }
    
    // Update episode count
    document.getElementById('previewEpisodeCount').textContent = data.episode_count || '0';
    
    // Update latest episode
    const latestEpEl = document.getElementById('previewLatestEpisode');
    if (data.latest_episode_date) {
        const epDate = new Date(data.latest_episode_date);
        const now = new Date();
        
        // Compare calendar dates, not elapsed time
        // Strip time component to compare only calendar days
        const epDay = new Date(epDate.getFullYear(), epDate.getMonth(), epDate.getDate());
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const daysDiff = Math.floor((today - epDay) / (1000 * 60 * 60 * 24));
        
        if (daysDiff < 0) {
            // Future date
            latestEpEl.textContent = 'Today';
            latestEpEl.classList.add('highlight');
        } else if (daysDiff == 0) {
            // Same calendar day = Today
            latestEpEl.textContent = 'Today';
            latestEpEl.classList.add('highlight');
        } else if (daysDiff == 1) {
            // One calendar day ago = Yesterday
            latestEpEl.textContent = 'Yesterday';
            latestEpEl.classList.add('highlight');
        } else if (daysDiff < 7) {
            // 2-6 days ago
            latestEpEl.textContent = `${daysDiff} days ago`;
            latestEpEl.classList.add('highlight');
        } else {
            // 7+ days ago - show date
            latestEpEl.textContent = formatDate(epDate);
            latestEpEl.classList.remove('highlight');
        }
    } else {
        latestEpEl.textContent = 'Unknown';
        latestEpEl.classList.remove('highlight');
    }
    
    // Update status
    const statusEl = document.getElementById('previewStatus');
    statusEl.textContent = data.status === 'active' ? 'Active' : 'Inactive';
    statusEl.style.color = data.status === 'active' ? 'var(--accent-primary)' : 'var(--accent-danger)';
    
    // Update category
    document.getElementById('previewCategory').textContent = data.category || 'Unknown';
    
    // Update feed type
    document.getElementById('previewFeedType').textContent = data.feed_type || 'RSS';
    
    // Update created date
    const createdEl = document.getElementById('previewCreatedDate');
    if (data.created_date) {
        createdEl.textContent = formatDate(new Date(data.created_date));
    } else {
        createdEl.textContent = 'Unknown';
    }
}

function showPreviewError(message) {
    document.getElementById('previewLoading').style.display = 'none';
    document.getElementById('previewContent').style.display = 'none';
    
    const errorEl = document.getElementById('previewError');
    document.getElementById('previewErrorMessage').textContent = message;
    errorEl.style.display = 'block';
}

function hidePreviewModal() {
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        currentPreviewPodcastId = null;
    }
}

// Preview modal action functions
function editPodcastFromPreview() {
    if (currentPreviewPodcastId) {
        hidePreviewModal();
        editPodcast(currentPreviewPodcastId);
    }
}

function refreshFeedFromPreview() {
    if (currentPreviewPodcastId) {
        hidePreviewModal();
        refreshFeedMetadata(currentPreviewPodcastId);
    }
}

function checkHealthFromPreview() {
    if (currentPreviewPodcastId) {
        const title = document.getElementById('previewTitle').textContent;
        hidePreviewModal();
        checkPodcastHealth(currentPreviewPodcastId, title);
    }
}

function deletePodcastFromPreview() {
    if (currentPreviewPodcastId) {
        const title = document.getElementById('previewTitle').textContent;
        hidePreviewModal();
        deletePodcast(currentPreviewPodcastId, title);
    }
}

// Helper functions
function formatDate(date) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
}

function getLanguageName(code) {
    const languages = {
        'en': 'English',
        'es': 'Spanish',
        'fr': 'French',
        'de': 'German',
        'it': 'Italian',
        'pt': 'Portuguese',
        'ru': 'Russian',
        'ja': 'Japanese',
        'zh': 'Chinese',
        'ko': 'Korean',
        'ar': 'Arabic',
        'hi': 'Hindi',
        'nl': 'Dutch',
        'sv': 'Swedish',
        'no': 'Norwegian',
        'da': 'Danish',
        'fi': 'Finnish',
        'pl': 'Polish',
        'tr': 'Turkish',
        'he': 'Hebrew'
    };
    
    if (!code) return 'Unknown';
    
    // Handle language codes with region (e.g., en-US)
    const baseCode = code.split('-')[0].toLowerCase();
    return languages[baseCode] || code.toUpperCase();
}

/**
 * Format date for display - SHARED UTILITY
 * This is the EXACT same logic used in player-modal.js
 * Uses user's local timezone for consistent display
 */
function formatLatestEpisodeDate(dateString) {
    if (!dateString || dateString.trim() === '') {
        return '<span style="color: var(--text-muted); font-style: italic;">Unknown</span>';
    }

    try {
        const date = new Date(dateString);
        const now = new Date();
        
        // Reset time to midnight for accurate day comparison
        const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const nowOnly = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        
        const diffTime = nowOnly - dateOnly;
        const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 0) {
            return '<span style="color: var(--accent-primary); font-weight: 500;">Today</span>';
        }
        if (diffDays === 1) {
            return '<span style="color: var(--accent-primary);">Yesterday</span>';
        }
        if (diffDays === -1) {
            return '<span style="color: var(--accent-primary); font-weight: 500;">Tomorrow</span>';
        }
        if (diffDays < 0) {
            return '<span style="color: var(--accent-primary); font-weight: 500;">In the future</span>';
        }
        if (diffDays < 7) {
            return `<span style="color: var(--accent-primary);">${diffDays} days ago</span>`;
        }
        
        const formatted = date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        return `<span class="text-muted">${formatted}</span>`;
    } catch (e) {
        console.error('Date parsing error:', e, dateString);
        return '<span style="color: var(--text-muted); font-style: italic;">Unknown</span>';
    }
}

/**
 * Update all latest episode dates on page load
 * This ensures consistent date display using user's local timezone
 * READS FROM THE SAME data-latest-episode ATTRIBUTE AS THE MODALS
 */
function updateAllLatestEpisodeDates() {
    const cells = document.querySelectorAll('.latest-episode-cell');
    
    if (cells.length === 0) {
        console.warn('No .latest-episode-cell elements found');
        return;
    }
    
    console.log(`Updating ${cells.length} date cells`);
    
    cells.forEach(cell => {
        // Get the date from the parent row's data-latest-episode attribute
        // This is the EXACT SAME source the modals use!
        const row = cell.closest('tr');
        if (!row) {
            console.warn('Cell has no parent row:', cell);
            return;
        }
        
        const dateString = row.dataset.latestEpisode || '';
        console.log('Processing date:', dateString);
        
        const formattedDate = formatLatestEpisodeDate(dateString);
        cell.innerHTML = formattedDate;
    });
    
    console.log('Date update complete');
}

// Make it globally available
window.updateAllLatestEpisodeDates = updateAllLatestEpisodeDates;

// Run on page load - multiple methods to ensure it runs
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateAllLatestEpisodeDates);
} else {
    // DOM already loaded, run immediately
    updateAllLatestEpisodeDates();
}

// Also run after a short delay as fallback
setTimeout(updateAllLatestEpisodeDates, 100);