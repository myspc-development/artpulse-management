<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class EventsEndpoint extends WP_REST_Controller {
    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'events';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getEvents' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function getEvents( WP_REST_Request $request ) {
        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = max( 1, (int) ( $request->get_param( 'per_page' ) ?: 10 ) );

        $query = new \WP_Query([
            'post_type'      => 'ead_event',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
        ]);

        $events = array_map(
            static function ( $post ) {
                return [
                    'id'    => $post->ID,
                    'title' => $post->post_title,
                    'date' => (string) get_post_meta($post->ID, 'event_date', true),
                    'link'  => get_permalink( $post ),
                ];
            },
            $query->posts
        );

        return new WP_REST_Response( $events, 200 );
    }
}
