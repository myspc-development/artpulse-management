<?php

namespace ArtPulse\Mobile;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class RequestMetrics
{
    private const OPTION_LOG     = 'ap_mobile_metrics_log';
    private const OPTION_SUMMARY = 'ap_mobile_metrics_summary';
    private const MAX_LOG_ENTRIES = 500;
    private const MAX_LATENCIES   = 200;

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        add_filter('rest_pre_dispatch', [self::class, 'mark_start'], 0, 3);
        add_filter('rest_post_dispatch', [self::class, 'record'], 99, 3);
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
            if (!is_array($data)) {
                continue;
            }

            $latencies = isset($data['latencies']) && is_array($data['latencies']) ? array_map('floatval', $data['latencies']) : [];
            if (empty($latencies)) {
                continue;
            }

            sort($latencies);

            $formatted[$route] = [
                'updated_at' => isset($data['updated_at']) ? (int) $data['updated_at'] : time(),
                'count'      => count($latencies),
                'p50'        => self::percentile($latencies, 0.50),
                'p95'        => self::percentile($latencies, 0.95),
                'statuses'   => isset($data['statuses']) && is_array($data['statuses']) ? $data['statuses'] : [],
            ];
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
        $status  = (int) ($entry['status'] ?? 0);
        $summary = get_option(self::OPTION_SUMMARY, []);
        if (!is_array($summary)) {
            $summary = [];
        }

        if (!isset($summary[$route])) {
            $summary[$route] = [
                'latencies' => [],
                'statuses'  => [],
                'updated_at'=> 0,
            ];
        }

        $latencies = isset($summary[$route]['latencies']) && is_array($summary[$route]['latencies'])
            ? $summary[$route]['latencies']
            : [];
        $latencies[] = (float) ($entry['duration_ms'] ?? 0.0);
        if (count($latencies) > self::MAX_LATENCIES) {
            $latencies = array_slice($latencies, -1 * self::MAX_LATENCIES);
        }
        $summary[$route]['latencies'] = $latencies;

        $bucket = self::status_bucket($status);
        if (!isset($summary[$route]['statuses']) || !is_array($summary[$route]['statuses'])) {
            $summary[$route]['statuses'] = [];
        }
        if (!isset($summary[$route]['statuses'][$bucket])) {
            $summary[$route]['statuses'][$bucket] = 0;
        }
        $summary[$route]['statuses'][$bucket]++;
        $summary[$route]['updated_at'] = (int) ($entry['timestamp'] ?? time());

        update_option(self::OPTION_SUMMARY, $summary, false);
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
