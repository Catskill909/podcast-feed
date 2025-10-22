/**
 * Ads Manager JavaScript
 * Handles drag-and-drop, uploads, deletion, and settings
 */

// State
let webAdRotationInterval = null;
let currentWebAdIndex = 0;
let deleteAdType = null;
let deleteAdId = null;
let currentFadeDuration = 1.2; // seconds
let currentUrlAdType = null;
let currentUrlAdId = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeUploadZones();
    initializeSortable();
    initializeDurationSlider();
    initializeToggles();
    startWebAdRotation();
});

/**
 * Initialize drag-and-drop upload zones
 */
function initializeUploadZones() {
    // Web ads upload zone
    const webZone = document.getElementById('webUploadZone');
    const webInput = document.getElementById('webFileInput');
    
    webZone.addEventListener('click', () => webInput.click());
    webZone.addEventListener('dragover', handleDragOver);
    webZone.addEventListener('dragleave', handleDragLeave);
    webZone.addEventListener('drop', (e) => handleDrop(e, 'web'));
    webInput.addEventListener('change', (e) => handleFileSelect(e, 'web'));
    
    // Mobile ads upload zone
    const mobileZone = document.getElementById('mobileUploadZone');
    const mobileInput = document.getElementById('mobileFileInput');
    
    mobileZone.addEventListener('click', () => mobileInput.click());
    mobileZone.addEventListener('dragover', handleDragOver);
    mobileZone.addEventListener('dragleave', handleDragLeave);
    mobileZone.addEventListener('drop', (e) => handleDrop(e, 'mobile'));
    mobileInput.addEventListener('change', (e) => handleFileSelect(e, 'mobile'));
}

/**
 * Handle drag over
 */
function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.add('drag-over');
}

/**
 * Handle drag leave
 */
function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('drag-over');
}

/**
 * Handle file drop
 */
function handleDrop(e, adType) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('drag-over');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        uploadAd(files[0], adType);
    }
}

/**
 * Handle file select from input
 */
function handleFileSelect(e, adType) {
    const files = e.target.files;
    if (files.length > 0) {
        uploadAd(files[0], adType);
    }
    // Reset input so same file can be selected again
    e.target.value = '';
}

/**
 * Upload ad image
 */
async function uploadAd(file, adType) {
    const formData = new FormData();
    formData.append('ad_image', file);
    formData.append('ad_type', adType);
    
    try {
        const response = await fetch('api/upload-ad.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload page to show new ad
            location.reload();
        } else {
            // Show error modal
            showErrorModal(result.message);
        }
    } catch (error) {
        showErrorModal('Upload failed. Please try again.');
        console.error('Upload error:', error);
    }
}

/**
 * Delete ad
 */
function deleteAd(adType, adId) {
    deleteAdType = adType;
    deleteAdId = adId;
    openModal('deleteModal');
}

/**
 * Confirm delete
 */
async function confirmDelete() {
    const formData = new FormData();
    formData.append('ad_type', deleteAdType);
    formData.append('ad_id', deleteAdId);
    
    try {
        const response = await fetch('api/delete-ad.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeModal('deleteModal');
            // Reload page to reflect changes
            location.reload();
        } else {
            showErrorModal(result.message);
        }
    } catch (error) {
        showErrorModal('Delete failed. Please try again.');
        console.error('Delete error:', error);
    }
}

// Attach confirm delete to button
document.getElementById('confirmDeleteBtn')?.addEventListener('click', confirmDelete);

/**
 * Initialize Sortable.js for drag-and-drop reordering
 */
function initializeSortable() {
    // Web ads sortable
    const webGrid = document.getElementById('webAdsGrid');
    if (webGrid && !webGrid.querySelector('.empty-state')) {
        new Sortable(webGrid, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                updateAdOrder('web');
            }
        });
    }
    
    // Phone ads sortable
    const phoneGrid = document.getElementById('phoneAdsGrid');
    if (phoneGrid) {
        new Sortable(phoneGrid, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                updateAdOrder('mobile');
            }
        });
    }
    
    // Tablet ads sortable
    const tabletGrid = document.getElementById('tabletAdsGrid');
    if (tabletGrid) {
        new Sortable(tabletGrid, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                updateAdOrder('mobile');
            }
        });
    }
}

/**
 * Update ad display order after drag-and-drop
 */
async function updateAdOrder(adType) {
    let orderedIds = [];
    
    if (adType === 'web') {
        const grid = document.getElementById('webAdsGrid');
        const adItems = grid.querySelectorAll('.ad-item');
        orderedIds = Array.from(adItems).map(item => item.dataset.adId);
    } else {
        // Mobile: combine phone and tablet ads in order
        const phoneGrid = document.getElementById('phoneAdsGrid');
        const tabletGrid = document.getElementById('tabletAdsGrid');
        
        if (phoneGrid) {
            const phoneItems = phoneGrid.querySelectorAll('.ad-item');
            orderedIds.push(...Array.from(phoneItems).map(item => item.dataset.adId));
        }
        
        if (tabletGrid) {
            const tabletItems = tabletGrid.querySelectorAll('.ad-item');
            orderedIds.push(...Array.from(tabletItems).map(item => item.dataset.adId));
        }
    }
    
    const formData = new FormData();
    formData.append('action', 'update_order');
    formData.append('ad_type', adType);
    formData.append('ordered_ids', JSON.stringify(orderedIds));
    
    try {
        const response = await fetch('api/update-ad-settings.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            console.error('Failed to update order:', result.message);
        }
    } catch (error) {
        console.error('Order update error:', error);
    }
}

