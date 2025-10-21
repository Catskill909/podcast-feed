<?php
/**
 * Self-Hosted Podcast RSS Feed Generator
 * Generates RSS 2.0 + iTunes namespace compliant feeds
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/SelfHostedPodcastManager.php';

// Get podcast ID from query string
$podcastId = $_GET['id'] ?? '';

if (empty($podcastId)) {
    header('HTTP/1.1 400 Bad Request');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<error>Podcast ID is required</error>';
    exit;
}

// Get podcast manager
$manager = new SelfHostedPodcastManager();
$podcast = $manager->getPodcast($podcastId);

if (!$podcast) {
    header('HTTP/1.1 404 Not Found');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<error>Podcast not found</error>';
    exit;
}

// Get episodes
$episodes = $manager->getEpisodes($podcastId);

// Filter only published episodes and sort by date (newest first)
$publishedEpisodes = array_filter($episodes, function($ep) {
    return ($ep['status'] ?? 'published') === 'published';
});

usort($publishedEpisodes, function($a, $b) {
    return strtotime($b['pub_date']) - strtotime($a['pub_date']);
});

// Get latest episode date for channel pubDate
$latestEpisodeDate = !empty($publishedEpisodes) ? $publishedEpisodes[0]['pub_date'] : $podcast['created_date'];

// Generate RSS feed
header('Content-Type: application/rss+xml; charset=UTF-8');

// Helper function to format date to RFC 2822
function formatRFC2822($dateString) {
    $timestamp = strtotime($dateString);
    return date('D, d M Y H:i:s O', $timestamp);
}

// Helper function to format duration
function formatDuration($seconds) {
    if (empty($seconds)) return '0';
    
    // If already in HH:MM:SS format, return as is
    if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $seconds)) {
        return $seconds;
    }
    
    // Convert seconds to HH:MM:SS
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" 
     xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <title><?php echo htmlspecialchars($podcast['title'], ENT_XML1); ?></title>
        <description><?php echo htmlspecialchars($podcast['description'], ENT_XML1); ?></description>
        <link><?php echo htmlspecialchars($podcast['website_url'] ?: APP_URL, ENT_XML1); ?></link>
        <language><?php echo htmlspecialchars($podcast['language'], ENT_XML1); ?></language>
        <?php if (!empty($podcast['copyright'])): ?>
        <copyright><?php echo htmlspecialchars($podcast['copyright'], ENT_XML1); ?></copyright>
        <?php endif; ?>
        <lastBuildDate><?php echo formatRFC2822($podcast['updated_date']); ?></lastBuildDate>
        <pubDate><?php echo formatRFC2822($latestEpisodeDate); ?></pubDate>
        <atom:link href="<?php echo htmlspecialchars(APP_URL . '/self-hosted-feed.php?id=' . $podcastId, ENT_XML1); ?>" rel="self" type="application/rss+xml"/>
        
        <!-- iTunes Tags -->
        <itunes:author><?php echo htmlspecialchars($podcast['author'], ENT_XML1); ?></itunes:author>
        <itunes:summary><?php echo htmlspecialchars($podcast['description'], ENT_XML1); ?></itunes:summary>
        <?php if (!empty($podcast['subtitle'])): ?>
        <itunes:subtitle><?php echo htmlspecialchars($podcast['subtitle'], ENT_XML1); ?></itunes:subtitle>
        <?php endif; ?>
        <itunes:owner>
            <itunes:name><?php echo htmlspecialchars($podcast['owner_name'], ENT_XML1); ?></itunes:name>
            <itunes:email><?php echo htmlspecialchars($podcast['owner_email'], ENT_XML1); ?></itunes:email>
        </itunes:owner>
        <?php if (!empty($podcast['cover_image'])): ?>
        <itunes:image href="<?php echo htmlspecialchars(APP_URL . '/uploads/covers/' . $podcast['cover_image'], ENT_XML1); ?>"/>
        <image>
            <url><?php echo htmlspecialchars(APP_URL . '/uploads/covers/' . $podcast['cover_image'], ENT_XML1); ?></url>
            <title><?php echo htmlspecialchars($podcast['title'], ENT_XML1); ?></title>
            <link><?php echo htmlspecialchars($podcast['website_url'] ?: APP_URL, ENT_XML1); ?></link>
        </image>
        <?php endif; ?>
        <?php if (!empty($podcast['category'])): ?>
        <itunes:category text="<?php echo htmlspecialchars($podcast['category'], ENT_XML1); ?>">
            <?php if (!empty($podcast['subcategory'])): ?>
            <itunes:category text="<?php echo htmlspecialchars($podcast['subcategory'], ENT_XML1); ?>"/>
            <?php endif; ?>
        </itunes:category>
        <?php endif; ?>
        <itunes:explicit><?php echo htmlspecialchars($podcast['explicit'], ENT_XML1); ?></itunes:explicit>
        <itunes:type><?php echo htmlspecialchars($podcast['podcast_type'], ENT_XML1); ?></itunes:type>
        <?php if (!empty($podcast['keywords'])): ?>
        <itunes:keywords><?php echo htmlspecialchars($podcast['keywords'], ENT_XML1); ?></itunes:keywords>
        <?php endif; ?>
        <?php if (!empty($podcast['complete']) && $podcast['complete'] === 'yes'): ?>
        <itunes:complete>Yes</itunes:complete>
        <?php endif; ?>
        
        <!-- Episodes -->
        <?php foreach ($publishedEpisodes as $episode): ?>
        <item>
            <title><?php echo htmlspecialchars($episode['title'], ENT_XML1); ?></title>
            <description><?php echo htmlspecialchars($episode['description'], ENT_XML1); ?></description>
            <enclosure url="<?php 
                // Use stream.php for local files to support range requests (seeking)
                $audioUrl = $episode['audio_url'];
                if (strpos($audioUrl, '/uploads/audio/') !== false) {
                    // Extract the file path and wrap with stream.php
                    $filePath = substr($audioUrl, strpos($audioUrl, 'uploads/audio/'));
                    $audioUrl = APP_URL . '/stream.php?file=' . urlencode($filePath);
                }
                echo htmlspecialchars($audioUrl, ENT_XML1); 
            ?>" 
                       length="<?php echo htmlspecialchars($episode['file_size'] ?: '0', ENT_XML1); ?>" 
                       type="audio/mpeg"/>
            <guid isPermaLink="false"><?php echo htmlspecialchars($episode['guid'], ENT_XML1); ?></guid>
            <pubDate><?php echo formatRFC2822($episode['pub_date']); ?></pubDate>
            <itunes:author><?php echo htmlspecialchars($podcast['author'], ENT_XML1); ?></itunes:author>
            <?php if (!empty($episode['duration'])): ?>
            <itunes:duration><?php echo formatDuration($episode['duration']); ?></itunes:duration>
            <?php endif; ?>
            <?php if (!empty($episode['episode_number'])): ?>
            <itunes:episode><?php echo htmlspecialchars($episode['episode_number'], ENT_XML1); ?></itunes:episode>
            <?php endif; ?>
            <?php if (!empty($episode['season_number'])): ?>
            <itunes:season><?php echo htmlspecialchars($episode['season_number'], ENT_XML1); ?></itunes:season>
            <?php endif; ?>
            <itunes:episodeType><?php echo htmlspecialchars($episode['episode_type'], ENT_XML1); ?></itunes:episodeType>
            <itunes:explicit><?php echo htmlspecialchars($episode['explicit'], ENT_XML1); ?></itunes:explicit>
            <?php if (!empty($episode['episode_image'])): ?>
            <itunes:image href="<?php echo htmlspecialchars(APP_URL . '/uploads/covers/' . $episode['episode_image'], ENT_XML1); ?>"/>
            <?php endif; ?>
        </item>
        <?php endforeach; ?>
    </channel>
</rss>
