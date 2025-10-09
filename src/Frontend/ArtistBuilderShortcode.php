<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\RateLimitHeaders;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Error;

/**
 * Shortcode for managing artist profiles via the front-end builder.
 */
final class ArtistBuilderShortcode
{
    public static function register(): void
    {
        add_shortcode('ap_artist_builder', [self::class, 'render']);
    }

    public static function render(): string
    {
        if (!get_option('ap_enable_artist_builder', true)) {
            status_header(404);

            if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
                wp_send_json_error([
                    'code'    => 'artist_builder_disabled',
                    'message' => __('Artist builder is currently disabled.', 'artpulse-management'),
                ], 404);
            }

            return '';
        }

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            self::enforce_post_security();
        }

        if (!is_user_logged_in()) {
            return wp_login_form(['echo' => false]);
        }

        $user_id    = get_current_user_id();
        $artist_ids = self::owned_artists($user_id);

        if (empty($artist_ids)) {
            return '<p>' . esc_html__('No artist profile to manage.', 'artpulse-management') . '</p>';
        }

        $mobile = isset($_GET['view']) && 'mobile' === sanitize_text_field(wp_unslash($_GET['view']));

        ob_start();

        $builder_artist_ids = $artist_ids;
        $is_mobile_view     = $mobile;

        include ARTPULSE_PLUGIN_DIR . 'templates/artist-builder/wrapper.php';

        return (string) ob_get_clean();
    }

    private static function enforce_post_security(): void
    {
        if (!is_user_logged_in()) {
            self::respond_with_error(
                'auth_required',
                __('You must be logged in to update this artist.', 'artpulse-management'),
                401
            );
        }

        $nonce_valid = isset($_POST['_ap_nonce'])
            && check_admin_referer('ap_portfolio_update', '_ap_nonce', false);

        if (!$nonce_valid) {
            self::respond_with_error(
                'invalid_nonce',
                __('Security check failed.', 'artpulse-management'),
                403,
                [
                    'nonce' => wp_create_nonce('ap_portfolio_update'),
                    'hint'  => 'refresh_nonce_and_retry',
                ]
            );
        }

        $user_id = get_current_user_id();
        $rate_error = FormRateLimiter::enforce($user_id, 'artist_builder_write', 30, 60);
        if ($rate_error instanceof WP_Error) {
            self::bail_rate_limited($rate_error);
        }
    }

    private static function bail_rate_limited(WP_Error $error): void
    {
        $data   = (array) $error->get_error_data();
        $status = isset($data['status']) ? (int) $data['status'] : 429;

        if (429 !== $status) {
            self::respond_with_error($error->get_error_code(), $error->get_error_message(), $status);
        }

        $retry_after = isset($data['retry_after']) ? max(1, (int) $data['retry_after']) : 60;
        $limit       = isset($data['limit']) ? max(1, (int) $data['limit']) : 30;
        $reset       = isset($data['reset']) ? (int) $data['reset'] : time() + $retry_after;

        $headers = $data['headers'] ?? RateLimitHeaders::build($limit, 0, $reset, $retry_after);
        RateLimitHeaders::emit($headers);

        AuditLogger::info('rate_limit.hit', [
            'user_id'     => get_current_user_id(),
            'route'       => sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')),
            'context'     => 'artist_builder',
            'retry_after' => $retry_after,
            'limit'       => $limit,
        ]);

        self::respond_with_error(
            $error->get_error_code(),
            $error->get_error_message(),
            429,
            [
                'limit'       => $limit,
                'retry_after' => $retry_after,
                'reset'       => $reset,
            ],
            $retry_after
        );
    }

    private static function respond_with_error(
        string $code,
        string $message,
        int $status,
        array $details = [],
        ?int $retry_after = null
    ): void {
        if (null !== $retry_after) {
            $details['retry_after'] = max(0, $retry_after);
        }

        if ('invalid_nonce' === $code && !isset($details['hint'])) {
            $details['hint'] = 'refresh_nonce_and_retry';
        }

        $payload = [
            'code'    => $code,
            'message' => $message,
            'details' => $details,
        ];

        if (isset($details['nonce']) && is_string($details['nonce'])) {
            header('X-ArtPulse-Nonce: ' . $details['nonce']);
        }

        wp_send_json($payload, $status);
    }

    private static function owned_artists(int $user_id): array
    {
        $authors = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => ['publish', 'draft', 'pending'],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'author'         => $user_id,
        ]);

        $meta_owned = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => ['publish', 'draft', 'pending'],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_key'       => '_ap_owner_user',
            'meta_value'     => $user_id,
        ]);

        $team_owned = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => ['publish', 'draft', 'pending'],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_ap_owner_users',
                    'value'   => sprintf(':%d;', $user_id),
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        $ids = array_merge($authors, $meta_owned, $team_owned);
        $ids = array_map('intval', $ids);
        $ids = array_values(array_unique(array_filter($ids)));

        return array_values(
            array_filter(
                $ids,
                static fn(int $post_id) => PortfolioAccess::is_owner($user_id, $post_id)
            )
        );
    }
}
