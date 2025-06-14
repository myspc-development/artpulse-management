<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Query;

class SyncEndpoint extends WP_REST_Controller {
    protected $namespace = 'artpulse/v1';
    protected $rest_base = 'sync';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_changes' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'since' => [
                        'description' => __( 'ISO8601 date to fetch updates after.', 'artpulse-management' ),
                        'type'        => 'string',
                        'required'    => false,
                    ],
                ],
            ]
        );
    }

    public function get_changes( WP_REST_Request $request ) {
        $since = sanitize_text_field( $request->get_param( 'since' ) );

        $args = [
            'post_type'      => [ 'ead_event', 'ead_organization', 'ead_artist', 'ead_artwork' ],
            'post_status'    => 'publish',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'posts_per_page' => 50,
        ];

        if ( $since ) {
            $args['date_query'] = [
                [
                    'column' => 'post_modified_gmt',
                    'after'  => $since,
                ],
            ];
        }

        $query   = new WP_Query( $args );
        $updates = [];

        foreach ( $query->posts as $post ) {
            $updates[] = [
                'id'       => $post->ID,
                'type'     => $post->post_type,
                'modified' => $post->post_modified_gmt,
            ];
        }

        return new WP_REST_Response( $updates, 200 );
    }
}
