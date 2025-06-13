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
        $user_id    = get_current_user_id();
        $user_rsvps = get_user_meta( $user_id, 'ead_rsvps', true );
        $user_rsvps = is_array( $user_rsvps ) ? $user_rsvps : [];

        $events = get_posts([
            'post_type'      => 'ead_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        $data = array_map(
            static function ( $post ) use ( $user_rsvps ) {
                $id = $post->ID;
                return [
                    'id'     => $id,
                    'title'  => $post->post_title,
                    'start'  => get_post_meta( $id, 'event_date', true ),
                    'url'    => get_permalink( $id ),
                    'rsvped' => in_array( $id, $user_rsvps, true ),
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
