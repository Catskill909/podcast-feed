<?php
/**
 * Self-Hosted Podcast Management
 * Create and manage podcasts hosted within this system
 */

session_start();

require_once __DIR__ . '/includes/SelfHostedPodcastManager.php';
require_once __DIR__ . '/config/config.php';

$manager = new SelfHostedPodcastManager();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_podcast':
            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'author' => $_POST['author'] ?? '',
                'email' => $_POST['email'] ?? '',
                'website_url' => $_POST['website_url'] ?? '',
                'category' => $_POST['category'] ?? '',
                'subcategory' => $_POST['subcategory'] ?? '',
                'language' => $_POST['language'] ?? 'en-us',
                'explicit' => $_POST['explicit'] ?? 'no',
                'copyright' => $_POST['copyright'] ?? '',
                'podcast_type' => $_POST['podcast_type'] ?? 'episodic',
                'owner_name' => $_POST['owner_name'] ?? $_POST['author'] ?? '',
                'owner_email' => $_POST['owner_email'] ?? $_POST['email'] ?? '',
                'subtitle' => $_POST['subtitle'] ?? '',
                'keywords' => $_POST['keywords'] ?? ''
            ];
            $result = $manager->createPodcast($data, $_FILES['cover_image'] ?? null);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;

        case 'delete_podcast':
            $id = $_POST['id'] ?? '';
            $result = $manager->deletePodcast($id);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
    }

    // Store message and redirect (PRG pattern)
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $messageType;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve flash message
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Get all self-hosted podcasts
$podcasts = $manager->getAllPodcasts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Podcasts - <?php echo APP_NAME; ?> - <?php echo date('H:i:s'); ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo ASSETS_VERSION; ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?php echo ASSETS_VERSION; ?>">
    
    <style>

        .podcast-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(600px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .podcast-card {
            background: #2d2d2d;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #404040;
            display: flex;
            flex-direction: row;
        }

        .podcast-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
            border-color: #4CAF50;
        }

        .podcast-card-image {
            width: 200px;
            height: 200px;
            background: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .podcast-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .podcast-card-image .placeholder {
            font-size: 4rem;
            color: #4CAF50;
        }

        .podcast-card-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .podcast-card-title {
            font-family: 'Oswald', sans-serif;
            font-size: 1.4rem;
            margin: 0 0 12px 0;
            color: #e0e0e0;
        }

        .podcast-card-meta {
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            color: #9e9e9e;
            margin-bottom: 15px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .podcast-card-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .podcast-card-meta-item i {
            color: #757575;
            width: 16px;
        }

        .podcast-card-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .podcast-card-actions .btn {
            flex: 1;
            padding: 10px 12px;
            font-size: 0.85rem;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #2d2d2d;
            border-radius: 8px;
            border: 2px dashed #404040;
            margin-top: 30px;
        }

        .empty-state i {
            font-size: 3rem;
            color: #4CAF50;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state h2 {
            font-family: 'Oswald', sans-serif;
            font-size: 1.5rem;
            color: #e0e0e0;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-family: 'Inter', sans-serif;
            color: #9e9e9e;
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }

        .badge-warning {
            background: rgba(255, 152, 0, 0.2);
            color: #FF9800;
        }

        .feed-url-box {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #333;
        }

        .feed-url-box label {
            display: block;
            font-size: 0.85rem;
            color: #9e9e9e;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .feed-url-input-group {
            display: flex;
            gap: 10px;
        }

        .feed-url-input-group input {
            flex: 1;
            background: #2d2d2d;
            border: 1px solid #404040;
            color: #e0e0e0;
            padding: 10px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .feed-url-input-group .btn {
            white-space: nowrap;
        }

        /* Modern Form Styling */
        .modern-form-modal {
            max-width: 95vw !important;
            width: 1200px !important;
        }

        .modern-form .form-section {
            margin-bottom: 35px;
        }

        .modern-form .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 12px;
            border-bottom: 2px solid #333;
        }

        .modern-form .section-header i {
            color: #4CAF50;
            font-size: 1.3rem;
        }

        .modern-form .section-header h3 {
            margin: 0;
            font-family: 'Oswald', sans-serif;
            font-size: 1.3rem;
            color: #e0e0e0;
            font-weight: 500;
        }

        .modern-form .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .modern-form .form-group {
            display: flex;
            flex-direction: column;
        }

        .modern-form .form-group.full-width {
            grid-column: 1 / -1;
        }

        .modern-form label {
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            color: #e0e0e0;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .modern-form label .required {
            color: #f44336;
        }

        .modern-form label .optional {
            color: #9e9e9e;
            font-weight: 400;
            font-size: 0.85rem;
        }

        .modern-form .form-input,
        .modern-form .form-select {
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            padding: 12px 16px;
            background: #1a1a1a;
            border: 2px solid #404040;
            border-radius: 8px;
            color: #e0e0e0;
            transition: all 0.3s ease;
        }

        .modern-form .form-input:focus,
        .modern-form .form-select:focus {
            outline: none;
            border-color: #4CAF50;
            background: #222;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .modern-form .form-input::placeholder {
            color: #666;
        }

        .modern-form textarea.form-input {
            resize: vertical;
            min-height: 100px;
            line-height: 1.6;
        }

        .modern-form .form-hint {
            font-size: 0.8rem;
            color: #9e9e9e;
            margin-top: 6px;
            display: block;
        }

        /* Beautiful File Upload */
        .file-upload-wrapper {
            position: relative;
        }

        .file-upload-wrapper .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 2;
        }

        .file-upload-display {
            background: #1a1a1a;
            border: 2px dashed #404040;
            border-radius: 12px;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-display:hover {
            border-color: #4CAF50;
            background: #222;
        }

        .file-upload-icon {
            font-size: 3rem;
            color: #4CAF50;
            opacity: 0.7;
        }

        .file-upload-text {
            flex: 1;
        }

        .file-upload-text .file-name {
            display: block;
            font-size: 1rem;
            color: #e0e0e0;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .file-upload-text .file-requirements {
            display: block;
            font-size: 0.8rem;
            color: #9e9e9e;
        }

        .image-preview {
            margin-top: 15px;
            display: none;
        }

        .image-preview.active {
            display: block;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #404040;
        }

        /* Select Dropdown Styling */
        .modern-form .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234CAF50' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
            cursor: pointer;
        }

        .modern-form .form-select option {
            background: #1a1a1a;
            color: #e0e0e0;
            padding: 10px;
        }

        /* Modal Footer */
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 25px;
            margin-top: 30px;
            border-top: 2px solid #333;
        }

        .modal-footer .btn {
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modern-form .form-grid {
                grid-template-columns: 1fr;
            }

            .file-upload-display {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div style="max-width: 100%; padding: 0 40px;">
        <!-- Compact Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0; border-bottom: 1px solid #30363d; margin-bottom: 30px;">
            <div style="display: flex; align-items: center; gap: 30px;">
                <a href="admin.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Admin
                </a>
                <div>
                    <h1 style="margin: 0; font-size: 1.5rem; color: #e0e0e0; font-family: 'Oswald', sans-serif;">
                        <i class="fas fa-broadcast-tower" style="color: #4CAF50; margin-right: 10px;"></i>
                        My Podcasts
                    </h1>
                    <div style="font-size: 0.85rem; color: #9e9e9e; margin-top: 2px;">Create and manage your podcast feeds</div>
                </div>
            </div>
            <div style="display: flex; gap: 12px;">
                <button class="btn btn-primary" onclick="toggleCreateForm()">
                    <i class="fas fa-plus"></i> <span id="toggleButtonText">Create New Podcast</span>
                </button>
                <button class="btn btn-secondary" onclick="showCloneModal()">
                    <i class="fas fa-clone"></i> Clone from RSS
                </button>
            </div>
        </div>

        <!-- Flash Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" id="flashMessage">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Create Podcast Form (Collapsible) -->
        <div id="createPodcastForm" style="display: none; margin-bottom: 40px;">
            <div style="background: #2d2d2d; padding: 30px; border-radius: 8px; border: 1px solid #404040;">
                <h2 style="margin: 0 0 30px 0; font-size: 1.5rem; color: #e0e0e0; font-family: 'Oswald', sans-serif;">
                    <i class="fas fa-plus-circle" style="color: #4CAF50;"></i> Create New Podcast
                </h2>
                
                <form method="POST" enctype="multipart/form-data" class="modern-form">
                    <input type="hidden" name="action" value="create_podcast">
                    
                    <!-- Form content will go here -->
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Podcast Title <span style="color: #f44336;">*</span></label>
                            <input type="text" name="title" required placeholder="My Awesome Podcast" class="form-input" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Description <span style="color: #f44336;">*</span></label>
                            <textarea name="description" required placeholder="Tell listeners what your podcast is about..." rows="4" class="form-input" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem; resize: vertical;"></textarea>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Author Name <span style="color: #f44336;">*</span></label>
                            <input type="text" name="author" required placeholder="John Doe" class="form-input" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Email <span style="color: #f44336;">*</span></label>
                            <input type="email" name="email" required placeholder="john@example.com" class="form-input" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                            <small style="display: block; margin-top: 6px; color: #9e9e9e; font-size: 0.8rem;">Required for iTunes compliance</small>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Website URL <span style="color: #9e9e9e; font-weight: 400;">(optional)</span></label>
                            <input type="url" name="website_url" placeholder="https://example.com" class="form-input" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Cover Image <span style="color: #f44336;">*</span></label>
                            <input type="file" name="cover_image" accept="image/*" required style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0;">
                            <small style="display: block; margin-top: 6px; color: #9e9e9e; font-size: 0.8rem;">1400x1400 to 3000x3000 pixels • Max 2MB • JPG or PNG</small>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Category <span style="color: #f44336;">*</span></label>
                            <select name="category" required style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="">Select Category</option>
                                <option value="Arts">🎨 Arts</option>
                                <option value="Business">💼 Business</option>
                                <option value="Comedy">😂 Comedy</option>
                                <option value="Education">📚 Education</option>
                                <option value="Technology">💻 Technology</option>
                                <option value="News">📰 News</option>
                                <option value="Sports">⚽ Sports</option>
                                <option value="Music">🎵 Music</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Language</label>
                            <select name="language" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="en-us">🇺🇸 English (US)</option>
                                <option value="es">🇪🇸 Spanish</option>
                                <option value="fr">🇫🇷 French</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Explicit Content</label>
                            <select name="explicit" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                                <option value="clean">Clean</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Podcast Type</label>
                            <select name="podcast_type" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="episodic">Episodic</option>
                                <option value="serial">Serial</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 30px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="toggleCreateForm()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Create Podcast
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Podcasts Grid -->
        <?php if (empty($podcasts)): ?>
            <div class="empty-state">
                <i class="fas fa-podcast"></i>
                <h2>No Podcasts Yet</h2>
                <p>Click "Create New Podcast" above to get started</p>
            </div>
        <?php else: ?>
            <div class="podcast-grid">
                <?php foreach ($podcasts as $podcast): ?>
                    <div class="podcast-card">
                        <div class="podcast-card-image">
                            <?php if ($podcast['cover_image']): ?>
                                <img src="<?php echo htmlspecialchars(COVERS_URL . '/' . $podcast['cover_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($podcast['title']); ?>">
                            <?php else: ?>
                                <div class="placeholder">
                                    <i class="fas fa-podcast"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="podcast-card-content">
                            <div>
                                <h3 class="podcast-card-title"><?php echo htmlspecialchars($podcast['title']); ?></h3>
                                
                                <div class="podcast-card-meta">
                                    <div class="podcast-card-meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($podcast['author']); ?></span>
                                    </div>
                                    <div class="podcast-card-meta-item">
                                        <i class="fas fa-list"></i>
                                        <span><?php echo $podcast['episode_count']; ?> episodes</span>
                                    </div>
                                    <div class="podcast-card-meta-item">
                                        <i class="fas fa-folder"></i>
                                        <span><?php echo htmlspecialchars($podcast['category'] ?: 'Uncategorized'); ?></span>
                                    </div>
                                    <div class="podcast-card-meta-item">
                                        <i class="fas fa-globe"></i>
                                        <span><?php echo strtoupper($podcast['language'] ?? 'EN-US'); ?></span>
                                    </div>
                                    <div class="podcast-card-meta-item">
                                        <i class="fas fa-stream"></i>
                                        <span><?php echo ucfirst($podcast['podcast_type'] ?? 'Episodic'); ?></span>
                                    </div>
                                    <div class="podcast-card-meta-item">
                                        <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                                        <span style="color: #4CAF50; font-weight: 500;">Active</span>
                                    </div>
                                </div>
                            </div>

                            <div class="podcast-card-actions">
                                <a href="self-hosted-episodes.php?podcast_id=<?php echo $podcast['id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-headphones"></i> Episodes
                                </a>
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="viewFeed('<?php echo $podcast['id']; ?>')">
                                    <i class="fas fa-rss"></i> View Feed
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deletePodcast('<?php echo $podcast['id']; ?>', '<?php echo htmlspecialchars(addslashes($podcast['title'])); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Clone Feed Modal -->
    <div id="cloneFeedModal" class="modal-overlay">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-clone"></i> Clone Podcast from RSS Feed</h3>
                <button type="button" class="modal-close" onclick="hideCloneModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Error Display -->
                <div id="cloneError" class="alert alert-danger" style="display: none;">
                    <div class="alert-icon">❌</div>
                    <div id="cloneErrorMessage"></div>
                </div>

                <!-- Loading Display -->
                <div id="cloneLoading" style="display: none; text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #4CAF50;"></i>
                    <p id="cloneLoadingMessage" style="margin-top: 10px; color: #9e9e9e;">Loading...</p>
                </div>

                <!-- Step 1: Enter Feed URL -->
                <div id="cloneStep1">
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; color: #e0e0e0; font-weight: 500;">
                            RSS Feed URL <span style="color: #f44336;">*</span>
                        </label>
                        <input type="text" id="cloneFeedUrlInput" 
                               style="width: 100%; padding: 12px; background: #2d2d2d; border: 1px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 14px;"
                               placeholder="https://example.com/podcast/feed.xml"
                               onkeypress="if(event.key==='Enter') validateCloneFeed()">
                        <small style="display: block; margin-top: 8px; color: #9e9e9e; font-size: 13px;">
                            Enter the RSS feed URL of the podcast you want to clone
                        </small>
                    </div>
                </div>

                <!-- Step 2: Preview & Options -->
                <div id="cloneStep2" style="display: none;">
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 15px 0; color: #4CAF50;">📊 Feed Preview</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <strong style="color: #9e9e9e;">Podcast:</strong>
                                <div id="clonePodcastTitle" style="color: #e0e0e0;">-</div>
                            </div>
                            <div>
                                <strong style="color: #9e9e9e;">Episodes:</strong>
                                <div id="cloneEpisodeCount" style="color: #e0e0e0;">-</div>
                            </div>
                            <div>
                                <strong style="color: #9e9e9e;">Total Size:</strong>
                                <div id="cloneTotalSize" style="color: #e0e0e0;">-</div>
                            </div>
                            <div>
                                <strong style="color: #9e9e9e;">Avg Episode:</strong>
                                <div id="cloneAvgSize" style="color: #e0e0e0;">-</div>
                            </div>
                        </div>
                    </div>

                    <div id="cloneWarnings" class="alert alert-warning" style="display: none; margin-bottom: 20px;"></div>

                    <div style="background: #2d2d2d; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 15px 0; color: #e0e0e0;">⚙️ Options</h4>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" id="cloneDownloadImages" checked>
                                <span>Download episode images</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" id="cloneImportToDirectory">
                                <span>Import to main directory after cloning</span>
                            </label>
                            <div>
                                <label>Limit episodes (optional):</label>
                                <input type="number" id="cloneLimitEpisodes" class="form-input" 
                                       placeholder="Leave empty for all episodes" min="1" 
                                       style="margin-top: 8px;">
                                <small style="display: block; margin-top: 6px; color: #9e9e9e;">
                                    Clone only the last N episodes (useful for testing)
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <div class="alert-icon">⚠️</div>
                        <div>
                            <strong>Warning:</strong> This will download ALL episodes to your server. 
                            Make sure you have enough storage space. This process may take several minutes.
                        </div>
                    </div>
                </div>

                <!-- Step 3: Progress -->
                <div id="cloneStep3" style="display: none;">
                    <div style="background: #1a1a1a; padding: 40px; border-radius: 8px; text-align: center;">
                        <!-- Spinner and Message -->
                        <div id="cloneCurrentAction" style="margin-bottom: 30px;">
                            <!-- Will be populated by JavaScript -->
                        </div>

                        <!-- Simple message -->
                        <div style="color: #9e9e9e; font-size: 0.95rem; line-height: 1.6;">
                            <p style="margin: 0 0 10px 0;">
                                <i class="fas fa-info-circle" style="color: #4CAF50; margin-right: 8px;"></i>
                                Downloading and processing all episodes
                            </p>
                            <p style="margin: 0;">
                                <i class="fas fa-clock" style="color: #4CAF50; margin-right: 8px;"></i>
                                This process will complete automatically
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Complete -->
                <div id="cloneStep4" style="display: none;">
                    <div style="text-align: center; padding: 30px;">
                        <div style="font-size: 4rem; color: #4CAF50; margin-bottom: 20px;">✅</div>
                        <h3 style="color: #e0e0e0; margin-bottom: 10px;">Cloning Complete!</h3>
                        <p id="cloneCompleteTitle" style="color: #9e9e9e; margin-bottom: 25px;">
                            Successfully cloned podcast
                        </p>
                        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                <div>
                                    <div style="color: #9e9e9e; font-size: 0.9rem;">Episodes Cloned:</div>
                                    <div id="cloneCompleteEpisodes" style="color: #4CAF50; font-size: 1.5rem; font-weight: 600;">0</div>
                                </div>
                                <div id="cloneFailedInfo" style="display: none;">
                                    <div style="color: #9e9e9e; font-size: 0.9rem;">Episodes Failed:</div>
                                    <div id="cloneCompleteFailed" style="color: #f44336; font-size: 1.5rem; font-weight: 600;">0</div>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 12px; justify-content: center;">
                            <button id="cloneViewPodcastBtn" class="btn btn-primary">
                                <i class="fas fa-podcast"></i> View Podcast
                            </button>
                            <button id="cloneManageEpisodesBtn" class="btn btn-secondary">
                                <i class="fas fa-headphones"></i> Manage Episodes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="cloneModalFooter">
                <button type="button" id="cloneValidateButton" class="btn btn-primary btn-lg" onclick="validateCloneFeed()">
                    <i class="fas fa-check"></i> Validate Feed
                </button>
                <button type="button" id="cloneStartButton" class="btn btn-primary btn-lg" onclick="startCloning()" style="display: none;">
                    <i class="fas fa-play"></i> Start Cloning
                </button>
                <button type="button" id="cloneBackButton" class="btn btn-secondary btn-lg" onclick="resetCloneModal()" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" id="cloneCancelButton" class="btn btn-secondary btn-lg" onclick="hideCloneModal()">
                    Cancel
                </button>
                <button type="button" id="cloneCloseButton" class="btn btn-primary btn-lg" onclick="closeAndReload()" style="display: none;">
                    <i class="fas fa-check"></i> Close
                </button>
            </div>
        </div>
    </div>

    <style>
        /* Clone Phase Styles */
        .clone-phase {
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }
        .clone-phase.active {
            opacity: 1;
        }
        .clone-phase.complete {
            opacity: 1;
        }
        .clone-phase.complete .phase-icon {
            background: #4CAF50;
        }
        .phase-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #e0e0e0;
        }
        .phase-label {
            color: #e0e0e0;
            font-size: 0.9rem;
        }
    </style>

    <script src="assets/js/feed-cloner.js?v=<?php echo ASSETS_VERSION; ?>"></script>
    <script>
        // Auto-hide flash message
        setTimeout(() => {
            const flash = document.getElementById('flashMessage');
            if (flash) {
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 300);
            }
        }, 5000);

        function toggleCreateForm() {
            const form = document.getElementById('createPodcastForm');
            const button = document.getElementById('toggleButtonText');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                button.textContent = 'Hide Form';
                // Scroll to form
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                form.style.display = 'none';
                button.textContent = 'Create New Podcast';
            }
        }

        function copyFeedUrl(podcastId) {
            const input = document.getElementById('feed-url-' + podcastId);
            input.select();
            document.execCommand('copy');
            
            // Show feedback
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            btn.classList.add('btn-success');
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('btn-success');
            }, 2000);
        }

        function viewFeed(podcastId) {
            const url = '<?php echo APP_URL; ?>/self-hosted-feed.php?id=' + podcastId;
            window.open(url, '_blank');
        }

        function deletePodcast(id, title) {
            if (confirm('Are you sure you want to delete "' + title + '"? This will also delete all episodes and cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_podcast">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('createPodcastModal');
            if (event.target === modal) {
                closeCreateModal();
            }
        }
    </script>
</body>
</html>
