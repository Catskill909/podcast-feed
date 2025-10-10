<?php

/**
 * Podcast Directory Manager - Main Interface
 * CRUD interface for managing podcast entries
 */

require_once __DIR__ . '/includes/PodcastManager.php';

$podcastManager = new PodcastManager();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $data = [
                'title' => $_POST['title'] ?? '',
                'feed_url' => $_POST['feed_url'] ?? '',
                'description' => $_POST['description'] ?? ''
            ];
            $result = $podcastManager->createPodcast($data, $_FILES['cover_image'] ?? null);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            $data = [
                'title' => $_POST['title'] ?? '',
                'feed_url' => $_POST['feed_url'] ?? '',
                'description' => $_POST['description'] ?? ''
            ];
            $result = $podcastManager->updatePodcast($id, $data, $_FILES['cover_image'] ?? null);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';
            $result = $podcastManager->deletePodcast($id);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;

        case 'update_status':
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? '';
            $result = $podcastManager->updatePodcastStatus($id, $status);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
    }

    // Redirect to prevent form resubmission
    if ($result['success']) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=' . urlencode($message));
        exit;
    }
}

// Handle GET success message
if (isset($_GET['success'])) {
    $message = $_GET['success'];
    $messageType = 'success';
}

// Get all podcasts
$podcasts = $podcastManager->getAllPodcasts(true);
$stats = $podcastManager->getStats();

