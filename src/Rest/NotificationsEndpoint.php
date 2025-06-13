<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class NotificationsEndpoint
 *
 * Provides notifications for the current user.
 */
class NotificationsEndpoint extends WP_REST_Controller {
    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'notifications';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getNotifications' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );
    }

    public function getNotifications( WP_REST_Request $request ) {
        $user_id = get_current_user_id();

        $messages = get_posts([
            'post_type'      => 'ead_notification',
            'meta_key'       => '_ead_user_id',
            'meta_value'     => $user_id,
            'post_status'    => 'publish',
            'posts_per_page' => 10,
        ]);

        $results = array_map(
            static function ( $msg ) {
                return [
                    'title'   => get_the_title( $msg ),
                    'content' => apply_filters( 'the_content', $msg->post_content ),
                    'date'    => get_the_date( '', $msg ),
                ];
            },
            $messages
        );

        return new WP_REST_Response( $results, 200 );
    }

    public function permissionsCheck( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
