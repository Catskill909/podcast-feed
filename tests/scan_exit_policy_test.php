<?php

/**
 * Auto-scan exit code tests.
 *
 * Run: php tests/scan_exit_policy_test.php
 *
 * The scan is a Coolify scheduled task (every 15 min) and Coolify emails a
 * "FAILED" alert on every non-zero exit. The failure mode these guard against:
 * the scan exited 1 when any single feed failed, so one remote DNS timeout
 * (2026-10-15, KPFK - Enfoque Latino) sent an alert that read like a total
 * outage while 49 of 50 feeds were fine.
 *
 * These cover the class - transient remote failures of any count below the
 * outage threshold must stay quiet, while each condition that does deserve an
 * email (local write failure, a feed being auto-disabled, a mass failure)
 * fails the run - plus the "disabled" transition, which must alert once and
 * not on every later run.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/ScanExitPolicy.php';

$failures = 0;

function check(string $label, $expected, $actual): void
{
    global $failures;
    if ($expected === $actual) {
        echo "PASS  $label\n";
    } else {
        $failures++;
        echo "FAIL  $label: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

// --- Remote failures below the outage threshold stay quiet (the reported bug and its siblings)
check('48 fetched, 0 failed', 0, ScanExitPolicy::exitCode(48, 0, 0, 0));
check('48 fetched, 1 transient failure (the 2026-10-15 alert)', 0, ScanExitPolicy::exitCode(48, 1, 0, 0));
for ($n = 1; $n <= 23; $n++) {
    check("48 fetched, $n failed: below half, quiet", 0, ScanExitPolicy::exitCode(48, $n, 0, 0));
}

// --- Mass failure means our own network/DNS is down: alert
check('48 fetched, 24 failed (half)', 1, ScanExitPolicy::exitCode(48, 24, 0, 0));
check('48 fetched, all failed', 1, ScanExitPolicy::exitCode(48, 48, 0, 0));
check('1 fetched, 1 failed', 1, ScanExitPolicy::exitCode(1, 1, 0, 0));
check('nothing fetched (all self-hosted)', 0, ScanExitPolicy::exitCode(0, 0, 0, 0));

// --- Our own problems and auto-disables always alert, even with no fetch failures
check('local write failure', 1, ScanExitPolicy::exitCode(48, 0, 1, 0));
check('feed auto-disabled this run', 1, ScanExitPolicy::exitCode(48, 1, 0, 1));

// --- "Newly disabled" fires exactly once: on the failure that reaches the threshold
$t = FeedHealthMonitor::THRESHOLD_AUTO_DISABLE_FAILURES;
for ($before = 0; $before <= $t + 3; $before++) {
    check("consecutive_failures $before -> " . ($before + 1), $before + 1 === $t,
        ScanExitPolicy::isNewlyDisabled(['consecutive_failures' => (string)$before]));
}
check('consecutive_failures missing (new feed)', $t === 1, ScanExitPolicy::isNewlyDisabled([]));

echo $failures === 0 ? "\nAll passed\n" : "\n$failures FAILED\n";
exit($failures === 0 ? 0 : 1);
