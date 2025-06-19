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
            'post_type'      => 'ead_artist',  // Adjust post type as needed
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
        ];

        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($args);
        $posts = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $posts[] = [
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                    'link'  => get_permalink(),
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json([
            'posts'    => $posts,
            'page'     => $page,
            'max_page' => $query->max_num_pages,
        ]);
    }
}
