<?php
namespace ArtPulse\Blocks;

class FilteredListShortcodeBlock {

    public static function register() {
        add_action('init', [self::class, 'register_block']);
    }

    public static function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('artpulse/filtered-list-shortcode', [
            'editor_script' => 'artpulse-filtered-list-shortcode-block',
            'render_callback' => [self::class, 'render_callback'],
            'attributes' => [
                'postType' => ['type' => 'string', 'default' => 'ead_artist'],
                'taxonomy' => ['type' => 'string', 'default' => 'artist_specialty'],
                'terms' => ['type' => 'string', 'default' => ''],
                'postsPerPage' => ['type' => 'number', 'default' => 5],
            ],
        ]);

        wp_register_script(
            'artpulse-filtered-list-shortcode-block',
            plugins_url('assets/js/filtered-list-shortcode-block.js', dirname(__DIR__, 3) . '/artpulse-management.php'),
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components'],
            filemtime(__DIR__ . '/../../assets/js/filtered-list-shortcode-block.js')
        );
    }

    public static function render_callback($attributes) {
        $atts = [
            'post_type' => $attributes['postType'] ?? 'ead_artist',
            'taxonomy' => $attributes['taxonomy'] ?? 'artist_specialty',
            'terms' => $attributes['terms'] ?? '',
            'posts_per_page' => $attributes['postsPerPage'] ?? 5,
        ];

        $shortcode = sprintf(
            '[ap_filtered_list post_type="%s" taxonomy="%s" terms="%s" posts_per_page="%d"]',
            esc_attr($atts['post_type']),
            esc_attr($atts['taxonomy']),
            esc_attr($atts['terms']),
            intval($atts['posts_per_page'])
        );

        return do_shortcode($shortcode);
    }
}
