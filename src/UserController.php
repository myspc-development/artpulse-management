<?php
namespace Artpulse;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class UserController extends WP_REST_Controller {
    protected $namespace = 'artpulse/v1';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/calendar',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_calendar_events' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/rsvp',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'add_rsvp' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => [
                    'event_id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/rsvp',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'remove_rsvp' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => [
                    'event_id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
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

                return [
                    'id'          => $id,
                    'title'       => $post->post_title,
                    'start'       => ead_get_meta( $id, 'event_date'),
                    'url'         => get_permalink( $id ),
                    'rsvped'      => in_array( $id, $user_rsvps, true ),
                    'category'    => $terms[0] ?? 'Uncategorized',
                    'tags'        => $tags,
                    'description' => (string) $post->post_content,
                ];
            },
            $events
        );

        return new WP_REST_Response( $data, 200 );
    }

    public function add_rsvp( WP_REST_Request $request ) {
        $user_id  = get_current_user_id();
        $event_id = (int) $request->get_param( 'event_id' );
        $post     = get_post( $event_id );
        if ( ! $post || $post->post_type !== 'ead_event' ) {
            return new WP_REST_Response( [ 'error' => 'Invalid event ID' ], 400 );
        }

        $rsvps = get_user_meta( $user_id, 'ead_rsvps', true );
        $rsvps = is_array( $rsvps ) ? $rsvps : [];

        if ( ! in_array( $event_id, $rsvps, true ) ) {
            $rsvps[] = $event_id;
            update_user_meta( $user_id, 'ead_rsvps', $rsvps );
        }

        return new WP_REST_Response(
            [
                'status' => 'added',
                'rsvps'  => $rsvps,
            ],
            200
        );
    }

    public function remove_rsvp( WP_REST_Request $request ) {
        $user_id  = get_current_user_id();
        $event_id = (int) $request->get_param( 'event_id' );
        $post     = get_post( $event_id );
        if ( ! $post || $post->post_type !== 'ead_event' ) {
            return new WP_REST_Response( [ 'error' => 'Invalid event ID' ], 400 );
        }

        $rsvps = get_user_meta( $user_id, 'ead_rsvps', true );
        $rsvps = is_array( $rsvps ) ? $rsvps : [];

        if ( in_array( $event_id, $rsvps, true ) ) {
            $rsvps = array_values( array_diff( $rsvps, [ $event_id ] ) );
            update_user_meta( $user_id, 'ead_rsvps', $rsvps );
        }

        return new WP_REST_Response(
            [
                'status' => 'removed',
                'rsvps'  => $rsvps,
            ],
            200
        );
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

    public function check_permissions() : bool {
        return is_user_logged_in();
    }
}
