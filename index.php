<?php
/**
 * Public Podcast Browser
 * Beautiful interface for browsing and listening to podcasts
 */

require_once __DIR__ . '/includes/PodcastManager.php';
require_once __DIR__ . '/includes/MenuManager.php';

$podcastManager = new PodcastManager();
$stats = $podcastManager->getStats();

// Load menu configuration with fallback
try {
    $menuManager = new MenuManager();
    $branding = $menuManager->getBranding();
    $menuItems = $menuManager->getMenuItems(true); // Active only
} catch (Exception $e) {
    // Fallback to default menu if MenuManager fails
    $branding = [
        'site_title' => 'Podcast Browser',
        'logo_type' => 'icon',
        'logo_icon' => 'fa-podcast',
        'logo_image' => ''
    ];
    $menuItems = [
        ['label' => 'Browse', 'url' => 'index.php', 'icon_type' => 'none', 'icon_value' => '', 'target' => '_self'],
        ['label' => 'Admin', 'url' => 'admin.php', 'icon_type' => 'fa', 'icon_value' => 'fa-lock', 'target' => '_self']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podcast Browser</title>
    <meta name="description" content="Browse and listen to our collection of podcasts">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/browse.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/sort-controls.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/player-modal.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/web-banner.css?v=<?php echo time(); ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎧</text></svg>">
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <?php if ($branding['logo_type'] === 'image' && !empty($branding['logo_image'])): ?>
                        <img src="<?php echo htmlspecialchars($branding['logo_image']); ?>" 
                             alt="Logo" class="logo-icon" style="width: 32px; height: 32px; object-fit: contain;">
                    <?php else: ?>
                        <i class="fa-solid <?php echo htmlspecialchars($branding['logo_icon']); ?> logo-icon"></i>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($branding['site_title']); ?></span>
                </a>
                <nav>
                    <ul class="nav-links">
                        <?php 
                        $currentPage = basename($_SERVER['PHP_SELF']);
                        foreach ($menuItems as $item): 
                            $isActive = ($currentPage === basename($item['url']));
                        ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                                   <?php echo $item['target'] === '_blank' ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                                   <?php echo $isActive ? 'class="active"' : ''; ?>>
                                    <?php if ($item['icon_type'] === 'fa' && !empty($item['icon_value'])): ?>
                                        <i class="fa-solid <?php echo htmlspecialchars($item['icon_value']); ?>"></i>
                                    <?php elseif ($item['icon_type'] === 'image' && !empty($item['icon_value'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['icon_value']); ?>" 
                                             alt="" style="width: 16px; height: 16px; object-fit: contain; vertical-align: middle;">
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['label']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            
            <!-- Web Banner Ad -->
            <?php
            require_once __DIR__ . '/includes/AdsManager.php';
            $adsManager = new AdsManager();
            $adsSettings = $adsManager->getSettings();
            $webAds = $adsManager->getEnabledWebAds(); // Only get enabled ads
            
            if ($adsSettings['web_ads_enabled'] && !empty($webAds)):
            ?>
            <div class="web-banner-container">
                <div class="web-banner-ads" id="webBannerAds">
                    <?php foreach ($webAds as $index => $ad): ?>
                        <div class="web-banner-ad <?php echo $index === 0 ? 'active' : ''; ?>" data-ad-id="<?php echo $ad['id']; ?>">
                            <?php if (!empty($ad['click_url'])): ?>
                                <a href="<?php echo htmlspecialchars($ad['click_url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Advertisement">
                                </a>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Advertisement">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Browse Controls -->
            <div class="browse-controls">
                <div class="browse-search">
                    <i class="fa-solid fa-magnifying-glass browse-search-icon"></i>
                    <input 
                        type="text" 
                        id="browseSearch" 
                        class="form-control" 
                        placeholder="Search podcasts..."
                        autocomplete="off"
                    >
                </div>
                
                <div class="browse-filters">
                    <!-- Stats Badges -->
                    <div class="stat-badge-inline">
                        <i class="fa-solid fa-podcast"></i>
                        <span class="stat-value" id="podcastCount"><?php echo $stats['active_podcasts']; ?></span>
                        <span class="stat-label">Podcasts</span>
                    </div>
                    <div class="stat-badge-inline">
                        <i class="fa-solid fa-headphones"></i>
                        <span class="stat-value" id="totalEpisodes">-</span>
                        <span class="stat-label">Episodes</span>
                    </div>
                    
                    <!-- Sort Controls -->
                    <div class="sort-controls">
                        <button type="button" id="browseSortButton" class="sort-button" aria-haspopup="true" aria-expanded="false">
                            <span style="display: flex; align-items: center; gap: var(--spacing-sm);">
                                <i class="fa-solid fa-arrow-down-wide-short"></i>
                                <span id="browseSortLabel">Latest Episodes</span>
                            </span>
                            <i class="fa-solid fa-chevron-down sort-chevron"></i>
                        </button>
                        <div id="browseSortDropdown" class="sort-dropdown" role="menu">
                            <button type="button" class="sort-option active" data-sort="latest" role="menuitem">
                                <i class="fa-solid fa-clock"></i>
                                <div class="sort-option-content">
                                    <div class="sort-option-label">Latest Episodes</div>
                                    <div class="sort-option-description">Newest content first</div>
                                </div>
                                <i class="fa-solid fa-check sort-option-check"></i>
                            </button>
                            <button type="button" class="sort-option" data-sort="title" role="menuitem">
                                <i class="fa-solid fa-arrow-down-a-z"></i>
                                <div class="sort-option-content">
                                    <div class="sort-option-label">Alphabetical</div>
                                    <div class="sort-option-description">Sort A to Z</div>
                                </div>
                                <i class="fa-solid fa-check sort-option-check"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Podcasts Grid -->
            <div class="podcasts-grid" id="podcastsGrid">
                <!-- Populated by JavaScript -->
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer style="background: var(--bg-secondary); border-top: 1px solid var(--border-primary); padding: var(--spacing-xl) 0; margin-top: var(--spacing-xxl); text-align: center;">
        <div class="container">
            <p style="color: var(--text-muted); margin: 0; font-size: var(--font-size-sm);">
                &copy; <?php echo date('Y'); ?> Podcast Browser. All rights reserved.
            </p>
        </div>
    </footer>

    <!-- Player Modal (Reused from existing implementation) -->
    <div class="player-modal-overlay" id="playerModal">
        <div class="player-modal">
            <!-- Modal Header -->
            <div class="player-modal-header">
                <div class="player-modal-title">
                    <div class="player-modal-icon">
                        <i class="fa-solid fa-podcast"></i>
                    </div>
                    <h2 id="playerModalPodcastTitle">Podcast Player</h2>
                </div>
                <button class="player-modal-close" onclick="hidePlayerModal()" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="player-modal-body">
                <!-- Podcast Info Section -->
                <div class="player-podcast-info">
                    <div class="player-podcast-cover">
                        <img id="playerPodcastCover" src="" alt="Podcast Cover">
                    </div>
                    <div class="player-podcast-details">
                        <h3 class="player-podcast-name" id="playerPodcastName">Podcast Name</h3>
                        <p class="player-podcast-description" id="playerPodcastDescription">Description</p>
                        <div class="player-podcast-meta">
                            <span class="badge badge-success" id="playerStatus">Active</span>
                            <span class="badge badge-success" id="playerEpisodeCount">0 Episodes</span>
                            <span class="badge badge-success" id="playerLatestEpisodeBadge">Latest: <span id="playerLatestEpisode">Unknown</span></span>
                        </div>
                    </div>
                </div>

                <!-- Episodes Section -->
                <div class="player-episodes-section">
                    <div class="player-episodes-header">
                        <h4>Episodes</h4>
                        <div class="player-episodes-controls">
                            <input 
                                type="text" 
                                id="playerEpisodeSearch" 
                                class="form-control" 
                                placeholder="Search episodes..."
                                autocomplete="off"
                            >
                            <select id="playerEpisodeSort" class="form-control">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="title">By Title</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Episodes List -->
                    <div class="player-episodes-list" id="playerEpisodesList">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Audio Player Bar (appears when playing) -->
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

    <!-- Scripts -->
    <script src="assets/js/auto-refresh.js"></script>
    <script src="assets/js/browse.js?v=3.0.2"></script>
    <script src="assets/js/player-modal.js?v=3.0.2"></script>
    <script src="assets/js/audio-player.js?v=3.0.5"></script>
    
    <!-- Web Banner Rotation Script -->
    <script>
    (function() {
        const bannerContainer = document.getElementById('webBannerAds');
        if (!bannerContainer) return;
        
        const banners = bannerContainer.querySelectorAll('.web-banner-ad');
        if (banners.length <= 1) return; // No rotation needed for 0 or 1 ads
        
        let currentIndex = 0;
        const rotationDuration = <?php echo isset($adsSettings['web_ads_rotation_duration']) ? $adsSettings['web_ads_rotation_duration'] : 10; ?> * 1000;
        
        setInterval(() => {
            // Hide current banner
            banners[currentIndex].classList.remove('active');
            
            // Move to next banner
            currentIndex = (currentIndex + 1) % banners.length;
            
            // Show next banner
            banners[currentIndex].classList.add('active');
        }, rotationDuration);
    })();
    </script>
</body>

</html>
