<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class BadgesEndpoint extends WP_REST_Controller {
    protected $namespace = 'artpulse/v1';
    protected $rest_base = 'badges';

    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_badges' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
            ]
        );
    }

    public function get_badges( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $badges  = $this->get_user_badges( $user_id );
        return new WP_REST_Response( $badges, 200 );
    }

    public function get_user_badges( int $user_id ) : array {
        $badges = [];

        $rsvps = get_user_meta( $user_id, 'ead_rsvps', true );
        $rsvps = is_array( $rsvps ) ? $rsvps : [];
        $count = count( $rsvps );

        if ( $count >= 1 ) {
            $badges[] = [ 'label' => '🎉 Rookie', 'desc' => 'RSVP\'d to 1 event' ];
        }
        if ( $count >= 5 ) {
            $badges[] = [ 'label' => '🧭 Explorer', 'desc' => 'RSVP\'d to 5+ events' ];
        }
        if ( $count >= 10 ) {
            $badges[] = [ 'label' => '💜 Superfan', 'desc' => 'RSVP\'d to 10+ events' ];
        }
        if ( $count >= 3 ) {
            $badges[] = [ 'label' => '🔥 Streak Master', 'desc' => 'RSVP\'d to 3 events in a row' ];
        }

        return $badges;
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
