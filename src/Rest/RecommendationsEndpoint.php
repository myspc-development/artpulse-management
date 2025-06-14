<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Query;

class RecommendationsEndpoint extends WP_REST_Controller {
    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'recommendations';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getRecommendations' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );
    }

    public function getRecommendations( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        $args = [
            'post_type'      => 'ead_event',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query  = new WP_Query( $args );
        $events = [];
        foreach ( $query->posts as $post ) {
            $events[] = $this->format_event_data( $post );
        }

        return new WP_REST_Response( $events, 200 );
    }

    private function format_event_data( $post ) {
        return [
            'id'          => $post->ID,
            'title'       => get_the_title( $post->ID ),
            'link'        => get_permalink( $post ),
            'description' => apply_filters( 'the_content', $post->post_content ),
            'start_date'  => get_post_meta( $post->ID, 'event_start_date', true ),
            'end_date'    => get_post_meta( $post->ID, 'event_end_date', true ),
            'venue'       => [
                'name'          => get_post_meta( $post->ID, 'venue_name', true ),
                'street_address' => get_post_meta( $post->ID, 'event_street_address', true ),
                'city'          => get_post_meta( $post->ID, 'event_city', true ),
                'state'         => get_post_meta( $post->ID, 'event_state', true ),
                'country'       => get_post_meta( $post->ID, 'event_country', true ),
                'postcode'      => get_post_meta( $post->ID, 'event_postcode', true ),
            ],
        ];
    }

    public function permissionsCheck( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
