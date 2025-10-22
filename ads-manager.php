<?php
/**
 * Ads Management Interface
 * Manage web banner ads and mobile banner feed
 */

session_start();

require_once __DIR__ . '/includes/AdsManager.php';
require_once __DIR__ . '/config/config.php';

$manager = new AdsManager();
$webAds = $manager->getWebAds();
$mobileAds = $manager->getMobileAds();
$settings = $manager->getSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ads Manager - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Sortable.js for drag and drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <link rel="stylesheet" href="assets/css/ads-manager.css?v=<?php echo time(); ?>">
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
            <h1><i class="fas fa-ad"></i> Ads Manager</h1>
            <p>Manage banner advertisements for your web app and mobile feed</p>
        </div>

        <!-- Web Banner Ads Section -->
        <div class="ads-section">
            <div class="section-header">
                <div>
                    <div class="section-title">
                        <i class="fas fa-desktop"></i>
                        <div>
                            <h2>Web Banner Ads</h2>
                            <div class="section-subtitle">Leaderboard: 728x90px</div>
                        </div>
                    </div>
                </div>
                <div class="toggle-container">
                    <span class="toggle-label">Enable Ads</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="webAdsToggle" <?php echo $settings['web_ads_enabled'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <!-- Live Preview -->
            <div class="preview-section">
                <div class="preview-header">
                    <i class="fas fa-eye"></i>
                    <h3>Live Preview</h3>
                </div>
                <div class="preview-container" id="webPreviewContainer">
                    <?php if (empty($webAds)): ?>
                        <div class="preview-placeholder">
                            <i class="fas fa-image"></i>
                            <p>Upload ads to see preview</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($webAds as $index => $ad): ?>
                            <div class="preview-ad <?php echo $index === 0 ? 'active' : ''; ?>" data-ad-id="<?php echo $ad['id']; ?>">
                                <?php if (!empty($ad['click_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($ad['click_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Web Ad">
                                    </a>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Web Ad">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rotation Duration Control -->
            <div class="duration-control">
                <div class="controls-row">
                    <!-- Rotation Duration -->
                    <div class="control-group">
                        <label>
                            <i class="fas fa-clock"></i> Rotation Duration
                        </label>
                        <div class="stepper-control">
                            <button class="stepper-btn" onclick="adjustDuration('rotation', -5)" aria-label="Decrease rotation duration">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="stepper-value" id="rotationValue" data-value="<?php echo $settings['web_ads_rotation_duration']; ?>">
                                <?php echo $settings['web_ads_rotation_duration']; ?>s
                            </div>
                            <button class="stepper-btn" onclick="adjustDuration('rotation', 5)" aria-label="Increase rotation duration">
                                <i class="fas fa-chevron-up"></i>
                            </button>
                        </div>
                        <div class="stepper-range">5s - 60s</div>
                    </div>

                    <!-- Fade Duration -->
                    <div class="control-group">
                        <label>
                            <i class="fas fa-adjust"></i> Fade Duration
                        </label>
                        <div class="stepper-control">
                            <button class="stepper-btn" onclick="adjustDuration('fade', -0.5)" aria-label="Decrease fade duration">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="stepper-value" id="fadeValue" data-value="<?php echo ($settings['web_ads_fade_duration'] ?? 1.2); ?>">
                                <?php echo ($settings['web_ads_fade_duration'] ?? 1.2); ?>s
                            </div>
                            <button class="stepper-btn" onclick="adjustDuration('fade', 0.5)" aria-label="Increase fade duration">
                                <i class="fas fa-chevron-up"></i>
                            </button>
                        </div>
                        <div class="stepper-range">0.5s - 3s</div>
                    </div>
                </div>
            </div>

            <!-- Upload Zone -->
            <div class="upload-zone" id="webUploadZone">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Drag & Drop Web Banner</h3>
                <p>or click to browse</p>
                <p class="requirements">Required: 728x90px • Max 2MB • JPG, PNG, or GIF</p>
                <input type="file" id="webFileInput" accept="image/*">
            </div>

            <!-- Ads Grid -->
            <div id="webAdsGrid" class="ads-grid">
                <?php if (empty($webAds)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-images"></i>
                        <h3>No web banner ads yet</h3>
                        <p>Upload your first 728x90px banner ad to get started</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($webAds as $ad): ?>
                        <div class="ad-item" data-ad-id="<?php echo $ad['id']; ?>">
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                                <span>Drag to reorder</span>
                            </div>
                            <button class="delete-btn" onclick="deleteAd('web', '<?php echo $ad['id']; ?>')">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="ad-image-container">
                                <?php if (!empty($ad['click_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($ad['click_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Web Ad">
                                    </a>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Web Ad">
                                <?php endif; ?>
                            </div>
                            <div class="ad-info">
                                <div class="ad-info-row">
                                    <div class="ad-dimensions">
                                        <i class="fas fa-desktop"></i>
                                        728x90
                                    </div>
                                    <button class="btn-url-compact" onclick="openUrlModal('web', '<?php echo $ad['id']; ?>', '<?php echo htmlspecialchars($ad['click_url'] ?? '', ENT_QUOTES); ?>')">
                                        <i class="fas fa-link"></i>
                                        <?php echo !empty($ad['click_url']) ? 'Edit URL' : 'Add URL'; ?>
                                    </button>
                                </div>
                                <div class="ad-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($ad['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mobile Banner Ads Section -->
        <div class="ads-section">
            <div class="section-header">
                <div>
                    <div class="section-title">
                        <i class="fas fa-mobile-alt"></i>
                        <div>
                            <h2>Mobile Banner Feed</h2>
                            <div class="section-subtitle">Phone: 320x50px • Tablet: 728x90px</div>
                        </div>
                    </div>
                </div>
                <div class="toggle-container">
                    <span class="toggle-label">Enable Feed</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="mobileAdsToggle" <?php echo $settings['mobile_ads_enabled'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <!-- Upload Zone -->
            <div class="upload-zone" id="mobileUploadZone">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Drag & Drop Mobile Banner</h3>
                <p>or click to browse</p>
                <p class="requirements">Phone: 320x50px or Tablet: 728x90px • Max 2MB • JPG, PNG, or GIF</p>
                <input type="file" id="mobileFileInput" accept="image/*">
            </div>

            <!-- Phone Ads Section -->
            <?php 
            $phoneAds = array_filter($mobileAds, function($ad) { 
                return $ad['dimensions'] === '320x50'; 
            });
            ?>
            <?php if (!empty($phoneAds)): ?>
            <div class="device-section">
                <div class="device-header">
                    <i class="fas fa-mobile-screen"></i>
                    <h3>Phone Banners (320x50)</h3>
                </div>
                <div class="ads-grid" id="phoneAdsGrid">
                    <?php foreach ($phoneAds as $ad): ?>
                        <div class="ad-item" data-ad-id="<?php echo $ad['id']; ?>" data-device="phone">
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                                <span>Drag to reorder</span>
                            </div>
                            <button class="delete-btn" onclick="deleteAd('mobile', '<?php echo $ad['id']; ?>')">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="ad-image-container">
                                <?php if (!empty($ad['click_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($ad['click_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Phone Ad">
                                    </a>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Phone Ad">
                                <?php endif; ?>
                            </div>
                            <div class="ad-info">
                                <div class="ad-info-row">
                                    <div class="ad-dimensions">
                                        <i class="fas fa-mobile-screen"></i>
                                        <?php echo htmlspecialchars($ad['dimensions']); ?>
                                    </div>
                                    <button class="btn-url-compact" onclick="openUrlModal('mobile', '<?php echo $ad['id']; ?>', '<?php echo htmlspecialchars($ad['click_url'] ?? '', ENT_QUOTES); ?>')">
                                        <i class="fas fa-link"></i>
                                        <?php echo !empty($ad['click_url']) ? 'Edit URL' : 'Add URL'; ?>
                                    </button>
                                </div>
                                <div class="ad-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($ad['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tablet Ads Section -->
            <?php 
            $tabletAds = array_filter($mobileAds, function($ad) { 
                return $ad['dimensions'] === '728x90'; 
            });
            ?>
            <?php if (!empty($tabletAds)): ?>
            <div class="device-section">
                <div class="device-header">
                    <i class="fas fa-tablet-screen-button"></i>
                    <h3>Tablet Banners (728x90)</h3>
                </div>
                <div class="ads-grid" id="tabletAdsGrid">
                    <?php foreach ($tabletAds as $ad): ?>
                        <div class="ad-item" data-ad-id="<?php echo $ad['id']; ?>" data-device="tablet">
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                                <span>Drag to reorder</span>
                            </div>
                            <button class="delete-btn" onclick="deleteAd('mobile', '<?php echo $ad['id']; ?>')">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="ad-image-container">
                                <?php if (!empty($ad['click_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($ad['click_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Tablet Ad">
                                    </a>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Tablet Ad">
                                <?php endif; ?>
                            </div>
                            <div class="ad-info">
                                <div class="ad-info-row">
                                    <div class="ad-dimensions">
                                        <i class="fas fa-tablet-screen-button"></i>
                                        <?php echo htmlspecialchars($ad['dimensions']); ?>
                                    </div>
                                    <button class="btn-url-compact" onclick="openUrlModal('mobile', '<?php echo $ad['id']; ?>', '<?php echo htmlspecialchars($ad['click_url'] ?? '', ENT_QUOTES); ?>')">
                                        <i class="fas fa-link"></i>
                                        <?php echo !empty($ad['click_url']) ? 'Edit URL' : 'Add URL'; ?>
                                    </button>
                                </div>
                                <div class="ad-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($ad['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Empty State -->
            <?php if (empty($mobileAds)): ?>
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <h3>No mobile banner ads yet</h3>
                <p>Upload your first phone (320x50) or tablet (728x90) banner ad to get started</p>
            </div>
            <?php endif; ?>

            <!-- RSS Feed URL -->
            <div class="feed-url-box">
                <label>
                    <i class="fas fa-rss"></i> Mobile Ads RSS Feed URL
                </label>
                <a href="<?php echo APP_URL; ?>/mobile-ads-feed.php" 
                   class="feed-url-link" 
                   target="_blank" 
                   rel="noopener noreferrer">
                    <i class="fas fa-external-link-alt"></i>
                    <?php echo APP_URL; ?>/mobile-ads-feed.php
                </a>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header error">
                <i class="fas fa-exclamation-circle"></i>
                <h3>Invalid Image Size</h3>
            </div>
            <div class="modal-body" id="errorMessage"></div>
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="closeModal('errorModal')">OK</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header warning">
                <i class="fas fa-trash-alt"></i>
                <h3>Confirm Delete</h3>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this banner ad? This action cannot be undone.
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- URL Modal -->
    <div id="urlModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-link"></i>
                <h3>Set Banner URL</h3>
            </div>
            <div class="modal-body">
                <p>Enter the destination URL for this banner ad. Users will be redirected here when they click the banner.</p>
                <input type="text" 
                       id="urlInput" 
                       class="url-modal-input" 
                       placeholder="https://example.com/landing-page">
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('urlModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveAdUrl()">Save URL</button>
            </div>
        </div>
    </div>

    <script src="assets/js/ads-manager.js?v=<?php echo time(); ?>"></script>
</body>
</html>
