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

        register_rest_route(
            $this->namespace,
            '/event-categories',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_event_categories' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            $this->namespace,
            '/event-locations',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_event_locations' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            $this->namespace,
            '/event-tags',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_event_tags' ],
                'permission_callback' => '__return_true',
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
                $id    = $post->ID;
                $terms = wp_get_post_terms( $id, 'ead_event_category', [ 'fields' => 'names' ] );
                $tags  = wp_get_post_terms( $id, 'post_tag', [ 'fields' => 'names' ] );

                $lat = get_post_meta( $id, 'event_latitude', true );
                $lng = get_post_meta( $id, 'event_longitude', true );

                return [
                    'id'        => $id,
                    'title'     => $post->post_title,
                    'start'     => get_post_meta( $id, 'event_date', true ),
                    'url'       => get_permalink( $id ),
                    'rsvped'    => in_array( $id, $user_rsvps, true ),
                    'category'  => $terms[0] ?? 'Uncategorized',
                    'location'  => get_post_meta( $id, 'event_location', true ) ?: 'Unspecified',
                    'tags'      => $tags,
                    'description' => $post->post_content,
                    'latitude'  => (float) $lat,
                    'longitude' => (float) $lng,
                ];
            },
            $events
        );

        return new WP_REST_Response( $data, 200 );
    }

    public function get_event_categories( WP_REST_Request $request ) {
        $terms = get_terms( [ 'taxonomy' => 'ead_event_category', 'hide_empty' => false ] );

        $data = array_map(
            static function ( $term ) {
                return [
                    'slug' => $term->slug,
                    'name' => $term->name,
                ];
            },
            is_array( $terms ) ? $terms : []
        );

        return new WP_REST_Response( $data, 200 );
    }

    public function get_event_locations( WP_REST_Request $request ) {
        global $wpdb;
        $results = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'event_location'" );
        return array_filter( $results );
    }

    public function get_event_tags( WP_REST_Request $request ) {
        $terms = get_terms( [ 'taxonomy' => 'post_tag', 'hide_empty' => false ] );
        return array_map( static fn( $t ) => [ 'name' => $t->name ], $terms );
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
