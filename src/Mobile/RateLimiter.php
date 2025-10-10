<?php

namespace ArtPulse\Mobile;

use ArtPulse\Core\RateLimitHeaders;
use WP_Error;

class RateLimiter
{
    private const WINDOW = 60;
    private const WRITE_LIMIT = 60;
    private const READ_LIMIT = 240;

    private static bool $registered = false;
    private static ?array $pending_headers = null;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        add_filter('rest_pre_dispatch', [self::class, 'handle_request'], 10, 3);
        add_filter('rest_post_dispatch', [self::class, 'inject_headers'], 99, 3);
        self::$registered = true;
    }

    /**
     * @param mixed $result
     * @return mixed
     */
    public static function handle_request($result, $server, $request)
    {
        if (!$request instanceof \WP_REST_Request) {
            return $result;
        }

        if (0 !== strpos($request->get_route(), '/artpulse/v1/mobile')) {
            return $result;
        }

        $is_write = in_array(strtoupper($request->get_method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $limit    = $is_write ? self::WRITE_LIMIT : self::READ_LIMIT;
        $window   = self::WINDOW;

        $limit  = (int) apply_filters('artpulse_mobile_rate_limit', $limit, $request, $is_write);
        $window = (int) apply_filters('artpulse_mobile_rate_window', $window, $request, $is_write);

        $route  = $request->get_route();
        $bucket = ($is_write ? 'write' : 'read') . ':' . trim($route, '/');

        $error = self::enforce($bucket, $limit, $window, $request);
        if ($error instanceof WP_Error) {
            return $error;
        }

        return $result;
    }

    public static function enforce(string $bucket, int $limit = 15, int $window = 60, ?\WP_REST_Request $request = null): ?WP_Error
    {
        $now     = time();
        $user_id = get_current_user_id();
        $route   = $request instanceof \WP_REST_Request ? $request->get_route() : '';
        $ip      = $request instanceof \WP_REST_Request ? $request->get_header('X-Forwarded-For') : '';
        if (!$ip) {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        } else {
            $ip = trim(explode(',', (string) $ip)[0]);
        }

        $keys = [];
        if ($user_id) {
            $keys[] = 'ap_rl_u_' . md5($bucket . '|' . $user_id);
        }

        if ($ip) {
            $keys[] = 'ap_rl_ip_' . md5($bucket . '|' . $ip);
        }

        if (empty($keys)) {
            $keys[] = 'ap_rl_global_' . md5($bucket);
        }

        $remaining   = $limit;
        $reset_at    = $now + $window;
        $retry_after = 0;

        foreach ($keys as $key) {
            $state = get_transient($key);

            if (!is_array($state) || !isset($state['start']) || ($now - (int) $state['start']) >= $window) {
                $state = ['start' => $now, 'count' => 0];
            }

            $reset_at = min($reset_at, (int) $state['start'] + $window);

            $count = (int) ($state['count'] ?? 0);
            if ($count >= $limit) {
                $retry_after = max($retry_after, max(1, ($state['start'] + $window) - $now));
                $remaining   = 0;
                continue;
            }

            $count++;
            $state['count'] = $count;
            $remaining = min($remaining, max(0, $limit - $count));
            set_transient($key, $state, $window);
        }

        $remaining = max(0, $remaining);
        $headers   = RateLimitHeaders::build($limit, $remaining, $reset_at, $retry_after > 0 ? $retry_after : null);

        self::$pending_headers = [
            'limit'      => $limit,
            'remaining'  => $remaining,
            'reset'      => $reset_at,
            'headers'    => $headers,
        ];

        if ($retry_after > 0) {
            self::$pending_headers['remaining']   = 0;
            self::$pending_headers['retry_after'] = $retry_after;
            self::$pending_headers['headers']     = RateLimitHeaders::build($limit, 0, $reset_at, $retry_after);
            $log_context = [
                'event'       => 'mobile_rate_limited',
                'route'       => $route,
                'user_id'     => $user_id,
                'ip'          => $ip,
                'limit'       => $limit,
                'remaining'   => (int) self::$pending_headers['remaining'],
                'retry_after' => $retry_after,
                'ts'          => $now,
            ];

            /**
             * Fires when a mobile rate limit is triggered.
             *
             * @param array<string, mixed> $log_context
             * @param string               $bucket
             * @param int                  $window
             */
            do_action('artpulse/mobile/rate_limited', $log_context, $bucket, $window);

            if (function_exists('wp_json_encode')) {
                $log = wp_json_encode($log_context);

                if ($log) {
                    error_log($log); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
            }

            return new WP_Error(
                RestErrorFormatter::RATE_LIMITED,
                __('Too many requests. Please slow down.', 'artpulse-management'),
                [
                    'status'      => 429,
                    'retry_after' => $retry_after,
                ]
            );
        }

        return null;
    }

    public static function inject_headers($response, $server, $request)
    {
        if (!$request instanceof \WP_REST_Request || 0 !== strpos($request->get_route(), '/artpulse/v1/mobile')) {
            self::$pending_headers = null;
            return $response;
        }

        if (!self::$pending_headers) {
            return $response;
        }

        if ($response instanceof \WP_Error) {
            $response = $server->error_to_response($response);
        } elseif (!$response instanceof \WP_HTTP_Response) {
            $response = rest_ensure_response($response);
        }

        $headers = self::$pending_headers;

        $header_map = $headers['headers'] ?? [];

        if ($response instanceof \WP_REST_Response) {
            foreach ($header_map as $name => $value) {
                $response->header($name, (string) $value);
            }
        } elseif ($response instanceof \WP_HTTP_Response) {
            $existing_headers = $response->get_headers();
            foreach ($header_map as $name => $value) {
                $existing_headers[$name] = (string) $value;
            }

            $response->set_headers($existing_headers);
        }

        self::$pending_headers = null;

        return $response;
    }
}
