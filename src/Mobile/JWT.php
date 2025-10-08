<?php

namespace ArtPulse\Mobile;

use WP_Error;

class JWT
{
    private const ALG               = 'HS256';
    private const DEFAULT_TTL       = DAY_IN_SECONDS;
    private const OPTION_KEYS       = 'ap_mobile_jwt_keys';
    private const KEY_BYTES         = 32;
    private const RETIREMENT_GRACE  = 14 * DAY_IN_SECONDS;

    private static ?array $state = null;
    private static bool $booted  = false;

    /**
     * Initialise hooks for background maintenance.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::ensure_initialized();
        add_action('init', [self::class, 'purge_retired_keys']);
        self::$booted = true;
    }

    /**
     * Issue a signed JWT for the given user ID.
     *
     * @return array{token:string,expires:int}
     */
    public static function issue(int $user_id, ?int $ttl = null): array
    {
        self::ensure_initialized();

        $issued_at = time();
        $ttl       = $ttl ?? self::DEFAULT_TTL;
        $expires   = $issued_at + max(60, $ttl);
        $key       = self::get_signing_key();

        $payload = [
            'iss' => get_site_url(),
            'sub' => $user_id,
            'iat' => $issued_at,
            'nbf' => $issued_at - 5,
            'exp' => $expires,
            'jti' => wp_generate_uuid4(),
        ];

        return [
            'token'   => self::encode($payload, $key),
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
        self::ensure_initialized();

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

        $kid  = isset($header['kid']) && is_string($header['kid']) ? $header['kid'] : '';
        $keys = [];

        if ($kid) {
            $key = self::get_key_by_kid($kid);
            if (null !== $key) {
                $keys[$kid] = $key;
            } else {
                return new WP_Error('ap_invalid_token', __('Token signature mismatch.', 'artpulse-management'), ['status' => 401]);
            }
        } else {
            $keys = self::get_all_keys();
        }

        foreach ($keys as $key) {
            $expected = hash_hmac('sha256', $header64 . '.' . $payload64, $key['secret'], true);
            if (hash_equals($expected, $signature)) {
                $now = time();
                if (!empty($payload['nbf']) && $payload['nbf'] > $now + 30) {
                    return new WP_Error('ap_invalid_token', __('Token not yet valid.', 'artpulse-management'), ['status' => 401]);
                }

                if (!empty($payload['exp']) && $payload['exp'] < $now) {
                    return new WP_Error('ap_invalid_token', __('Token expired.', 'artpulse-management'), ['status' => 401]);
                }

                return $payload;
            }
        }

        return new WP_Error('ap_invalid_token', __('Token signature mismatch.', 'artpulse-management'), ['status' => 401]);
    }

    /**
     * Provide key metadata for administrative interfaces.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_keys_for_admin(): array
    {
        self::ensure_initialized();
        $state = self::get_state();
        $keys  = [];

        foreach ($state['keys'] as $kid => $record) {
            $secret = self::decode_secret($record['secret'] ?? '');
            $keys[] = [
                'kid'        => $kid,
                'status'     => $record['status'] ?? 'active',
                'created_at' => (int) ($record['created_at'] ?? 0),
                'retired_at' => isset($record['retired_at']) ? (int) $record['retired_at'] : null,
                'is_current' => ($state['current'] ?? '') === $kid,
                'fingerprint'=> substr(hash('sha256', $secret), 0, 12),
            ];
        }

        usort(
            $keys,
            static function (array $a, array $b): int {
                return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
            }
        );

        return $keys;
    }

    /**
     * Mint a new signing key and make it active.
     */
    public static function add_key(): array
    {
        self::ensure_initialized();
        $state = self::get_state();

        $kid    = wp_generate_uuid4();
        $secret = self::random_secret();

        $state['keys'][$kid] = self::create_key_record($kid, $secret, true);
        $state['current']    = $kid;

        self::save_state($state);

        return [
            'kid'        => $kid,
            'fingerprint'=> substr(hash('sha256', $secret), 0, 12),
        ];
    }

    /**
     * Retire an existing key. Returns false when the key does not exist.
     */
    public static function retire_key(string $kid): bool
    {
        self::ensure_initialized();
        $state = self::get_state();

        if (empty($state['keys'][$kid])) {
            return false;
        }

        if ('retired' === ($state['keys'][$kid]['status'] ?? '')) {
            return true;
        }

        $state['keys'][$kid]['status']     = 'retired';
        $state['keys'][$kid]['retired_at'] = time();

        if (($state['current'] ?? '') === $kid) {
            $replacement = self::find_active_kid($state, $kid);
            if (null === $replacement) {
                $new_kid = wp_generate_uuid4();
                $secret  = self::random_secret();
                $state['keys'][$new_kid] = self::create_key_record($new_kid, $secret, true);
                $state['current']        = $new_kid;
            } else {
                $state['current'] = $replacement;
            }
        }

        self::save_state($state);
        self::ensure_initialized();

        return true;
    }

    /**
     * Mark a key as the current signing key.
     */
    public static function set_current_key(string $kid): bool
    {
        self::ensure_initialized();
        $state = self::get_state();

        if (empty($state['keys'][$kid]) || 'active' !== ($state['keys'][$kid]['status'] ?? '')) {
            return false;
        }

        $state['current'] = $kid;
        self::save_state($state);

        return true;
    }

    /**
     * Remove retired keys after their grace period.
     */
    public static function purge_retired_keys(): void
    {
        self::ensure_initialized();
        $state   = self::get_state();
        $changed = false;
        $now     = time();

        foreach ($state['keys'] as $kid => $record) {
            if ('retired' !== ($record['status'] ?? '')) {
                continue;
            }

            $retired_at = isset($record['retired_at']) ? (int) $record['retired_at'] : 0;
            if ($retired_at && ($retired_at + self::RETIREMENT_GRACE) < $now) {
                unset($state['keys'][$kid]);
                if (($state['current'] ?? '') === $kid) {
                    $state['current'] = null;
                }
                $changed = true;
            }
        }

        if ($changed) {
            self::save_state($state);
            self::ensure_initialized();
        }
    }

    /**
     * Encode payload into JWT string.
     *
     * @param array<string, mixed> $payload
     * @param array{kid:string,secret:string} $key
     */
    private static function encode(array $payload, array $key): string
    {
        $header = ['typ' => 'JWT', 'alg' => self::ALG, 'kid' => $key['kid']];

        $segments = [
            self::base64url_encode(wp_json_encode($header)),
            self::base64url_encode(wp_json_encode($payload)),
        ];

        $signature   = hash_hmac('sha256', implode('.', $segments), $key['secret'], true);
        $segments[]  = self::base64url_encode($signature);

        return implode('.', $segments);
    }

    /**
     * Ensure the key store has at least one active key.
     */
    private static function ensure_initialized(): void
    {
        $state = self::get_state();

        if (empty($state['keys'])) {
            $kid             = wp_generate_uuid4();
            $state['keys']   = [$kid => self::create_key_record($kid, self::get_legacy_secret(), true)];
            $state['current'] = $kid;
            self::save_state($state);
            return;
        }

        $current = $state['current'] ?? '';
        if (!$current || empty($state['keys'][$current]) || 'active' !== ($state['keys'][$current]['status'] ?? '')) {
            $active = self::find_active_kid($state);
            if (null === $active) {
                $kid                 = wp_generate_uuid4();
                $state['keys'][$kid] = self::create_key_record($kid, self::random_secret(), true);
                $active              = $kid;
            }

            $state['current'] = $active;
            self::save_state($state);
        }
    }

    /**
     * Retrieve the current signing key.
     *
     * @return array{kid:string,secret:string}
     */
    private static function get_signing_key(): array
    {
        $state  = self::get_state();
        $record = $state['keys'][$state['current']] ?? null;

        if (!is_array($record)) {
            self::ensure_initialized();
            $state  = self::get_state();
            $record = $state['keys'][$state['current']] ?? null;
        }

        $secret = self::decode_secret($record['secret'] ?? '');

        return [
            'kid'    => (string) $record['kid'],
            'secret' => $secret,
        ];
    }

    /**
     * @return array<string, array{kid:string,secret:string}>
     */
    private static function get_all_keys(): array
    {
        $state = self::get_state();
        $keys  = [];

        foreach ($state['keys'] as $kid => $record) {
            $keys[$kid] = [
                'kid'    => $kid,
                'secret' => self::decode_secret($record['secret'] ?? ''),
            ];
        }

        return $keys;
    }

    /**
     * @return array{kid:string,secret:string}|null
     */
    private static function get_key_by_kid(string $kid): ?array
    {
        $state = self::get_state();
        if (empty($state['keys'][$kid])) {
            return null;
        }

        return [
            'kid'    => $kid,
            'secret' => self::decode_secret($state['keys'][$kid]['secret'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function find_active_kid(array $state, ?string $exclude = null): ?string
    {
        foreach ($state['keys'] as $kid => $record) {
            if ($exclude && $exclude === $kid) {
                continue;
            }

            if ('active' === ($record['status'] ?? 'active')) {
                return $kid;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function save_state(array $state): void
    {
        $state = [
            'keys'    => $state['keys'] ?? [],
            'current' => $state['current'] ?? null,
        ];

        self::$state = $state;
        update_option(self::OPTION_KEYS, $state);
    }

    /**
     * @return array<string, mixed>
     */
    private static function get_state(): array
    {
        if (null === self::$state) {
            $state = get_option(self::OPTION_KEYS, []);
            if (!is_array($state)) {
                $state = [];
            }

            if (!isset($state['keys']) || !is_array($state['keys'])) {
                $state['keys'] = [];
            }

            self::$state = $state;
        }

        return self::$state;
    }

    private static function create_key_record(string $kid, string $secret, bool $active, ?int $created_at = null): array
    {
        return [
            'kid'        => $kid,
            'secret'     => base64_encode($secret),
            'created_at' => $created_at ?? time(),
            'status'     => $active ? 'active' : 'retired',
            'retired_at' => $active ? null : time(),
        ];
    }

    private static function decode_secret(string $stored): string
    {
        $decoded = base64_decode($stored, true);
        if (false === $decoded) {
            return self::get_legacy_secret();
        }

        return $decoded;
    }

    private static function random_secret(): string
    {
        return random_bytes(self::KEY_BYTES);
    }

    private static function get_legacy_secret(): string
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
