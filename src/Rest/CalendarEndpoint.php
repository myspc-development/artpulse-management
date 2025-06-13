<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class CalendarEndpoint extends WP_REST_Controller {
    protected string $namespace = 'artpulse/v1';
    protected string $rest_base = 'calendar';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_calendar_events' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
            ]
        );
    }

    public function get_calendar_events( WP_REST_Request $request ) {
        $events = get_posts([
            'post_type'      => 'ead_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        $data = array_map(
            static function ( $post ) {
                return [
                    'id'    => $post->ID,
                    'title' => $post->post_title,
                    'start' => get_post_meta( $post->ID, 'event_date', true ),
                    'url'   => get_permalink( $post->ID ),
                ];
            },
            $events
        );

        return new WP_REST_Response( $data, 200 );
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
