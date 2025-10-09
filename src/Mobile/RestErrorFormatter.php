<?php

namespace ArtPulse\Mobile;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestErrorFormatter
{
    public const AUTH_EXPIRED        = 'ap_refresh_expired';
    public const REFRESH_REUSE       = 'refresh_reuse';
    public const CORS_FORBIDDEN      = 'cors_forbidden';
    public const GEO_INVALID_BOUNDS  = 'ap_geo_invalid';
    public const RATE_LIMITED        = 'ap_rate_limited';
    public const AUTH_REVOKED        = 'auth_revoked';

    private const STATUS_MAP = [
        'rest_invalid_param'      => 400,
        'rest_invalid_route'      => 404,
        'rest_post_invalid_id'    => 404,
        'rest_no_route'           => 404,
        'rest_no_callback'        => 404,
        'rest_user_cannot_view'   => 403,
        'rest_forbidden'          => 403,
        'rest_cannot_edit'        => 403,
        'rest_cookie_invalid_nonce' => 403,
        self::CORS_FORBIDDEN      => 403,
        'rest_not_logged_in'      => 401,
        'rest_invalid_token'      => 401,
        'ap_invalid_credentials'  => 401,
        'ap_invalid_token'        => 401,
        'ap_missing_token'        => 401,
        'ap_invalid_refresh'      => 401,
        self::AUTH_EXPIRED        => 401,
        self::AUTH_REVOKED        => 401,
        self::REFRESH_REUSE       => 401,
        self::GEO_INVALID_BOUNDS  => 400,
        self::RATE_LIMITED        => 429,
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        add_filter('rest_post_dispatch', [self::class, 'format'], 50, 3);
        self::$registered = true;
    }

    /**
     * @param WP_Error|WP_REST_Response $response
     * @return WP_Error|WP_REST_Response
     */
    public static function format($response, WP_REST_Server $server, WP_REST_Request $request)
    {
        if (0 !== strpos($request->get_route(), '/artpulse/v1/mobile')) {
            return $response;
        }

        if ($response instanceof WP_Error) {
            return self::from_error($response);
        }

        if ($response instanceof WP_REST_Response) {
            $data = $response->get_data();
            if ($data instanceof WP_Error) {
                return self::from_error($data);
            }
        }

        return $response;
    }

    private static function from_error(WP_Error $error): WP_REST_Response
    {
        $primary_code = $error->get_error_codes()[0] ?? 'ap_error';
        $status       = self::determine_status($error, $primary_code);
        $message      = $error->get_error_message($primary_code);
        $details      = self::collect_details($error);

        $response = new WP_REST_Response([
            'code'    => $primary_code,
            'message' => $message,
            'details' => $details,
        ], $status);

        return $response;
    }

    private static function determine_status(WP_Error $error, string $primary_code): int
    {
        foreach ($error->get_error_codes() as $code) {
            $data = $error->get_error_data($code);
            if (is_array($data) && isset($data['status'])) {
                return (int) $data['status'];
            }

            if (is_int($data) && $data >= 100 && $data < 600) {
                return $data;
            }
        }

        if (isset(self::STATUS_MAP[$primary_code])) {
            return self::STATUS_MAP[$primary_code];
        }

        foreach ($error->get_error_codes() as $code) {
            if (isset(self::STATUS_MAP[$code])) {
                return self::STATUS_MAP[$code];
            }
        }

        return 400;
    }

    private static function collect_details(WP_Error $error): array
    {
        $details = [];

        foreach ($error->get_error_codes() as $code) {
            $messages = $error->get_error_messages($code);
            $data     = $error->get_error_data($code);

            $entry = [];
            if (!empty($messages)) {
                $entry['messages'] = array_values($messages);
            }

            if (is_array($data)) {
                $entry['data'] = self::filter_status($data);
            } elseif (null !== $data) {
                $entry['data'] = $data;
            }

            $details[$code] = $entry;
        }

        return $details;
    }

    private static function filter_status(array $data): array
    {
        if (array_key_exists('status', $data)) {
            unset($data['status']);
        }

        return $data;
    }
}
