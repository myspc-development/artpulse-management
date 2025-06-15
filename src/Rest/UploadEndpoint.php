<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class UploadEndpoint extends WP_REST_Controller {
    protected $namespace = 'artpulse/v1';
    protected $rest_base = 'upload';

    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_upload' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/uploads',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_user_uploads' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
            ]
        );
    }

    public function handle_upload( WP_REST_Request $request ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $user_id = get_current_user_id();
        $title   = sanitize_text_field( $request->get_param( 'title' ) ?? '' );

        if ( empty( $_FILES['file'] ) ) {
            return new WP_REST_Response( [ 'error' => 'No file received' ], 400 );
        }

        $upload_id = media_handle_upload( 'file', 0 );

        if ( is_wp_error( $upload_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Upload failed' ], 400 );
        }

        $artwork_id = wp_insert_post(
            [
                'post_type'   => 'ead_artwork',
                'post_status' => 'pending',
                'post_title'  => $title,
                'post_author' => $user_id,
                'meta_input'  => [
                    '_ead_attachment_id' => $upload_id,
                ],
            ],
            true
        );

        if ( is_wp_error( $artwork_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Could not create artwork' ], 500 );
        }

        set_post_thumbnail( $artwork_id, $upload_id );

        return new WP_REST_Response(
            [
                'success'       => true,
                'attachment_id' => $upload_id,
                'artwork_id'    => $artwork_id,
            ],
            200
        );
    }

    public function get_user_uploads( WP_REST_Request $request ) {
        $user_id = get_current_user_id();

        $uploads = get_posts(
            [
                'post_type'      => 'ead_artwork',
                'author'         => $user_id,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]
        );

        $items = array_map(
            static function ( $post ) {
                return [
                    'title'       => $post->post_title,
                    'description' => (string) $post->post_content,
                    'image_url'   => get_the_post_thumbnail_url( $post->ID, 'medium' ),
                ];
            },
            $uploads
        );

        return new WP_REST_Response( $items, 200 );
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
