<?php

namespace ArtPulse\Mobile;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class RequestMetrics
{
    private const OPTION_LOG       = 'ap_mobile_metrics_log';
    private const OPTION_SUMMARY   = 'ap_mobile_metrics_summary';
    private const MAX_LOG_ENTRIES  = 500;
    private const MAX_LATENCIES    = 200;
    private const LOG_TTL          = 14 * DAY_IN_SECONDS;
    private const METHOD_UNKNOWN   = 'UNKNOWN';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        add_filter('rest_pre_dispatch', [self::class, 'mark_start'], 0, 3);
        add_filter('rest_post_dispatch', [self::class, 'record'], 99, 3);
        add_action('ap_mobile_purge_metrics', [self::class, 'purge_stale_entries']);
        self::$registered = true;
    }

    /**
     * @param mixed $result
     * @return mixed
     */
    public static function mark_start($result, $server, $request)
    {
        if ($request instanceof WP_REST_Request && 0 === strpos($request->get_route(), '/artpulse/v1/mobile')) {
            $request->set_attribute('_ap_metric_start', microtime(true));
        }

        return $result;
    }

    /**
     * Purge metrics log entries and summaries older than the configured TTL.
     *
     * @return array{log_removed:int,summary_removed:int,log_remaining:int,summary_remaining:int}
     */
    public static function purge_stale_entries(?int $ttl = null): array
    {
        $ttl = $ttl ?? self::LOG_TTL;
        if ($ttl <= 0) {
            return [
                'log_removed'       => 0,
                'summary_removed'   => 0,
                'log_remaining'     => 0,
                'summary_remaining' => 0,
            ];
        }

        $cutoff = time() - $ttl;

        $log_removed   = 0;
        $log_remaining = 0;
        $log           = get_option(self::OPTION_LOG, []);
        if (is_array($log) && !empty($log)) {
            $filtered = [];
            foreach ($log as $entry) {
                if (!is_array($entry)) {
                    $log_removed++;
                    continue;
                }

                $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;
                if ($timestamp && $timestamp >= $cutoff) {
                    $filtered[] = $entry;
                    continue;
                }

                $log_removed++;
            }

            $log_remaining = count($filtered);

            if (empty($filtered)) {
                delete_option(self::OPTION_LOG);
            } elseif ($filtered !== $log) {
                update_option(self::OPTION_LOG, array_values($filtered), false);
            }
        }

        $summary_removed   = 0;
        $summary_remaining = 0;
        $summary           = get_option(self::OPTION_SUMMARY, []);
        if (is_array($summary) && !empty($summary)) {
            $updated_summary = [];
            foreach ($summary as $route => $data) {
                $methods     = self::normalize_route_summary($data);
                $kept        = [];
                foreach ($methods as $method => $method_data) {
                    $updated_at = isset($method_data['updated_at']) ? (int) $method_data['updated_at'] : 0;
                    if ($updated_at && $updated_at >= $cutoff) {
                        $kept[$method] = $method_data;
                        continue;
                    }

                    $summary_removed++;
                }

                if (!empty($kept)) {
                    $updated_summary[$route] = $kept;
                    $summary_remaining      += count($kept);
                }
            }

            if (0 === $summary_remaining) {
                delete_option(self::OPTION_SUMMARY);
            } elseif ($updated_summary !== $summary) {
                update_option(self::OPTION_SUMMARY, $updated_summary, false);
            }
        }

        return [
            'log_removed'       => $log_removed,
            'summary_removed'   => $summary_removed,
            'log_remaining'     => $log_remaining,
            'summary_remaining' => $summary_remaining,
        ];
    }

    /**
     * @param mixed $response
     * @return mixed
     */
    public static function record($response, $server, $request)
    {
        if (!$request instanceof WP_REST_Request || 0 !== strpos($request->get_route(), '/artpulse/v1/mobile')) {
            return $response;
        }

        $start = (float) ($request->get_attribute('_ap_metric_start') ?? microtime(true));
        $duration = max(0.0, (microtime(true) - $start) * 1000);
        $status   = self::resolve_status_code($response);
        $method   = strtoupper($request->get_method());
        $route    = $request->get_route();
        $now      = time();

        $entry = [
            'timestamp'   => $now,
            'route'       => $route,
            'method'      => $method,
            'status'      => $status,
            'duration_ms' => round($duration, 3),
        ];

        self::store_log_entry($entry);
        self::update_summary($entry);

        return $response;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_recent_entries(int $window): array
    {
        $window = max(1, $window);
        $cutoff = time() - $window;
        $log    = get_option(self::OPTION_LOG, []);
        if (!is_array($log)) {
            return [];
        }

        return array_values(array_filter(
            $log,
            static function (array $entry) use ($cutoff): bool {
                return isset($entry['timestamp']) && (int) $entry['timestamp'] >= $cutoff;
            }
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function get_summary(): array
    {
        $summary = get_option(self::OPTION_SUMMARY, []);
        if (!is_array($summary)) {
            return [];
        }

        $formatted = [];
        foreach ($summary as $route => $data) {
            $methods = self::normalize_route_summary($data);
            if (empty($methods)) {
                continue;
            }

            foreach ($methods as $method => $method_data) {
                $latencies = isset($method_data['latencies']) ? array_map('floatval', $method_data['latencies']) : [];
                if (empty($latencies)) {
                    continue;
                }

                sort($latencies);

                if (!isset($formatted[$route])) {
                    $formatted[$route] = [];
                }

                $formatted[$route][$method] = [
                    'method'     => $method,
                    'updated_at' => isset($method_data['updated_at']) ? (int) $method_data['updated_at'] : time(),
                    'count'      => count($latencies),
                    'p50'        => self::percentile($latencies, 0.50),
                    'p95'        => self::percentile($latencies, 0.95),
                    'statuses'   => isset($method_data['statuses']) && is_array($method_data['statuses']) ? $method_data['statuses'] : [],
                ];
            }
        }

        return $formatted;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function store_log_entry(array $entry): void
    {
        $log = get_option(self::OPTION_LOG, []);
        if (!is_array($log)) {
            $log = [];
        }

        $log[] = $entry;

        if (count($log) > self::MAX_LOG_ENTRIES) {
            $log = array_slice($log, -1 * self::MAX_LOG_ENTRIES);
        }

        update_option(self::OPTION_LOG, $log, false);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function update_summary(array $entry): void
    {
        $route   = (string) ($entry['route'] ?? '');
        $method  = strtoupper((string) ($entry['method'] ?? ''));
        if ('' === $method) {
            $method = self::METHOD_UNKNOWN;
        }
        $status  = (int) ($entry['status'] ?? 0);
        $summary = get_option(self::OPTION_SUMMARY, []);
        if (!is_array($summary)) {
            $summary = [];
        }

        $route_summary = $summary[$route] ?? [];
        $route_summary = self::normalize_route_summary($route_summary);

        if (!isset($route_summary[$method])) {
            $route_summary[$method] = [
                'latencies' => [],
                'statuses'  => [],
                'updated_at'=> 0,
            ];
        }

        $latencies = isset($route_summary[$method]['latencies']) && is_array($route_summary[$method]['latencies'])
            ? $route_summary[$method]['latencies']
            : [];
        $latencies[] = (float) ($entry['duration_ms'] ?? 0.0);
        if (count($latencies) > self::MAX_LATENCIES) {
            $latencies = array_slice($latencies, -1 * self::MAX_LATENCIES);
        }
        $route_summary[$method]['latencies'] = $latencies;

        $bucket = self::status_bucket($status);
        if (!isset($route_summary[$method]['statuses']) || !is_array($route_summary[$method]['statuses'])) {
            $route_summary[$method]['statuses'] = [];
        }
        if (!isset($route_summary[$method]['statuses'][$bucket])) {
            $route_summary[$method]['statuses'][$bucket] = 0;
        }
        $route_summary[$method]['statuses'][$bucket]++;
        $route_summary[$method]['updated_at'] = (int) ($entry['timestamp'] ?? time());

        $summary[$route] = $route_summary;

        update_option(self::OPTION_SUMMARY, $summary, false);
    }

    /**
     * @param mixed $data
     * @return array<string, array<string, mixed>>
     */
    private static function normalize_route_summary($data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $looks_like_legacy = isset($data['latencies']) || isset($data['statuses']) || isset($data['updated_at']);
        if ($looks_like_legacy) {
            return [
                self::METHOD_UNKNOWN => self::sanitize_method_summary($data),
            ];
        }

        $normalized = [];
        foreach ($data as $method => $method_data) {
            if (!is_string($method)) {
                continue;
            }

            if (!is_array($method_data)) {
                continue;
            }

            $normalized[strtoupper($method)] = self::sanitize_method_summary($method_data);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function sanitize_method_summary(array $data): array
    {
        $latencies = [];
        if (isset($data['latencies']) && is_array($data['latencies'])) {
            foreach ($data['latencies'] as $latency) {
                $latencies[] = (float) $latency;
            }
        }

        $statuses = [];
        if (isset($data['statuses']) && is_array($data['statuses'])) {
            foreach ($data['statuses'] as $bucket => $count) {
                if (!is_string($bucket)) {
                    continue;
                }

                $statuses[$bucket] = (int) $count;
            }
        }

        return [
            'latencies'  => $latencies,
            'statuses'   => $statuses,
            'updated_at' => isset($data['updated_at']) ? (int) $data['updated_at'] : 0,
        ];
    }

    private static function resolve_status_code($response): int
    {
        if ($response instanceof WP_REST_Response) {
            return (int) $response->get_status();
        }

        if ($response instanceof WP_Error) {
            $data = $response->get_error_data();
            if (is_array($data) && isset($data['status'])) {
                return (int) $data['status'];
            }
        }

        return 500;
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

    private static function status_bucket(int $status): string
    {
        $hundreds = (int) floor($status / 100);
        if ($hundreds < 1 || $hundreds > 5) {
            $hundreds = 5;
        }

        return $hundreds . 'xx';
    }
}
