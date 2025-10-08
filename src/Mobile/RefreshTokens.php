<?php

namespace ArtPulse\Mobile;

use WP_Error;

class RefreshTokens
{
    private const META_KEY = 'ap_mobile_refresh_tokens';
    private const DEFAULT_TTL = 30 * DAY_IN_SECONDS;
    private const MAX_TOKENS = 20;

    /**
     * Mint a refresh token for the provided user and device identifier.
     *
     * @return array{token:string,kid:string,expires:int}
     */
    public static function mint(int $user_id, ?string $device_id = null, ?int $ttl = null): array
    {
        $device  = self::normalize_device($device_id);
        $issued  = time();
        $ttl     = $ttl ?? self::DEFAULT_TTL;
        $expires = $issued + max(HOUR_IN_SECONDS, $ttl);

        $kid    = wp_generate_uuid4();
        $secret = self::generate_secret();
        $hash   = self::hash_token($kid, $secret);

        $records = self::get_records($user_id);
        $records = self::prune_records($records, $device);

        $records[] = [
            'kid'     => $kid,
            'hash'    => $hash,
            'device'  => $device,
            'expires' => $expires,
            'issued'  => $issued,
        ];

        if (count($records) > self::MAX_TOKENS) {
            $records = array_slice($records, -1 * self::MAX_TOKENS);
        }

        update_user_meta($user_id, self::META_KEY, array_values($records));

        return [
            'token'   => self::encode_token($kid, $user_id, $secret),
            'kid'     => $kid,
            'expires' => $expires,
        ];
    }

    /**
     * Validate a refresh token and return context for rotation.
     *
     * @return array{user_id:int,device_id:string,kid:string,expires:int}|WP_Error
     */
    public static function validate(string $refresh_token)
    {
        $parts = explode('.', trim($refresh_token));
        if (3 !== count($parts)) {
            return new WP_Error('ap_invalid_refresh', __('Invalid refresh token structure.', 'artpulse-management'), ['status' => 401]);
        }

        [$kid, $user_part, $secret] = $parts;

        if (!is_numeric($user_part)) {
            return new WP_Error('ap_invalid_refresh', __('Refresh token missing subject.', 'artpulse-management'), ['status' => 401]);
        }

        $user_id = (int) $user_part;
        $records = self::get_records($user_id);
        $now     = time();

        foreach ($records as $index => $record) {
            if (($record['kid'] ?? '') !== $kid) {
                continue;
            }

            if (($record['expires'] ?? 0) < $now) {
                unset($records[$index]);
                update_user_meta($user_id, self::META_KEY, array_values($records));

                return new WP_Error('ap_refresh_expired', __('Refresh token expired.', 'artpulse-management'), ['status' => 401]);
            }

            $expected = self::hash_token($kid, $secret);
            if (!hash_equals((string) ($record['hash'] ?? ''), $expected)) {
                unset($records[$index]);
                update_user_meta($user_id, self::META_KEY, array_values($records));

                return new WP_Error('ap_refresh_revoked', __('Refresh token revoked.', 'artpulse-management'), ['status' => 401]);
            }

            return [
                'user_id'   => $user_id,
                'device_id' => (string) ($record['device'] ?? 'unknown'),
                'kid'       => (string) $kid,
                'expires'   => (int) ($record['expires'] ?? $now),
            ];
        }

        return new WP_Error('ap_refresh_revoked', __('Refresh token revoked.', 'artpulse-management'), ['status' => 401]);
    }

    /**
     * Rotate a refresh token after successful validation.
     *
     * @param array{user_id:int,device_id:string,kid:string} $context
     *
     * @return array{token:string,kid:string,expires:int}
     */
    public static function rotate(array $context): array
    {
        $user_id  = (int) ($context['user_id'] ?? 0);
        $device   = self::normalize_device($context['device_id'] ?? '');
        $previous = (string) ($context['kid'] ?? '');

        $records = self::get_records($user_id);
        $records = array_values(array_filter(
            $records,
            static fn($record) => ($record['kid'] ?? '') !== $previous
        ));
        update_user_meta($user_id, self::META_KEY, $records);

        return self::mint($user_id, $device);
    }

    public static function revoke_all(int $user_id): void
    {
        delete_user_meta($user_id, self::META_KEY);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function get_records(int $user_id): array
    {
        $records = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($records)) {
            return [];
        }

        $now = time();
        $changed = false;

        foreach ($records as $index => $record) {
            if (($record['expires'] ?? 0) < $now) {
                unset($records[$index]);
                $changed = true;
            }
        }

        if ($changed) {
            update_user_meta($user_id, self::META_KEY, array_values($records));
        }

        return array_values($records);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private static function prune_records(array $records, string $device): array
    {
        $now = time();

        $records = array_filter(
            $records,
            static function ($record) use ($device, $now) {
                if (!is_array($record)) {
                    return false;
                }

                if (($record['expires'] ?? 0) < $now) {
                    return false;
                }

                return (string) ($record['device'] ?? '') !== $device;
            }
        );

        return array_values($records);
    }

    private static function normalize_device(?string $device_id): string
    {
        $device_id = $device_id ?? '';
        $device_id = sanitize_text_field($device_id);

        if ('' === $device_id) {
            $device_id = 'unknown';
        }

        return substr($device_id, 0, 191);
    }

    private static function generate_secret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function encode_token(string $kid, int $user_id, string $secret): string
    {
        return sprintf('%s.%d.%s', $kid, $user_id, $secret);
    }

    private static function hash_token(string $kid, string $secret): string
    {
        $key = wp_salt('ap_refresh');

        return hash_hmac('sha256', $kid . '|' . $secret, $key);
    }
}
