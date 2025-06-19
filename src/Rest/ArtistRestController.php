<?php

namespace ArtPulse\Rest;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ArtistRestController extends WP_REST_Controller
{
    /**
     * Namespace for the REST API
     * @var string
     */
    protected $namespace = 'artpulse/v1';

    /**
     * Constructor: register routes
     */
    public function __construct()
    {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    /**
     * Optional static initializer for consistency with other controllers
     */
    public static function register(): void
    {
        new self();
    }

    /**
     * Register REST API routes for artists
     */
    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            '/artists',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_artists' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            $this->namespace,
            '/artists/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_artist' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => [
                        'validate_callback' => 'is_numeric',
                    ],
                ],
            ]
        );
    }

    /**
     * GET /artists
     * Return list of artists
     */
    public function get_artists(WP_REST_Request $request): WP_REST_Response
    {
        $query = new \WP_Query([
            'post_type'      => 'artpulse_artist',
            'posts_per_page' => -1,
            // Only fetch IDs for a lighter query and skip found rows since
            // pagination isn't used here.
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        $data = [];
        foreach ($query->posts as $post_id) {
            $item = [
                'id'    => $post_id,
                'title' => get_the_title($post_id),
                'link'  => get_permalink($post_id),
            ];
            $data[] = $this->prepare_item_for_response($item, get_post($post_id));
        }

        return rest_ensure_response($data);
    }

    /**
     * GET /artists/{id}
     * Return single artist
     */
    public function get_artist(WP_REST_Request $request): WP_REST_Response
    {
        $id   = (int) $request['id'];
        $post = get_post($id);

        if (empty($post) || 'artpulse_artist' !== $post->post_type) {
            return new WP_REST_Response(['message' => 'Artist not found'], 404);
        }

        $item = [
            'id'      => $post->ID,
            'title'   => get_the_title($post),
            'content' => apply_filters('the_content', $post->post_content),
            'meta'    => [
                'bio' => get_post_meta($post->ID, '_ap_artist_bio', true),
                'org' => get_post_meta($post->ID, '_ap_artist_org', true),
            ],
            'link'    => get_permalink($post),
        ];

        return rest_ensure_response($item);
    }
}