// Get specific podcast for editing
$editPodcast = null;
if (isset($_GET['edit'])) {
    $editPodcast = $podcastManager->getPodcast($_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PodFeed Builder</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎧</text></svg>">
    
    <!-- Simple Password Protection - ALWAYS ACTIVE -->
    <script src="auth.js"></script>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fa-solid fa-headphones logo-icon"></i>
                    <span>PodFeed Builder</span>
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="#" onclick="showFeedModal()">View Feed</a></li>
                        <li><a href="#" onclick="showStats()">Stats</a></li>
                        <li><a href="#" onclick="logout()" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="content-header">
                <div>
                    <button type="button" class="btn btn-primary" onclick="showAddModal()">
                        <i class="fa-solid fa-plus"></i> Add New Podcast
                    </button>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible">
                    <div class="alert-icon">
                        <?php if ($messageType === 'success'): ?>✅<?php else: ?>❌<?php endif; ?>
                    </div>
                    <div>
                        <strong><?php echo $messageType === 'success' ? 'Success!' : 'Error!'; ?></strong>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>


            <!-- Podcasts Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Podcast Directory</h2>
                    <div class="card-actions">
                        <div class="search-bar">
                            <input type="text" class="form-control search-input" placeholder="Search podcasts..." id="searchInput">
                            <div class="search-actions">
                                <button type="button" class="btn btn-outline btn-sm" onclick="clearSearch()">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($podcasts)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🎧</div>
                        <h3 class="empty-state-title">No Podcasts Yet</h3>
                        <p class="empty-state-description">
                            Get started by adding your first podcast to the directory.
                            Click the "Add New Podcast" button above to begin.
                        </p>
                        <button type="button" class="btn btn-primary" onclick="showAddModal()">
                            Add Your First Podcast
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table" id="podcastsTable">
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Title</th>
                                    <th>Feed URL</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($podcasts as $podcast): ?>
                                    <tr data-podcast-id="<?php echo htmlspecialchars($podcast['id']); ?>" 
                                        data-description="<?php echo htmlspecialchars($podcast['description'] ?? ''); ?>">
                                        <td>
                                            <?php if ($podcast['cover_image'] && $podcast['image_info']): ?>
                                                <img src="<?php echo htmlspecialchars($podcast['image_info']['url']); ?>"
                                                    alt="<?php echo htmlspecialchars($podcast['title']); ?>"
                                                    class="podcast-cover"
                                                    title="<?php echo $podcast['image_info']['width']; ?>x<?php echo $podcast['image_info']['height']; ?>px">
                                            <?php else: ?>
                                                <div class="podcast-cover-placeholder">No Image</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($podcast['title']); ?></strong>
                                        </td>
                                        <td>
                                            <a href="#" 
                                                onclick="showPodcastFeedModal('<?php echo htmlspecialchars($podcast['feed_url']); ?>', '<?php echo htmlspecialchars($podcast['title']); ?>'); return false;"
                                                title="Click to view feed">
                                                <?php echo htmlspecialchars(strlen($podcast['feed_url']) > 50 ? substr($podcast['feed_url'], 0, 50) . '...' : $podcast['feed_url']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                class="badge badge-<?php echo $podcast['status'] === 'active' ? 'success' : 'danger'; ?> badge-clickable tooltip"
                                                data-tooltip="Change Status"
                                                onclick="showStatusModal('<?php echo htmlspecialchars($podcast['id']); ?>', '<?php echo htmlspecialchars($podcast['title']); ?>', '<?php echo $podcast['status']; ?>')">
                                                <?php echo $podcast['status'] === 'active' ? '✓ Active' : '✕ Inactive'; ?>
                                            </button>
                                        </td>
                                        <td class="text-muted">
                                            <?php echo date('M j, Y', strtotime($podcast['created_date'])); ?>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <button type="button" class="btn btn-outline btn-sm tooltip"
                                                    data-tooltip="Edit Podcast"
                                                    onclick="editPodcast('<?php echo htmlspecialchars($podcast['id']); ?>')">
                                                    ✏️
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm tooltip"
                                                    data-tooltip="Delete Podcast"
                                                    onclick="deletePodcast('<?php echo htmlspecialchars($podcast['id']); ?>', '<?php echo htmlspecialchars($podcast['title']); ?>')">
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="podcastModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Podcast</h3>
                <button type="button" class="modal-close" onclick="hideModal()">&times;</button>
            </div>
            <form id="podcastForm" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="podcastId" value="">

                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label for="title" class="form-label required">Podcast Title</label>
                        <input type="text" class="form-control" id="title" name="title"
                            placeholder="Enter podcast title" required maxlength="200">
                        <div class="invalid-feedback"></div>
                        <small class="form-text">Max 200 characters</small>
                    </div>

                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label for="feed_url" class="form-label required">RSS Feed URL</label>
                        <input type="url" class="form-control" id="feed_url" name="feed_url"
                            placeholder="https://example.com/podcast.xml" required>
                        <div class="invalid-feedback"></div>
                        <small class="form-text">Valid RSS feed URL required</small>
                    </div>

                    <div class="form-group" style="margin-bottom: var(--spacing-md);">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Enter podcast description (optional)" maxlength="500"></textarea>
                        <div class="invalid-feedback"></div>
                        <small class="form-text">Max 500 characters (optional)</small>
                    </div>

                    <div class="form-group" style="margin-bottom: var(--spacing-sm);">
                        <label for="cover_image" class="form-label">Cover Image</label>
                        <div class="file-input-wrapper">
                            <input type="file" class="file-input" id="cover_image" name="cover_image"
                                accept="image/jpeg,image/png,image/gif">
                            <label for="cover_image" class="file-input-label" id="fileLabel" style="padding: var(--spacing-md);">
                                <span>📁</span>
                                <span>Select image</span>
                            </label>
                        </div>
                        <div class="invalid-feedback"></div>
                        <small class="form-text">1400-2400px square, max 2MB (JPG/PNG/GIF)</small>
                        <div class="file-input-preview" id="imagePreview" style="display: none; margin-top: var(--spacing-sm);">
                            <img id="previewImage" style="max-width: 80px; max-height: 80px; border-radius: var(--border-radius);" alt="Cover preview">
                            <div id="imageInfo" style="font-size: var(--font-size-xs); color: var(--text-muted);"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span id="submitIcon">➕</span>
                        <span id="submitText">Add Podcast</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h3 class="modal-title">Change Podcast Status</h3>
                <button type="button" class="modal-close" onclick="hideStatusModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: var(--spacing-md);">Change the status of:</p>
                <p style="margin-bottom: var(--spacing-lg);"><strong id="statusPodcastTitle"></strong></p>
                <div class="status-options">
                    <button type="button" class="status-option status-option-active" onclick="changeStatus('active')">
                        <span class="status-icon">✓</span>
                        <div class="status-content">
                            <div class="status-label">Active</div>
                            <div class="status-description">Visible in RSS feed</div>
                        </div>
                    </button>
                    <button type="button" class="status-option status-option-inactive" onclick="changeStatus('inactive')">
                        <span class="status-icon">✕</span>
                        <div class="status-content">
                            <div class="status-label">Inactive</div>
                            <div class="status-description">Hidden from RSS feed</div>
                        </div>
                    </button>
                </div>
                <form method="POST" id="statusForm" style="display: none;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" id="statusId" value="">
                    <input type="hidden" name="status" id="statusValue" value="">
                </form>
            </div>
        </div>
    </div>

    <!-- Feed Viewer Modal -->
    <div class="modal-overlay" id="feedModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title" id="feedModalTitle">RSS Feed</h3>
                <button type="button" class="modal-close" onclick="hideFeedModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="feed-viewer-container">
                    <div class="feed-url-display">
                        <input type="text" id="feedUrlInput" readonly class="feed-url-input">
                        <button type="button" class="btn btn-primary" onclick="copyFeedUrl()" title="Copy URL">
                            📋 Copy URL
                        </button>
                    </div>
                    <div class="feed-content-wrapper">
                        <pre id="feedContent" class="feed-content">Loading feed...</pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideFeedModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Stats Modal -->
    <div class="modal-overlay" id="statsModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">📊 Directory Statistics</h3>
                <button type="button" class="modal-close" onclick="hideStatsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="stats-modal-content">
                    <!-- Overview Section -->
                    <div class="stats-section">
                        <h4 class="stats-section-title">Overview</h4>
                        <div class="stats-cards-grid">
                            <div class="stats-modal-card">
                                <div class="stats-modal-card-icon">📊</div>
                                <div class="stats-modal-card-content">
                                    <div class="stats-modal-card-value"><?php echo $stats['total_podcasts']; ?></div>
                                    <div class="stats-modal-card-label">Total Podcasts</div>
                                </div>
                            </div>
                            <div class="stats-modal-card stats-success">
                                <div class="stats-modal-card-icon">✅</div>
                                <div class="stats-modal-card-content">
                                    <div class="stats-modal-card-value"><?php echo $stats['active_podcasts']; ?></div>
                                    <div class="stats-modal-card-label">Active</div>
                                </div>
                            </div>
                            <div class="stats-modal-card stats-warning">
                                <div class="stats-modal-card-icon">⏸️</div>
                                <div class="stats-modal-card-content">
                                    <div class="stats-modal-card-value"><?php echo $stats['total_podcasts'] - $stats['active_podcasts']; ?></div>
                                    <div class="stats-modal-card-label">Inactive</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Section -->
                    <div class="stats-section">
                        <h4 class="stats-section-title">Storage</h4>
                        <div class="stats-details-grid">
                            <div class="stats-detail-item">
                                <div class="stats-detail-icon">💾</div>
                                <div class="stats-detail-content">
                                    <div class="stats-detail-label">Cover Images</div>
                                    <div class="stats-detail-value"><?php echo $stats['storage_stats']['file_count']; ?> files</div>
                                </div>
                            </div>
                            <div class="stats-detail-item">
                                <div class="stats-detail-icon">📏</div>
                                <div class="stats-detail-content">
                                    <div class="stats-detail-label">Total Storage</div>
                                    <div class="stats-detail-value"><?php echo $stats['storage_stats']['total_size_formatted']; ?></div>
                                </div>
                            </div>
                            <div class="stats-detail-item">
                                <div class="stats-detail-icon">📁</div>
                                <div class="stats-detail-content">
                                    <div class="stats-detail-label">Average File Size</div>
                                    <div class="stats-detail-value">
                                        <?php 
                                        if ($stats['storage_stats']['file_count'] > 0) {
                                            $avgSize = $stats['storage_stats']['total_size'] / $stats['storage_stats']['file_count'];
                                            echo $avgSize >= 1024 ? round($avgSize / 1024, 2) . ' KB' : round($avgSize) . ' B';
                                        } else {
                                            echo '0 B';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Section -->
                    <div class="stats-section">
                        <h4 class="stats-section-title">Activity</h4>
                        <div class="stats-activity-list">
                            <?php 
                            $recentPodcasts = array_slice($podcasts, 0, 3);
                            if (!empty($recentPodcasts)): 
                            ?>
                                <?php foreach ($recentPodcasts as $podcast): ?>
                                    <div class="stats-activity-item">
                                        <div class="stats-activity-icon">
                                            <?php if ($podcast['cover_image'] && $podcast['image_info']): ?>
                                                <img src="<?php echo htmlspecialchars($podcast['image_info']['url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($podcast['title']); ?>"
                                                     class="stats-activity-thumb">
                                            <?php else: ?>
                                                <div class="stats-activity-thumb-placeholder">🎧</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="stats-activity-content">
                                            <div class="stats-activity-title"><?php echo htmlspecialchars($podcast['title']); ?></div>
                                            <div class="stats-activity-meta">
                                                <span class="badge badge-<?php echo $podcast['status'] === 'active' ? 'success' : 'danger'; ?> badge-sm">
                                                    <?php echo $podcast['status'] === 'active' ? '✓ Active' : '✕ Inactive'; ?>
                                                </span>
                                                <span class="stats-activity-date">
                                                    Added <?php echo date('M j, Y', strtotime($podcast['created_date'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="stats-empty-state">
                                    <div class="stats-empty-icon">🎧</div>
                                    <p>No podcasts yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideStatsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal modal-sm">
            <div class="modal-header" style="background-color: rgba(218, 54, 51, 0.1); border-bottom-color: rgba(218, 54, 51, 0.2);">
                <h3 class="modal-title" style="color: var(--accent-danger);">⚠️ Confirm Delete</h3>
                <button type="button" class="modal-close" onclick="hideDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="delete-warning">
                    <div class="delete-icon">🗑️</div>
                    <p style="margin-bottom: var(--spacing-md); font-size: var(--font-size-md);">Are you sure you want to delete this podcast?</p>
                    <div class="delete-podcast-info">
                        <strong id="deletePodcastTitle"></strong>
                    </div>
                    <div class="delete-consequences">
                        <div class="consequence-item">
                            <span class="consequence-icon">⚠️</span>
                            <span>This action cannot be undone</span>
                        </div>
                        <div class="consequence-item">
                            <span class="consequence-icon">🖼️</span>
                            <span>Cover image will be permanently deleted</span>
                        </div>
                        <div class="consequence-item">
                            <span class="consequence-icon">📊</span>
                            <span>All podcast data will be removed</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="gap: var(--spacing-sm);">
                <button type="button" class="btn btn-secondary btn-lg" onclick="hideDeleteModal()" style="flex: 1;">
                    Cancel
                </button>
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId" value="">
                    <button type="submit" class="btn btn-danger btn-lg" style="width: 100%;">
                        🗑️ Delete Forever
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="assets/js/validation.js"></script>
    <script src="assets/js/app.js"></script>
</body>

</html>