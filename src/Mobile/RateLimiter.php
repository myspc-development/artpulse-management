<?php

namespace ArtPulse\Mobile;

use WP_Error;

class RateLimiter
{
    public static function enforce(string $bucket, int $limit = 15, int $window = 60): ?WP_Error
    {
        $user_id = get_current_user_id();
        $ip      = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $key     = 'ap_rl_' . md5($bucket . '|' . $user_id . '|' . $ip);

        $state = get_transient($key);
        $now   = time();

        if (!is_array($state) || empty($state['start']) || ($now - (int) $state['start']) >= $window) {
            set_transient($key, ['start' => $now, 'count' => 1], $window);
            return null;
        }

        $count = (int) ($state['count'] ?? 0);
        if ($count >= $limit) {
            $retry = $window - ($now - (int) $state['start']);

            return new WP_Error(
                'ap_rate_limited',
                __('Too many requests. Please slow down.', 'artpulse-management'),
                [
                    'status'     => 429,
                    'retry_after'=> max(1, $retry),
                ]
            );
        }

        $state['count'] = $count + 1;
        set_transient($key, $state, $window);

        return null;
    }
}
