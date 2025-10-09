<?php

namespace ArtPulse\Tools\CLI;

use ArtPulse\Mobile\RefreshTokens;
use ArtPulse\Mobile\RequestMetrics;
use WP_CLI;

class Purge
{
    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $assoc_args
     */
    public static function handle(array $args, array $assoc_args): void
    {
        $purge_sessions = isset($assoc_args['sessions']);
        $purge_metrics  = isset($assoc_args['metrics']);

        if (!$purge_sessions && !$purge_metrics) {
            WP_CLI::error('Specify at least one of --sessions or --metrics.');
        }

        $messages = [];

        if ($purge_sessions) {
            $count = RefreshTokens::purge_inactive_sessions();
            $messages[] = sprintf('Inactive sessions removed: %d', $count);
        }

        if ($purge_metrics) {
            $results   = RequestMetrics::purge_stale_entries();
            $messages[] = sprintf(
                'Metrics log entries removed: %d; Routes trimmed: %d',
                (int) ($results['log_removed'] ?? 0),
                (int) ($results['summary_removed'] ?? 0)
            );
        }

        WP_CLI::success(implode(' | ', $messages));
    }
}
