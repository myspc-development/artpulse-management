<?php

namespace ArtPulse\Mobile;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

class Cors
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        add_action('rest_api_init', [self::class, 'boot'], 15);
        self::$registered = true;
    }

    public static function boot(): void
    {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', [self::class, 'send_headers'], 15, 3);
        add_filter('rest_pre_dispatch', [self::class, 'guard'], 10, 3);
    }

    /**
     * @param mixed $served
     * @param mixed $result
     * @return mixed
     */
    public static function send_headers($served, $result, $request)
    {
        if (!$request instanceof WP_REST_Request || 0 !== strpos($request->get_route(), '/artpulse/v1/mobile')) {
            return $served;
        }

        $origin  = self::detect_origin($request);
        $allowed = self::get_allowed_origins();

        if (null !== $origin && in_array($origin['normalized'], $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin['raw']);
            header('Access-Control-Allow-Credentials: true');
        } else {
            header_remove('Access-Control-Allow-Origin');
            header_remove('Access-Control-Allow-Credentials');
        }

        header('Vary: Origin', false);

        if ('OPTIONS' === strtoupper($request->get_method())) {
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: ' . implode(', ', self::get_allowed_headers()));
            header('Access-Control-Max-Age: 600');
        }

        return $served;
    }

    /**
     * @param mixed $result
     * @return mixed
     */
    public static function guard($result, WP_REST_Server $server, WP_REST_Request $request)
    {
        if (0 !== strpos($request->get_route(), '/artpulse/v1/mobile')) {
            return $result;
        }

        $origin  = self::detect_origin($request);
        $allowed = self::get_allowed_origins();

        if (null === $origin || in_array($origin['normalized'], $allowed, true)) {
            return $result;
        }

        return new WP_Error('cors_forbidden', __('Origin is not allowed for this resource.', 'artpulse-management'), ['status' => 403]);
    }

    /**
     * @return array{raw: string, normalized: string}|null
     */
    private static function detect_origin(WP_REST_Request $request): ?array
    {
        $origin = $request->get_header('origin');

        if ('' === $origin && isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = (string) $_SERVER['HTTP_ORIGIN'];
        }

        return self::parse_origin($origin);
    }

    /**
     * @return array<int, string>
     */
    private static function get_allowed_origins(): array
    {
        $candidates = apply_filters('artpulse_mobile_allowed_origins', []);
        if (!is_array($candidates)) {
            $candidates = [];
        }

        $candidates = array_merge($candidates, self::get_configured_origins());

        $normalized = [];
        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $origin = self::normalize_origin($candidate);
            if (null === $origin) {
                continue;
            }

            $normalized[] = $origin;
        }

        $domain = apply_filters('artpulse_mobile_universal_link_domain', '');
        if (is_string($domain) && '' !== trim($domain)) {
            $domain = trim($domain);
            $domain = preg_replace('#^https?://#i', '', $domain);
            $domain = preg_replace('#/+$#', '', $domain);
            if ('' !== $domain) {
                $origin = self::normalize_origin('https://' . $domain);
                if (null !== $origin) {
                    $normalized[] = $origin;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<int, string>
     */
    private static function get_configured_origins(): array
    {
        $options = get_option('artpulse_settings', []);
        if (!is_array($options)) {
            return [];
        }

        $raw = $options['approved_mobile_origins'] ?? '';
        if (!is_string($raw) || '' === trim($raw)) {
            return [];
        }

        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];

        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, static fn ($line) => '' !== $line);

        return array_values($lines);
    }

    /**
     * @return array<int, string>
     */
    private static function get_allowed_headers(): array
    {
        $headers = apply_filters('artpulse_mobile_allowed_headers', [
            'Authorization',
            'Content-Type',
            'X-Requested-With',
            'X-Device-Id',
            'X-Forwarded-For',
        ]);

        if (!is_array($headers)) {
            $headers = [];
        }

        $headers = array_map('trim', array_filter(array_map('strval', $headers)));

        return array_values(array_unique($headers));
    }

    private static function parse_origin($origin): ?array
    {
        if (!is_string($origin)) {
            return null;
        }

        $origin = trim($origin);
        if ('' === $origin) {
            return null;
        }

        $normalized = self::normalize_origin($origin);
        if (null === $normalized) {
            return null;
        }

        return [
            'raw'        => rtrim($origin, '/'),
            'normalized' => $normalized,
        ];
    }

    private static function normalize_origin(string $origin): ?string
    {
        $filtered = filter_var($origin, FILTER_VALIDATE_URL);
        if (false === $filtered) {
            return null;
        }

        $filtered = rtrim($filtered, '/');
        $parts    = wp_parse_url($filtered);

        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }

        if ('https' !== strtolower($parts['scheme'])) {
            return null;
        }

        if (!empty($parts['user']) || !empty($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        if (!empty($parts['path']) && '/' !== $parts['path']) {
            return null;
        }

        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return 'https://' . $host . $port;
    }
}
