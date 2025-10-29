/**
 * Menu Manager JavaScript
 * Handles all interactions for menu management
 */

// State
let currentEditId = null;
let currentDeleteId = null;
let sortable = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeBrandingForm();
    initializeMenuItemsList();
    initializeToggles();
});

/**
 * Branding Form Initialization
 */
function initializeBrandingForm() {
    const form = document.getElementById('brandingForm');
    const logoTypeRadios = document.querySelectorAll('input[name="logo_type"]');
    const siteTitle = document.getElementById('siteTitle');
    const logoIcon = document.getElementById('logoIcon');
    const logoImage = document.getElementById('logoImage');

    // Toggle icon/image inputs based on logo type
    logoTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const iconGroup = document.getElementById('iconGroup');
            const imageGroup = document.getElementById('imageGroup');
            
            if (this.value === 'icon') {
                iconGroup.style.display = 'block';
                imageGroup.style.display = 'none';
            } else {
                iconGroup.style.display = 'none';
                imageGroup.style.display = 'block';
            }
            
            updateBrandingPreview();
        });
    });

    // Update preview on input changes
    siteTitle.addEventListener('input', updateBrandingPreview);
    logoIcon.addEventListener('input', updateBrandingPreview);
    logoImage.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                updateBrandingPreview(e.target.result);
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        saveBranding();
    });
}

/**
 * Update branding preview
 */
function updateBrandingPreview(imageDataUrl = null) {
    const preview = document.getElementById('brandingPreview');
    const siteTitle = document.getElementById('siteTitle').value || 'Podcast Browser';
    const logoType = document.querySelector('input[name="logo_type"]:checked').value;
    const logoIcon = document.getElementById('logoIcon').value || 'fa-podcast';

    if (logoType === 'image' && imageDataUrl) {
        preview.innerHTML = `
            <img src="${imageDataUrl}" alt="Logo" class="preview-logo-image">
            <span class="preview-title">${escapeHtml(siteTitle)}</span>
        `;
    } else if (logoType === 'icon') {
        preview.innerHTML = `
            <i class="fas ${escapeHtml(logoIcon)} preview-logo-icon"></i>
            <span class="preview-title">${escapeHtml(siteTitle)}</span>
        `;
    } else {
        preview.innerHTML = `
            <i class="fas fa-podcast preview-logo-icon"></i>
            <span class="preview-title">${escapeHtml(siteTitle)}</span>
        `;
    }
}

/**
 * Save branding configuration
 */
function saveBranding() {
    const form = document.getElementById('brandingForm');
    const formData = new FormData(form);

    fetch('api/save-menu-branding.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Branding saved successfully!', 'success');
        } else {
            showToast(data.message || 'Failed to save branding', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while saving', 'error');
    });
}

/**
 * Initialize menu items list with drag-and-drop
 */
function initializeMenuItemsList() {
    const list = document.getElementById('menuItemsList');
    
    if (!list || list.querySelector('.empty-state')) {
        return;
    }

    // Initialize Sortable.js for drag-and-drop
    sortable = Sortable.create(list, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        onEnd: function(evt) {
            saveMenuOrder();
        }
    });
}

/**
 * Save menu order after drag-and-drop
 */
function saveMenuOrder() {
    const items = document.querySelectorAll('.menu-item-card');
    const order = Array.from(items).map(item => item.dataset.itemId);

    fetch('api/reorder-menu-items.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order: order })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Menu order saved', 'success');
        } else {
            showToast(data.message || 'Failed to save order', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while saving order', 'error');
    });
}

/**
 * Initialize toggle switches
 */
function initializeToggles() {
    const toggles = document.querySelectorAll('.item-toggle');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const itemId = this.dataset.itemId;
            const active = this.checked;
            toggleMenuItem(itemId, active, this);
        });
    });
}

/**
 * Toggle menu item active state
 */
function toggleMenuItem(itemId, active, toggleElement) {
    const formData = new FormData();
    formData.append('id', itemId);
    formData.append('active', active ? '1' : '0');

    fetch('api/toggle-menu-item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update card opacity
            const card = toggleElement.closest('.menu-item-card');
            card.style.opacity = active ? '1' : '0.5';
            showToast(active ? 'Menu item enabled' : 'Menu item disabled', 'success');
        } else {
            // Revert toggle on error
            toggleElement.checked = !active;
            showToast(data.message || 'Failed to toggle item', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toggleElement.checked = !active;
        showToast('An error occurred', 'error');
    });
}

/**
 * Show add menu item modal
 */
function showAddItemModal() {
    currentEditId = null;
    
    document.getElementById('modalTitle').textContent = 'Add Menu Item';
    document.getElementById('itemId').value = '';
    document.getElementById('itemAction').value = 'add';
    document.getElementById('menuItemForm').reset();
    
    // Reset icon type display
    document.getElementById('faIconGroup').style.display = 'none';
    document.getElementById('imageIconGroup').style.display = 'none';
    
    // Setup icon type listeners
    setupIconTypeListeners();
    
    // Update preview
    updateMenuItemPreview();
    
    document.getElementById('menuItemModal').classList.add('active');
}

