<?php
namespace ArtPulse\Blocks;

class AdvancedTaxonomyFilterBlock {

    public static function register() {
        add_action('init', [self::class, 'register_block']);
    }

    public static function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('artpulse/advanced-taxonomy-filter', [
            'editor_script' => 'artpulse-advanced-taxonomy-filter-block',
            'render_callback' => [self::class, 'render_callback'],
            'attributes' => [
                'postType' => ['type' => 'string', 'default' => 'ead_artist'],
                'taxonomy' => ['type' => 'string', 'default' => 'artist_specialty'],
            ],
        ]);

        wp_register_script(
            'artpulse-advanced-taxonomy-filter-block',
            plugins_url('assets/js/advanced-taxonomy-filter-block.js', dirname(__DIR__, 3) . '/artpulse-management.php'),
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-api-fetch'],
            filemtime(__DIR__ . '/../../assets/js/advanced-taxonomy-filter-block.js')
        );
    }

    public static function render_callback($attributes) {
        // Render fallback content (frontend rendering is handled by JS)
        return '<div class="artpulse-advanced-taxonomy-filter-block">Loading filtered posts...</div>';
    }
}
