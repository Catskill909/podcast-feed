<?php
/**
 * Menu Manager Interface
 * Customize site branding and navigation menu
 */

session_start();

require_once __DIR__ . '/includes/MenuManager.php';

$manager = new MenuManager();
$branding = $manager->getBranding();
$menuItems = $manager->getMenuItems();

/**
 * Escape string for use in JavaScript (onclick attributes, etc.)
 */
function escapeJs($text) {
    if (empty($text)) return '';
    return str_replace(
        ['\\', "'", '"', "\n", "\r", "\t"],
        ['\\\\', "\\'", '\\"', '\\n', '\\r', '\\t'],
        $text
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Manager</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Sortable.js for drag and drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <link rel="stylesheet" href="assets/css/menu-manager.css?v=<?php echo time(); ?>">
    
    <!-- Simple Password Protection -->
    <script src="auth.js"></script>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <div class="back-button-container">
            <a href="admin.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </a>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-bars"></i> Menu Manager</h1>
            <p>Customize your site branding and navigation menu</p>
        </div>

        <!-- Site Branding Section -->
        <div class="menu-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-palette"></i>
                    <h2>Site Branding</h2>
                </div>
            </div>

            <div class="branding-form">
                <form id="brandingForm">
                    <!-- Site Title -->
                    <div class="form-group">
                        <label for="siteTitle">Site Title</label>
                        <input type="text" 
                               id="siteTitle" 
                               name="site_title" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($branding['site_title']); ?>"
                               placeholder="Podcast Browser"
                               required>
                    </div>

                    <!-- Logo Type -->
                    <div class="form-group">
                        <label>Logo Type</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" 
                                       name="logo_type" 
                                       value="icon" 
                                       <?php echo $branding['logo_type'] === 'icon' ? 'checked' : ''; ?>>
                                <span>Font Awesome Icon</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" 
                                       name="logo_type" 
                                       value="image" 
                                       <?php echo $branding['logo_type'] === 'image' ? 'checked' : ''; ?>>
                                <span>Custom Image</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" 
                                       name="logo_type" 
                                       value="image_only" 
                                       <?php echo $branding['logo_type'] === 'image_only' ? 'checked' : ''; ?>>
                                <span>Image Only (No Text)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Icon Input -->
                    <div class="form-group" id="iconGroup" style="display: <?php echo $branding['logo_type'] === 'icon' ? 'block' : 'none'; ?>;">
                        <label for="logoIcon">Font Awesome Icon Class</label>
                        <input type="text" 
                               id="logoIcon" 
                               name="logo_icon" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($branding['logo_icon']); ?>"
                               placeholder="fa-podcast">
                        <small class="form-hint">Use format: fa-icon-name (e.g., fa-podcast, fa-microphone)</small>
                    </div>

                    <!-- Image Upload (shown for both 'image' and 'image_only') -->
                    <div class="form-group" id="imageGroup" style="display: <?php echo ($branding['logo_type'] === 'image' || $branding['logo_type'] === 'image_only') ? 'block' : 'none'; ?>;">
                        <label for="logoImage">Logo Image</label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="logoImage" name="logo_file" accept="image/*">
                            <label for="logoImage" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Choose Image</span>
                            </label>
                        </div>
                        <?php if ($branding['logo_type'] === 'image' && !empty($branding['logo_image'])): ?>
                            <div class="current-image">
                                <img src="<?php echo htmlspecialchars($branding['logo_image']); ?>" alt="Current Logo">
                            </div>
                        <?php endif; ?>
                        <small class="form-hint">Max 2MB • JPG, PNG, GIF, or SVG</small>
                    </div>

                    <!-- Preview -->
                    <div class="form-group">
                        <label>Preview</label>
                        <div class="branding-preview" id="brandingPreview">
                            <?php if (($branding['logo_type'] === 'image' || $branding['logo_type'] === 'image_only') && !empty($branding['logo_image'])): ?>
                                <img src="<?php echo htmlspecialchars($branding['logo_image']); ?>" alt="Logo" class="preview-logo-image">
                            <?php else: ?>
                                <i class="fas <?php echo htmlspecialchars($branding['logo_icon']); ?> preview-logo-icon"></i>
                            <?php endif; ?>
                            <?php if ($branding['logo_type'] !== 'image_only'): ?>
                                <span class="preview-title"><?php echo htmlspecialchars($branding['site_title']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Branding
                    </button>
                </form>
            </div>
        </div>

        <!-- Menu Items Section -->
        <div class="menu-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-list"></i>
                    <h2>Menu Items</h2>
                </div>
                <button class="btn btn-primary" onclick="showAddItemModal()">
                    <i class="fas fa-plus"></i> Add Menu Item
                </button>
            </div>

            <!-- Menu Items List -->
            <div id="menuItemsList" class="menu-items-list">
                <?php if (empty($menuItems)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list"></i>
                        <h3>No menu items yet</h3>
                        <p>Add your first menu item to get started</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($menuItems as $item): ?>
                        <div class="menu-item-card" 
                             data-item-id="<?php echo $item['id']; ?>"
                             data-label="<?php echo htmlspecialchars($item['label']); ?>"
                             data-url="<?php echo htmlspecialchars($item['url']); ?>"
                             data-icon-type="<?php echo htmlspecialchars($item['icon_type']); ?>"
                             data-icon-value="<?php echo htmlspecialchars($item['icon_value']); ?>"
                             data-target="<?php echo htmlspecialchars($item['target']); ?>"
                             style="opacity: <?php echo $item['active'] ? '1' : '0.5'; ?>;">
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            
                            <div class="menu-item-icon">
                                <?php if ($item['icon_type'] === 'fa' && !empty($item['icon_value'])): ?>
                                    <i class="fas <?php echo htmlspecialchars($item['icon_value']); ?>"></i>
                                <?php elseif ($item['icon_type'] === 'image' && !empty($item['icon_value'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['icon_value']); ?>" alt="Icon">
                                <?php endif; ?>
                            </div>
                            
                            <div class="menu-item-content">
                                <div class="menu-item-label"><?php echo htmlspecialchars($item['label']); ?></div>
                                <div class="menu-item-url">
                                    <i class="fas fa-external-link-alt"></i>
                                    <?php echo htmlspecialchars($item['url']); ?>
                                    <?php if ($item['target'] === '_blank'): ?>
                                        <span class="badge">New Tab</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="menu-item-actions">
                                <div class="menu-item-toggle" title="<?php echo $item['active'] ? 'Enabled - Click to disable' : 'Disabled - Click to enable'; ?>">
                                    <label class="toggle-switch">
                                        <input type="checkbox" 
                                               class="item-toggle" 
                                               data-item-id="<?php echo $item['id']; ?>"
                                               <?php echo $item['active'] ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <button class="btn-icon" onclick="editMenuItem('<?php echo $item['id']; ?>')" title="Edit Menu Item">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon btn-danger" onclick="deleteMenuItem('<?php echo $item['id']; ?>', '<?php echo escapeJs($item['label']); ?>')" title="Delete Menu Item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Menu Item Modal -->
    <div class="modal-overlay" id="menuItemModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">Add Menu Item</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="menuItemForm">
                    <input type="hidden" id="itemId" name="id">
                    <input type="hidden" id="itemAction" name="action" value="add">

                    <!-- Label -->
                    <div class="form-group">
                        <label for="itemLabel">Label *</label>
                        <input type="text" 
                               id="itemLabel" 
                               name="label" 
                               class="form-control" 
                               placeholder="About Us"
                               required>
                    </div>

                    <!-- URL -->
                    <div class="form-group">
                        <label for="itemUrl">URL *</label>
                        <input type="text" 
                               id="itemUrl" 
                               name="url" 
                               class="form-control" 
                               placeholder="/about.php or https://example.com"
                               required>
                        <small class="form-hint">Use relative URLs (/about.php) or full URLs (https://...)</small>
                    </div>

                    <!-- Icon Type -->
                    <div class="form-group">
                        <label>Icon (optional)</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="icon_type" value="none" checked>
                                <span>No Icon</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="icon_type" value="fa">
                                <span>Font Awesome</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="icon_type" value="image">
                                <span>Custom Image</span>
                            </label>
                        </div>
                    </div>

                    <!-- Font Awesome Icon -->
                    <div class="form-group" id="faIconGroup" style="display: none;">
                        <label for="itemIconValue">Font Awesome Class</label>
                        <input type="text" 
                               id="itemIconValue" 
                               name="icon_value" 
                               class="form-control" 
                               placeholder="fa-info-circle">
                    </div>

                    <!-- Image Upload -->
                    <div class="form-group" id="imageIconGroup" style="display: none;">
                        <label for="itemIconFile">Icon Image</label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="itemIconFile" name="icon_file" accept="image/*">
                            <label for="itemIconFile" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Choose Image</span>
                            </label>
                        </div>
                    </div>

                    <!-- Target -->
                    <div class="form-group">
                        <label>Open in</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="target" value="_self" checked>
                                <span>Same Window</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="target" value="_blank">
                                <span>New Tab</span>
                            </label>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="form-group">
                        <label>Preview</label>
                        <div class="menu-item-preview" id="menuItemPreview">
                            <i class="fas fa-link"></i>
                            <span>Menu Item</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveMenuItem()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="delete-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Are you sure you want to delete this menu item?</p>
                    <p><strong id="deleteItemName"></strong></p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <script src="assets/js/menu-manager.js?v=<?php echo time(); ?>"></script>
</body>
</html>
