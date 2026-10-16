<?php
require_once __DIR__ . '/FeedHealthMonitor.php';

/**
 * ScanExitPolicy
 * Decides whether an auto-scan run counts as FAILED (non-zero exit code).
 *
 * The scan runs every 15 minutes as a Coolify scheduled task, and Coolify
 * emails the operator on every non-zero exit. The scan used to exit 1 whenever
 * any single feed failed, so one remote hiccup (a DNS timeout on someone
 * else's server) produced a "FAILED" email that read like a total outage,
 * while 49 of 50 feeds were fine.
 *
 * A run only fails for things worth an email:
 *  - a local write failed (our problem, not a remote blip);
 *  - a feed just hit the auto-disable threshold (down for that many scans in a
 *    row; reported once, on the run that disables it, not on every run after);
 *  - at least half of the feeds we fetched failed (our network or DNS is down).
 */
class ScanExitPolicy
{
    /**
     * True when this failure is the one that makes FeedHealthMonitor auto-disable
     * the feed. $podcast is the record as loaded BEFORE recordFailure() ran.
     */
    public static function isNewlyDisabled(array $podcast): bool
    {
        $before = (int)($podcast['consecutive_failures'] ?? 0);
        return $before + 1 === FeedHealthMonitor::THRESHOLD_AUTO_DISABLE_FAILURES;
    }

    public static function exitCode(int $fetched, int $fetchFailures, int $localFailures, int $newlyDisabled): int
    {
        if ($localFailures > 0 || $newlyDisabled > 0) {
            return 1;
        }
        if ($fetched > 0 && $fetchFailures * 2 >= $fetched) {
            return 1;
        }
        return 0;
    }
}
