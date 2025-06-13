<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class BadgesEndpoint extends WP_REST_Controller {
    protected string $namespace = 'artpulse/v1';
    protected string $rest_base = 'badges';

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

        $favorites = get_user_meta( $user_id, 'ead_favorites', true );
        $favorites = is_array( $favorites ) ? $favorites : [];

        $rsvps = get_user_meta( $user_id, 'ead_rsvps', true );
        $rsvps = is_array( $rsvps ) ? $rsvps : [];

        if ( count( $rsvps ) >= 1 ) {
            $badges[] = [ 'label' => '🎉 First RSVP', 'desc' => 'Thanks for joining an event!' ];
        }
        if ( count( $rsvps ) >= 5 ) {
            $badges[] = [ 'label' => '🏃 Frequent Attendee', 'desc' => 'RSVPed to 5+ events' ];
        }
        if ( count( $favorites ) >= 10 ) {
            $badges[] = [ 'label' => '💖 Super Fan', 'desc' => 'Favorited 10+ items' ];
        }
        if ( count( $rsvps ) >= 5 && count( $favorites ) >= 10 ) {
            $badges[] = [ 'label' => '🌟 Power User', 'desc' => 'You’re all in!' ];
        }

        return $badges;
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
