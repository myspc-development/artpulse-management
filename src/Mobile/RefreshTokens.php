<?php

namespace ArtPulse\Mobile;

use WP_Error;
use WP_User;

class RefreshTokens
{
    private const META_KEY        = 'ap_mobile_refresh_tokens';
    private const DEFAULT_TTL     = 30 * DAY_IN_SECONDS;
    private const MAX_TOKENS      = 20;
    private const HISTORY_TTL     = 14 * DAY_IN_SECONDS;
    private const HISTORY_LIMIT   = 40;

    private static bool $hooks_registered = false;

    /**
     * Register hooks for global invalidation events.
     */
    public static function register_hooks(): void
    {
        if (self::$hooks_registered) {
            return;
        }

        add_action('password_reset', [self::class, 'handle_password_reset'], 10, 2);
        add_action('after_password_reset', [self::class, 'handle_password_reset'], 10, 2);
        add_action('profile_update', [self::class, 'handle_profile_update'], 10, 2);

        self::$hooks_registered = true;
    }

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

        $records         = self::get_records($user_id);
        $original_records = $records;
        $records          = self::prune_records($records);
        if ($records !== $original_records) {
            self::save_records($user_id, $records);
        }

        $changed = false;

        foreach ($records as &$record) {
            if (!isset($record['device_id']) || (string) $record['device_id'] !== $device) {
                continue;
            }

            if (empty($record['revoked_at'])) {
                $record['revoked_at'] = $issued;
                $changed               = true;
            }
        }
        unset($record);

        $records[] = [
            'user_id'      => $user_id,
            'device_id'    => $device,
            'kid'          => $kid,
            'hash'         => $hash,
            'created_at'   => $issued,
            'last_used_at' => $issued,
            'expires_at'   => $expires,
            'revoked_at'   => null,
        ];
        $changed = true;

        if ($changed) {
            $records = self::trim_history($records);
            self::save_records($user_id, $records);
        }

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
        $records          = self::get_records($user_id);
        $original_records = $records;
        $records          = self::prune_records($records);
        if ($records !== $original_records) {
            self::save_records($user_id, $records);
        }
        $now     = time();
        $changed = false;

        foreach ($records as $index => $record) {
            if (!is_array($record) || (string) ($record['kid'] ?? '') !== $kid) {
                continue;
            }

            if (!empty($record['revoked_at'])) {
                self::revoke_device($user_id, (string) ($record['device_id'] ?? 'unknown'));

                return new WP_Error('refresh_reuse', __('Refresh token reuse detected.', 'artpulse-management'), ['status' => 401]);
            }

            $expires = (int) ($record['expires_at'] ?? 0);
            if ($expires < $now) {
                $records[$index]['revoked_at'] = $expires;
                $changed                       = true;
                self::save_records($user_id, $records);

                return new WP_Error('ap_refresh_expired', __('Refresh token expired.', 'artpulse-management'), ['status' => 401]);
            }

            $expected = self::hash_token($kid, $secret);
            if (!hash_equals((string) ($record['hash'] ?? ''), $expected)) {
                self::revoke_device($user_id, (string) ($record['device_id'] ?? 'unknown'));

                return new WP_Error('refresh_reuse', __('Refresh token reuse detected.', 'artpulse-management'), ['status' => 401]);
            }

            $records[$index]['last_used_at'] = $now;
            $changed                          = true;

            if ($changed) {
                self::save_records($user_id, $records);
            }

            return [
                'user_id'   => $user_id,
                'device_id' => (string) ($record['device_id'] ?? 'unknown'),
                'kid'       => (string) $kid,
                'expires'   => (int) ($record['expires_at'] ?? $expires),
            ];
        }

        if ($changed) {
            self::save_records($user_id, $records);
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
        $now      = time();

        $records = self::get_records($user_id);
        $records = self::prune_records($records);
        $changed = false;

        foreach ($records as &$record) {
            if (!is_array($record) || (string) ($record['kid'] ?? '') !== $previous) {
                continue;
            }

            if (empty($record['revoked_at'])) {
                $record['revoked_at'] = $now;
                $changed              = true;
            }
        }
        unset($record);

        if ($changed) {
            self::save_records($user_id, $records);
        }

        return self::mint($user_id, $device);
    }

