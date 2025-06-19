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
            'post_type' => sanitize_text_field($atts['post_type']),
            'posts_per_page' => intval($atts['posts_per_page']),
            'post_status' => 'publish',
        ];

        if (!empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($query_args);

        if (!$query->have_posts()) {
            return '<p>' . __('No items found.', 'artpulse-management') . '</p>';
        }

        ob_start();
        echo '<div class="ap-filtered-list">';
        while ($query->have_posts()) {
            $query->the_post();

            // Make current post available to template
            set_query_var('post', get_post());

            // Path to template partial, adjust if needed
            $template_path = plugin_dir_path(__FILE__) . '../../templates/partials/content-artpulse-item.php';

            if (file_exists($template_path)) {
                include $template_path;
            } else {
                // Fallback output
                printf(
                    '<li><a href="%s">%s</a></li>',
                    esc_url(get_permalink()),
                    esc_html(get_the_title())
                );
            }
        }
        echo '</div>';

        wp_reset_postdata();
        return ob_get_clean();
    }
}
