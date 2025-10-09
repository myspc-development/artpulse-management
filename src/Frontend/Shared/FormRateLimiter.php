<?php

namespace ArtPulse\Frontend\Shared;

use WP_Error;
use function sanitize_key;

/**
 * Lightweight transient-backed rate limiter for front-end form submissions.
 */
final class FormRateLimiter
{
    private const DEFAULT_LIMIT  = 30;
    private const DEFAULT_WINDOW = MINUTE_IN_SECONDS;

    /**
     * Enforce a per-user, per-context rate limit.
     *
     * @param string $context   Context key (e.g. "portfolio", "event").
     * @param int    $user_id   Current user identifier.
     * @param int    $limit     Maximum number of actions per window.
     * @param int    $window    Window size in seconds.
     *
     * @return WP_Error|null    A WP_Error when limited, otherwise null.
     */
    public static function enforce(
        string $context,
        int $user_id,
        int $limit = self::DEFAULT_LIMIT,
        int $window = self::DEFAULT_WINDOW
    ): ?WP_Error {
        if ($user_id <= 0) {
            return new WP_Error(
                'rate_limit_user_missing',
                __('You must be logged in to perform this action.', 'artpulse-management'),
                ['status' => 401]
            );
        }

        $context_key = sanitize_key($context);
        if ($context_key === '') {
            $context_key = 'default';
        }

        $key = sprintf('ap_rate_%s_%d', $context_key, $user_id);
        $bucket = get_transient($key);

        $now = time();
        $reset = $now + $window;

        if (!is_array($bucket) || !isset($bucket['reset']) || $bucket['reset'] <= $now) {
            $bucket = [
                'count' => 0,
                'reset' => $reset,
            ];
        }

        if ($bucket['count'] >= $limit) {
            $retry_after = max(1, (int) ($bucket['reset'] - $now));

            return new WP_Error(
                'rate_limited',
                __('Too many updates in a short period. Please wait a moment and try again.', 'artpulse-management'),
                [
                    'status'       => 429,
                    'retry_after'  => $retry_after,
                    'limit'        => $limit,
                    'window'       => $window,
                    'reset'        => $bucket['reset'],
                ]
            );
        }

        $bucket['count']++;
        $ttl = max(1, (int) ($bucket['reset'] - $now));
        set_transient($key, $bucket, $ttl);

        return null;
    }
}
