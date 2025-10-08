<?php

namespace ArtPulse\Tools\CLI;

use ArtPulse\Mobile\RequestMetrics;
use WP_CLI;

class MetricsDump
{
    /**
     * @param array<int, string> $args
     * @param array<string, string> $assoc_args
     */
    public static function handle(array $args, array $assoc_args): void
    {
        $window_arg = $assoc_args['last'] ?? '15m';
        $window     = self::parse_interval($window_arg);
        if ($window <= 0) {
            WP_CLI::error('Invalid --last interval. Use formats like 15m, 1h, or 900s.');
        }

        $entries = RequestMetrics::get_recent_entries($window);
        if (empty($entries)) {
            WP_CLI::line('No metrics recorded in the requested window.');
            return;
        }

        $grouped = [];
        foreach ($entries as $entry) {
            $route = (string) ($entry['route'] ?? '');
            if (!isset($grouped[$route])) {
                $grouped[$route] = [];
            }
            $grouped[$route][] = $entry;
        }

        $rows = [];
        foreach ($grouped as $route => $items) {
            $durations = array_map(static function (array $item): float {
                return isset($item['duration_ms']) ? (float) $item['duration_ms'] : 0.0;
            }, $items);
            sort($durations);

            $statuses = [];
            foreach ($items as $item) {
                $bucket = self::status_bucket((int) ($item['status'] ?? 0));
                if (!isset($statuses[$bucket])) {
                    $statuses[$bucket] = 0;
                }
                $statuses[$bucket]++;
            }

            $rows[] = [
                'route' => $route,
                'count' => count($items),
                'p50'   => self::percentile($durations, 0.50),
                'p95'   => self::percentile($durations, 0.95),
                'statuses' => self::format_statuses($statuses),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return $a['route'] <=> $b['route'];
        });

        \WP_CLI\Utils\format_items('table', $rows, ['route', 'count', 'p50', 'p95', 'statuses']);
    }

    private static function parse_interval(string $value): int
    {
        $value = trim($value);
        if ('' === $value) {
            return 0;
        }

        if (preg_match('/^(\d+)([smhd])$/i', $value, $matches)) {
            $amount = (int) $matches[1];
            $unit   = strtolower($matches[2]);
            switch ($unit) {
                case 's':
                    return $amount;
                case 'm':
                    return $amount * MINUTE_IN_SECONDS;
                case 'h':
                    return $amount * HOUR_IN_SECONDS;
                case 'd':
                    return $amount * DAY_IN_SECONDS;
            }
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param array<int, float> $values
     */
    private static function percentile(array $values, float $percent): float
    {
        $count = count($values);
        if (0 === $count) {
            return 0.0;
        }

        $index = ($count - 1) * $percent;
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return round($values[$lower], 3);
        }

        $weight = $index - $lower;
        $result = $values[$lower] * (1 - $weight) + $values[$upper] * $weight;

        return round($result, 3);
    }

    /**
     * @param array<string, int> $statuses
     */
    private static function format_statuses(array $statuses): string
    {
        ksort($statuses);
        $parts = [];
        foreach ($statuses as $bucket => $count) {
            $parts[] = sprintf('%s:%d', $bucket, $count);
        }

        return implode(', ', $parts);
    }

    private static function status_bucket(int $status): string
    {
        $hundreds = (int) floor($status / 100);
        if ($hundreds < 1 || $hundreds > 5) {
            $hundreds = 5;
        }

        return $hundreds . 'xx';
    }
}
