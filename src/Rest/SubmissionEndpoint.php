<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SubmissionEndpoint extends WP_REST_Controller {
    protected string $namespace;
    protected string $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base = 'submissions';
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
        $pending = get_posts( [
            'post_type'      => 'ead_artwork',
            'post_status'    => 'pending',
            'posts_per_page' => 20,
        ] );

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
        wp_update_post( [ 'ID' => $id, 'post_status' => $new_status ] );

        return new WP_REST_Response( [ 'success' => true, 'new_status' => $new_status ], 200 );
    }
}
