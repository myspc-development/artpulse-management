<?php

namespace ArtPulse\Mobile;

use ArtPulse\Core\AuditLogger;
use WP_Error;
use WP_User;

class RefreshTokens
{
    private const META_KEY          = 'ap_mobile_refresh_tokens';
    private const DEFAULT_TTL       = 30 * DAY_IN_SECONDS;
    private const MAX_ACTIVE_TOKENS = 10;
    private const HISTORY_TTL       = 14 * DAY_IN_SECONDS;
    private const HISTORY_LIMIT     = 40;
    private const INACTIVITY_TTL    = 90 * DAY_IN_SECONDS;

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
        add_action('ap_mobile_purge_inactive_sessions', [self::class, 'purge_inactive_sessions']);

        self::$hooks_registered = true;
    }

    /**
     * Purge sessions that have not been active within the inactivity window.
     */
    public static function purge_inactive_sessions(?int $inactivity_ttl = null): int
    {
        global $wpdb;

        $ttl = $inactivity_ttl ?? self::INACTIVITY_TTL;
        if ($ttl <= 0) {
            return 0;
        }

        $cutoff   = time() - $ttl;
        $meta_key = self::META_KEY;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return 0;
        }

        $removed = 0;

        foreach ($rows as $row) {
            $user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            if ($user_id <= 0) {
                continue;
            }

            $records = maybe_unserialize($row['meta_value']);
            if (!is_array($records) || empty($records)) {
                continue;
            }

            $updated_records = [];

            foreach ($records as $record) {
                if (!is_array($record)) {
                    $removed++;
                    continue;
                }

                $last_seen  = isset($record['last_seen_at']) ? (int) $record['last_seen_at'] : 0;
                $last_used  = isset($record['last_used_at']) ? (int) $record['last_used_at'] : 0;
                $created_at = isset($record['created_at']) ? (int) $record['created_at'] : 0;
                $activity   = max($last_seen, $last_used, $created_at);

                if ($activity && $activity >= $cutoff) {
                    $updated_records[] = $record;
                    continue;
                }

                $removed++;
            }

            if (count($updated_records) === count($records)) {
                continue;
            }

            if (empty($updated_records)) {
                delete_user_meta($user_id, $meta_key);
                continue;
            }

            update_user_meta($user_id, $meta_key, array_values($updated_records));
        }

        return $removed;
    }

    /**
     * Mint a refresh token for the provided user and device identifier.
     *
     * @param array<string, mixed> $metadata
     *
     * @return array{
     *     token:string,
     *     kid:string,
     *     expires:int,
     *     device_id:string,
     *     session:array<string, mixed>,
     *     evicted_device_id?:string|null,
     *     evicted_device_ids?:array<int, string>
     * }
     */
    public static function mint(int $user_id, ?string $device_id = null, array $metadata = [], ?int $ttl = null): array
    {
        $device   = self::normalize_device($device_id);
        $issued   = time();
        $ttl      = $ttl ?? self::DEFAULT_TTL;
        $expires  = $issued + max(HOUR_IN_SECONDS, $ttl);
        $metadata = self::sanitize_metadata($metadata, [
            'last_seen_at' => $issued,
        ]);

        $kid    = wp_generate_uuid4();
        $secret = self::generate_secret();
        $hash   = self::hash_token($kid, $secret);

        $records         = self::get_records($user_id);
        $original_records = $records;
        $records          = self::prune_records($records);
        if ($records !== $original_records) {
            self::save_records($user_id, $records);
        }

        $changed         = false;
        $evicted_devices = [];

        $records = self::enforce_session_cap($records, $issued, $changed, $evicted_devices);

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
            'device_name'  => $metadata['device_name'] ?? null,
            'platform'     => $metadata['platform'] ?? null,
            'app_version'  => $metadata['app_version'] ?? null,
            'last_ip'      => $metadata['last_ip'] ?? null,
            'last_seen_at' => $metadata['last_seen_at'] ?? $issued,
            'push_token'   => $metadata['push_token'] ?? null,
        ];
        $changed = true;

        if ($changed) {
            $records = self::trim_history($records);
            self::save_records($user_id, $records);
        }

        $session_payload = self::build_session_payload($device, $metadata, $issued);

        $evicted_devices = array_values(array_unique(array_filter($evicted_devices)));

        if (!empty($evicted_devices)) {
            foreach ($evicted_devices as $evicted_device) {
                AuditLogger::info('mobile_session_evicted', [
                    'user_id'   => $user_id,
                    'device_id' => $evicted_device,
                    'reason'    => 'session_cap',
                ]);
            }
        }

        return [
            'token'              => self::encode_token($kid, $user_id, $secret),
            'kid'                => $kid,
            'expires'            => $expires,
            'device_id'          => $device,
            'session'            => $session_payload,
            'evicted_device_id'  => $evicted_devices[0] ?? null,
            'evicted_device_ids' => $evicted_devices,
        ];
    }

    /**
     * Validate a refresh token and return context for rotation.
     *
     * @return array{user_id:int,device_id:string,kid:string,expires:int,metadata:array<string, mixed>}|WP_Error
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
        $ip      = self::detect_request_ip();

        foreach ($records as $index => $record) {
            if (!is_array($record) || (string) ($record['kid'] ?? '') !== $kid) {
                continue;
            }

            if (!empty($record['revoked_at'])) {
                $revoked_reason = isset($record['revoked_reason']) ? (string) $record['revoked_reason'] : '';

                if ($revoked_reason && 0 === strpos($revoked_reason, 'global_')) {
                    return new WP_Error(
                        RestErrorFormatter::AUTH_REVOKED,
                        __('Refresh token revoked after account change.', 'artpulse-management'),
                        ['status' => 401]
                    );
                }

                self::revoke_device($user_id, (string) ($record['device_id'] ?? 'unknown'));

                return new WP_Error(RestErrorFormatter::REFRESH_REUSE, __('Refresh token reuse detected.', 'artpulse-management'), ['status' => 401]);
            }

            $expires = (int) ($record['expires_at'] ?? 0);
            if ($expires < $now) {
                $records[$index]['revoked_at'] = $expires;
                $changed                       = true;
                self::save_records($user_id, $records);

                return new WP_Error(RestErrorFormatter::AUTH_EXPIRED, __('Refresh token expired.', 'artpulse-management'), ['status' => 401]);
            }

            $expected = self::hash_token($kid, $secret);
            if (!hash_equals((string) ($record['hash'] ?? ''), $expected)) {
                self::revoke_device($user_id, (string) ($record['device_id'] ?? 'unknown'));

                return new WP_Error(RestErrorFormatter::REFRESH_REUSE, __('Refresh token reuse detected.', 'artpulse-management'), ['status' => 401]);
            }

            $records[$index]['last_used_at'] = $now;
            $records[$index]['last_seen_at'] = $now;
            if ($ip) {
                $records[$index]['last_ip'] = $ip;
            }
            $changed = true;

            if ($changed) {
                self::save_records($user_id, $records);
            }

            return [
                'user_id'   => $user_id,
                'device_id' => (string) ($record['device_id'] ?? 'unknown'),
                'kid'       => (string) $kid,
                'expires'   => (int) ($record['expires_at'] ?? $expires),
                'metadata'  => [
                    'device_name' => isset($record['device_name']) ? (string) $record['device_name'] : null,
                    'platform'    => isset($record['platform']) ? (string) $record['platform'] : null,
                    'app_version' => isset($record['app_version']) ? (string) $record['app_version'] : null,
                    'last_ip'     => isset($record['last_ip']) ? (string) $record['last_ip'] : null,
                    'last_seen_at'=> isset($record['last_seen_at']) ? (int) $record['last_seen_at'] : $now,
                    'push_token'  => isset($record['push_token']) ? (string) $record['push_token'] : null,
                ],
            ];
        }

        if ($changed) {
            self::save_records($user_id, $records);
        }

        return new WP_Error(RestErrorFormatter::AUTH_REVOKED, __('Refresh token revoked.', 'artpulse-management'), ['status' => 401]);
    }

    /**
     * Rotate a refresh token after successful validation.
     *
     * @param array{user_id:int,device_id:string,kid:string} $context
     * @param array<string, mixed> $metadata
     *
     * @return array{token:string,kid:string,expires:int}
     */
    public static function rotate(array $context, array $metadata = []): array
    {
        $user_id  = (int) ($context['user_id'] ?? 0);
        $device   = self::normalize_device($context['device_id'] ?? '');
        $previous = (string) ($context['kid'] ?? '');
        $now      = time();

        $metadata = self::sanitize_metadata($metadata, [
            'last_seen_at' => $now,
        ]);

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

        return self::mint($user_id, $device, $metadata);
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

    public static function revoke_all(int $user_id, ?string $reason = null): void
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
                if ($reason) {
                    $record['revoked_reason'] = $reason;
                }
                $changed              = true;
            } elseif ($reason && empty($record['revoked_reason'])) {
                $record['revoked_reason'] = $reason;
                $changed                  = true;
            }
        }
        unset($record);

        if ($changed) {
            $records = self::trim_history($records);
            self::save_records($user_id, $records);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function update_device_metadata(int $user_id, string $device_id, array $metadata): void
    {
        $device           = self::normalize_device($device_id);
        $records          = self::get_records($user_id);
        $original_records = $records;
        $records          = self::prune_records($records);
        $changed          = $records !== $original_records;

        $metadata = self::sanitize_metadata($metadata);

        foreach ($records as &$record) {
            if (!is_array($record) || (string) ($record['device_id'] ?? '') !== $device) {
                continue;
            }

            $updates = [];
            foreach (['device_name', 'platform', 'app_version', 'push_token', 'last_ip'] as $key) {
                if (array_key_exists($key, $metadata) && null !== $metadata[$key]) {
                    $updates[$key] = $metadata[$key];
                }
            }

            if (isset($metadata['last_seen_at'])) {
                $updates['last_seen_at'] = (int) $metadata['last_seen_at'];
            }

            if (!empty($updates)) {
                $record = array_merge($record, $updates);
                $changed = true;
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
                $device_name = self::nullable_string($record['device_name'] ?? null);
                $platform    = self::nullable_string($record['platform'] ?? null);
                $app_version = self::nullable_string($record['app_version'] ?? null);
                $last_ip     = self::nullable_string($record['last_ip'] ?? null);
                $last_seen   = (int) ($record['last_seen_at'] ?? $now);

                $devices[$device] = [
                    'deviceId'    => $device,
                    'deviceName'  => $device_name,
                    'platform'    => $platform,
                    'appVersion'  => $app_version,
                    'createdAt'   => (int) ($record['created_at'] ?? $now),
                    'lastUsedAt'  => (int) ($record['last_used_at'] ?? $now),
                    'lastSeenAt'  => $last_seen,
                    'expiresAt'   => $expires,
                    'lastIp'      => $last_ip,
                    'pushToken'   => self::nullable_string($record['push_token'] ?? null),
                    'tokenCount'  => 0,
                    'device_id'   => $device,
                    'device_name' => $device_name,
                    'platform'    => $platform,
                    'app_version' => $app_version,
                    'last_ip'     => $last_ip,
                    'last_seen_at'=> $last_seen,
                ];
            }

            $devices[$device]['createdAt']  = min($devices[$device]['createdAt'], (int) ($record['created_at'] ?? $now));
            $devices[$device]['lastUsedAt'] = max($devices[$device]['lastUsedAt'], (int) ($record['last_used_at'] ?? $now));
            $devices[$device]['lastSeenAt'] = max($devices[$device]['lastSeenAt'], (int) ($record['last_seen_at'] ?? $now));
            $devices[$device]['last_seen_at'] = $devices[$device]['lastSeenAt'];
            $devices[$device]['expiresAt']  = max($devices[$device]['expiresAt'], $expires);
            $devices[$device]['tokenCount']++;

            if (!empty($record['device_name'])) {
                $name = self::nullable_string($record['device_name']);
                if (null !== $name) {
                    $devices[$device]['deviceName'] = $name;
                    $devices[$device]['device_name'] = $name;
                }
            }

            if (!empty($record['platform'])) {
                $platform_value = self::nullable_string($record['platform']);
                if (null !== $platform_value) {
                    $devices[$device]['platform'] = $platform_value;
                }
            }

            if (!empty($record['app_version'])) {
                $app_value = self::nullable_string($record['app_version']);
                if (null !== $app_value) {
                    $devices[$device]['appVersion'] = $app_value;
                    $devices[$device]['app_version'] = $app_value;
                }
            }

            if (!empty($record['last_ip'])) {
                $ip = self::nullable_string($record['last_ip']);
                $devices[$device]['lastIp'] = $ip;
                $devices[$device]['last_ip'] = $ip;
            }

            if (!empty($record['push_token'])) {
                $devices[$device]['pushToken'] = self::nullable_string($record['push_token']);
            }
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
            $trigger = function_exists('current_filter') ? (string) current_filter() : 'password_reset';

            self::revoke_all($user->ID, 'global_password_reset');
            self::log_revocation($user->ID, $trigger ?: 'password_reset');
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
            $reason  = 'global_account_update';
            $trigger = 'account_update';

            if ($password_changed && !$email_changed) {
                $reason  = 'global_password_change';
                $trigger = 'password_change';
            } elseif ($email_changed && !$password_changed) {
                $reason  = 'global_email_change';
                $trigger = 'email_change';
            }

            self::revoke_all($user_id, $reason);
            self::log_revocation($user_id, $trigger);
        }
    }

    private static function log_revocation(int $user_id, string $trigger): void
    {
        AuditLogger::info('mobile_sessions_revoked', [
            'user_id' => $user_id,
            'trigger' => $trigger,
        ]);
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

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private static function sanitize_metadata(array $metadata, array $defaults = []): array
    {
        $metadata = array_merge($defaults, $metadata);

        $metadata['device_name'] = self::sanitize_nullable_text($metadata['device_name'] ?? null, 120);
        $metadata['platform']    = self::sanitize_nullable_text($metadata['platform'] ?? null, 60);
        $metadata['app_version'] = self::sanitize_nullable_text($metadata['app_version'] ?? null, 60);
        $metadata['push_token']  = self::sanitize_nullable_text($metadata['push_token'] ?? null, 255);
        $metadata['last_ip']     = self::sanitize_ip($metadata['last_ip'] ?? null);

        $last_seen = isset($metadata['last_seen_at']) ? (int) $metadata['last_seen_at'] : null;
        if ($last_seen && $last_seen > 0) {
            $metadata['last_seen_at'] = $last_seen;
        } else {
            unset($metadata['last_seen_at']);
        }

        return $metadata;
    }

    private static function sanitize_nullable_text($value, int $length): ?string
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $clean = sanitize_text_field($value);

        return substr($clean, 0, $length);
    }

    private static function sanitize_ip($value): ?string
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $value = trim($value);

        if (false !== strpos($value, ',')) {
            $value = trim(explode(',', $value)[0]);
        }

        $clean = sanitize_text_field($value);

        return substr($clean, 0, 100);
    }

    private static function detect_request_ip(): ?string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($ip) {
            $ip = trim(explode(',', (string) $ip)[0]);
        }

        if (!$ip && isset($_SERVER['REMOTE_ADDR'])) {
            $ip = (string) $_SERVER['REMOTE_ADDR'];
        }

        return self::sanitize_ip($ip);
    }

    private static function nullable_string($value): ?string
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private static function enforce_session_cap(array $records, int $timestamp, bool &$changed, array &$evicted_devices): array
    {
        $active = [];

        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                continue;
            }

            $expires = (int) ($record['expires_at'] ?? 0);
            if (!empty($record['revoked_at']) || $expires < $timestamp) {
                continue;
            }

            $last_used = (int) ($record['last_used_at'] ?? ($record['created_at'] ?? $timestamp));
            $active[]  = [
                'index' => $index,
                'last'  => $last_used,
            ];
        }

        if (count($active) < self::MAX_ACTIVE_TOKENS) {
            return $records;
        }

        usort(
            $active,
            static function (array $a, array $b): int {
                return $a['last'] <=> $b['last'];
            }
        );

        $excess = count($active) - self::MAX_ACTIVE_TOKENS + 1;
        for ($i = 0; $i < $excess; $i++) {
            $target_index = $active[$i]['index'];
            if (!isset($records[$target_index]) || !is_array($records[$target_index])) {
                continue;
            }

            if (empty($records[$target_index]['revoked_at'])) {
                $records[$target_index]['revoked_at'] = $timestamp;
                $changed                               = true;
                $evicted_devices[]                     = (string) ($records[$target_index]['device_id'] ?? 'unknown');
            }
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private static function build_session_payload(string $device_id, array $metadata, int $timestamp): array
    {
        $last_seen = isset($metadata['last_seen_at']) ? (int) $metadata['last_seen_at'] : $timestamp;

        return [
            'device_id'    => $device_id,
            'device_name'  => isset($metadata['device_name']) ? self::nullable_string($metadata['device_name']) : null,
            'platform'     => isset($metadata['platform']) ? self::nullable_string($metadata['platform']) : null,
            'app_version'  => isset($metadata['app_version']) ? self::nullable_string($metadata['app_version']) : null,
            'last_ip'      => isset($metadata['last_ip']) ? self::nullable_string($metadata['last_ip']) : null,
            'last_seen_at' => $last_seen,
        ];
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
