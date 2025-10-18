<?php
/**
 * Self-Hosted Podcast Episode Management
 * Add and manage episodes for self-hosted podcasts
 */

session_start();

require_once __DIR__ . '/includes/SelfHostedPodcastManager.php';
require_once __DIR__ . '/config/config.php';

$manager = new SelfHostedPodcastManager();
$message = '';
$messageType = '';

// Get podcast ID
$podcastId = $_GET['podcast_id'] ?? '';

if (empty($podcastId)) {
    header('Location: self-hosted-podcasts.php');
    exit;
}

// Get podcast
$podcast = $manager->getPodcast($podcastId);

if (!$podcast) {
    $_SESSION['flash_message'] = 'Podcast not found';
    $_SESSION['flash_type'] = 'danger';
    header('Location: self-hosted-podcasts.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_episode':
            // Debug logging
            error_log("=== ADD EPISODE DEBUG ===");
            error_log("POST data: " . print_r($_POST, true));
            error_log("FILES data: " . print_r($_FILES, true));
            
            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'audio_url' => $_POST['audio_url'] ?? '',
                'duration' => $_POST['duration'] ?? '0',
                'file_size' => $_POST['file_size'] ?? '0',
                'pub_date' => $_POST['pub_date'] ?? date('Y-m-d H:i:s'),
                'episode_number' => $_POST['episode_number'] ?? '',
                'season_number' => $_POST['season_number'] ?? '',
                'episode_type' => $_POST['episode_type'] ?? 'full',
                'explicit' => $_POST['explicit'] ?? 'no',
                'status' => $_POST['status'] ?? 'published'
            ];
            
            error_log("Episode data: " . print_r($data, true));
            
            $result = $manager->addEpisode($podcastId, $data, $_FILES['episode_image'] ?? null, $_FILES['audio_file'] ?? null);
            
            error_log("Result: " . print_r($result, true));
            
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;

        case 'update_podcast':
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
                'owner_name' => $_POST['owner_name'] ?? '',
                'owner_email' => $_POST['owner_email'] ?? '',
                'subtitle' => $_POST['subtitle'] ?? '',
                'keywords' => $_POST['keywords'] ?? '',
                'complete' => $_POST['complete'] ?? 'no',
                'status' => $_POST['status'] ?? 'active'
            ];
            $result = $manager->updatePodcast($podcastId, $data, $_FILES['cover_image'] ?? null);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;

        case 'update_episode':
            $episodeId = $_POST['episode_id'] ?? '';
            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'audio_url' => $_POST['audio_url'] ?? '',
                'duration' => $_POST['duration'] ?? '0',
                'file_size' => $_POST['file_size'] ?? '0',
                'pub_date' => $_POST['pub_date'] ?? date('Y-m-d H:i:s'),
                'episode_number' => $_POST['episode_number'] ?? '',
                'season_number' => $_POST['season_number'] ?? '',
                'episode_type' => $_POST['episode_type'] ?? 'full',
                'explicit' => $_POST['explicit'] ?? 'no',
                'status' => $_POST['status'] ?? 'published'
            ];
            $result = $manager->updateEpisode($podcastId, $episodeId, $data, $_FILES['episode_image'] ?? null, $_FILES['audio_file'] ?? null);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;

        case 'delete_episode':
            $episodeId = $_POST['episode_id'] ?? '';
            $result = $manager->deleteEpisode($podcastId, $episodeId);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
    }

    // Store message and redirect
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $messageType;
    header('Location: ' . $_SERVER['PHP_SELF'] . '?podcast_id=' . $podcastId);
    exit;
}

