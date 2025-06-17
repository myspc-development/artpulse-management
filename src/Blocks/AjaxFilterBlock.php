<?php
namespace ArtPulse\Blocks;

class AjaxFilterBlock {

    public static function register() {
        add_action('init', [self::class, 'register_block']);
        add_action('rest_api_init', [self::class, 'register_rest_routes']);
    }

    public static function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('artpulse/ajax-filter', [
            'editor_script'   => 'artpulse-ajax-filter-block',
            'render_callback' => [self::class, 'render_callback'],
            'attributes' => [
                'postType' => ['type' => 'string', 'default' => 'ead_artist'],
                'taxonomy' => ['type' => 'string', 'default' => 'artist_specialty'],
            ],
        ]);

        wp_register_script(
            'artpulse-ajax-filter-block',
            plugins_url('assets/js/ajax-filter-block.js', dirname(__DIR__, 3) . '/artpulse-management.php'),
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-api-fetch'],
            filemtime(__DIR__ . '/../../assets/js/ajax-filter-block.js')
        );
    }

    public static function register_rest_routes() {
        register_rest_route('artpulse/v1', '/filtered-posts', [
            'methods' => 'GET',
            'callback' => [self::class, 'rest_filtered_posts'],
            'permission_callback' => '__return_true',
            'args' => [
                'post_type' => ['required' => true],
                'taxonomy'  => ['required' => true],
                'terms'     => ['required' => false],
                'per_page'  => ['required' => false, 'default' => 5],
                'page'      => ['required' => false, 'default' => 1],
            ],
        ]);
    }

    public static function rest_filtered_posts(\WP_REST_Request $request) {
        $post_type = sanitize_text_field($request->get_param('post_type'));
        $taxonomy  = sanitize_text_field($request->get_param('taxonomy'));
        $terms     = $request->get_param('terms');
        $per_page  = intval($request->get_param('per_page'));
        $page      = intval($request->get_param('page'));

        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        ];

        if (!empty($terms)) {
            $terms_array = explode(',', sanitize_text_field($terms));
            $args['tax_query'] = [[
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $terms_array,
            ]];
        }

        $query = new \WP_Query($args);

        $posts = [];
        while ($query->have_posts()) {
            $query->the_post();
            $posts[] = [
                'id'    => get_the_ID(),
                'title' => get_the_title(),
                'link'  => get_permalink(),
            ];
        }
        wp_reset_postdata();

        return [
            'posts'      => $posts,
            'total'      => (int) $query->found_posts,
            'totalPages' => (int) $query->max_num_pages,
        ];
    }

    public static function render_callback($attributes) {
        return '<div class="artpulse-ajax-filter-block" data-post-type="' . esc_attr($attributes['postType']) . '" data-taxonomy="' . esc_attr($attributes['taxonomy']) . '"></div>';
    }
}
