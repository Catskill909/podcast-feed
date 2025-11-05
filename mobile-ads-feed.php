<?php
/**
 * Mobile Banner Ads RSS Feed
 * Generates RSS feed for mobile/tablet app consumption
 */

require_once __DIR__ . '/includes/AdsManager.php';

header('Content-Type: application/xml; charset=utf-8');

$manager = new AdsManager();
$settings = $manager->getSettings();
$mobileAds = $manager->getEnabledMobileAds();

// Check if mobile ads are enabled
$adsEnabled = $settings['mobile_ads_enabled'];

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:ads="http://podcast-app.com/ads">
    <channel>
        <title>Mobile Banner Ads</title>
        <description>Banner advertisements for mobile app</description>
        <link><?php echo htmlspecialchars(APP_URL); ?></link>
        <lastBuildDate><?php $utcDate = new DateTime('now', new DateTimeZone('UTC')); echo $utcDate->format('r'); ?></lastBuildDate>
        <ads:enabled><?php echo $adsEnabled ? 'true' : 'false'; ?></ads:enabled>
        
        <?php if ($adsEnabled && !empty($mobileAds)): ?>
            <?php foreach ($mobileAds as $ad): ?>
        <item>
            <title>Banner Ad <?php echo htmlspecialchars($ad['id']); ?></title>
            <link><?php echo !empty($ad['click_url']) ? htmlspecialchars($ad['click_url']) : htmlspecialchars(APP_URL); ?></link>
            <guid><?php echo htmlspecialchars($ad['id']); ?></guid>
            <pubDate><?php echo date('r', strtotime($ad['created_at'])); ?></pubDate>
            <enclosure 
                url="<?php echo htmlspecialchars($ad['url']); ?>" 
                length="<?php echo file_exists($ad['filepath']) ? filesize($ad['filepath']) : 0; ?>" 
                type="<?php echo mime_content_type($ad['filepath']); ?>" />
            <ads:dimensions><?php echo htmlspecialchars($ad['dimensions']); ?></ads:dimensions>
            <ads:clickUrl><?php echo !empty($ad['click_url']) ? htmlspecialchars($ad['click_url']) : htmlspecialchars(APP_URL); ?></ads:clickUrl>
            <ads:displayOrder><?php echo htmlspecialchars($ad['display_order']); ?></ads:displayOrder>
        </item>
            <?php endforeach; ?>
        <?php endif; ?>
    </channel>
</rss>
