<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/SelfHostedPodcastManager.php';

echo "<h1>Direct Episode Add Test</h1>";

$manager = new SelfHostedPodcastManager();
$podcastId = 'shp_1760743410_68f2cff24487e';

echo "<h2>Testing with minimal data (no file):</h2>";

$episodeData = [
    'title' => 'Test Episode ' . time(),
    'description' => 'This is a test episode',
    'audio_url' => 'https://example.com/test.mp3',
    'duration' => '1800',
    'file_size' => '28000000',
    'pub_date' => date('Y-m-d H:i:s'),
    'episode_number' => '1',
    'season_number' => '1',
    'episode_type' => 'full',
    'explicit' => 'no',
    'status' => 'published'
];

echo "<pre>";
print_r($episodeData);
echo "</pre>";

echo "<h3>Calling addEpisode()...</h3>";

$result = $manager->addEpisode($podcastId, $episodeData, null, null);

echo "<h3>Result:</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";

if ($result['success']) {
    echo "<h2 style='color: green;'>✅ SUCCESS! Episode added!</h2>";
    echo "<p>Episode ID: " . $result['id'] . "</p>";
    
    // Check XML
    echo "<h3>Checking XML:</h3>";
    $xml = simplexml_load_file(DATA_DIR . '/self-hosted-podcasts.xml');
    $podcast = $xml->xpath("//podcast[id='$podcastId']")[0];
    $episodes = $podcast->episodes->episode;
    echo "<p>Episodes in XML: " . count($episodes) . "</p>";
    
    if (count($episodes) > 0) {
        echo "<h4>Latest episode:</h4>";
        echo "<pre>";
        print_r($episodes[count($episodes) - 1]);
        echo "</pre>";
    }
} else {
    echo "<h2 style='color: red;'>❌ FAILED!</h2>";
    echo "<p>Error: " . $result['message'] . "</p>";
}

echo "<hr>";
echo "<h2>Check error log:</h2>";
if (file_exists(LOGS_DIR . '/error.log')) {
    echo "<pre>";
    echo file_get_contents(LOGS_DIR . '/error.log');
    echo "</pre>";
} else {
    echo "<p>No error log found</p>";
}
?>
