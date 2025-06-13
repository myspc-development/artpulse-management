<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class CommentEndpoint
 *
 * Handles comments via the REST API.
 */
class CommentEndpoint extends WP_REST_Controller {

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
        $this->rest_base  = 'comments';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // GET comments for a post.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getComments' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => [
                    'post_id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'description'       => __( 'The ID of the post to retrieve comments for.', 'artpulse-management' ),
                        'sanitize_callback' => 'absint',
                        'validate_callback' => [ $this, 'validatePostId' ],
                    ],
                ],
            ]
        );

        // POST approve/reject comment.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/moderate',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'moderateComment' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    /**
     * Get comments for a post.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getComments( WP_REST_Request $request ) {
        $postId = (int) $request->get_param( 'post_id' );

        if ( ! get_post_status( $postId ) ) {
            return new WP_Error( 'invalid_post_id', __( 'Invalid post ID.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view comments.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $comments = get_comments(
            [
                'post_id' => $postId,
                'status'  => 'approve', // Only approved comments
            ]
        );

        $output = [];

        foreach ( $comments as $comment ) {
            $output[] = [
                'id'      => $comment->comment_ID,
                'author'  => $comment->comment_author,
                'content' => apply_filters( 'comment_text', $comment->comment_content ),
                'date'    => $comment->comment_date,
            ];
        }

        return new WP_REST_Response( $output, 200 );
    }

    /**
     * Moderate (approve/reject) a comment.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function moderateComment( WP_REST_Request $request ) {
        $commentId = (int) $request->get_param( 'comment_id' );
        $action     = sanitize_text_field( $request->get_param( 'action' ) );

        if ( ! current_user_can( 'moderate_comments' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to moderate comments.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        if ( ! in_array( $action, [ 'approve', 'reject' ], true ) ) {
            return new WP_Error( 'invalid_action', __( 'Invalid action.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        $comment = get_comment( $commentId );

        if ( ! $comment ) {
            return new WP_Error( 'comment_not_found', __( 'Comment not found.', 'artpulse-management' ), [ 'status' => 404 ] );
        }

        if ( $action === 'approve' ) {
            $result = wp_set_comment_status( $commentId, 'approve' );
            $resultMessage = __( 'Comment approved.', 'artpulse-management' );
        } else {
            $result = wp_set_comment_status( $commentId, 'hold' );
            $resultMessage = __( 'Comment rejected.', 'artpulse-management' );
        }

        if ( false === $result ) {
            error_log( 'ArtPulse Management: Failed to moderate comment ID ' . $commentId );

            return new WP_Error( 'failed_to_moderate_comment', __( 'Failed to moderate comment.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => $resultMessage,
            ],
            200
        );
    }

    /**
     * Validate post ID callback.
     *
     * @param int $postId Post ID.
     *
     * @return bool
     */
    public function validatePostId( $postId ) {
        return (bool) get_post_status( $postId );
    }

    /**
     * Permission check callback.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool
     */
    public function permissionsCheck( WP_REST_Request $request ) {
        if ( $request->get_method() === 'GET' ) {
            return current_user_can( 'read' );
        } elseif ( $request->get_method() === 'EDIT' ) {
            return current_user_can( 'moderate_comments' );
        }

        return false;
    }

    /**
     * Define endpoint arguments.
     *
     * @return array
     */
    public function getEndpointArgs() {
        return [
            'comment_id' => [
                'required'          => true,
                'type'              => 'integer',
                'description'       => __( 'The ID of the comment to moderate.', 'artpulse-management' ),
                'sanitize_callback' => 'absint',
            ],
            'action'     => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __( 'The action to perform (approve or reject).', 'artpulse-management' ),
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
    public function validateAction( $action ) {
        return in_array( $action, [ 'approve', 'reject' ], true );
    }
}