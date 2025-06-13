<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class RsvpEndpoint extends WP_REST_Controller {
    protected string $namespace = 'artpulse/v1';
    protected string $rest_base = 'rsvp';

    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'toggle_rsvp' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
                'args'                => [
                    'event_id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    public function toggle_rsvp( WP_REST_Request $request ) {
        $user_id  = get_current_user_id();
        $event_id = (int) $request->get_param( 'event_id' );

        $post = function_exists('get_post') ? get_post( $event_id ) : null;
        if ( ! $post || $post->post_type !== 'ead_event' ) {
            return new WP_REST_Response( [ 'error' => 'Invalid event ID' ], 400 );
        }

        $rsvps = get_user_meta( $user_id, 'ead_rsvps', true );
        $rsvps = is_array( $rsvps ) ? $rsvps : [];

        if ( in_array( $event_id, $rsvps, true ) ) {
            $rsvps = array_values( array_diff( $rsvps, [ $event_id ] ) );
            $status = 'removed';
        } else {
            $rsvps[] = $event_id;
            $status = 'added';
        }

        update_user_meta( $user_id, 'ead_rsvps', $rsvps );

        return new WP_REST_Response(
            [
                'status' => $status,
                'rsvps'  => $rsvps,
            ],
            200
        );
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
