<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ModerationEndpoint extends WP_REST_Controller {
    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base = 'moderation';
    }

    /**
     * Static registration method.
     */
    public static function register() {
        $instance = new self();
        $instance->register_routes();
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [ $this, 'handleModerationAction' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args' => $this->getEndpointArgs(),
            ]
        );
    }

    public function getEndpointArgs() {
        return [
            'post_id' => [
                'required' => true,
                'type' => 'integer',
                'description' => __( 'The ID of the post to moderate.', 'artpulse-management' ),
                'sanitize_callback' => 'absint',
            ],
            'action' => [
                'required' => true,
                'type' => 'string',
                'description' => __( 'The action to perform ("approve" or "reject").', 'artpulse-management' ),
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [ $this, 'validateAction' ],
            ],
        ];
    }

    public function handleModerationAction( WP_REST_Request $request ) {
        // Your existing code for handling moderation.
    }

    public function permissionsCheck( WP_REST_Request $request ) {
        return is_user_logged_in();
    }

    public function validateAction( string $action ): bool {
        return in_array( $action, [ 'approve', 'reject' ], true );
    }
}
