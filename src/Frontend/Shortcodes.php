<?php
namespace ArtPulse\Frontend;

class Shortcodes {

    public static function register() {
        add_shortcode('ap_filtered_list', [self::class, 'render_filtered_list']);
    }

    /**
     * Shortcode to render filtered CPT list by taxonomy terms.
     * Usage example: [ap_filtered_list post_type="artpulse_artist" taxonomy="artist_specialty" terms="painting,sculpture" posts_per_page="5"]
     */
    public static function render_filtered_list($atts) {
        $atts = shortcode_atts([
            'post_type' => 'artpulse_artist',
            'taxonomy' => 'artist_specialty',
            'terms' => '',
            'posts_per_page' => 5,
        ], $atts, 'ap_filtered_list');

        $tax_query = [];

        if (!empty($atts['terms'])) {
            $terms = array_map('trim', explode(',', $atts['terms']));
            $tax_query[] = [
                'taxonomy' => sanitize_text_field($atts['taxonomy']),
                'field'    => 'slug',
                'terms'    => $terms,
            ];
        }

        $query_args = [
            'post_type'      => sanitize_text_field($atts['post_type']),
            'posts_per_page' => intval($atts['posts_per_page']),
            'post_status'    => 'publish',
            // Fetch IDs only and skip FOUND_ROWS for performance.
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];

        if (!empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($query_args);

        if (empty($query->posts)) {
            return '<p>' . __('No items found.', 'artpulse-management') . '</p>';
        }

        ob_start();
        echo '<div class="ap-filtered-list">';
        foreach ($query->posts as $post_id) {
            $post = \get_post($post_id);

            if (!$post instanceof \WP_Post) {
                continue;
            }

            \setup_postdata($post);

            // Path to template partial, adjust if needed
            $template_path = plugin_dir_path(__FILE__) . '../../templates/partials/content-artpulse-item.php';

            if (file_exists($template_path)) {
                include $template_path;
            } else {
                // Fallback output
                printf(
                    '<li><a href="%s">%s</a></li>',
                    esc_url(\get_permalink($post_id)),
                    esc_html(\get_the_title($post_id))
                );
            }

            \wp_reset_postdata();
        }
        echo '</div>';

        return ob_get_clean();
    }
}
