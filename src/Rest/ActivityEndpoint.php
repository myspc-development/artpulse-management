<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class ActivityEndpoint extends WP_REST_Controller {
    protected string $namespace = 'artpulse/v1';
    protected string $rest_base = 'activity';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_activity_data' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
            ]
        );
    }

    public function get_activity_data( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $rsvps   = get_user_meta( $user_id, 'ead_rsvps', true );
        $rsvps   = is_array( $rsvps ) ? $rsvps : [];

        $counts = [];
        foreach ( $rsvps as $event_id ) {
            $date  = get_post_field( 'post_date', $event_id );
            $month = date( 'Y-m', strtotime( $date ) );
            $counts[ $month ] = isset( $counts[ $month ] ) ? $counts[ $month ] + 1 : 1;
        }

        ksort( $counts );

        return new WP_REST_Response(
            [
                'labels' => array_keys( $counts ),
                'data'   => array_values( $counts ),
            ],
            200
        );
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
