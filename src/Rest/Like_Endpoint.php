<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Like_Endpoint
 *
 * Handles like and unlike functionality via the REST API.
 */
class Like_Endpoint extends WP_REST_Controller {

    /**
     * The namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * The REST base.
     *
     * @var string
     */
    protected $rest_base;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'likes';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handleLike' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    /**
     * Handle like/unlike action.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handleLike( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You must be logged in to like or unlike a post.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        $postId = (int) $request->get_param( 'post_id' );
        $action = sanitize_text_field( $request->get_param( 'action' ) );

        if ( empty( $postId ) ) {
            return new WP_Error( 'missing_post_id', __( 'Post ID is required.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( ! in_array( $action, [ 'like', 'unlike' ], true ) ) {
            return new WP_Error( 'invalid_action', __( 'Invalid action. Must be "like" or "unlike".', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( ! $this->validatePostType( $postId ) ) {
            return new WP_Error( 'invalid_post_type', __( 'Liking is not supported for this post type.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        // Check if post exists.
        if ( ! get_post_status( $postId ) ) {
            return new WP_Error( 'post_not_found', __( 'Post not found.', 'artpulse-management' ), [ 'status' => 404 ] );
        }

        $userId  = get_current_user_id();
        $metaKey = 'artpulse_likes';

        $likes = get_post_meta( $postId, $metaKey, true );

        if ( ! is_array( $likes ) ) {
            $likes = [];
        }

        if ( $action === 'like' ) {
            if ( ! in_array( $userId, $likes, true ) ) {
                $likes[] = $userId;
                update_post_meta( $postId, $metaKey, $likes );
            }
        } else {
            $likes = array_diff( $likes, [ $userId ] );
            update_post_meta( $postId, $metaKey, $likes );
        }

        $likesCount = count( $likes );

        return new WP_REST_Response(
            [
                'success'     => true,
                'message'     => ucfirst( $action ) . ' ' . __( 'action completed.', 'artpulse-management' ),
                'likes_count' => $likesCount,
            ],
            200
        );
    }

    /**
     * Validate that the post type supports liking.
     *
     * @param int $postId Post ID.
     *
     * @return bool
     */
    private function validatePostType( int $postId ): bool {
        $postType = get_post_type( $postId );
        $supportedPostTypes = apply_filters( 'ead_supported_like_post_types', [ 'ead_artwork', 'ead_event' ] ); // Example

        return in_array( $postType, $supportedPostTypes, true );
    }

    /**
     * Permission check callback.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool
     */
    public function permissionsCheck( WP_REST_Request $request ) {
        return is_user_logged_in();
    }

    /**
     * Define endpoint arguments.
     *
     * @return array
     */
    public function getEndpointArgs() {
        return [
            'post_id' => [
                'required'          => true,
                'type'              => 'integer',
                'description'       => __( 'The ID of the post to like or unlike.', 'artpulse-management' ),
                'sanitize_callback' => 'absint',
            ],
            'action'  => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __( 'The action to perform ("like" or "unlike").', 'artpulse-management' ),
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [ $this, 'validateAction' ],
            ],
        ];
    }

    /**
     * Validate action callback.
     *
     * @param string $action Action to perform.
     *
     * @return bool
     */
    public function validateAction( string $action ): bool {
        return in_array( $action, [ 'like', 'unlike' ], true );
    }
}