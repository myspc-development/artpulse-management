<?php

namespace ArtPulse\Mobile;

use WP_REST_Request;

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

        $origin  = self::get_origin();
        $allowed = self::get_allowed_origins();

        if ($origin && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
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

    private static function get_origin(): ?string
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : '';
        if ('' === $origin) {
            return null;
        }

        $origin = esc_url_raw($origin);

        if ('' === $origin) {
            return null;
        }

        return $origin;
    }

    /**
     * @return array<int, string>
     */
    private static function get_allowed_origins(): array
    {
        $origins = apply_filters('artpulse_mobile_allowed_origins', []);
        if (!is_array($origins)) {
            $origins = [];
        }

        $domain = apply_filters('artpulse_mobile_universal_link_domain', '');
        if (is_string($domain) && '' !== trim($domain)) {
            $domain = trim($domain);
            $domain = preg_replace('#^https?://#', '', $domain);
            $origins[] = 'https://' . $domain;
        }

        $cleaned = [];
        foreach ($origins as $origin) {
            if (!is_string($origin)) {
                continue;
            }

            $origin = trim($origin);
            if ('' === $origin) {
                continue;
            }

            if (false !== strpos($origin, '*')) {
                continue;
            }

            $origin = esc_url_raw($origin);
            if ('' === $origin) {
                continue;
            }

            if (0 !== strpos($origin, 'https://')) {
                continue;
            }

            $cleaned[] = rtrim($origin, '/');
        }

        return array_values(array_unique($cleaned));
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
}
