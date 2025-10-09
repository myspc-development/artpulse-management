<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\AuditLogger;
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
            status_header(401);
            wp_send_json_error([
                'code'    => 'auth_required',
                'message' => __('You must be logged in to update this artist.', 'artpulse-management'),
            ], 401);
        }

        $nonce_valid = isset($_POST['_ap_nonce'])
            && check_admin_referer('ap_portfolio_update', '_ap_nonce', false);

        if (!$nonce_valid) {
            status_header(403);
            wp_send_json_error([
                'code'    => 'invalid_nonce',
                'message' => __('Security check failed.', 'artpulse-management'),
            ], 403);
        }

        $user_id = get_current_user_id();
        $rate_error = FormRateLimiter::enforce($user_id, 'artist_builder_write', 30, 60);
        if ($rate_error instanceof WP_Error) {
            self::bail_rate_limited($rate_error);
        }
    }

    private static function bail_rate_limited(WP_Error $error): void
    {
        $data   = $error->get_error_data();
        $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 429;

        if (429 !== $status) {
            status_header($status);
            wp_send_json_error([
                'code'    => $error->get_error_code(),
                'message' => $error->get_error_message(),
            ], $status);
        }

        $retry_after = is_array($data) && isset($data['retry_after'])
            ? max(1, (int) $data['retry_after'])
            : 60;
        $limit = is_array($data) && isset($data['limit'])
            ? max(1, (int) $data['limit'])
            : 30;

        if (is_array($data) && isset($data['reset'])) {
            header('X-RateLimit-Reset: ' . (int) $data['reset']);
        }

        header('Retry-After: ' . $retry_after);
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: 0');

        AuditLogger::info('rate_limit.hit', [
            'user_id'     => get_current_user_id(),
            'route'       => sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')),
            'context'     => 'artist_builder',
            'retry_after' => $retry_after,
            'limit'       => $limit,
        ]);

        wp_send_json_error([
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message(),
        ], 429);
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