    public static function revoke_device(int $user_id, string $device_id): void
    {
        $device           = self::normalize_device($device_id);
        $records          = self::get_records($user_id);
        $original_records = $records;
        $records          = self::prune_records($records);
        $now              = time();
        $changed          = $records !== $original_records;

        foreach ($records as &$record) {
            if (!is_array($record)) {
                continue;
            }

            if ((string) ($record['device_id'] ?? '') !== $device) {
                continue;
            }

            if (empty($record['revoked_at'])) {
                $record['revoked_at'] = $now;
                $changed              = true;
            }
        }
        unset($record);

        if ($changed) {
            $records = self::trim_history($records);
            self::save_records($user_id, $records);
        }
    }

    public static function revoke_all(int $user_id): void
    {
        $records          = self::get_records($user_id);
        $original_records = $records;
        $records          = self::prune_records($records);
        $now              = time();
        $changed          = $records !== $original_records;

        foreach ($records as &$record) {
            if (!is_array($record)) {
                continue;
            }

            if (empty($record['revoked_at'])) {
                $record['revoked_at'] = $now;
                $changed              = true;
            }
        }
        unset($record);

        if ($changed) {
            $records = self::trim_history($records);
            self::save_records($user_id, $records);
        }
    }

    /**
     * Return the active session list for a user grouped by device.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function list_sessions(int $user_id): array
    {
        $raw_records = self::get_records($user_id);
        $records     = self::prune_records($raw_records);
        if ($records !== $raw_records) {
            self::save_records($user_id, $records);
        }
        $now     = time();
        $devices = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            if (!empty($record['revoked_at'])) {
                continue;
            }

            $expires = (int) ($record['expires_at'] ?? 0);
            if ($expires < $now) {
                continue;
            }

            $device = (string) ($record['device_id'] ?? 'unknown');

            if (!isset($devices[$device])) {
                $devices[$device] = [
                    'deviceId'   => $device,
                    'createdAt'  => (int) ($record['created_at'] ?? $now),
                    'lastUsedAt' => (int) ($record['last_used_at'] ?? $now),
                    'expiresAt'  => $expires,
                    'tokenCount' => 0,
                ];
            }

            $devices[$device]['createdAt']  = min($devices[$device]['createdAt'], (int) ($record['created_at'] ?? $now));
            $devices[$device]['lastUsedAt'] = max($devices[$device]['lastUsedAt'], (int) ($record['last_used_at'] ?? $now));
            $devices[$device]['expiresAt']  = max($devices[$device]['expiresAt'], $expires);
            $devices[$device]['tokenCount']++;
        }

        usort(
            $devices,
            static function (array $a, array $b): int {
                return $b['lastUsedAt'] <=> $a['lastUsedAt'];
            }
        );

        return array_values($devices);
    }

    public static function handle_password_reset($user, string $new_password = ''): void
    {
        if ($user instanceof WP_User) {
            self::revoke_all($user->ID);
        }
    }

    public static function handle_profile_update(int $user_id, $old_user_data): void
    {
        $old_user = $old_user_data instanceof WP_User ? $old_user_data : null;
        $new_user = get_userdata($user_id);

        if (!$new_user) {
            return;
        }

        $password_changed = $old_user && $old_user->user_pass !== $new_user->user_pass;
        $email_changed    = $old_user && strtolower((string) $old_user->user_email) !== strtolower((string) $new_user->user_email);

        if ($password_changed || $email_changed) {
            self::revoke_all($user_id);
        }
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

        return array_values($records);
    }

    private static function save_records(int $user_id, array $records): void
    {
        update_user_meta($user_id, self::META_KEY, array_values($records));
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private static function prune_records(array $records): array
    {
        $now     = time();
        $changed = false;

        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                unset($records[$index]);
                $changed = true;
                continue;
            }

            $expires   = (int) ($record['expires_at'] ?? 0);
            $revoked   = (int) ($record['revoked_at'] ?? 0);
            $is_active = empty($record['revoked_at']);

            if ($expires < $now && $is_active) {
                $records[$index]['revoked_at'] = $expires ?: $now;
                $changed                        = true;
                $revoked                        = (int) ($records[$index]['revoked_at'] ?? 0);
            }

            if ($revoked && ($revoked + self::HISTORY_TTL) < $now) {
                unset($records[$index]);
                $changed = true;
            }
        }

        if ($changed) {
            $records = array_values($records);
        }

        return array_values($records);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private static function trim_history(array $records): array
    {
        if (count($records) <= self::HISTORY_LIMIT) {
            return $records;
        }

        usort(
            $records,
            static function ($a, $b): int {
                return ((int) ($a['created_at'] ?? 0)) <=> ((int) ($b['created_at'] ?? 0));
            }
        );

        return array_slice($records, -1 * self::HISTORY_LIMIT);
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

        return hash('sha256', $kid . '|' . $secret . '|' . $key);
    }
}
