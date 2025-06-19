<?php
namespace ArtPulse\Blocks;

class TaxonomyFilterBlock {

    public static function register() {
        add_action('init', [self::class, 'register_block']);
    }

    public static function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('artpulse/taxonomy-filter', [
            'editor_script' => 'artpulse-taxonomy-filter-block',
            'render_callback' => [self::class, 'render_callback'],
            'attributes' => [
                'postType' => ['type' => 'string', 'default' => 'artpulse_artist'],
                'taxonomy' => ['type' => 'string', 'default' => 'artist_specialty'],
                'terms' => ['type' => 'array', 'default' => []],
            ],
        ]);

        wp_register_script(
            'artpulse-taxonomy-filter-block',
            plugins_url('assets/js/taxonomy-filter-block.js', dirname(__DIR__, 3) . '/artpulse-management.php'),
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-api-fetch'],
            filemtime(__DIR__ . '/../../assets/js/taxonomy-filter-block.js')
        );
    }

    public static function render_callback($attributes) {
        if (empty($attributes['postType']) || empty($attributes['taxonomy'])) {
            return '<p>Please select post type and taxonomy.</p>';
        }

        $post_type = sanitize_text_field($attributes['postType']);
        $taxonomy = sanitize_text_field($attributes['taxonomy']);
        $terms = $attributes['terms'] ?? [];

        $args = [
            'post_type' => $post_type,
            'posts_per_page' => 5,
            'post_status' => 'publish',
        ];

        if (!empty($terms)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => $terms,
                ],
            ];
        }

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return '<p>' . __('No posts found.', 'artpulse-management') . '</p>';
        }

        ob_start();
        echo '<ul class="artpulse-taxonomy-filter-list">';
        while ($query->have_posts()) {
            $query->the_post();
            printf(
                '<li><a href="%s">%s</a></li>',
                esc_url(get_permalink()),
                esc_html(get_the_title())
            );
        }
        echo '</ul>';
        wp_reset_postdata();

        return ob_get_clean();
    }
}
