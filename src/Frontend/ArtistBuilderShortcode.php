<?php

namespace ArtPulse\Frontend;

use ArtPulse\Frontend\Shared\PortfolioAccess;

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
