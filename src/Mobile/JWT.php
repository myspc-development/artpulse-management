<?php

namespace ArtPulse\Mobile;

use WP_Error;

class JWT
{
    private const ALG = 'HS256';
    private const DEFAULT_TTL = DAY_IN_SECONDS;

    /**
     * Issue a signed JWT for the given user ID.
     *
     * @return array{token:string,expires:int}
     */
    public static function issue(int $user_id, ?int $ttl = null): array
    {
        $issued_at = time();
        $ttl       = $ttl ?? self::DEFAULT_TTL;
        $expires   = $issued_at + max(60, $ttl);

        $payload = [
            'iss' => get_site_url(),
            'sub' => $user_id,
            'iat' => $issued_at,
            'nbf' => $issued_at - 5,
            'exp' => $expires,
            'jti' => wp_generate_uuid4(),
        ];

        return [
            'token'   => self::encode($payload),
            'expires' => $expires,
        ];
    }

    /**
     * Validate a JWT and return its payload.
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function validate(string $token)
    {
        $parts = explode('.', $token);
        if (3 !== count($parts)) {
            return new WP_Error('ap_invalid_token', __('Invalid token structure.', 'artpulse-management'), ['status' => 401]);
        }

        [$header64, $payload64, $signature64] = $parts;
        $header    = json_decode(self::base64url_decode($header64) ?: '', true);
        $payload   = json_decode(self::base64url_decode($payload64) ?: '', true);
        $signature = self::base64url_decode($signature64);

        if (!is_array($header) || !is_array($payload) || !is_string($signature)) {
            return new WP_Error('ap_invalid_token', __('Malformed token.', 'artpulse-management'), ['status' => 401]);
        }

        if (empty($header['alg']) || self::ALG !== $header['alg']) {
            return new WP_Error('ap_invalid_token', __('Unsupported token algorithm.', 'artpulse-management'), ['status' => 401]);
        }

        $expected = hash_hmac('sha256', $header64 . '.' . $payload64, self::get_secret(), true);
        if (!hash_equals($expected, $signature)) {
            return new WP_Error('ap_invalid_token', __('Token signature mismatch.', 'artpulse-management'), ['status' => 401]);
        }

        $now = time();
        if (!empty($payload['nbf']) && $payload['nbf'] > $now + 30) {
            return new WP_Error('ap_invalid_token', __('Token not yet valid.', 'artpulse-management'), ['status' => 401]);
        }

        if (!empty($payload['exp']) && $payload['exp'] < $now) {
            return new WP_Error('ap_invalid_token', __('Token expired.', 'artpulse-management'), ['status' => 401]);
        }

        return $payload;
    }

    /**
     * Encode payload into JWT string.
     *
     * @param array<string, mixed> $payload
     */
    private static function encode(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => self::ALG];

        $segments = [
            self::base64url_encode(wp_json_encode($header)),
            self::base64url_encode(wp_json_encode($payload)),
        ];

        $signature   = hash_hmac('sha256', implode('.', $segments), self::get_secret(), true);
        $segments[]  = self::base64url_encode($signature);

        return implode('.', $segments);
    }

    private static function get_secret(): string
    {
        if (defined('ARTPULSE_JWT_SECRET') && ARTPULSE_JWT_SECRET) {
            return (string) ARTPULSE_JWT_SECRET;
        }

        if (defined('AUTH_KEY') && AUTH_KEY) {
            return (string) AUTH_KEY;
        }

        return wp_salt('auth');
    }

    private static function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode(string $data): string|false
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return false === $decoded ? false : $decoded;
    }
}
