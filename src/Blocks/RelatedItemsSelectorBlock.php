<?php
namespace ArtPulse\Blocks;

class RelatedItemsSelectorBlock
{
    public static function register() {
        add_action('init', [self::class, 'register_block_and_meta']);
    }

    public static function register_block_and_meta() {
        // Register block editor script (adjust the path accordingly)
        wp_register_script(
            'artpulse-related-items-selector',
            plugins_url('assets/js/blocks/related-items-selector.js', dirname(__DIR__, 3) . '/artpulse-management.php'),
            [
                'wp-blocks',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-editor',
                'wp-api-fetch',
            ],
            filemtime(__DIR__ . '/../../assets/js/blocks/related-items-selector.js')
        );

        // Example: Register post meta fields with REST API enabled

        // Meta for multiple related artworks for artists (array)
        register_post_meta('artpulse_artist', '_ap_artist_artworks', [
            'show_in_rest' => true,
            'single'       => false,
            'type'         => 'array',
            'items'        => ['type' => 'integer'],
            'auth_callback'=> function() {
                return current_user_can('edit_posts');
            },
        ]);

        // Meta for single related artist for artwork (integer)
        register_post_meta('artpulse_artwork', '_ap_artwork_artist', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'integer',
            'auth_callback'=> function() {
                return current_user_can('edit_posts');
            },
        ]);

        // Register the block type
        register_block_type('artpulse/related-items-selector', [
            'editor_script' => 'artpulse-related-items-selector',
            'attributes'    => [
                'postType' => [
                    'type' => 'string',
                    'default' => 'artpulse_artist',
                ],
                'metaKey' => [
                    'type' => 'string',
                    'default' => '_ap_artist_artworks',
                ],
                'label' => [
                    'type' => 'string',
                    'default' => 'Select Related Items',
                ],
                'multiple' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
            'render_callback' => [self::class, 'render_block'],
        ]);
    }

    public static function render_block($attributes) {
        $post = get_post();
        if (!$post) {
            return '';
        }

        $meta_key = $attributes['metaKey'] ?? '';
        if (!$meta_key) {
            return '';
        }

        $related = get_post_meta($post->ID, $meta_key, true);
        if (!$related) {
            return '<p>' . __('No related items selected.', 'artpulse-management') . '</p>';
        }

        if (is_array($related)) {
            $items = [];
            foreach ($related as $id) {
                $title = get_the_title($id);
                if ($title) {
                    $url = get_permalink($id);
                    $items[] = sprintf('<li><a href="%s">%s</a></li>', esc_url($url), esc_html($title));
                }
            }
            return '<ul class="ap-related-items-list">' . implode('', $items) . '</ul>';
        } else {
            $title = get_the_title($related);
            $url = get_permalink($related);
            if ($title) {
                return sprintf('<p><a href="%s">%s</a></p>', esc_url($url), esc_html($title));
            }
            return '';
        }
    }
}
