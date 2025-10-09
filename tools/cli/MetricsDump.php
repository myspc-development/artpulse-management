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

        $route_filter  = isset($assoc_args['route']) ? (string) $assoc_args['route'] : null;
        $method_filter = isset($assoc_args['method']) ? strtoupper((string) $assoc_args['method']) : null;

        $entries = RequestMetrics::get_recent_entries($window);
        if (empty($entries)) {
            WP_CLI::line('No metrics recorded in the requested window.');
            return;
        }

        $entries = array_filter(
            $entries,
            static function (array $entry) use ($route_filter, $method_filter): bool {
                $route  = isset($entry['route']) ? (string) $entry['route'] : '';
                $method = isset($entry['method']) ? strtoupper((string) $entry['method']) : '';

                if (null !== $route_filter && $route !== $route_filter) {
                    return false;
                }

                if (null !== $method_filter && $method !== $method_filter) {
                    return false;
                }

                return true;
            }
        );

        if (empty($entries)) {
            WP_CLI::line('No metrics recorded in the requested window.');
            return;
        }

        $grouped = [];
        foreach ($entries as $entry) {
            $route = (string) ($entry['route'] ?? '');
            $method = strtoupper((string) ($entry['method'] ?? ''));
            if ('' === $method) {
                $method = 'UNKNOWN';
            }
            $key = $method . '|' . $route;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'route'     => $route,
                    'method'    => $method,
                    'durations' => [],
                    'statuses'  => [],
                ];
            }

            $grouped[$key]['durations'][] = isset($entry['duration_ms']) ? (float) $entry['duration_ms'] : 0.0;

            $bucket = self::status_bucket((int) ($entry['status'] ?? 0));
            if (!isset($grouped[$key]['statuses'][$bucket])) {
                $grouped[$key]['statuses'][$bucket] = 0;
            }
            $grouped[$key]['statuses'][$bucket]++;
        }

        $rows = [];
        foreach ($grouped as $item) {
            $durations = $item['durations'];
            sort($durations);

            $rows[] = [
                'method'   => $item['method'],
                'route'    => $item['route'],
                'count'    => count($durations),
                'p50'      => self::percentile($durations, 0.50),
                'p95'      => self::percentile($durations, 0.95),
                'statuses' => self::format_statuses($item['statuses']),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $route_compare = $a['route'] <=> $b['route'];
            if (0 !== $route_compare) {
                return $route_compare;
            }

            return $a['method'] <=> $b['method'];
        });

        \WP_CLI\Utils\format_items('table', $rows, ['method', 'route', 'count', 'p50', 'p95', 'statuses']);
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
