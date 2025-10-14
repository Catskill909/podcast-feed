<?php
/**
 * Test the display fix - calendar days vs elapsed time
 */

echo "=== DISPLAY LOGIC TEST ===\n\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

$testCases = [
    [
        'name' => 'Labor Radio (23h ago, but yesterday)',
        'episode_date' => '2025-10-13 16:00:00',
        'expected_old' => 'Today',
        'expected_new' => 'Yesterday'
    ],
    [
        'name' => 'WJFF Radio (today)',
        'episode_date' => '2025-10-14 14:00:00',
        'expected_old' => 'Today',
        'expected_new' => 'Today'
    ],
    [
        'name' => '3rd & Fairfax (5 days ago)',
        'episode_date' => '2025-10-09 22:31:00',
        'expected_old' => '4 days ago',
        'expected_new' => '5 days ago'
    ],
    [
        'name' => 'Edge Case: Yesterday 11:30 PM (16h ago)',
        'episode_date' => '2025-10-13 23:30:00',
        'expected_old' => 'Today',
        'expected_new' => 'Yesterday'
    ],
    [
        'name' => 'Edge Case: Today 12:01 AM (15h ago)',
        'episode_date' => '2025-10-14 00:01:00',
        'expected_old' => 'Today',
        'expected_new' => 'Today'
    ],
];

foreach ($testCases as $test) {
    echo "Test: {$test['name']}\n";
    echo "  Episode Date: {$test['episode_date']}\n";
    
    $epDate = strtotime($test['episode_date']);
    $now = time();
    
    // OLD METHOD (elapsed time)
    $diffOld = $now - $epDate;
    $hoursOld = floor($diffOld / 3600);
    if ($diffOld < 86400) {
        $resultOld = 'Today';
    } elseif ($diffOld < 172800) {
        $resultOld = 'Yesterday';
    } else {
        $daysOld = floor($diffOld / 86400);
        $resultOld = "$daysOld days ago";
    }
    
    // NEW METHOD (calendar days)
    $epDay = strtotime(date('Y-m-d', $epDate));
    $today = strtotime(date('Y-m-d', $now));
    $daysDiff = (int)floor(($today - $epDay) / 86400);
    
    if ($daysDiff < 0) {
        $resultNew = 'Today';
    } elseif ($daysDiff == 0) {
        $resultNew = 'Today';
    } elseif ($daysDiff == 1) {
        $resultNew = 'Yesterday';
    } elseif ($daysDiff < 7) {
        $resultNew = "$daysDiff days ago";
    } else {
        $resultNew = date('M j, Y', $epDate);
    }
    
    echo "  OLD (elapsed time): $resultOld ({$hoursOld}h ago)\n";
    echo "    Expected: {$test['expected_old']}\n";
    echo "    Status: " . ($resultOld === $test['expected_old'] ? '✅ PASS' : '❌ FAIL') . "\n";
    echo "  NEW (calendar days): $resultNew\n";
    echo "    Expected: {$test['expected_new']}\n";
    echo "    Status: " . ($resultNew === $test['expected_new'] ? '✅ PASS' : '❌ FAIL') . "\n";
    echo "\n";
}