/**
 * Initialize duration slider
 */
function initializeDurationSlider() {
    // Rotation duration slider
    const slider = document.getElementById('durationSlider');
    const valueDisplay = document.getElementById('durationValue');
    
    if (slider && valueDisplay) {
        slider.addEventListener('input', function() {
            valueDisplay.textContent = this.value + 's';
        });
        
        slider.addEventListener('change', async function() {
            await updateSetting('web_ads_rotation_duration', this.value);
            // Restart rotation with new duration
            startWebAdRotation();
        });
    }

    // Fade duration slider
    const fadeSlider = document.getElementById('fadeSlider');
    const fadeValueDisplay = document.getElementById('fadeValue');
    
    if (fadeSlider && fadeValueDisplay) {
        fadeSlider.addEventListener('input', function() {
            fadeValueDisplay.textContent = this.value + 's';
            currentFadeDuration = parseFloat(this.value);
            updateFadeDuration(currentFadeDuration);
        });
    }
}

/**
 * Initialize toggle switches
 */
function initializeToggles() {
    const webToggle = document.getElementById('webAdsToggle');
    const mobileToggle = document.getElementById('mobileAdsToggle');
    
    if (webToggle) {
        webToggle.addEventListener('change', async function() {
            await updateSetting('web_ads_enabled', this.checked ? '1' : '0');
        });
    }
    
    if (mobileToggle) {
        mobileToggle.addEventListener('change', async function() {
            await updateSetting('mobile_ads_enabled', this.checked ? '1' : '0');
        });
    }
}

/**
 * Update a single setting
 */
async function updateSetting(key, value) {
    const formData = new FormData();
    formData.append('action', 'update_settings');
    formData.append(key, value);
    
    try {
        const response = await fetch('api/update-ad-settings.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            console.error('Failed to update setting:', result.message);
        }
    } catch (error) {
        console.error('Setting update error:', error);
    }
}

/**
 * Start web ad rotation in preview
 */
function startWebAdRotation() {
    // Clear existing interval
    if (webAdRotationInterval) {
        clearInterval(webAdRotationInterval);
    }
    
    const previewContainer = document.getElementById('webPreviewContainer');
    const previewAds = previewContainer.querySelectorAll('.preview-ad');
    
    // No rotation needed for 0 or 1 ads
    if (previewAds.length <= 1) {
        return;
    }
    
    const duration = parseInt(document.getElementById('durationSlider').value) * 1000;
    
    currentWebAdIndex = 0;
    
    webAdRotationInterval = setInterval(() => {
        // Hide current ad
        previewAds[currentWebAdIndex].classList.remove('active');
        
        // Move to next ad
        currentWebAdIndex = (currentWebAdIndex + 1) % previewAds.length;
        
        // Show next ad
        previewAds[currentWebAdIndex].classList.add('active');
    }, duration);
}

/**
 * Update fade duration for all preview ads
 */
function updateFadeDuration(duration) {
    const previewAds = document.querySelectorAll('.preview-ad');
    previewAds.forEach(ad => {
        ad.style.transition = `opacity ${duration}s ease-in-out`;
    });
}

/**
 * Open URL modal
 */
function openUrlModal(adType, adId, currentUrl) {
    currentUrlAdType = adType;
    currentUrlAdId = adId;
    
    const urlInput = document.getElementById('urlInput');
    urlInput.value = currentUrl || '';
    
    openModal('urlModal');
    
    // Focus input after modal opens
    setTimeout(() => urlInput.focus(), 100);
}

/**
 * Save ad URL from modal
 */
async function saveAdUrl() {
    const url = document.getElementById('urlInput').value.trim();
    
    const formData = new FormData();
    formData.append('ad_type', currentUrlAdType);
    formData.append('ad_id', currentUrlAdId);
    formData.append('url', url);
    
    try {
        const response = await fetch('api/update-ad-url.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeModal('urlModal');
            // Reload to show updated button text
            location.reload();
        } else {
            showErrorModal(result.message);
        }
    } catch (error) {
        showErrorModal('Failed to update URL. Please try again.');
        console.error('URL update error:', error);
    }
}

/**
 * Copy feed URL to clipboard
 */
function copyFeedUrl() {
    const feedUrlInput = document.getElementById('feedUrl');
    feedUrlInput.select();
    feedUrlInput.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        
        // Visual feedback
        const btn = event.target.closest('.btn');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.background = '#4CAF50';
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.style.background = '';
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
}

/**
 * Show error modal
 */
function showErrorModal(message) {
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.innerHTML = message;
    openModal('errorModal');
}

/**
 * Open modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

/**
 * Close modal
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});
