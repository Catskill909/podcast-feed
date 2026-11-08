<?php

/**
 * Request-input safety tests.
 *
 * Run: php tests/request_input_safety_test.php
 *
 * The class of bug: a value from the browser (an ID, a URL) reaching a file
 * path or a server-side fetch without being checked. These assert that
 * hostile values are refused before any file or network access happens.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$tmp = sys_get_temp_dir() . '/podfeed-safety-' . getmypid();
mkdir($tmp . '/uploads/audio', 0777, true);
define('UPLOADS_DIR', $tmp . '/uploads');
define('UPLOADS_URL', 'https://example.test/uploads');

require __DIR__ . '/../includes/AudioUploader.php';
require __DIR__ . '/../includes/StreamingProxy.php';

$failures = 0;
function ok(string $label, bool $cond): void
{
    global $failures;
    if ($cond) {
        echo "  pass  $label\n";
        return;
    }
    $failures++;
    echo "  FAIL  $label\n";
}

echo "AudioUploader refuses IDs that would leave uploads/audio/\n";
ini_set('error_log', '/dev/null'); // keep AudioUploader's own error_log calls out of the test output
$uploader = new AudioUploader();
$audioDir = $tmp . '/uploads/audio';

/** A file that passes the upload checks (ID3 header, .mp3), as cloning passes it. */
function fakeMp3(string $dir): array
{
    $path = tempnam($dir, 'src');
    file_put_contents($path, "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 512));
    return ['name' => 'x.mp3', 'tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK];
}

// Each case would land the file at $tmp/uploads/escaped.mp3, outside audio/.
$escaped = $tmp . '/uploads/escaped.mp3';
foreach ([['..', 'escaped'], ['shp_1', '../../escaped'], ['shp_1/../..', 'escaped']] as [$podcastId, $episodeId]) {
    $uploader->uploadAudio(fakeMp3($tmp), $podcastId, $episodeId);
    ok("upload with ($podcastId, $episodeId) writes nothing outside audio/", !file_exists($escaped));
    @unlink($escaped);
}

$victim = $tmp . '/uploads/victim.mp3';
file_put_contents($victim, 'keep me');
$uploader->deleteAudio('..', 'victim');
ok('delete with podcast id ".." leaves a file outside audio/ alone', file_exists($victim));
$uploader->deleteAudio('shp_1', '../../victim');
ok('delete with episode id "../../victim" leaves it alone', file_exists($victim));
ok('info does not report a file outside audio/', $uploader->getAudioInfo('..', 'victim') === null);

$good = $uploader->uploadAudio(fakeMp3($tmp), 'shp_1_abc', 'ep_1_def');
ok('a normal shp_/ep_ upload still works', $good['success'] === true && file_exists("$audioDir/shp_1_abc/ep_1_def.mp3"));
ok('and can be read back', ($uploader->getAudioInfo('shp_1_abc', 'ep_1_def')['exists'] ?? false) === true);

echo "StreamingProxy only fetches from the WPFW hosts\n";
$proxy = new StreamingProxy();
foreach ([
    'http://10.0.1.5/',
    'http://localhost:3000/',
    'http://169.254.169.254/latest/meta-data/',
    'https://example.com/x.m3u',
    'https://docs.pacifica.org.evil.test/x.m3u',
    'file:///etc/passwd',
] as $url) {
    try {
        $proxy->fetchStreamUrl($url);
        ok("refuses $url", false);
    } catch (Throwable $e) {
        // Only the up-front host check counts. A network error means the
        // request was actually attempted, which is the bug.
        ok("refuses $url", $e instanceof InvalidArgumentException);
    }
}

exec('rm -rf ' . escapeshellarg($tmp));
echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
