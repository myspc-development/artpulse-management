<?php
namespace ArtPulse\Ajax;

class FrontendFilterHandler
{
    public static function register()
    {
        add_action('wp_ajax_ap_filter_posts', [self::class, 'handle_filter_posts']);
        add_action('wp_ajax_nopriv_ap_filter_posts', [self::class, 'handle_filter_posts']);
    }

    public static function handle_filter_posts()
    {
        check_ajax_referer('ap_frontend_filter_nonce', 'nonce');

        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = intval($_GET['per_page'] ?? 5);
        $terms = isset($_GET['terms']) ? explode(',', sanitize_text_field($_GET['terms'])) : [];

        $tax_query = [];
        if (!empty($terms)) {
            $tax_query[] = [
                'taxonomy' => 'artist_specialty', // Adjust taxonomy as needed
                'field'    => 'slug',
                'terms'    => $terms,
            ];
        }

        $args = [
            'post_type'      => 'artpulse_artist',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            // We still rely on found rows for pagination so `no_found_rows` is
            // intentionally omitted here. Fetch only IDs to reduce memory usage.
            'fields'         => 'ids',
        ];

        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($args);
        $posts = [];

        // `fields => 'ids'` returns an array of post IDs. Loop through IDs and
        // fetch post data manually.
        foreach ($query->posts as $post_id) {
            $posts[] = [
                'id'    => $post_id,
                'title' => get_the_title($post_id),
                'link'  => get_permalink($post_id),
            ];
        }

        wp_send_json([
            'posts'    => $posts,
            'page'     => $page,
            'max_page' => $query->max_num_pages,
        ]);
    }
}
