<?php

namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SubmissionEndpoint extends WP_REST_Controller {
    protected $namespace = 'artpulse/v1';
    protected $rest_base = 'submissions';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_pending_submissions' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/stats',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_submission_stats' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/submission/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_submission_status' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'action' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );
    }

    public function permissions_check( WP_REST_Request $request ): bool {
        $user = wp_get_current_user();

        return current_user_can( 'manage_options' ) || in_array( 'organization', $user->roles, true );
    }

    public function get_pending_submissions( WP_REST_Request $request ) {
        $pending = get_posts(
            [
                'post_type'      => 'ead_artwork',
                'post_status'    => 'pending',
                'posts_per_page' => 20,
            ]
        );

        $items = array_map(
            static function ( $post ) {
                return [
                    'id'    => $post->ID,
                    'title' => $post->post_title,
                    'author' => get_the_author_meta( 'display_name', $post->post_author ),
                    'thumb' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
                    'date'  => get_the_date( '', $post ),
                ];
            },
            $pending
        );

        return new WP_REST_Response( $items, 200 );
    }

    public function update_submission_status( WP_REST_Request $request ) {
        $id     = (int) $request['id'];
        $action = sanitize_text_field( $request->get_param( 'action' ) );

        if ( ! in_array( $action, [ 'approve', 'reject' ], true ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid action' ], 400 );
        }

        $new_status = $action === 'approve' ? 'publish' : 'trash';
        $updated    = wp_update_post( [ 'ID' => $id, 'post_status' => $new_status ], true );

        if ( is_wp_error( $updated ) ) {
            error_log( 'ArtPulse Management: Error updating submission status: ' . $updated->get_error_message() );

            return new WP_REST_Response( [ 'error' => 'Failed to update submission status' ], 500 );
        }

        return new WP_REST_Response( [ 'success' => true, 'new_status' => $new_status ], 200 );
    }

    public function get_submission_stats( WP_REST_Request $request ) {
        global $wpdb;

        $month_start = date( 'Y-m-01 00:00:00' );
        $month_start = sanitize_text_field( $month_start );

        $pending = wp_count_posts( 'ead_artwork' )->pending;

        $approved = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ead_artwork' AND post_status = 'publish' AND post_date >= %s",
                $month_start
            )
        );

        $rejected = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ead_artwork' AND post_status = 'trash' AND post_date >= %s",
                $month_start
            )
        );

        $rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(post_date) as date, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = 'ead_artwork' AND post_status = 'publish' AND post_date >= %s GROUP BY DATE(post_date)",
                date( 'Y-m-d', strtotime( '-30 days' ) )
            )
        );
        $labels = array_map( static fn( $r ) => $r->date, $rows );
        $data   = array_map( static fn( $r ) => (int) $r->count, $rows );

        return new WP_REST_Response(
            [
                'pending'  => (int) $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'labels'   => $labels,
                'data'     => $data,
            ],
            200
        );
    }
}