// Retrieve flash message
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Get episodes
$episodes = $manager->getEpisodes($podcastId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Episodes - <?php echo htmlspecialchars($podcast['title']); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo ASSETS_VERSION; ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?php echo ASSETS_VERSION; ?>">
    <link rel="stylesheet" href="assets/css/upload-components.css?v=<?php echo ASSETS_VERSION; ?>">
    <link rel="stylesheet" href="assets/css/custom-audio-player.css?v=<?php echo ASSETS_VERSION; ?>">
    
    <style>
        .episode-header {
            background: linear-gradient(135deg, #2d2d2d 0%, #1a1a1a 100%);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #404040;
        }

        .episode-header h1 {
            font-family: 'Oswald', sans-serif;
            font-size: 2rem;
            margin: 0 0 10px 0;
            color: #e0e0e0;
        }

        .episode-header .podcast-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
        }

        .episode-header .podcast-cover {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
        }

        .episode-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .episode-item {
            background: #2d2d2d;
            border: 1px solid #404040;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .episode-item:hover {
            border-color: #4CAF50;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.1);
        }

        .episode-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .episode-title {
            font-family: 'Oswald', sans-serif;
            font-size: 1.3rem;
            color: #e0e0e0;
            margin: 0;
        }

        .episode-meta {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: #9e9e9e;
            margin-bottom: 10px;
        }

        .episode-meta i {
            color: #4CAF50;
            margin-right: 5px;
        }

        .episode-description {
            color: #b0b0b0;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .episode-actions {
            display: flex;
            gap: 10px;
        }

        .empty-episodes {
            text-align: center;
            padding: 40px 20px;
            background: #2d2d2d;
            border-radius: 8px;
            border: 2px dashed #404040;
        }

        .empty-episodes i {
            font-size: 3rem;
            color: #4CAF50;
            opacity: 0.5;
            margin-bottom: 15px;
        }

        /* Audio Upload Styling */
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

        .file-upload-display.audio-upload {
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

        .file-upload-display.audio-upload:hover {
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

        .audio-preview {
            margin-top: 15px;
            display: none;
            padding: 15px;
            background: #222;
            border-radius: 8px;
            border: 1px solid #404040;
        }

        .audio-preview.active {
            display: block;
        }

        .audio-preview .audio-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #e0e0e0;
        }

        .audio-preview .audio-icon {
            font-size: 2rem;
            color: #4CAF50;
        }

        .audio-preview .audio-details {
            flex: 1;
        }

        .audio-preview .audio-filename {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .audio-preview .audio-meta {
            font-size: 0.9rem;
            color: #9e9e9e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-podcast"></i>
                <span><?php echo APP_NAME; ?></span>
            </div>
            <div class="header-actions">
                <a href="self-hosted-podcasts.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Podcasts
                </a>
            </div>
        </div>

        <div class="episode-header">
            <h1 style="margin: 0 0 25px 0; font-size: 1.8rem;"><i class="fas fa-headphones"></i> EPISODES</h1>
            
            <!-- Podcast Info Card -->
            <div style="background: #1a1a1a; border-radius: 12px; padding: 25px; border: 1px solid #404040; margin-bottom: 20px;">
                <div style="display: flex; gap: 25px; margin-bottom: 20px;">
                    <!-- Podcast Cover -->
                    <?php if ($podcast['cover_image']): ?>
                        <div style="flex-shrink: 0;">
                            <img src="<?php echo htmlspecialchars(COVERS_URL . '/' . $podcast['cover_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($podcast['title']); ?>"
                                 style="width: 160px; height: 160px; object-fit: cover; border-radius: 12px; border: 3px solid #404040; box-shadow: 0 6px 16px rgba(0,0,0,0.4);">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Podcast Details -->
                    <div style="flex: 1; min-width: 0;">
                        <h2 style="margin: 0 0 10px 0; font-size: 2rem; color: #4CAF50; font-family: 'Oswald', sans-serif; line-height: 1.2;">
                            <?php echo htmlspecialchars($podcast['title']); ?>
                        </h2>
                        
                        <?php if (!empty($podcast['subtitle'])): ?>
                            <p style="margin: 0 0 15px 0; color: #b0b0b0; font-size: 1.05rem; font-style: italic;">
                                <?php echo htmlspecialchars($podcast['subtitle']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($podcast['description'])): ?>
                            <p style="margin: 0 0 20px 0; color: #9e9e9e; font-size: 0.95rem; line-height: 1.6;">
                                <?php echo htmlspecialchars($podcast['description']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <!-- Podcast Metadata Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; padding: 15px; background: #2d2d2d; border-radius: 8px; border: 1px solid #404040;">
                            <!-- Author -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-user" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.7rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Author</div>
                                    <div style="font-size: 0.85rem; color: #e0e0e0; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($podcast['author']); ?></div>
                                </div>
                            </div>
                            
                            <!-- Episodes Count -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-list" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.7rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Episodes</div>
                                    <div style="font-size: 0.85rem; color: #e0e0e0; font-weight: 500;"><?php echo count($episodes); ?> Published</div>
                                </div>
                            </div>
                            
                            <!-- Language -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-globe" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.7rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Language</div>
                                    <div style="font-size: 0.85rem; color: #e0e0e0; font-weight: 500;"><?php echo strtoupper($podcast['language'] ?? 'EN-US'); ?></div>
                                </div>
                            </div>
                            
                            <!-- Category -->
                            <?php if (!empty($podcast['category'])): ?>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-folder" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.7rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Category</div>
                                    <div style="font-size: 0.85rem; color: #e0e0e0; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($podcast['category']); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Podcast Type -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-stream" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.7rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Type</div>
                                    <div style="font-size: 0.85rem; color: #e0e0e0; font-weight: 500;"><?php echo ucfirst($podcast['podcast_type'] ?? 'Episodic'); ?></div>
                                </div>
                            </div>
                            
                            <!-- Explicit Content -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-<?php echo ($podcast['explicit'] ?? 'no') === 'yes' ? 'exclamation-triangle' : 'shield-alt'; ?>" style="color: <?php echo ($podcast['explicit'] ?? 'no') === 'yes' ? '#F44336' : '#9e9e9e'; ?>; font-size: 0.9rem;"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.7rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Content</div>
                                    <div style="font-size: 0.85rem; color: #e0e0e0; font-weight: 500;"><?php echo ucfirst($podcast['explicit'] ?? 'Clean'); ?></div>
                                </div>
                            </div>
                            
                            <!-- Status -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-<?php echo ($podcast['status'] ?? 'active') === 'active' ? 'check-circle' : 'pause-circle'; ?>" style="color: <?php echo ($podcast['status'] ?? 'active') === 'active' ? '#4CAF50' : '#9e9e9e'; ?>; font-size: 0.9rem;"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.7rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Status</div>
                                    <div style="font-size: 0.85rem; color: #e0e0e0; font-weight: 500;"><?php echo ucfirst($podcast['status'] ?? 'Active'); ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($podcast['website_url'])): ?>
                            <!-- Website -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-link" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.7rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Website</div>
                                    <a href="<?php echo htmlspecialchars($podcast['website_url']); ?>" target="_blank" style="font-size: 0.85rem; color: #4CAF50; font-weight: 500; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;">
                                        Visit Site <i class="fas fa-external-link-alt" style="font-size: 0.7rem; margin-left: 4px;"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 12px; flex-wrap: wrap; padding-top: 15px; border-top: 1px solid #404040;">
                    <button class="btn btn-primary" onclick="toggleEpisodeForm()">
                        <i class="fas fa-plus"></i> <span id="toggleEpisodeText">Add New Episode</span>
                    </button>
                    <button class="btn btn-secondary" onclick="toggleEditPodcastForm()">
                        <i class="fas fa-edit"></i> <span id="toggleEditPodcastText">Edit Podcast</span>
                    </button>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" id="flashMessage" style="margin-bottom: 20px;">
                <strong><?php echo $messageType === 'success' ? '✓ Success:' : '✗ Error:'; ?></strong>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Edit Podcast Form (Collapsible) -->
        <div id="editPodcastForm" style="display: none; margin-bottom: 30px;">
            <div style="background: #2d2d2d; padding: 30px; border-radius: 8px; border: 1px solid #404040;">
                <h2 style="margin: 0 0 25px 0; font-size: 1.3rem; color: #e0e0e0; font-family: 'Oswald', sans-serif;">
                    <i class="fas fa-edit" style="color: #4CAF50;"></i> Edit Podcast
                </h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_podcast">
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Podcast Title <span style="color: #f44336;">*</span></label>
                            <input type="text" name="title" required value="<?php echo htmlspecialchars($podcast['title']); ?>" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Description <span style="color: #f44336;">*</span></label>
                            <textarea name="description" required rows="4" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem; resize: vertical;"><?php echo htmlspecialchars($podcast['description']); ?></textarea>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Author <span style="color: #f44336;">*</span></label>
                            <input type="text" name="author" required value="<?php echo htmlspecialchars($podcast['author']); ?>" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Email <span style="color: #f44336;">*</span></label>
                            <input type="email" name="email" required value="<?php echo htmlspecialchars($podcast['email']); ?>" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Website URL</label>
                            <input type="url" name="website_url" value="<?php echo htmlspecialchars($podcast['website_url'] ?? ''); ?>" placeholder="https://example.com" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 12px; font-weight: 600; color: #e0e0e0; font-size: 1.05rem;">
                                <i class="fas fa-image" style="color: #4CAF50;"></i> Replace Cover Image <span style="color: #9e9e9e; font-weight: 400;">(optional)</span>
                            </label>
                            
                            <?php if ($podcast['cover_image']): ?>
                                <div style="margin-bottom: 15px;">
                                    <p style="color: #9e9e9e; margin-bottom: 8px; font-size: 0.9rem;">Current cover:</p>
                                    <img src="<?php echo htmlspecialchars(COVERS_URL . '/' . $podcast['cover_image']); ?>" 
                                         alt="Current cover" 
                                         style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #404040;">
                                </div>
                            <?php endif; ?>
                            
                            <input type="file" name="cover_image" accept="image/*" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                            <small style="display: block; margin-top: 6px; color: #9e9e9e; font-size: 0.8rem;">1400x1400 to 3000x3000 px • Max 2MB • JPG or PNG</small>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Category</label>
                            <input type="text" name="category" value="<?php echo htmlspecialchars($podcast['category'] ?? ''); ?>" placeholder="Technology" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Subcategory</label>
                            <input type="text" name="subcategory" value="<?php echo htmlspecialchars($podcast['subcategory'] ?? ''); ?>" placeholder="Tech News" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Language</label>
                            <input type="text" name="language" value="<?php echo htmlspecialchars($podcast['language'] ?? 'en-us'); ?>" placeholder="en-us" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Explicit Content</label>
                            <select name="explicit" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="no" <?php echo ($podcast['explicit'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
                                <option value="yes" <?php echo ($podcast['explicit'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="clean" <?php echo ($podcast['explicit'] ?? 'no') === 'clean' ? 'selected' : ''; ?>>Clean</option>
                            </select>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Copyright</label>
                            <input type="text" name="copyright" value="<?php echo htmlspecialchars($podcast['copyright'] ?? ''); ?>" placeholder="© 2025 Your Name" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Podcast Type</label>
                            <select name="podcast_type" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="episodic" <?php echo ($podcast['podcast_type'] ?? 'episodic') === 'episodic' ? 'selected' : ''; ?>>Episodic</option>
                                <option value="serial" <?php echo ($podcast['podcast_type'] ?? 'episodic') === 'serial' ? 'selected' : ''; ?>>Serial</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Status</label>
                            <select name="status" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="active" <?php echo ($podcast['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($podcast['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Subtitle</label>
                            <input type="text" name="subtitle" value="<?php echo htmlspecialchars($podcast['subtitle'] ?? ''); ?>" placeholder="A brief subtitle" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Keywords</label>
                            <input type="text" name="keywords" value="<?php echo htmlspecialchars($podcast['keywords'] ?? ''); ?>" placeholder="technology, news, interviews" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Owner Name</label>
                            <input type="text" name="owner_name" value="<?php echo htmlspecialchars($podcast['owner_name'] ?? ''); ?>" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Owner Email</label>
                            <input type="email" name="owner_email" value="<?php echo htmlspecialchars($podcast['owner_email'] ?? ''); ?>" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Complete</label>
                            <select name="complete" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="no" <?php echo ($podcast['complete'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
                                <option value="yes" <?php echo ($podcast['complete'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 25px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="toggleEditPodcastForm()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_POST['action']) && $_POST['action'] === 'add_episode'): ?>
            <div class="alert alert-info" style="margin-bottom: 20px; font-family: monospace; font-size: 0.9rem;">
                <strong style="font-size: 1.1rem;">🔍 DEBUG INFO</strong><br><br>
                <strong>Form submitted:</strong> Yes<br>
                <strong>Title:</strong> <?php echo htmlspecialchars($_POST['title'] ?? 'N/A'); ?><br>
                <strong>Description:</strong> <?php echo htmlspecialchars(substr($_POST['description'] ?? 'N/A', 0, 50)); ?>...<br>
                <strong>Audio URL field:</strong> <?php echo htmlspecialchars($_POST['audio_url'] ?? 'EMPTY'); ?><br>
                <br>
                <strong>$_FILES['audio_file']:</strong><br>
                <?php if (isset($_FILES['audio_file'])): ?>
                    - name: <?php echo htmlspecialchars($_FILES['audio_file']['name'] ?? 'N/A'); ?><br>
                    - type: <?php echo htmlspecialchars($_FILES['audio_file']['type'] ?? 'N/A'); ?><br>
                    - size: <?php echo isset($_FILES['audio_file']['size']) ? number_format($_FILES['audio_file']['size']) . ' bytes' : 'N/A'; ?><br>
                    - tmp_name: <?php echo htmlspecialchars($_FILES['audio_file']['tmp_name'] ?? 'N/A'); ?><br>
                    - error: <?php echo $_FILES['audio_file']['error'] ?? 'N/A'; ?> 
                    <?php 
                    $errorCodes = [
                        UPLOAD_ERR_OK => 'OK',
                        UPLOAD_ERR_INI_SIZE => 'File too large (php.ini)',
                        UPLOAD_ERR_FORM_SIZE => 'File too large (form)',
                        UPLOAD_ERR_PARTIAL => 'Partial upload',
                        UPLOAD_ERR_NO_FILE => 'NO FILE',
                        UPLOAD_ERR_NO_TMP_DIR => 'No temp dir',
                        UPLOAD_ERR_CANT_WRITE => 'Cannot write',
                        UPLOAD_ERR_EXTENSION => 'Extension blocked'
                    ];
                    echo '(' . ($errorCodes[$_FILES['audio_file']['error']] ?? 'Unknown') . ')';
                    ?><br>
                <?php else: ?>
                    <span style="color: #f44336;">❌ $_FILES['audio_file'] NOT SET!</span><br>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Add Episode Form (Collapsible) -->
        <div id="addEpisodeForm" style="display: none; margin-bottom: 30px;">
            <div style="background: #2d2d2d; padding: 30px; border-radius: 8px; border: 1px solid #404040;">
                <h2 style="margin: 0 0 25px 0; font-size: 1.3rem; color: #e0e0e0; font-family: 'Oswald', sans-serif;">
                    <i class="fas fa-plus-circle" style="color: #4CAF50;"></i> Add New Episode
                </h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_episode">
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Episode Title <span style="color: #f44336;">*</span></label>
                            <input type="text" name="title" required placeholder="Episode 1: Introduction" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Description <span style="color: #f44336;">*</span></label>
                            <textarea name="description" required placeholder="Describe what this episode is about..." rows="4" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem; resize: vertical;"></textarea>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 12px; font-weight: 600; color: #e0e0e0; font-size: 1.05rem;">
                                <i class="fas fa-music" style="color: #4CAF50;"></i> Audio File (MP3)
                            </label>
                            
                            <!-- Upload Zone -->
                            <div id="audioUploadZone" class="upload-zone audio-upload-zone">
                                <input type="file" name="audio_file" id="audioFileInput" accept="audio/mpeg,audio/mp3,.mp3">
                                <div class="upload-icon">
                                    <i class="fas fa-headphones"></i>
                                </div>
                                <div class="upload-text">Drag & drop your MP3 file here</div>
                                <div class="upload-hint">or click to browse • Max 500MB • Duration auto-detected</div>
                            </div>
                            
                            <!-- Progress -->
                            <div class="upload-progress">
                                <div class="progress-bar-container">
                                    <div class="progress-bar"></div>
                                </div>
                                <div class="progress-text">Preparing upload...</div>
                            </div>
                            
                            <!-- Success -->
                            <div class="upload-success">
                                <div class="upload-success-header">
                                    <div class="upload-success-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="upload-success-info">
                                        <div class="upload-filename">audio.mp3</div>
                                        <div class="upload-meta"></div>
                                    </div>
                                </div>
                                <div class="audio-preview-player"></div>
                                <div class="upload-actions">
                                    <button type="button" class="btn-remove" onclick="audioUploader.remove()">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Error -->
                            <div class="upload-error">
                                <div class="upload-error-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="upload-error-message">Upload failed</div>
                            </div>
                        </div>

                        <div style="grid-column: 1 / -1; margin-top: 10px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Or External Audio URL <span style="color: #9e9e9e; font-weight: 400;">(optional if file uploaded)</span></label>
                            <input type="url" name="audio_url" id="audioUrlInput" placeholder="https://example.com/audio/episode1.mp3" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                            <small style="display: block; margin-top: 6px; color: #9e9e9e; font-size: 0.8rem;">Only needed if not uploading a file above</small>
                        </div>

                        <!-- Hidden fields for auto-detected values -->
                        <input type="hidden" name="duration" id="hiddenDuration">
                        <input type="hidden" name="file_size" id="hiddenFileSize">
                        <input type="hidden" name="pub_date" value="<?php echo date('Y-m-d H:i:s'); ?>">

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 12px; font-weight: 600; color: #e0e0e0; font-size: 1.05rem;">
                                <i class="fas fa-image" style="color: #4CAF50;"></i> Episode Cover <span style="color: #9e9e9e; font-weight: 400;">(optional)</span>
                            </label>
                            
                            <!-- Image Upload Zone -->
                            <div id="imageUploadZone" class="upload-zone image-upload-zone">
                                <input type="file" name="episode_image" id="imageFileInput" accept="image/*">
                                <div class="image-upload-preview-area">
                                    <i class="fas fa-image" style="font-size: 3rem; color: #4CAF50; opacity: 0.5;"></i>
                                </div>
                                <div class="image-upload-content">
                                    <div class="upload-text">Drag & drop cover image here</div>
                                    <div class="upload-hint">or click to browse • 1400x1400 to 3000x3000 px • Max 2MB • JPG or PNG</div>
                                    <div class="upload-hint" style="margin-top: 8px; font-style: italic;">Inherits podcast cover if not provided</div>
                                </div>
                            </div>
                            
                            <!-- Image Success -->
                            <div class="upload-success" id="imageUploadSuccess">
                                <div class="upload-success-header">
                                    <div class="image-upload-preview-area">
                                        <img id="imagePreviewImg" src="" alt="Preview">
                                    </div>
                                    <div class="upload-success-info">
                                        <div class="upload-filename" id="imageFileName">image.jpg</div>
                                        <div class="upload-meta" id="imageMeta"></div>
                                    </div>
                                </div>
                                <div class="upload-actions">
                                    <button type="button" class="btn-remove" onclick="removeImage()">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Image Error -->
                            <div class="upload-error" id="imageUploadError">
                                <div class="upload-error-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="upload-error-message" id="imageErrorMessage">Upload failed</div>
                            </div>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Episode Number</label>
                            <input type="number" name="episode_number" placeholder="1" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Season Number</label>
                            <input type="number" name="season_number" placeholder="1" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Episode Type</label>
                            <select name="episode_type" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="full">Full</option>
                                <option value="trailer">Trailer</option>
                                <option value="bonus">Bonus</option>
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
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e0e0e0;">Status</label>
                            <select name="status" style="width: 100%; padding: 12px; background: #1a1a1a; border: 2px solid #404040; border-radius: 8px; color: #e0e0e0; font-size: 0.95rem;">
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 25px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="toggleEpisodeForm()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Add Episode
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($episodes)): ?>
            <div class="empty-episodes">
                <i class="fas fa-headphones"></i>
                <h2 style="color: #e0e0e0; margin-bottom: 10px;">No Episodes Yet</h2>
                <p style="color: #9e9e9e; margin-bottom: 0;">Click "Add New Episode" above to get started</p>
            </div>
        <?php else: ?>
            <div class="episode-list">
                <?php foreach ($episodes as $episode): 
                    // Determine episode image - use episode image or fallback to podcast cover
                    $episodeImage = !empty($episode['episode_image']) 
                        ? 'uploads/covers/' . $episode['episode_image']
                        : 'uploads/covers/' . $podcast['cover_image'];
                    
                    // Format duration
                    $duration = !empty($episode['duration']) ? $episode['duration'] : '0';
                    if (is_numeric($duration)) {
                        $hours = floor($duration / 3600);
                        $minutes = floor(($duration % 3600) / 60);
                        $seconds = $duration % 60;
                        $durationFormatted = $hours > 0 
                            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
                            : sprintf('%d:%02d', $minutes, $seconds);
                    } else {
                        $durationFormatted = $duration;
                    }
                    
                    // Format file size
                    $fileSize = !empty($episode['file_size']) ? $episode['file_size'] : 0;
                    $fileSizeFormatted = $fileSize > 0 ? number_format($fileSize / 1048576, 1) . ' MB' : 'N/A';
                ?>
                    <div class="episode-item" style="background: #2d2d2d; padding: 25px; border-radius: 12px; border: 1px solid #404040; margin-bottom: 20px;">
                        <!-- Episode Header with Image and Title -->
                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <!-- Episode Image -->
                            <div style="flex-shrink: 0;">
                                <img src="<?php echo htmlspecialchars($episodeImage); ?>" 
                                     alt="<?php echo htmlspecialchars($episode['title']); ?>"
                                     style="width: 140px; height: 140px; object-fit: cover; border-radius: 10px; border: 2px solid #404040; box-shadow: 0 4px 8px rgba(0,0,0,0.3);">
                            </div>
                            
                            <!-- Title and Status -->
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <h3 style="margin: 0; font-size: 1.4rem; color: #e0e0e0; font-family: 'Oswald', sans-serif; line-height: 1.3;">
                                        <?php echo htmlspecialchars($episode['title']); ?>
                                    </h3>
                                    <span class="badge badge-<?php echo $episode['status'] === 'published' ? 'success' : 'warning'; ?>" style="margin-left: 15px; flex-shrink: 0;">
                                        <?php echo ucfirst($episode['status']); ?>
                                    </span>
                                </div>
                                
                                <!-- Description -->
                                <?php if (!empty($episode['description'])): ?>
                                    <div style="color: #b0b0b0; font-size: 0.9rem; line-height: 1.6; margin-bottom: 15px;">
                                        <?php echo htmlspecialchars($episode['description']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Metadata Grid -->
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; padding: 15px; background: #1a1a1a; border-radius: 8px; border: 1px solid #404040;">
                                    <!-- Publication Date -->
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-calendar" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div style="font-size: 0.75rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Published</div>
                                            <div style="font-size: 0.9rem; color: #e0e0e0; font-weight: 500;"><?php echo date('M d, Y', strtotime($episode['pub_date'])); ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Duration -->
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-clock" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div style="font-size: 0.75rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Duration</div>
                                            <div style="font-size: 0.9rem; color: #e0e0e0; font-weight: 500;"><?php echo $durationFormatted; ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- File Size -->
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-file-audio" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div style="font-size: 0.75rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">File Size</div>
                                            <div style="font-size: 0.9rem; color: #e0e0e0; font-weight: 500;"><?php echo $fileSizeFormatted; ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Episode Type -->
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-tag" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div style="font-size: 0.75rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Type</div>
                                            <div style="font-size: 0.9rem; color: #e0e0e0; font-weight: 500;"><?php echo ucfirst($episode['episode_type'] ?? 'Full'); ?></div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($episode['episode_number'])): ?>
                                    <!-- Episode Number -->
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-hashtag" style="color: #9e9e9e; font-size: 0.9rem;"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div style="font-size: 0.75rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Episode</div>
                                            <div style="font-size: 0.9rem; color: #e0e0e0; font-weight: 500;">#<?php echo htmlspecialchars($episode['episode_number']); ?><?php if (!empty($episode['season_number'])) echo ' (S' . htmlspecialchars($episode['season_number']) . ')'; ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Explicit Content -->
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: #404040; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-<?php echo ($episode['explicit'] ?? 'no') === 'yes' ? 'exclamation-triangle' : 'check-circle'; ?>" style="color: <?php echo ($episode['explicit'] ?? 'no') === 'yes' ? '#F44336' : '#9e9e9e'; ?>; font-size: 0.9rem;"></i>
                                        </div>
                                        <div style="min-width: 0;">
                                            <div style="font-size: 0.75rem; color: #9e9e9e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Content</div>
                                            <div style="font-size: 0.9rem; color: #e0e0e0; font-weight: 500;"><?php echo ucfirst($episode['explicit'] ?? 'Clean'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                            <!-- Audio Player -->
                            <div class="audio-player-container" style="margin-bottom: 15px;" data-duration="<?php echo intval($episode['duration']); ?>">
                                <audio preload="metadata">
                                    <?php 
                                    // Use stream.php for local files to enable seeking
                                    $audioUrl = $episode['audio_url'];
                                    
                                    // Check if it's a local file (contains uploads/audio/)
                                    if (strpos($audioUrl, 'uploads/audio/') !== false) {
                                        // Extract just the path part
                                        if (preg_match('#(uploads/audio/.+)$#', $audioUrl, $matches)) {
                                            $audioUrl = 'stream.php?file=' . urlencode($matches[1]);
                                        }
                                    }
                                    ?>
                                    <source src="<?php echo htmlspecialchars($audioUrl); ?>" type="audio/mpeg">
                                </audio>
                            </div>

                            <!-- Actions -->
                            <div id="episodeActions_<?php echo $episode['id']; ?>" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="<?php echo htmlspecialchars($episode['audio_url']); ?>" 
                                   download 
                                   class="btn btn-sm btn-secondary">
                                    <i class="fas fa-download"></i> Download
                                </a>
                                <button class="btn btn-sm btn-primary" 
                                        onclick="toggleEditEpisodeForm('<?php echo $episode['id']; ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="deleteEpisode('<?php echo $episode['id']; ?>', '<?php echo htmlspecialchars(addslashes($episode['title'])); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>

                            <!-- Edit Episode Form (Inline, Hidden by Default) -->
                            <div id="editEpisodeForm_<?php echo $episode['id']; ?>" style="display: none; margin-top: 20px; padding: 20px; background: #1a1a1a; border-radius: 8px; border: 2px solid #4CAF50;">
                                <h4 style="margin: 0 0 20px 0; color: #4CAF50; font-family: 'Oswald', sans-serif;">
                                    <i class="fas fa-edit"></i> Edit Episode
                                </h4>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_episode">
                                    <input type="hidden" name="episode_id" value="<?php echo $episode['id']; ?>">
                                    
                                    <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">Episode Title <span style="color: #f44336;">*</span></label>
                                            <input type="text" name="title" required value="<?php echo htmlspecialchars($episode['title']); ?>" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                        </div>

                                        <div>
                                            <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">Description <span style="color: #f44336;">*</span></label>
                                            <textarea name="description" required rows="3" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem; resize: vertical;"><?php echo htmlspecialchars($episode['description']); ?></textarea>
                                        </div>

                                        <div>
                                            <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">
                                                <i class="fas fa-music" style="color: #4CAF50;"></i> Replace Audio File <span style="color: #9e9e9e; font-weight: 400;">(optional)</span>
                                            </label>
                                            <p style="color: #9e9e9e; font-size: 0.8rem; margin: 0 0 8px 0;">Current: <?php echo basename($episode['audio_url']); ?></p>
                                            <input type="file" name="audio_file" accept="audio/mpeg,audio/mp3,.mp3" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                            <small style="display: block; margin-top: 4px; color: #9e9e9e; font-size: 0.75rem;">Leave empty to keep current audio file</small>
                                        </div>

                                        <div>
                                            <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">Or External Audio URL</label>
                                            <input type="url" name="audio_url" value="<?php echo htmlspecialchars($episode['audio_url']); ?>" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                        </div>

                                        <div>
                                            <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">
                                                <i class="fas fa-image" style="color: #4CAF50;"></i> Replace Episode Cover <span style="color: #9e9e9e; font-weight: 400;">(optional)</span>
                                            </label>
                                            <?php if (!empty($episode['episode_image'])): ?>
                                                <p style="color: #9e9e9e; font-size: 0.8rem; margin: 0 0 8px 0;">Current cover set</p>
                                            <?php endif; ?>
                                            <input type="file" name="episode_image" accept="image/*" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                            <small style="display: block; margin-top: 4px; color: #9e9e9e; font-size: 0.75rem;">Leave empty to keep current image (or use podcast cover)</small>
                                        </div>

                                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                            <div>
                                                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">Episode Number</label>
                                                <input type="number" name="episode_number" value="<?php echo htmlspecialchars($episode['episode_number'] ?? ''); ?>" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                            </div>

                                            <div>
                                                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">Season Number</label>
                                                <input type="number" name="season_number" value="<?php echo htmlspecialchars($episode['season_number'] ?? ''); ?>" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                            </div>

                                            <div>
                                                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">Episode Type</label>
                                                <select name="episode_type" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                                    <option value="full" <?php echo ($episode['episode_type'] ?? 'full') === 'full' ? 'selected' : ''; ?>>Full</option>
                                                    <option value="trailer" <?php echo ($episode['episode_type'] ?? 'full') === 'trailer' ? 'selected' : ''; ?>>Trailer</option>
                                                    <option value="bonus" <?php echo ($episode['episode_type'] ?? 'full') === 'bonus' ? 'selected' : ''; ?>>Bonus</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">Explicit</label>
                                                <select name="explicit" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                                    <option value="no" <?php echo ($episode['explicit'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
                                                    <option value="yes" <?php echo ($episode['explicit'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                                    <option value="clean" <?php echo ($episode['explicit'] ?? 'no') === 'clean' ? 'selected' : ''; ?>>Clean</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">Status</label>
                                                <select name="status" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                                    <option value="published" <?php echo ($episode['status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Published</option>
                                                    <option value="draft" <?php echo ($episode['status'] ?? 'published') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="scheduled" <?php echo ($episode['status'] ?? 'published') === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #e0e0e0; font-size: 0.9rem;">Publication Date</label>
                                                <input type="datetime-local" name="pub_date" value="<?php echo date('Y-m-d\TH:i', strtotime($episode['pub_date'])); ?>" style="width: 100%; padding: 10px; background: #2d2d2d; border: 2px solid #404040; border-radius: 6px; color: #e0e0e0; font-size: 0.9rem;">
                                            </div>
                                        </div>

                                        <!-- Hidden fields for metadata -->
                                        <input type="hidden" name="duration" value="<?php echo $episode['duration']; ?>">
                                        <input type="hidden" name="file_size" value="<?php echo $episode['file_size']; ?>">
                                    </div>

                                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="toggleEditEpisodeForm('<?php echo $episode['id']; ?>')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="fas fa-check"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Audio Uploader Script -->
    <script src="assets/js/audio-uploader.js?v=<?php echo ASSETS_VERSION; ?>"></script>
    <script src="assets/js/custom-audio-player.js?v=<?php echo ASSETS_VERSION; ?>"></script>
    
    <script>
        setTimeout(() => {
            const flash = document.getElementById('flashMessage');
            if (flash) {
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 300);
            }
        }, 5000);

        function toggleEpisodeForm() {
            const form = document.getElementById('addEpisodeForm');
            const button = document.getElementById('toggleEpisodeText');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                button.textContent = 'Hide Form';
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                form.style.display = 'none';
                button.textContent = 'Add New Episode';
            }
        }

        function toggleEditPodcastForm() {
            const form = document.getElementById('editPodcastForm');
            const button = document.getElementById('toggleEditPodcastText');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                button.textContent = 'Hide Form';
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                form.style.display = 'none';
                button.textContent = 'Edit Podcast';
            }
        }

        // Initialize Audio Uploader
        let audioUploader;
        document.addEventListener('DOMContentLoaded', function() {
            audioUploader = new AudioUploader('audioUploadZone', {
                podcastId: '<?php echo $podcastId; ?>',
                onUploadComplete: function(file, metadata) {
                    // Auto-fill form fields with uploaded file data
                    const audioUrlInput = document.getElementById('audio_url');
                    const durationInput = document.getElementById('hiddenDuration');
                    const fileSizeInput = document.getElementById('hiddenFileSize');
                    
                    // Set the audio URL from the server response
                    if (audioUrlInput && metadata.url) {
                        audioUrlInput.value = metadata.url;
                        console.log('Audio URL set:', metadata.url);
                    }
                    
                    if (durationInput) {
                        durationInput.value = Math.floor(metadata.duration);
                        console.log('Duration set:', Math.floor(metadata.duration), 'seconds');
                    }
                    
                    if (fileSizeInput) {
                        fileSizeInput.value = metadata.fileSize;
                        console.log('File size set:', metadata.fileSize, 'bytes');
                    }
                    
                    console.log('Audio uploaded successfully:', metadata);
                },
                onUploadError: function(error) {
                    console.error('Upload error:', error);
                    alert('Audio upload failed: ' + error);
                }
            });
            
            // Also replace the preview player with custom player
            const previewPlayer = document.querySelector('.audio-preview-player');
            if (previewPlayer) {
                previewPlayer.classList.add('audio-player-container');
            }
        });

        // Image Upload Handler
        let currentImageFile = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const imageZone = document.getElementById('imageUploadZone');
            const imageInput = document.getElementById('imageFileInput');
            const imageSuccess = document.getElementById('imageUploadSuccess');
            const imageError = document.getElementById('imageUploadError');
            
            // Drag and drop
            imageZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                imageZone.classList.add('drag-over');
            });
            
            imageZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                imageZone.classList.remove('drag-over');
            });
            
            imageZone.addEventListener('drop', (e) => {
                e.preventDefault();
                imageZone.classList.remove('drag-over');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    processImage(files[0]);
                }
            });
            
            // File input change
            imageInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    processImage(e.target.files[0]);
                }
            });
            
            function processImage(file) {
                // Validate
                if (!file.type.match('image.*')) {
                    showImageError('Please select an image file');
                    return;
                }
                
                if (file.size > 2 * 1024 * 1024) {
                    showImageError('Image too large. Maximum size is 2MB');
                    return;
                }
                
                currentImageFile = file;
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreviewImg').src = e.target.result;
                    document.getElementById('imageFileName').textContent = file.name;
                    document.getElementById('imageMeta').innerHTML = `
                        <span class="upload-meta-item">
                            <i class="fas fa-file"></i> ${formatFileSize(file.size)}
                        </span>
                        <span class="upload-meta-item">
                            <i class="fas fa-image"></i> ${file.type.split('/')[1].toUpperCase()}
                        </span>
                    `;
                    
                    imageZone.style.display = 'none';
                    imageSuccess.classList.add('active');
                    imageError.classList.remove('active');
                };
                reader.readAsDataURL(file);
            }
            
            function showImageError(message) {
                document.getElementById('imageErrorMessage').textContent = message;
                imageError.classList.add('active');
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            }
        });
        
        function removeImage() {
            currentImageFile = null;
            document.getElementById('imageFileInput').value = '';
            document.getElementById('imageUploadSuccess').classList.remove('active');
            document.getElementById('imageUploadZone').style.display = 'flex';
        }

        function validateForm() {
            console.log('validateForm called');
            const audioFileInput = document.getElementById('audioFileInput');
            const audioUrlInput = document.getElementById('audioUrlInput');
            const durationInput = document.getElementById('hiddenDuration');
            
            console.log('audioFileInput:', audioFileInput);
            console.log('audioUrlInput:', audioUrlInput);
            console.log('durationInput value:', durationInput?.value);
            
            // Check if either file or URL is provided
            // AudioUploader sets the duration when upload completes
            const hasFile = audioFileInput && audioFileInput.files.length > 0;
            const hasUploadedFile = durationInput && durationInput.value && durationInput.value !== '0';
            const hasUrl = audioUrlInput && audioUrlInput.value.trim() !== '';
            
            console.log('hasFile:', hasFile);
            console.log('hasUploadedFile:', hasUploadedFile);
            console.log('hasUrl:', hasUrl);
            
            if (!hasFile && !hasUploadedFile && !hasUrl) {
                alert('Please upload an audio file or provide an audio URL');
                return false;
            }
            
            console.log('Form validation passed, submitting...');
            return true;
        }

        // Audio file upload handler
        document.addEventListener('DOMContentLoaded', function() {
            const audioInput = document.getElementById('audio_file');
            const audioFileNameDisplay = document.querySelector('.audio-upload .file-name');
            const audioPreview = document.getElementById('audioPreview');
            const audioUrlInput = document.getElementById('audio_url');

            if (audioInput) {
                audioInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    
                    if (file) {
                        // Update file name
                        audioFileNameDisplay.textContent = file.name;
                        
                        // Format file size
                        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
                        
                        // Show preview
                        audioPreview.innerHTML = `
                            <div class="audio-info">
                                <div class="audio-icon">
                                    <i class="fas fa-file-audio"></i>
                                </div>
                                <div class="audio-details">
                                    <div class="audio-filename">${file.name}</div>
                                    <div class="audio-meta">${fileSizeMB} MB • MP3 Audio File</div>
                                </div>
                            </div>
                        `;
                        audioPreview.classList.add('active');
                        
                        // Make audio URL optional when file is selected
                        audioUrlInput.removeAttribute('required');
                    } else {
                        audioFileNameDisplay.textContent = 'Click to upload MP3 file or drag and drop';
                        audioPreview.classList.remove('active');
                        audioPreview.innerHTML = '';
                        
                        // Make audio URL required again if no file
                        audioUrlInput.setAttribute('required', 'required');
                    }
                });
            }
        });

        function toggleEditEpisodeForm(episodeId) {
            const form = document.getElementById('editEpisodeForm_' + episodeId);
            const actions = document.getElementById('episodeActions_' + episodeId);
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                form.style.display = 'none';
            }
        }

        function deleteEpisode(episodeId, title) {
            if (confirm('Are you sure you want to delete "' + title + '"? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_episode">
                    <input type="hidden" name="episode_id" value="${episodeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('addEpisodeModal');
            if (event.target === modal) {
                closeAddEpisodeModal();
            }
        }
    </script>
</body>
</html>
