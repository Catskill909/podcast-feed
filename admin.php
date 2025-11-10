<?php

/**
 * Podcast Directory Manager - Main Interface
 * CRUD interface for managing podcast entries
 */

// Start session for flash messages
session_start();

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
                'description' => $_POST['description'] ?? '',
                'rss_image_url' => $_POST['rss_image_url'] ?? '',
                'latest_episode_date' => $_POST['latest_episode_date'] ?? '',
                'episode_count' => $_POST['episode_count'] ?? '0'
            ];
            $result = $podcastManager->createPodcast($data, $_FILES['cover_image'] ?? null);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            
            // CRITICAL: Auto-refresh feed metadata after successful import
            // This populates latest_episode_date immediately instead of waiting for cron
            if ($result['success'] && !empty($result['id'])) {
                require_once __DIR__ . '/includes/RssFeedParser.php';
                try {
                    $parser = new RssFeedParser();
                    $podcast = $podcastManager->getPodcast($result['id']);
                    if ($podcast && !empty($podcast['feed_url'])) {
                        $feedResult = $parser->fetchFeedMetadata($podcast['feed_url']);
                        if ($feedResult['success']) {
                            $podcastManager->updatePodcastMetadata($result['id'], [
                                'latest_episode_date' => $feedResult['latest_episode_date'] ?? '',
                                'episode_count' => $feedResult['episode_count'] ?? '0'
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    // Log error but don't fail the import
                    error_log('Auto-refresh after import failed: ' . $e->getMessage());
                }
            }
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

    // Store message in session and redirect to prevent form resubmission (PRG pattern)
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $messageType;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve flash message from session
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'];
    // Clear flash message after displaying
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Get all podcasts
$podcasts = $podcastManager->getAllPodcasts(true);
$stats = $podcastManager->getStats();

// Get specific podcast for editing
$editPodcast = null;
if (isset($_GET['edit'])) {
    $editPodcast = $podcastManager->getPodcast($_GET['edit']);
}

/**
 * Escape string for use in JavaScript (onclick attributes, etc.)
 * Handles quotes, apostrophes, backslashes, and newlines
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
    <title>PodFeed Admin</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/sort-controls.css">
    <link rel="stylesheet" href="assets/css/player-modal.css">
    <link rel="stylesheet" href="assets/css/analytics.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎧</text></svg>">
    
    <!-- Simple Password Protection - ALWAYS ACTIVE -->
    <script src="auth.js"></script>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="admin.php" class="logo">
                    <i class="fa-solid fa-screwdriver-wrench logo-icon"></i>
                    <span>Admin Panel</span>
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="index.php"><i class="fa-solid fa-house"></i> Public Site</a></li>
                        <li><a href="ads-manager.php"><i class="fa-solid fa-ad"></i> Ads</a></li>
                        <li><a href="menu-manager.php"><i class="fa-solid fa-bars"></i> Menu</a></li>
                        <li><a href="javascript:void(0)" onclick="showFeedModal()">View Feed</a></li>
                        <li><a href="javascript:void(0)" onclick="showStats()">Stats</a></li>
                        <li><a href="javascript:void(0)" onclick="logout()" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a></li>
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
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div style="display: flex; gap: var(--spacing-md);">
                        <button type="button" class="btn btn-primary" onclick="showAddModal()">
                            <i class="fa-solid fa-plus"></i> Add New Podcast
                        </button>
                        <a href="javascript:void(0)" class="btn btn-secondary" onclick="showImportRssModal()">
                            <i class="fa-solid fa-rss"></i> Import from RSS
                        </a>
                        <a href="self-hosted-podcasts.php" class="btn btn-secondary">
                            <i class="fa-solid fa-broadcast-tower"></i> My Podcasts
                        </a>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="showHelpModal()">
                        <i class="fa-solid fa-circle-question"></i> Help
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

                <!-- Sort Controls -->
                <?php if (!empty($podcasts)): ?>
                <div style="padding: 0 var(--spacing-xl) var(--spacing-md) var(--spacing-xl); display: flex; justify-content: space-between; align-items: center; gap: var(--spacing-md);">
                    <div class="sort-controls">
                        <button type="button" id="sortButton" class="sort-button" aria-haspopup="true" aria-expanded="false">
                            <span style="display: flex; align-items: center; gap: var(--spacing-sm);">
                                <i class="fa-solid fa-arrow-down-wide-short"></i>
                                <span id="currentSortLabel">Newest Episodes</span>
                            </span>
                            <i class="fa-solid fa-chevron-down sort-chevron"></i>
                        </button>
                        <div id="sortDropdown" class="sort-dropdown" role="menu">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Auto-Sync Status -->
                    <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                        <div class="tooltip" data-tooltip="Feeds automatically update every 30 minutes" style="display: flex; align-items: center; gap: var(--spacing-sm); font-size: var(--font-size-sm);">
                            <i class="fa-solid fa-rotate" style="color: #238636;"></i>
                            <span id="autoScanStatus" style="color: var(--text-muted);">
                                <?php
                                $lastScanFile = __DIR__ . '/data/last-scan.txt';
                                if (file_exists($lastScanFile)) {
                                    $lastScan = file_get_contents($lastScanFile);
                                    $lastScanTime = strtotime($lastScan);
                                    $timeAgo = time() - $lastScanTime;
                                    
                                    if ($timeAgo < 60) {
                                        echo 'Auto-scan: Just now';
                                    } elseif ($timeAgo < 3600) {
                                        $mins = floor($timeAgo / 60);
                                        echo 'Auto-scan: ' . $mins . ' min' . ($mins != 1 ? 's' : '') . ' ago';
                                    } else {
                                        echo 'Auto-scan: ' . date('g:i A', $lastScanTime);
                                    }
                                } else {
                                    echo 'Auto-scan: Active (every 30 min)';
                                }
                                ?>
                            </span>
                        </div>
                        
                        <!-- Sort Sync Indicator -->
                        <div class="tooltip" data-tooltip="Sort preferences sync automatically across browsers" style="display: flex; align-items: center; gap: var(--spacing-sm); font-size: var(--font-size-sm);">
                            <i class="fa-solid fa-arrows-rotate" style="color: #238636;"></i>
                            <span style="color: var(--text-muted);">
                                Auto-sync: Active
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

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
                                    <th>Latest Episode</th>
                                    <th>Episodes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($podcasts as $podcast): ?>
                                    <tr data-podcast-id="<?php echo htmlspecialchars($podcast['id']); ?>" 
                                        data-description="<?php echo htmlspecialchars($podcast['description'] ?? ''); ?>"
                                        data-latest-episode="<?php echo htmlspecialchars($podcast['latest_episode_date'] ?? ''); ?>"
                                        data-episode-count="<?php echo htmlspecialchars($podcast['episode_count'] ?? '0'); ?>"
                                        data-feed-url="<?php echo htmlspecialchars($podcast['feed_url']); ?>">
                                        <td>
                                            <?php if ($podcast['cover_image'] && $podcast['image_info']): ?>
                                                <div class="podcast-cover-wrapper">
                                                    <img src="<?php echo htmlspecialchars($podcast['image_info']['url']); ?>"
                                                        alt="<?php echo htmlspecialchars($podcast['title']); ?>"
                                                        class="podcast-cover podcast-cover-clickable"
                                                        onclick="showPlayerModal('<?php echo htmlspecialchars($podcast['id']); ?>')">
                                                    <div class="play-icon-overlay">
                                                        <i class="fa-solid fa-play"></i>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="podcast-cover-placeholder podcast-cover-clickable" 
                                                    onclick="showPlayerModal('<?php echo htmlspecialchars($podcast['id']); ?>')">No Image</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                                                <strong class="podcast-title-clickable" 
                                                    onclick="showPlayerModal('<?php echo htmlspecialchars($podcast['id']); ?>')"
                                                    title="Click to play"><?php echo htmlspecialchars($podcast['title']); ?></strong>
                                                <?php if (!empty($podcast['is_self_hosted'])): ?>
                                                    <span class="badge badge-info" style="font-size: 0.7rem; white-space: nowrap;">
                                                        <i class="fas fa-server"></i> Hosted
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($podcast['is_cloned']) && $podcast['is_cloned'] === 'yes'): ?>
                                                    <span class="badge badge-info" style="font-size: 0.7rem; white-space: nowrap;">
                                                        <i class="fas fa-clone"></i> Cloned
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                class="btn btn-outline btn-sm"
                                                onclick="showPodcastFeedModal('<?php echo escapeJs($podcast['feed_url']); ?>', '<?php echo escapeJs($podcast['title']); ?>'); return false;"
                                                title="<?php echo htmlspecialchars($podcast['feed_url']); ?>">
                                                <i class="fa-solid fa-rss"></i> View Feed
                                            </button>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                class="badge badge-<?php echo $podcast['status'] === 'active' ? 'success' : 'danger'; ?> badge-clickable tooltip"
                                                data-tooltip="Change Status"
                                                onclick="showStatusModal('<?php echo escapeJs($podcast['id']); ?>', '<?php echo escapeJs($podcast['title']); ?>', '<?php echo $podcast['status']; ?>')">
                                                <?php echo $podcast['status'] === 'active' ? '✓ Active' : '✕ Inactive'; ?>
                                            </button>
                                        </td>
                                        <td class="text-muted latest-episode-cell">
                                            <?php 
                                            // Fallback: Show formatted date server-side, JavaScript will update it
                                            if (!empty($podcast['latest_episode_date'])) {
                                                try {
                                                    $epDate = new DateTime($podcast['latest_episode_date']);
                                                    echo '<span class="server-date">' . $epDate->format('M j, Y') . '</span>';
                                                } catch (Exception $e) {
                                                    echo '<span style="color: var(--text-muted); font-style: italic;">Unknown</span>';
                                                }
                                            } else {
                                                echo '<span style="color: var(--text-muted); font-style: italic;">Unknown</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                class="btn btn-outline btn-sm"
                                                onclick="showPlayerModal('<?php echo htmlspecialchars($podcast['id']); ?>')">
                                                <?php echo $podcast['episode_count'] ?? 0; ?>
                                            </button>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <button type="button" class="btn btn-outline btn-sm tooltip"
                                                    data-tooltip="View Details"
                                                    onclick="showPodcastPreview('<?php echo htmlspecialchars($podcast['id']); ?>')">
                                                    <i class="fa-solid fa-circle-info"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-sm tooltip"
                                                    data-tooltip="Refresh Feed Data"
                                                    onclick="refreshFeedMetadata('<?php echo htmlspecialchars($podcast['id']); ?>')">
                                                    <i class="fa-solid fa-rotate"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-sm tooltip"
                                                    data-tooltip="Check Health"
                                                    onclick="checkPodcastHealth('<?php echo escapeJs($podcast['id']); ?>', '<?php echo escapeJs($podcast['title']); ?>')">
                                                    <i class="fa-solid fa-heart-pulse"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline btn-sm tooltip"
                                                    data-tooltip="Edit Podcast"
                                                    onclick="editPodcast('<?php echo htmlspecialchars($podcast['id']); ?>')">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm tooltip"
                                                    data-tooltip="Delete Podcast"
                                                    onclick="deletePodcast('<?php echo escapeJs($podcast['id']); ?>', '<?php echo escapeJs($podcast['title']); ?>')">
                                                    <i class="fa-solid fa-trash"></i>
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
                        <small class="form-text">1400-3000px square, max 2MB (JPG/PNG/GIF)</small>
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
        <div class="modal modal-xl">
            <div class="modal-header">
                <h3 class="modal-title">📊 Directory Statistics</h3>
                <button type="button" class="modal-close" onclick="hideStatsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="stats-modal-content">
                    <!-- Overview Section -->
                    <div class="stats-section">
                        <h4 class="stats-section-title">Directory Overview</h4>
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

                    <!-- Analytics Section -->
                    <div class="analytics-section">
                        <h4 class="analytics-section-title">
                            <i class="fa-solid fa-chart-line"></i>
                            Engagement Analytics
                        </h4>

                        <!-- Analytics Controls -->
                        <div class="analytics-controls">
                            <!-- Time Range Selector -->
                            <div class="analytics-time-range">
                                <button class="analytics-time-range-btn active" data-range="7d">7 Days</button>
                                <button class="analytics-time-range-btn" data-range="30d">30 Days</button>
                                <button class="analytics-time-range-btn" data-range="90d">90 Days</button>
                                <button class="analytics-time-range-btn" data-range="all">All Time</button>
                            </div>

                            <!-- Podcast Filter -->
                            <div class="analytics-podcast-filter">
                                <label class="analytics-podcast-filter-label" for="analyticsPodcastFilter">
                                    <i class="fa-solid fa-filter"></i> Filter by Podcast:
                                </label>
                                <select id="analyticsPodcastFilter" class="analytics-podcast-select">
                                    <option value="">All Podcasts</option>
                                    <!-- Populated by JavaScript -->
                                </select>
                            </div>
                        </div>

                        <!-- Analytics Content (populated by JavaScript) -->
                        <div id="analyticsContent">
                            <div class="analytics-loading">
                                <div class="spinner"></div>
                                <p>Loading analytics...</p>
                            </div>
                        </div>
                    </div>
                </div>
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

    <!-- Import RSS Modal -->
    <div class="modal-overlay" id="importRssModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-rss"></i> Import Podcast from RSS Feed</h3>
                <button type="button" class="modal-close" onclick="hideImportRssModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Enter Feed URL -->
                <div id="rssImportStep1">
                    <div class="form-group">
                        <label class="form-label required">RSS Feed URL</label>
                        <input 
                            type="url" 
                            id="rssFeedUrlInput" 
                            class="form-control" 
                            placeholder="https://example.com/feed.xml"
                            required
                        />
                        <small class="form-text">Enter the RSS feed URL of the podcast you want to import</small>
                    </div>
                    
                    <div id="rssImportError" class="alert alert-danger" style="display: none; margin-top: var(--spacing-md);">
                        <div class="alert-icon">❌</div>
                        <div id="rssImportErrorMessage"></div>
                    </div>
                    
                    <!-- Validation Results Panel (NEW) -->
                    <div id="rssValidationPanel" style="display: none; margin-top: var(--spacing-md);"></div>
                    
                    <div id="rssImportLoading" style="display: none; text-align: center; padding: var(--spacing-xl);">
                        <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">⏳</div>
                        <p id="rssLoadingMessage" style="color: var(--text-secondary);">Validating feed...</p>
                    </div>
                </div>
                
                <!-- Step 2: Preview and Confirm -->
                <div id="rssImportStep2" style="display: none;">
                    <div class="alert alert-info" style="margin-bottom: var(--spacing-lg);">
                        <div class="alert-icon">ℹ️</div>
                        <div>Preview the extracted data below. You can edit any field before importing.</div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 200px 1fr; gap: var(--spacing-xl); margin-bottom: var(--spacing-xl);">
                        <!-- Cover Image Preview -->
                        <div>
                            <label class="form-label">Cover Image</label>
                            <div id="rssPreviewImageContainer" style="
                                width: 200px; 
                                height: 200px; 
                                border: 1px solid var(--border-primary); 
                                border-radius: var(--border-radius);
                                overflow: hidden;
                                background: var(--bg-tertiary);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">
                                <img id="rssPreviewImage" src="" alt="Cover" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                <span id="rssNoImage" style="color: var(--text-muted); font-size: var(--font-size-sm);">No image</span>
                            </div>
                            <small class="form-text" id="rssImageInfo" style="display: none; margin-top: var(--spacing-sm);"></small>
                        </div>
                        
                        <!-- Feed Info -->
                        <div>
                            <div class="form-group">
                                <label class="form-label">Feed Type</label>
                                <input type="text" id="rssFeedType" class="form-control" readonly style="background: var(--bg-tertiary);">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Episodes Found</label>
                                <input type="text" id="rssEpisodeCount" class="form-control" readonly style="background: var(--bg-tertiary);">
                            </div>
                        </div>
                    </div>
                    
                    <form id="rssImportForm" method="POST">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" id="rssImageUrl" name="rss_image_url" value="">
                        
                        <div class="form-group">
                            <label class="form-label required">Podcast Title</label>
                            <input type="text" id="rssTitle" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Feed URL</label>
                            <input type="url" id="rssFeedUrl" name="feed_url" class="form-control" required readonly style="background: var(--bg-tertiary);">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea id="rssDescription" name="description" class="form-control" rows="4"></textarea>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-lg" onclick="hideImportRssModal()">
                    Cancel
                </button>
                <button type="button" id="rssFetchButton" class="btn btn-primary btn-lg" onclick="fetchRssFeedData()">
                    <i class="fa-solid fa-download"></i> Fetch Feed
                </button>
                <button type="button" id="rssImportButton" class="btn btn-primary btn-lg" onclick="importRssFeed()" style="display: none;">
                    <i class="fa-solid fa-check"></i> Import Podcast
                </button>
                <button type="button" id="rssBackButton" class="btn btn-secondary btn-lg" onclick="resetRssImportModal()" style="display: none;">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>
            </div>
        </div>
    </div>

    <!-- Health Check Modal -->
    <div class="modal-overlay" id="healthCheckModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-heart-pulse"></i> <span id="healthCheckTitle">Podcast Health Check</span></h3>
                <button type="button" class="modal-close" onclick="hideHealthCheckModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Loading State -->
                <div id="healthCheckLoading" style="display: none; text-align: center; padding: var(--spacing-xl);">
                    <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">🔍</div>
                    <p style="color: var(--text-secondary);">Running health checks...</p>
                </div>
                
                <!-- Results -->
                <div id="healthCheckResults" style="display: none;">
                    <!-- Overall Status -->
                    <div id="healthOverallStatus" class="alert" style="margin-bottom: var(--spacing-xl);">
                        <div class="alert-icon" id="healthOverallIcon"></div>
                        <div>
                            <strong id="healthOverallMessage"></strong>
                            <p id="healthOverallDetails" style="margin: var(--spacing-xs) 0 0 0; font-size: var(--font-size-sm);"></p>
                        </div>
                    </div>
                    
                    <!-- Individual Checks -->
                    <div class="health-checks-grid">
                        <!-- Feed URL Check -->
                        <div class="health-check-card" id="healthCheckFeedUrl">
                            <div class="health-check-header">
                                <span class="health-check-icon">🌐</span>
                                <h4>Feed URL</h4>
                                <span class="health-check-status-badge" id="feedUrlBadge"></span>
                            </div>
                            <div class="health-check-body">
                                <p id="feedUrlMessage"></p>
                                <div class="health-check-details" id="feedUrlDetails"></div>
                            </div>
                        </div>
                        
                        <!-- RSS Structure Check -->
                        <div class="health-check-card" id="healthCheckRss">
                            <div class="health-check-header">
                                <span class="health-check-icon">📄</span>
                                <h4>RSS 2.0 Structure</h4>
                                <span class="health-check-status-badge" id="rssStructureBadge"></span>
                            </div>
                            <div class="health-check-body">
                                <p id="rssStructureMessage"></p>
                                <div class="health-check-details" id="rssStructureDetails"></div>
                            </div>
                        </div>
                        
                        <!-- iTunes Namespace Check -->
                        <div class="health-check-card" id="healthCheckItunes">
                            <div class="health-check-header">
                                <span class="health-check-icon">🍎</span>
                                <h4>iTunes Namespace</h4>
                                <span class="health-check-status-badge" id="itunesNamespaceBadge"></span>
                            </div>
                            <div class="health-check-body">
                                <p id="itunesNamespaceMessage"></p>
                                <div class="health-check-details" id="itunesNamespaceDetails"></div>
                            </div>
                        </div>
                        
                        <!-- Cover Image Check -->
                        <div class="health-check-card" id="healthCheckImage">
                            <div class="health-check-header">
                                <span class="health-check-icon">🖼️</span>
                                <h4>Cover Image</h4>
                                <span class="health-check-status-badge" id="coverImageBadge"></span>
                            </div>
                            <div class="health-check-body">
                                <p id="coverImageMessage"></p>
                                <div class="health-check-details" id="coverImageDetails"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: var(--spacing-xl); padding: var(--spacing-md); background: var(--bg-tertiary); border-radius: var(--border-radius); font-size: var(--font-size-sm); color: var(--text-muted);">
                        <strong>Checked:</strong> <span id="healthCheckTimestamp"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-lg" onclick="hideHealthCheckModal()">
                    Close
                </button>
                <button type="button" id="healthCheckAgainButton" class="btn btn-primary btn-lg" onclick="recheckPodcastHealth()" style="display: none;">
                    <i class="fa-solid fa-rotate-right"></i> Check Again
                </button>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal-overlay" id="helpModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-circle-question"></i> PodFeed Builder - Help & Guide</h3>
                <button type="button" class="modal-close" onclick="hideHelpModal()">&times;</button>
            </div>
            <div class="modal-body">
                
                <!-- Quick Start -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🚀</span>
                        Quick Start
                    </h3>
                    <div class="help-section-content">
                        <p>Welcome to PodFeed Builder! Choose your workflow:</p>
                        <ul>
                            <li><strong>Quick Import:</strong> Have an existing RSS feed? Use "Import from RSS" to auto-extract all details in seconds.</li>
                            <li><strong>Manual Entry:</strong> Add podcasts from scratch with custom details using "Add New Podcast".</li>
                        </ul>
                    </div>
                </div>

                <!-- Main Actions -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🎬</span>
                        Main Actions
                    </h3>
                    <div class="help-section-content">
                        <div class="help-button-example">
                            <button type="button" class="btn btn-primary" disabled>
                                <i class="fa-solid fa-plus"></i> Add New Podcast
                            </button>
                            <p class="help-button-description">
                                Manually add a podcast with custom details. Required: Title, Feed URL. Optional: Cover image, Description.
                            </p>
                        </div>
                        
                        <div class="help-button-example">
                            <button type="button" class="btn btn-info" disabled>
                                <i class="fa-solid fa-circle-question"></i> Help
                            </button>
                            <p class="help-button-description">
                                View this help guide anytime. Access all documentation and feature explanations.
                            </p>
                        </div>
                        
                        <div class="help-button-example">
                            <button type="button" class="btn btn-secondary" disabled>
                                <i class="fa-solid fa-rss"></i> Import from RSS
                            </button>
                            <p class="help-button-description">
                                Auto-import from any RSS feed URL. Supports RSS 2.0, Atom, and iTunes formats. Automatically downloads cover images.
                            </p>
                        </div>
                        
                        <div class="help-button-example">
                            <button type="button" class="btn btn-secondary" disabled>
                                <i class="fa-solid fa-broadcast-tower"></i> My Podcasts
                            </button>
                            <p class="help-button-description">
                                Create and host your own podcasts! Upload audio files, manage episodes, and generate iTunes-compliant RSS feeds. Perfect for hosting your own shows.
                            </p>
                        </div>
                        
                        <div class="help-button-example">
                            <button type="button" class="btn btn-secondary" disabled>
                                <i class="fa-solid fa-ad"></i> Ads Manager
                            </button>
                            <p class="help-button-description">
                                Upload and manage banner advertisements. Display rotating banners on your homepage and serve ads to mobile apps via RSS feed. Full monetization system with URL tracking.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Live Streaming Player Modal -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">📻</span>
                        Live Streaming Player Modal
                    </h3>
                    <div class="help-section-content">
                        <p>Launch your live radio stream in a dedicated modal without sending visitors away from the directory.</p>
                        <ul>
                            <li>Add a standard menu item in <strong>Menu Manager</strong> that points to <code>streaming-audio-player.html</code> (relative or full URL).</li>
                            <li>When users click the link on the public site, the streaming player loads inside a polished overlay with keyboard and screen-reader support.</li>
                            <li>Closing the modal (X button, Escape key, or overlay click) automatically pauses playback so audio never continues in the background.</li>
                            <li>The same player page (<code>/streaming-audio-player.html</code>) can be shared directly or embedded elsewhere if you need an external landing page.</li>
                        </ul>
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>💡 Tip:</strong> Use Menu Manager icons (e.g., <code>fa-tower-broadcast</code>) and custom labels like “Live Audio” or “Listen Live” to match your station branding.
                        </div>
                    </div>
                </div>

                <!-- My Podcasts (Self-Hosted) -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🎙️</span>
                        My Podcasts - Host Your Own Shows
                    </h3>
                    <div class="help-section-content">
                        <p><strong>What is "My Podcasts"?</strong></p>
                        <p>Create and host complete podcasts directly on your server. Upload audio files, manage episodes, and generate professional RSS feeds for distribution to Apple Podcasts, Spotify, and other platforms.</p>
                        
                        <p><strong>How to Create a Podcast:</strong></p>
                        <ol>
                            <li>Click <strong>"My Podcasts"</strong> button in the header</li>
                            <li>Click <strong>"Create New Podcast"</strong></li>
                            <li>Fill in podcast details:
                                <ul>
                                    <li>Basic info (title, description, author, email)</li>
                                    <li>Cover image (1400-3000px square)</li>
                                    <li>iTunes metadata (category, language, explicit flag)</li>
                                </ul>
                            </li>
                            <li>Click <strong>"Create Podcast"</strong></li>
                        </ol>
                        
                        <p><strong>How to Add Episodes:</strong></p>
                        <ol>
                            <li>From your podcast card, click <strong>"Episodes"</strong></li>
                            <li>Click <strong>"Add New Episode"</strong></li>
                            <li>Upload MP3 audio file (up to 500MB)</li>
                            <li>Add episode details (title, description, duration)</li>
                            <li>Optional: Upload episode artwork</li>
                            <li>Set publication date and status</li>
                            <li>Click <strong>"Add Episode"</strong></li>
                        </ol>
                        
                        <p><strong>Your RSS Feed:</strong></p>
                        <ul>
                            <li>Each podcast gets its own RSS feed URL</li>
                            <li>iTunes-compliant (RSS 2.0 + iTunes namespace)</li>
                            <li>Submit to Apple Podcasts, Spotify, etc.</li>
                            <li>Optionally import into your main directory</li>
                        </ul>
                        
                        <p><strong>Features:</strong></p>
                        <ul>
                            <li><strong>Audio Hosting:</strong> Upload and host MP3 files on your server</li>
                            <li><strong>Episode Management:</strong> Add, edit, delete episodes with full control</li>
                            <li><strong>Cover Images:</strong> Podcast and episode artwork support</li>
                            <li><strong>iTunes Compliance:</strong> Professional RSS feeds ready for distribution</li>
                            <li><strong>Persistent Storage:</strong> All files stored safely in persistent volumes</li>
                        </ul>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>💡 Pro Tip:</strong> After creating your podcast, use "Import from RSS" to add it to your main directory for easy browsing alongside your other podcasts!
                        </div>
                    </div>
                </div>

                <!-- Podcast Cloning Feature -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🔄</span>
                        Clone from RSS - Download & Host Podcasts
                    </h3>
                    <div class="help-section-content">
                        <p><strong>What is Podcast Cloning?</strong></p>
                        <p>Unlike RSS import (which links to external feeds), cloning downloads and hosts entire podcasts on your server. All audio files are stored locally, giving you full control.</p>
                        
                        <p><strong>How to Clone a Podcast:</strong></p>
                        <ol>
                            <li>Go to <strong>"My Podcasts"</strong> page</li>
                            <li>Click <strong>"Clone from RSS"</strong> button</li>
                            <li>Enter the RSS feed URL</li>
                            <li>Click <strong>"Validate Feed"</strong> to preview episode count and storage needs</li>
                            <li>Click <strong>"Start Cloning"</strong> and wait (don't close window)</li>
                            <li>Time estimates: 5-10 episodes (1-2 min), 20-50 episodes (3-5 min), 100+ episodes (10-30 min)</li>
                        </ol>
                        
                        <p><strong>What Gets Cloned:</strong></p>
                        <ul>
                            <li>Podcast metadata (title, description, author, category)</li>
                            <li>Podcast cover image</li>
                            <li>All episode audio files (up to 500MB each)</li>
                            <li>Episode metadata (titles, descriptions, dates, durations)</li>
                            <li>Episode images (optional)</li>
                            <li>iTunes tags (episode numbers, seasons, explicit flags)</li>
                        </ul>
                        
                        <p><strong>Technical Details:</strong></p>
                        <ul>
                            <li>Supported formats: MP3 and M4A (works with Anchor.fm/Spotify podcasts)</li>
                            <li>Max file size: 500MB per episode</li>
                            <li>Timeout: 10 minutes per file</li>
                            <li>Error recovery: Continues if some episodes fail</li>
                            <li>Storage: Files saved to <code>uploads/audio/[podcast_id]/</code></li>
                            <li>RSS: Generates iTunes-compliant RSS 2.0 feed</li>
                        </ul>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>⚠️ Important:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li>Ensure adequate disk space before cloning</li>
                                <li>Don't close browser window during cloning</li>
                                <li>Only clone content you have rights to host</li>
                                <li>Avoid multiple simultaneous clones (resource-intensive)</li>
                            </ul>
                        </div>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>💡 Common Uses:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li>Archive podcasts before they're removed</li>
                                <li>Create backups of your own shows</li>
                                <li>Migrate content between hosting platforms</li>
                                <li>Build offline podcast libraries</li>
                                <li>Preserve educational content</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Banner Ads Manager -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">📢</span>
                        Banner Ads Manager - Monetize Your Platform
                    </h3>
                    <div class="help-section-content">
                        <p><strong>What is the Ads Manager?</strong></p>
                        <p>Upload and manage banner advertisements for your web app and mobile feed. Display rotating banners on your homepage and serve ads to mobile apps via RSS feed.</p>
                        
                        <p><strong>How to Access:</strong></p>
                        <ul>
                            <li>Click <strong>"Ads Manager"</strong> button in the header</li>
                            <li>Or visit <code>/ads-manager.php</code> directly</li>
                        </ul>
                        
                        <p><strong>Three Ad Types:</strong></p>
                        <ul>
                            <li><strong>Web Banners (728x90px)</strong> - Display on homepage with rotation</li>
                            <li><strong>Phone Banners (320x50px)</strong> - Small mobile banners for apps</li>
                            <li><strong>Tablet Banners (728x90px)</strong> - Larger mobile banners for tablets</li>
                        </ul>
                        
                        <p><strong>How to Upload Banners:</strong></p>
                        <ol>
                            <li>Go to Ads Manager page</li>
                            <li>Choose section (Web, Phone, or Tablet)</li>
                            <li>Drag image to upload zone or click to browse</li>
                            <li>System validates exact dimensions automatically</li>
                            <li>Banner appears in grid and live preview</li>
                        </ol>
                        
                        <p><strong>Add Destination URLs:</strong></p>
                        <ol>
                            <li>Click <strong>"Add URL"</strong> button on any banner card</li>
                            <li>Enter destination URL in modal (e.g., https://example.com/landing-page)</li>
                            <li>Click <strong>"Save URL"</strong></li>
                            <li>Banner becomes clickable, opens in new tab</li>
                            <li>Button changes to <strong>"Edit URL"</strong> for future updates</li>
                        </ol>
                        
                        <p><strong>Manage Banners:</strong></p>
                        <ul>
                            <li><strong>Delete</strong> - Click X button, confirm deletion</li>
                            <li><strong>Section Toggle</strong> - Enable/disable entire web or mobile ad sections</li>
                            <li><strong>Individual Toggle</strong> - Enable/disable each ad individually (toggle on date row)</li>
                            <li><strong>Adjust Timing</strong> - Set rotation duration (5-60s) and fade duration (0.5-3s)</li>
                        </ul>
                        
                        <p><strong>Per-Ad Enable/Disable:</strong></p>
                        <ul>
                            <li>Each banner has its own toggle switch (bottom right, on date row)</li>
                            <li>Toggle ON (green) = Ad appears in preview and feeds</li>
                            <li>Toggle OFF (gray) = Ad is hidden, shown at 50% opacity in manager</li>
                            <li>Changes are instant - preview updates in real-time</li>
                            <li>Only enabled ads appear in mobile RSS feed</li>
                        </ul>
                        
                        <p><strong>Live Preview:</strong></p>
                        <ul>
                            <li>See exactly how banners will rotate on your site</li>
                            <li>Preview updates in real-time as you adjust settings</li>
                            <li>Click banners to test destination URLs</li>
                            <li>Single ad detection (no rotation for 0-1 ads)</li>
                        </ul>
                        
                        <p><strong>Front-End Display:</strong></p>
                        <ul>
                            <li>Banner appears under header on public homepage (index.php)</li>
                            <li>Rotates automatically based on your settings</li>
                            <li>Clickable if URL is set</li>
                            <li>Only shows when enabled in Ads Manager</li>
                            <li>Responsive design (scales on mobile)</li>
                        </ul>
                        
                        <p><strong>Mobile RSS Feed:</strong></p>
                        <ul>
                            <li>URL: <code>https://your-domain.com/mobile-ads-feed.php</code></li>
                            <li>Copy URL from Ads Manager interface</li>
                            <li>Use in mobile/tablet apps for ad consumption</li>
                            <li>Includes dimensions, URLs, and display order</li>
                            <li>Standard RSS 2.0 format with custom ads namespace</li>
                        </ul>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>📏 Image Requirements:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li><strong>Web:</strong> Exactly 728x90 pixels</li>
                                <li><strong>Phone:</strong> Exactly 320x50 pixels</li>
                                <li><strong>Tablet:</strong> Exactly 728x90 pixels</li>
                                <li><strong>Formats:</strong> PNG, JPG, or GIF</li>
                                <li><strong>Validation:</strong> System rejects wrong dimensions with clear error message</li>
                            </ul>
                        </div>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>💡 Best Practices:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li>Use high-quality images with clear call-to-action</li>
                                <li>Test URLs before adding to ensure they work</li>
                                <li>Keep rotation duration reasonable (10-15 seconds recommended)</li>
                                <li>Use 3-5 banners for good variety without overwhelming</li>
                                <li>Monitor click-through rates to optimize performance</li>
                                <li>Update banners regularly to keep content fresh</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Custom Menu Manager -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🎨</span>
                        Custom Menu Manager - Customize Your Site
                    </h3>
                    <div class="help-section-content">
                        <p><strong>What is the Menu Manager?</strong></p>
                        <p>Customize your site branding and navigation menu. Change the site title, logo, and add/remove menu items with full control over appearance and behavior.</p>
                        
                        <p><strong>How to Access:</strong></p>
                        <ul>
                            <li>Click <strong>"Menu"</strong> button in the header</li>
                            <li>Or visit <code>/menu-manager.php</code> directly</li>
                        </ul>
                        
                        <p><strong>Customize Site Branding:</strong></p>
                        <ol>
                            <li>Enter your site title (e.g., "My Podcast Network")</li>
                            <li>Choose logo type:
                                <ul>
                                    <li><strong>Font Awesome Icon</strong> - Enter class like <code>fa-microphone</code> or <code>fa-headphones</code></li>
                                    <li><strong>Custom Image</strong> - Upload PNG/JPG/SVG (max 2MB, recommended 64x64px)</li>
                                </ul>
                            </li>
                            <li>Preview changes in real-time</li>
                            <li>Click <strong>"Save Branding"</strong></li>
                        </ol>
                        
                        <p><strong>Manage Menu Items:</strong></p>
                        <ul>
                            <li><strong>Add Item</strong> - Click "+ Add Menu Item"
                                <ul>
                                    <li>Enter label (e.g., "About Us")</li>
                                    <li>Enter URL (relative like <code>/about.php</code> or full URL)</li>
                                    <li>Choose icon (none, Font Awesome, or custom image)</li>
                                    <li>Set link behavior (same window or new tab)</li>
                                </ul>
                            </li>
                            <li><strong>Reorder</strong> - Drag items by grip handle (⋮⋮) to reorder</li>
                            <li><strong>Toggle</strong> - Enable/disable items without deleting (toggle switch)</li>
                            <li><strong>Edit</strong> - Click edit icon to modify item</li>
                            <li><strong>Delete</strong> - Click trash icon to remove (with confirmation)</li>
                        </ul>
                        
                        <p><strong>Features:</strong></p>
                        <ul>
                            <li>Changes appear instantly on public site</li>
                            <li>Active page automatically highlighted in menu</li>
                            <li>Disabled items hidden from public (50% opacity in admin)</li>
                            <li>Supports relative URLs, absolute URLs, and anchor links</li>
                            <li>Menu order saves automatically on drag</li>
                            <li>Falls back to default menu if system fails</li>
                        </ul>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>💡 Pro Tips:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li>Use Font Awesome icons for consistent look (browse icons at fontawesome.com)</li>
                                <li>Keep menu items to 5-7 for best UX</li>
                                <li>Use relative URLs for internal pages (faster, no domain changes needed)</li>
                                <li>Test menu on mobile devices after making changes</li>
                                <li>Disable items instead of deleting to easily re-enable later</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Engagement Analytics -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">📊</span>
                        Engagement Analytics - Track Your Audience
                    </h3>
                    <div class="help-section-content">
                        <p><strong>What is Engagement Analytics?</strong></p>
                        <p>Monitor how your audience interacts with your podcasts. Track plays and downloads from the public player with beautiful visualizations and detailed metrics.</p>
                        
                        <p><strong>Key Features:</strong></p>
                        <ul>
                            <li><strong>Overview Metrics:</strong> Total plays, downloads, unique listeners, and download conversion rate</li>
                            <li><strong>Interactive Charts:</strong> Daily trend visualization with Chart.js (7d, 30d, 90d, all-time views)</li>
                            <li><strong>Top Content:</strong> Top 10 episodes and podcasts ranked by engagement</li>
                            <li><strong>Podcast Filtering:</strong> Use the dropdown to view analytics for individual podcasts</li>
                            <li><strong>Privacy-Focused:</strong> No personal information collected, random session IDs only</li>
                        </ul>
                        
                        <p><strong>How It Works:</strong></p>
                        <ul>
                            <li>Analytics are tracked only from the public player on <code>index.php</code></li>
                            <li>Each episode is counted once per session (prevents duplicate tracking)</li>
                            <li>Sessions rotate every 24 hours for accurate unique listener counts</li>
                            <li>Play button, download button, and next/previous controls all trigger tracking</li>
                        </ul>
                        
                        <p><strong>Accessing Analytics:</strong></p>
                        <ol>
                            <li>Click the <strong>"Stats"</strong> button in the admin panel</li>
                            <li>Scroll down to the <strong>"Engagement Analytics"</strong> section</li>
                            <li>Select a time range (7 days, 30 days, 90 days, or all time)</li>
                            <li>Optionally filter by podcast using the dropdown</li>
                        </ol>
                        
                        <p><strong>What Gets Tracked:</strong></p>
                        <ul>
                            <li><strong>Plays:</strong> When someone clicks play on an episode (including next/previous navigation)</li>
                            <li><strong>Downloads:</strong> When someone clicks the download button</li>
                            <li><strong>Unique Listeners:</strong> Approximate count based on session IDs</li>
                        </ul>
                    </div>
                </div>

                <!-- Embed Generator -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🔗</span>
                        Embed Generator - Full-Featured Podcast Browser & Player
                    </h3>
                    <div class="help-section-content">
                        <p><strong>What is the Embed Generator?</strong></p>
                        <p>A complete, embeddable podcast browsing and playback system with a visual configuration tool. This isn't just an embed generator—it's a <strong>full-featured podcast player and browser</strong> that you can customize and embed anywhere. Features multi-podcast support, modal audio player, episode browsing, and complete playback controls.</p>

                        <p><strong>🎧 Complete Podcast Player Features:</strong></p>
                        <ul>
                            <li><strong>Multi-Podcast Browser:</strong> Browse and switch between all podcasts in your feed with custom dropdown</li>
                            <li><strong>Episode List View:</strong> Scrollable episode list with cover art, titles, descriptions, and dates</li>
                            <li><strong>Modal Audio Player:</strong> Sticky bottom player bar with full playback controls</li>
                            <li><strong>Playback Controls:</strong> Play/pause, skip ±15s/30s, progress scrubber, time display</li>
                            <li><strong>Speed Control:</strong> Adjustable playback speed (0.5x, 0.75x, 1x, 1.25x, 1.5x, 2x)</li>
                            <li><strong>Volume Control:</strong> Volume slider with mute toggle</li>
                            <li><strong>Episode Download:</strong> Direct download button for each episode</li>
                            <li><strong>Expandable Descriptions:</strong> Click to expand full episode descriptions</li>
                            <li><strong>Cover Art Display:</strong> Podcast and episode artwork throughout the interface</li>
                            <li><strong>Dark/Light Theme:</strong> Built-in theme toggle with auto-detection</li>
                            <li><strong>Keyboard Shortcuts:</strong> Space (play/pause), arrows (skip), M (mute)</li>
                            <li><strong>Responsive Design:</strong> Perfect on desktop, tablet, and mobile devices</li>
                        </ul>

                        <p><strong>How to Access the Generator:</strong></p>
                        <ul>
                            <li>Navigate to <code>/embed/iframe-generator.html</code> or <code>/embed/iframe-generator.php</code></li>
                            <li>Visual configuration interface with live, interactive preview</li>
                            <li>Test the full player functionality before generating embed code</li>
                        </ul>

                        <p><strong>🎯 What You're Embedding:</strong></p>
                        <p>The embed code creates a complete podcast browsing and listening experience with:</p>
                        <ul>
                            <li><strong>Podcast Selector:</strong> Dropdown to browse all available podcasts</li>
                            <li><strong>Episode Browser:</strong> Scrollable list of episodes with metadata</li>
                            <li><strong>Audio Player:</strong> Modal player bar that appears when playing episodes</li>
                            <li><strong>Full Controls:</strong> All playback, speed, and volume controls included</li>
                        </ul>

                        <p><strong>Configuration Options:</strong></p>
                        
                        <p><strong>1. Iframe Dimensions</strong></p>
                        <ul>
                            <li><strong>Width:</strong> Set in pixels (px) or percentage (%), range: 200-1200</li>
                            <li><strong>Height:</strong> Set in pixels (px), range: 400-1000</li>
                            <li>Dimensions update the preview and embed code in real-time</li>
                        </ul>

                        <p><strong>2. Content & Behavior</strong></p>
                        <ul>
                            <li><strong>Default Podcast:</strong> Choose which podcast loads first (or "First Available")</li>
                            <li><strong>Episode Order:</strong> Newest first, oldest first, or alphabetical</li>
                            <li><strong>Max Episodes Shown:</strong> Limit to 5, 10, 25, 50, or show all episodes</li>
                            <li><strong>Podcast Dropdown Order:</strong> 7 sorting options including:
                                <ul style="margin-left: var(--spacing-lg);">
                                    <li>Feed Order (default)</li>
                                    <li>Alphabetical A-Z or Z-A</li>
                                    <li>Most/Least Episodes First</li>
                                    <li>Latest/Oldest Content First</li>
                                </ul>
                            </li>
                        </ul>

                        <p><strong>3. Theme & UI Customization</strong></p>
                        <ul>
                            <li><strong>Default Theme:</strong> Dark, Light, or Auto (system preference)</li>
                            <li><strong>Hide Theme Toggle:</strong> Remove the theme switcher button</li>
                            <li><strong>Show Header:</strong> Display or hide the player header</li>
                            <li><strong>Show Podcast Selector:</strong> Display or hide the podcast dropdown</li>
                            <li><strong>Show Cover Art:</strong> Display or hide podcast/episode artwork</li>
                            <li><strong>Show Download Buttons:</strong> Enable or disable episode download buttons</li>
                        </ul>

                        <p><strong>Live Preview Features:</strong></p>
                        <ul>
                            <li><strong>Device Modes:</strong> Switch between Desktop, Tablet, and Mobile views</li>
                            <li><strong>Real-Time Updates:</strong> Preview updates instantly as you change settings</li>
                            <li><strong>Interactive:</strong> Test the player directly in the preview window</li>
                            <li><strong>Responsive Testing:</strong> See how your embed looks on different screen sizes</li>
                        </ul>

                        <p><strong>How to Use Generated Code:</strong></p>
                        <ol>
                            <li>Configure all settings in the three control columns</li>
                            <li>Preview your configuration in real-time</li>
                            <li>Click <strong>"Copy Embed Code"</strong> button (or the copy icon)</li>
                            <li>Paste the iframe code into your website's HTML</li>
                            <li>The player will load with your exact configuration</li>
                        </ol>

                        <p><strong>URL Parameters (Advanced):</strong></p>
                        <ul>
                            <li>The generator creates URL parameters automatically</li>
                            <li>Example: <code>?theme=dark&hideToggle=1&podcast=0&episode=5</code></li>
                            <li>Parameters allow deep linking to specific podcasts and episodes</li>
                            <li>All settings are encoded in the URL for easy sharing</li>
                        </ul>

                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>💡 Use Cases:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li><strong>Blog Posts:</strong> Embed specific episodes in articles</li>
                                <li><strong>Partner Sites:</strong> Share your full catalog with customized branding</li>
                                <li><strong>Landing Pages:</strong> Create focused players for marketing campaigns</li>
                                <li><strong>Email Newsletters:</strong> Link to pre-configured player states</li>
                                <li><strong>Social Media:</strong> Share direct links to specific episodes</li>
                            </ul>
                        </div>

                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>🎨 Customization Tips:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li>Hide the header and selector for a minimal, single-podcast player</li>
                                <li>Use "Auto" theme to match the visitor's system preferences</li>
                                <li>Limit episodes to latest 10 for faster loading on external sites</li>
                                <li>Hide download buttons if you want listening-only embeds</li>
                                <li>Test on all three device modes before deploying</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Podcast Actions -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">⚙️</span>
                        Podcast Actions
                    </h3>
                    <div class="help-section-content">
                        <p>Each podcast in your directory has these action buttons:</p>
                        
                        <div class="help-button-example">
                            <button type="button" class="btn btn-outline btn-sm" disabled>
                                <i class="fa-solid fa-heart-pulse"></i>
                            </button>
                            <p class="help-button-description">
                                <strong>Health Check:</strong> Validate feed health, RSS 2.0 structure, iTunes compatibility, and cover image availability. Results show as 🟢 Pass, 🟡 Warning, or 🔴 Fail.
                            </p>
                        </div>
                        
                        <div class="help-button-example">
                            <button type="button" class="btn btn-outline btn-sm" disabled>
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <p class="help-button-description">
                                <strong>Edit:</strong> Modify podcast details, update feed URL, upload new cover image, or change description.
                            </p>
                        </div>
                        
                        <div class="help-button-example">
                            <button type="button" class="btn btn-danger btn-sm" disabled>
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            <p class="help-button-description">
                                <strong>Delete:</strong> Permanently remove podcast and its cover image. Requires confirmation to prevent accidents.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Podcast Player -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🎧</span>
                        Podcast Player - Listen in Browser
                    </h3>
                    <div class="help-section-content">
                        <p><strong>How to Access:</strong></p>
                        <ul>
                            <li>Click on any <strong>podcast cover image</strong> in the table</li>
                            <li>Or click on the <strong>podcast title</strong></li>
                            <li>Player modal opens with full episode list</li>
                        </ul>
                        
                        <p><strong>Player Features:</strong></p>
                        <ul>
                            <li><strong>Browse Episodes</strong> - See all episodes with covers, titles, and dates</li>
                            <li><strong>Search Episodes</strong> - Find specific episodes by title</li>
                            <li><strong>Sort Episodes</strong> - Newest first or oldest first</li>
                            <li><strong>Download Episodes</strong> - Save MP3 files to your device</li>
                            <li><strong>Play in Browser</strong> - Stream episodes directly without downloading</li>
                        </ul>
                        
                        <p><strong>Playback Controls:</strong></p>
                        <ul>
                            <li><strong>Play/Pause</strong> - Spacebar or click button</li>
                            <li><strong>Skip Forward</strong> - +15 seconds (→ arrow key)</li>
                            <li><strong>Skip Backward</strong> - -15 seconds (← arrow key)</li>
                            <li><strong>Previous/Next Episode</strong> - Navigate between episodes</li>
                            <li><strong>Progress Scrubber</strong> - Click or drag to jump to any position</li>
                            <li><strong>Volume Control</strong> - Adjust volume or mute (M key)</li>
                            <li><strong>Playback Speed</strong> - 0.5x to 2.0x (great for catching up!)</li>
                        </ul>
                        
                        <p><strong>Keyboard Shortcuts:</strong></p>
                        <ul>
                            <li><kbd>Space</kbd> - Play/Pause</li>
                            <li><kbd>→</kbd> - Skip forward 15 seconds</li>
                            <li><kbd>←</kbd> - Skip backward 15 seconds</li>
                            <li><kbd>M</kbd> - Mute/Unmute</li>
                            <li><kbd>Escape</kbd> - Close player (audio stops automatically)</li>
                        </ul>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>💡 Pro Tips:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li>Playback speed resets to 1.0x when switching podcasts</li>
                                <li>Audio stops automatically when you close the modal</li>
                                <li>Episode dates show as "Today", "Yesterday", or specific date</li>
                                <li>Click episode cover for larger view</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Podcast Info Modal -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">ℹ️</span>
                        Podcast Info - Quick Preview
                    </h3>
                    <div class="help-section-content">
                        <p><strong>How to Access:</strong></p>
                        <ul>
                            <li>Click the <strong>info button (ℹ️)</strong> next to any podcast</li>
                            <li>Preview modal opens with comprehensive details</li>
                        </ul>
                        
                        <p><strong>What You'll See:</strong></p>
                        <ul>
                            <li><strong>Large Cover Image</strong> - 240×240px preview</li>
                            <li><strong>Full Description</strong> - Complete podcast description from RSS feed</li>
                            <li><strong>Episode Count</strong> - Total number of episodes</li>
                            <li><strong>Latest Episode</strong> - When the last episode was published (always fresh from feed!)</li>
                            <li><strong>Category</strong> - Podcast genre/category</li>
                            <li><strong>Author</strong> - Podcast creator/host</li>
                            <li><strong>Language</strong> - Human-readable language name</li>
                            <li><strong>Feed Type</strong> - RSS 2.0, Atom, or iTunes</li>
                            <li><strong>Added Date</strong> - When you added it to your directory</li>
                        </ul>
                        
                        <p><strong>Quick Actions:</strong></p>
                        <ul>
                            <li><strong>Edit</strong> - Opens edit modal</li>
                            <li><strong>Refresh</strong> - Updates feed metadata</li>
                            <li><strong>Health Check</strong> - Runs diagnostics</li>
                            <li><strong>Delete</strong> - Removes podcast</li>
                        </ul>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>💡 Note:</strong> Info modal always fetches fresh data from the RSS feed, so you always see the most current episode information!
                        </div>
                    </div>
                </div>

                <!-- Status Management -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">📊</span>
                        Status Management
                    </h3>
                    <div class="help-section-content">
                        <p>Click any status badge to toggle between active and inactive:</p>
                        
                        <div class="help-badge-example" style="margin: var(--spacing-md) 0;">
                            <span class="badge badge-success">✓ Active</span>
                            <span style="color: var(--text-secondary); margin-left: var(--spacing-sm);">
                                Podcast appears in RSS feed and is visible to users
                            </span>
                        </div>
                        
                        <div class="help-badge-example" style="margin: var(--spacing-md) 0;">
                            <span class="badge badge-danger">✕ Inactive</span>
                            <span style="color: var(--text-secondary); margin-left: var(--spacing-sm);">
                                Podcast hidden from RSS feed but preserved in database
                            </span>
                        </div>
                        
                        <p style="margin-top: var(--spacing-md); font-size: var(--font-size-sm); color: var(--text-muted);">
                            💡 <strong>Tip:</strong> Use inactive status instead of deleting to temporarily hide podcasts while keeping their data.
                        </p>
                    </div>
                </div>

                <!-- RSS Import Details -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">📥</span>
                        RSS Import - Step by Step
                    </h3>
                    <div class="help-section-content">
                        <ol>
                            <li>Click <strong>"Import from RSS"</strong> button</li>
                            <li>Paste your RSS feed URL (e.g., <code>https://example.com/feed.xml</code>)</li>
                            <li>Click <strong>"Fetch Feed"</strong></li>
                            <li>Review extracted data:
                                <ul>
                                    <li>Title (editable)</li>
                                    <li>Description (editable)</li>
                                    <li>Cover image (preview shown)</li>
                                    <li>Feed type (RSS 2.0, Atom, iTunes)</li>
                                    <li>Episode count</li>
                                </ul>
                            </li>
                            <li>Edit any field if needed</li>
                            <li>Click <strong>"Import Podcast"</strong></li>
                        </ol>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>Supported Formats:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li>RSS 2.0</li>
                                <li>Atom</li>
                                <li>iTunes podcast namespace</li>
                                <li>Remote images (auto-downloaded)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Health Check Details -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🏥</span>
                        Health Check - What Gets Validated
                    </h3>
                    <div class="help-section-content">
                        <p>The health check runs 4 comprehensive tests:</p>
                        
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>1. Feed URL Check</strong>
                            <ul>
                                <li>HTTP accessibility (expects 200 OK)</li>
                                <li>Response time measurement</li>
                                <li>SSL certificate validation (production only)</li>
                                <li>Timeout handling (10 seconds max)</li>
                            </ul>
                        </div>
                        
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>2. RSS 2.0 Structure</strong>
                            <ul>
                                <li>Root <code>&lt;rss&gt;</code> element with version="2.0"</li>
                                <li>Required <code>&lt;channel&gt;</code> element</li>
                                <li>Required elements: <code>&lt;title&gt;</code>, <code>&lt;link&gt;</code>, <code>&lt;description&gt;</code></li>
                                <li>Presence of <code>&lt;item&gt;</code> elements (episodes)</li>
                            </ul>
                        </div>
                        
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>3. iTunes Namespace</strong>
                            <ul>
                                <li>iTunes namespace declaration</li>
                                <li>Recommended tags: author, summary, image, category, explicit</li>
                                <li>Image href attribute validation</li>
                                <li>Explicit tag format check</li>
                            </ul>
                        </div>
                        
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>4. Cover Image</strong>
                            <ul>
                                <li>File exists and is readable (local files)</li>
                                <li>URL accessibility (remote images)</li>
                                <li>Content-Type validation</li>
                                <li>Response time measurement</li>
                            </ul>
                        </div>
                        
                        <div style="background: var(--bg-tertiary); padding: var(--spacing-md); border-radius: var(--border-radius); margin-top: var(--spacing-md);">
                            <strong>Status Meanings:</strong>
                            <ul style="margin: var(--spacing-xs) 0 0 var(--spacing-lg);">
                                <li>🟢 <strong>PASS</strong> - All checks passed successfully</li>
                                <li>🟡 <strong>WARNING</strong> - Works but has non-critical issues</li>
                                <li>🔴 <strong>FAIL</strong> - Critical problems that need fixing</li>
                                <li>⚪ <strong>SKIP</strong> - Skipped due to previous failure</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Image Requirements -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🖼️</span>
                        Image Requirements
                    </h3>
                    <div class="help-section-content">
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>Dimensions:</strong>
                            <ul>
                                <li>Minimum: 1400×1400 pixels</li>
                                <li>Maximum: 3000×3000 pixels</li>
                                <li>Must be square (1:1 aspect ratio)</li>
                            </ul>
                        </div>
                        
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>File Format:</strong>
                            <ul>
                                <li>Supported: JPG, PNG, GIF, WebP</li>
                                <li>Maximum file size: 2MB</li>
                            </ul>
                        </div>
                        
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>Upload Methods:</strong>
                            <ul>
                                <li><strong>Manual Upload:</strong> Click "Choose File" in Add/Edit modal</li>
                                <li><strong>Auto-Download:</strong> Import from RSS (automatic)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Keyboard Shortcuts -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">⌨️</span>
                        Keyboard Shortcuts
                    </h3>
                    <div class="help-section-content">
                        <ul>
                            <li><kbd>Escape</kbd> - Close any open modal</li>
                            <li><kbd>Enter</kbd> - Submit RSS URL (in RSS import modal)</li>
                            <li><strong>Click overlay</strong> - Close modal (all modals)</li>
                        </ul>
                    </div>
                </div>

                <!-- Sorting & Automation -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🔄</span>
                        Sorting & Automated Updates
                    </h3>
                    <div class="help-section-content">
                        <p><strong>Smart Sorting Options:</strong></p>
                        <ul>
                            <li><strong>Newest Episodes</strong> - Shows podcasts with the latest episodes first (perfect for finding fresh content!)</li>
                            <li><strong>Oldest Episodes</strong> - Shows podcasts that haven't updated recently</li>
                            <li><strong>A-Z / Z-A</strong> - Alphabetical sorting by podcast title</li>
                            <li><strong>Active/Inactive First</strong> - Sort by podcast status</li>
                        </ul>
                        <p><strong>How to Use:</strong></p>
                        <ul>
                            <li>Click the <strong>sort dropdown</strong> below "Podcast Directory" title</li>
                            <li>Choose your preferred sort option</li>
                            <li>Table updates instantly - your choice is saved automatically</li>
                            <li>Click <strong>"View Feed"</strong> to see RSS with your current sort applied</li>
                        </ul>
                        <p><strong>Auto-Sync Across Browsers:</strong></p>
                        <ul>
                            <li><strong>Changes sync automatically</strong> - no hard refresh needed!</li>
                            <li><strong>Works across all browsers</strong> - change sort on one machine, see it everywhere</li>
                            <li><strong>Updates every 30 seconds</strong> - checks for changes in the background</li>
                            <li><strong>Instant when switching tabs</strong> - updates immediately when you return to the app</li>
                            <li><strong>External apps stay in sync</strong> - your mobile app always gets the correct order</li>
                        </ul>
                        <p><strong>Automated Episode Updates:</strong></p>
                        <ul>
                            <li><strong>Auto-scan runs every 30 minutes</strong> - checks all podcast feeds for new episodes</li>
                            <li><strong>Latest Episode column</strong> shows when each podcast last published (Today, Yesterday, or date)</li>
                            <li><strong>Refresh button (🔄)</strong> - manually update any podcast's episode data instantly</li>
                            <li><strong>Status indicator</strong> - see "Auto-scan: X mins ago" to know when last scan ran</li>
                        </ul>
                        <p><strong>Pro Tip:</strong> Use "Newest Episodes" sort to quickly see which podcasts have fresh content!</p>
                    </div>
                </div>

                <!-- Tips & Best Practices -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">💡</span>
                        Tips & Best Practices
                    </h3>
                    <div class="help-section-content">
                        <ul>
                            <li><strong>Use RSS Import</strong> for quick setup - saves time and ensures accuracy</li>
                            <li><strong>Run Health Checks</strong> regularly to catch broken feeds early</li>
                            <li><strong>Keep images optimized</strong> - use 1400×1400 for best compatibility</li>
                            <li><strong>Use descriptive titles</strong> - helps with search and organization</li>
                            <li><strong>Check iTunes namespace</strong> - ensures Apple Podcasts compatibility</li>
                            <li><strong>Toggle inactive</strong> instead of delete - keeps history and allows reactivation</li>
                            <li><strong>Watch the Latest Episode column</strong> - green text means recent activity!</li>
                        </ul>
                    </div>
                </div>

                <!-- Troubleshooting -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🔧</span>
                        Troubleshooting
                    </h3>
                    <div class="help-section-content">
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>RSS Import Fails:</strong>
                            <ul>
                                <li>Verify feed URL is valid and publicly accessible</li>
                                <li>Try copying URL directly from browser address bar</li>
                                <li>Check your internet connection</li>
                                <li>Ensure feed uses HTTPS (or HTTP in development)</li>
                            </ul>
                        </div>
                        
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>Image Not Showing:</strong>
                            <ul>
                                <li>Verify image meets size requirements (1400-3000px)</li>
                                <li>Check file format (JPG, PNG, GIF, WebP only)</li>
                                <li>Ensure file size is under 2MB</li>
                                <li>Try re-uploading or re-importing</li>
                            </ul>
                        </div>
                        
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>Health Check Shows Warnings:</strong>
                            <ul>
                                <li>Review specific check details in the modal</li>
                                <li>Fix issues in the source RSS feed</li>
                                <li>Re-run check after making fixes</li>
                                <li>Contact feed provider if issues persist</li>
                            </ul>
                        </div>
                        
                        <div style="margin: var(--spacing-md) 0;">
                            <strong>Search Not Working:</strong>
                            <ul>
                                <li>Clear search field and try again</li>
                                <li>Check spelling</li>
                                <li>Search works on: title, feed URL, and description</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Navigation Features -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <span class="help-section-icon">🧭</span>
                        Navigation Features
                    </h3>
                    <div class="help-section-content">
                        <ul>
                            <li><strong>Stats:</strong> Click "Stats" in navigation to view directory statistics, active/inactive counts, storage usage, and recent activity</li>
                            <li><strong>View Feed:</strong> Click "View Feed" to see the generated RSS XML feed and copy the URL for app integration</li>
                            <li><strong>Search:</strong> Use the search bar at the top of the table to filter podcasts by title, feed URL, or description</li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Podcast Player Modal -->
    <div class="modal-overlay player-modal-overlay" id="playerModal">
        <div class="modal modal-xl player-modal">
            
            <!-- Modal Header -->
            <div class="player-modal-header">
                <div class="player-modal-title">
                    <span class="player-modal-icon"><i class="fa-solid fa-microphone"></i></span>
                    <h2 id="playerModalTitle">Podcast Player</h2>
                </div>
                <button class="player-modal-close" onclick="hidePlayerModal()" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body player-modal-body">
                
                <!-- Podcast Info Section -->
                <div class="player-podcast-info">
                    <div class="player-podcast-cover">
                        <img id="playerPodcastCover" src="" alt="Podcast Cover" style="display: none;">
                        <div class="player-podcast-cover-placeholder">🎧</div>
                    </div>
                    <div class="player-podcast-details">
                        <h3 id="playerPodcastName" class="player-podcast-name"></h3>
                        <div id="playerPodcastDescription" class="player-podcast-description"></div>
                        <div class="player-podcast-meta">
                            <span class="badge badge-success" id="playerStatus">Active</span>
                            <span class="badge badge-primary" id="playerEpisodeCount">0 Episodes</span>
                            <span class="badge badge-primary" id="playerLatestEpisodeBadge">Latest: <span id="playerLatestEpisode">Unknown</span></span>
                        </div>
                    </div>
                </div>

                <!-- Episodes Section -->
                <div class="player-episodes-section">
                    <div class="player-episodes-header">
                        <h4>EPISODES</h4>
                        <div class="player-episodes-controls">
                            <input type="text" id="playerEpisodeSearch" class="form-control form-control-sm" 
                                   placeholder="Search episodes...">
                            <select id="playerEpisodeSort" class="form-control form-control-sm">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="title">Title A-Z</option>
                            </select>
                        </div>
                    </div>

                    <!-- Episodes List -->
                    <div id="playerEpisodesList" class="player-episodes-list">
                        <div class="player-loading">
                            <div class="spinner"></div>
                            <p>Loading episodes...</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Audio Player (Sticky Bottom) -->
            <div id="audioPlayerBar" class="audio-player-bar" style="display: none;">
                <div class="audio-player-info">
                    <span class="audio-player-label">NOW PLAYING</span>
                    <span id="currentEpisodeTitle" class="audio-player-title"></span>
                    <button class="audio-player-close" onclick="stopPlayback()">&times;</button>
                </div>
                
                <div class="audio-player-progress">
                    <span id="currentTime" class="audio-time">0:00</span>
                    <div class="audio-progress-bar">
                        <div id="audioBuffered" class="audio-buffered"></div>
                        <div id="audioProgress" class="audio-progress"></div>
                        <input type="range" id="audioScrubber" class="audio-scrubber" 
                               min="0" max="100" value="0" step="0.1">
                    </div>
                    <span id="totalDuration" class="audio-time">0:00</span>
                </div>

                <div class="audio-player-controls">
                    <button class="audio-control-btn" onclick="previousEpisode()" title="Previous">
                        <i class="fa-solid fa-backward-step"></i>
                    </button>
                    <button class="audio-control-btn" onclick="skipBackward()" title="Skip -15s">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                    <button class="audio-control-btn audio-control-play" onclick="togglePlayback()" title="Play/Pause">
                        <span id="playPauseIcon"><i class="fa-solid fa-play"></i></span>
                    </button>
                    <button class="audio-control-btn" onclick="skipForward()" title="Skip +15s">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                    <button class="audio-control-btn" onclick="nextEpisode()" title="Next">
                        <i class="fa-solid fa-forward-step"></i>
                    </button>
                </div>

                <div class="audio-player-extras">
                    <div class="audio-volume">
                        <button onclick="toggleMute()"><span id="volumeIcon"><i class="fa-solid fa-volume-high"></i></span></button>
                        <input type="range" id="volumeSlider" min="0" max="100" value="100">
                    </div>
                    <div class="audio-speed">
                        <button onclick="cyclePlaybackSpeed()">
                            <span id="speedLabel">1x</span>
                        </button>
                    </div>
                </div>

                <!-- Hidden audio element -->
                <audio id="audioPlayer" preload="metadata"></audio>
            </div>

        </div>
    </div>

    <!-- Podcast Preview Modal (Keep for info button) -->
    <div class="modal-overlay" id="previewModal">
        <div class="modal podcast-preview-modal">
            <div class="modal-header preview-modal-header">
                <h3 class="modal-title" id="previewModalTitle">Podcast Preview</h3>
                <button type="button" class="modal-close" onclick="hidePreviewModal()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <!-- Loading State -->
                <div id="previewLoading" class="preview-loading">
                    <div class="preview-loading-spinner"></div>
                    <div class="preview-loading-text">Loading podcast details...</div>
                </div>

                <!-- Error State -->
                <div id="previewError" class="preview-error" style="display: none;">
                    <div class="preview-error-icon">⚠️</div>
                    <div class="preview-error-message">Failed to load podcast details</div>
                    <div class="preview-error-details" id="previewErrorMessage"></div>
                </div>

                <!-- Content -->
                <div id="previewContent" class="preview-content" style="display: none;">
                    <!-- Image Section -->
                    <div class="preview-image-section">
                        <img id="podcastPreviewImage" class="preview-podcast-image" alt="Podcast Cover" style="display: none;">
                        <div id="previewImagePlaceholder" class="preview-image-placeholder">🎧</div>
                        <div id="previewImageDimensions" class="preview-image-dimensions" style="display: none;"></div>
                    </div>

                    <!-- Details Section -->
                    <div class="preview-details-section">
                        <h2 id="previewTitle" class="preview-title"></h2>
                        <div id="previewDescription" class="preview-description"></div>

                        <!-- Meta Grid - 3 columns for compact layout -->
                        <div class="preview-meta-grid">
                            <div class="preview-meta-item">
                                <div class="preview-meta-label">
                                    <i class="fa-solid fa-podcast"></i>
                                    Episodes
                                </div>
                                <div id="previewEpisodeCount" class="preview-meta-value highlight">0</div>
                            </div>

                            <div class="preview-meta-item">
                                <div class="preview-meta-label">
                                    <i class="fa-solid fa-calendar"></i>
                                    Latest Episode
                                </div>
                                <div id="previewLatestEpisode" class="preview-meta-value">Unknown</div>
                            </div>

                            <div class="preview-meta-item">
                                <div class="preview-meta-label">
                                    <i class="fa-solid fa-toggle-on"></i>
                                    Status
                                </div>
                                <div id="previewStatus" class="preview-meta-value">Active</div>
                            </div>

                            <div class="preview-meta-item">
                                <div class="preview-meta-label">
                                    <i class="fa-solid fa-tag"></i>
                                    Category
                                </div>
                                <div id="previewCategory" class="preview-meta-value">Unknown</div>
                            </div>

                            <div class="preview-meta-item">
                                <div class="preview-meta-label">
                                    <i class="fa-solid fa-rss"></i>
                                    Feed Type
                                </div>
                                <div id="previewFeedType" class="preview-meta-value">RSS</div>
                            </div>

                            <div class="preview-meta-item">
                                <div class="preview-meta-label">
                                    <i class="fa-solid fa-clock"></i>
                                    Added
                                </div>
                                <div id="previewCreatedDate" class="preview-meta-value">Unknown</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/auto-refresh.js"></script>
    <script src="assets/js/validation.js?v=<?php echo ASSETS_VERSION; ?>"></script>
    <script src="assets/js/app.js?v=<?php echo ASSETS_VERSION; ?>"></script>
    <script src="assets/js/sort-manager.js?v=<?php echo ASSETS_VERSION; ?>"></script>
    <script src="assets/js/player-modal.js?v=<?php echo ASSETS_VERSION; ?>"></script>
    <script src="assets/js/audio-player.js?v=3.0.5"></script>
    
    <script>
    // Make all podcasts available to JavaScript for analytics dropdown
    // This ensures the dropdown shows ALL podcasts, not just those with analytics data
    window.ALL_PODCASTS_FOR_FILTER = <?php echo json_encode(array_map(function($p) {
        return ['id' => $p['id'], 'title' => $p['title']];
    }, $podcasts)); ?>;
    </script>
    
    <script src="assets/js/analytics-dashboard.js?v=1.0.0"></script>
</body>

</html>