/**
 * Edit menu item
 */
function editMenuItem(itemId) {
    currentEditId = itemId;
    
    // Get item data from data attributes
    const card = document.querySelector(`[data-item-id="${itemId}"]`);
    const label = card.dataset.label;
    const url = card.dataset.url;
    const iconType = card.dataset.iconType || 'none';
    const iconValue = card.dataset.iconValue || '';
    const target = card.dataset.target || '_self';
    
    // Populate form
    document.getElementById('modalTitle').textContent = 'Edit Menu Item';
    document.getElementById('itemId').value = itemId;
    document.getElementById('itemAction').value = 'update';
    document.getElementById('itemLabel').value = label;
    document.getElementById('itemUrl').value = url;
    
    // Reset icon groups visibility
    document.getElementById('faIconGroup').style.display = 'none';
    document.getElementById('imageIconGroup').style.display = 'none';
    
    // Set icon type
    document.querySelector(`input[name="icon_type"][value="${iconType}"]`).checked = true;
    if (iconType === 'fa') {
        document.getElementById('itemIconValue').value = iconValue;
        document.getElementById('faIconGroup').style.display = 'block';
    } else if (iconType === 'image') {
        document.getElementById('imageIconGroup').style.display = 'block';
        // Note: Can't pre-populate file input for security reasons
    }
    
    // Set target
    document.querySelector(`input[name="target"][value="${target}"]`).checked = true;
    
    // Setup listeners
    setupIconTypeListeners();
    setupPreviewListeners();
    
    // Update preview
    updateMenuItemPreview();
    
    document.getElementById('menuItemModal').classList.add('active');
}

/**
 * Setup icon type radio listeners
 */
function setupIconTypeListeners() {
    const iconTypeRadios = document.querySelectorAll('input[name="icon_type"]');
    
    iconTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('faIconGroup').style.display = this.value === 'fa' ? 'block' : 'none';
            document.getElementById('imageIconGroup').style.display = this.value === 'image' ? 'block' : 'none';
            updateMenuItemPreview();
        });
    });
    
    setupPreviewListeners();
}

/**
 * Setup preview update listeners
 */
function setupPreviewListeners() {
    const label = document.getElementById('itemLabel');
    const iconValue = document.getElementById('itemIconValue');
    const iconFile = document.getElementById('itemIconFile');
    
    label.removeEventListener('input', updateMenuItemPreview);
    iconValue.removeEventListener('input', updateMenuItemPreview);
    
    label.addEventListener('input', updateMenuItemPreview);
    iconValue.addEventListener('input', updateMenuItemPreview);
    iconFile.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                updateMenuItemPreview(e.target.result);
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
}

/**
 * Update menu item preview
 */
function updateMenuItemPreview(imageDataUrl = null) {
    const preview = document.getElementById('menuItemPreview');
    const label = document.getElementById('itemLabel').value || 'Menu Item';
    const iconType = document.querySelector('input[name="icon_type"]:checked').value;
    const iconValue = document.getElementById('itemIconValue').value || 'fa-link';

    if (iconType === 'image' && imageDataUrl) {
        preview.innerHTML = `
            <img src="${imageDataUrl}" alt="Icon" style="max-width: 20px; max-height: 20px; border-radius: 4px;">
            <span>${escapeHtml(label)}</span>
        `;
    } else if (iconType === 'fa') {
        preview.innerHTML = `
            <i class="fas ${escapeHtml(iconValue)}"></i>
            <span>${escapeHtml(label)}</span>
        `;
    } else {
        // No icon - just show label
        preview.innerHTML = `
            <span>${escapeHtml(label)}</span>
        `;
    }
}

/**
 * Save menu item
 */
function saveMenuItem() {
    const form = document.getElementById('menuItemForm');
    const formData = new FormData(form);

    fetch('api/save-menu-item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Menu item saved successfully!', 'success');
            closeModal();
            // Reload page to show updated menu
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to save menu item', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while saving', 'error');
    });
}

/**
 * Delete menu item
 */
function deleteMenuItem(itemId, itemName) {
    currentDeleteId = itemId;
    document.getElementById('deleteItemName').textContent = itemName;
    document.getElementById('deleteModal').classList.add('active');
}

/**
 * Confirm delete
 */
function confirmDelete() {
    if (!currentDeleteId) return;

    const formData = new FormData();
    formData.append('id', currentDeleteId);

    fetch('api/delete-menu-item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Menu item deleted successfully!', 'success');
            closeDeleteModal();
            // Remove card from DOM
            const card = document.querySelector(`[data-item-id="${currentDeleteId}"]`);
            if (card) {
                card.remove();
            }
            currentDeleteId = null;
        } else {
            showToast(data.message || 'Failed to delete menu item', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while deleting', 'error');
    });
}

/**
 * Close modal
 */
function closeModal() {
    document.getElementById('menuItemModal').classList.remove('active');
    currentEditId = null;
}

/**
 * Close delete modal
 */
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    currentDeleteId = null;
}

/**
 * Show toast notification
 */
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast show';
    
    if (type === 'error') {
        toast.classList.add('error');
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closeModal();
        closeDeleteModal();
    }
});
