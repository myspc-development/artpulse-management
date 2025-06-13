<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class NotificationSettingsEndpoint
 *
 * Handles notification settings via REST API.
 */
class NotificationSettingsEndpoint extends WP_REST_Controller {

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
        $this->rest_base  = 'settings/notification';

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
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getNotificationSettings' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'updateNotificationSettings' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    /**
     * Get current notification settings.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getNotificationSettings( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view notification settings.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $settings = get_option( 'artpulse_notification_settings', [] );

        return new WP_REST_Response( $settings, 200 );
    }

    /**
     * Update notification settings.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function updateNotificationSettings( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to update notification settings.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $settings = $request->get_param( 'settings' );

        if ( ! is_array( $settings ) ) {
            return new WP_Error( 'invalid_settings_format', __( 'Settings must be an array.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        $sanitizedSettings = $this->sanitizeSettings( $settings );

        if ( is_wp_error( $sanitizedSettings ) ) {
            return $sanitizedSettings;
        }

        $updateResult = update_option( 'artpulse_notification_settings', $sanitizedSettings );

        if ( ! $updateResult ) {
            error_log( 'ArtPulse Management: Failed to update notification settings.' );

            return new WP_Error( 'failed_to_update_settings', __( 'Failed to update notification settings.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        return new WP_REST_Response( [ 'success' => true, 'message' => __( 'Notification settings updated successfully.', 'artpulse-management' ) ], 200 );
    }

    /**
     * Permission check callback.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool
     */
    public function permissionsCheck( WP_REST_Request $request ) {
        return current_user_can( 'manage_options' );
    }

    /**
     * Define endpoint arguments.
     *
     * @return array
     */
    public function getEndpointArgs() {
        return [
            'settings' => [
                'required'    => true,
                'type'        => 'array',
                'description' => __( 'Notification settings to update.', 'artpulse-management' ),
                'sanitize_callback' => [ $this, 'sanitizeSettings' ],
            ],
        ];
    }

    /**
     * Sanitize the settings array.
     *
     * @param array $settings The settings to sanitize.
     *
     * @return array|WP_Error The sanitized settings or a WP_Error object if there was an error.
     */
    public function sanitizeSettings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return new WP_Error( 'invalid_settings', __( 'Settings must be an array.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        $sanitized = [];
        $settingDefinitions = [
            'new_review_notification' => 'sanitize_checkbox',
            'new_event_submission_notification' => 'sanitize_checkbox',
            'firebase_server_key'        => 'sanitize_text_field',
            'enable_push_notifications'  => 'sanitize_checkbox',
            'push_event_approved'        => 'sanitize_checkbox',
            'push_event_updated'         => 'sanitize_checkbox',
            'push_organization_approved' => 'sanitize_checkbox',
            'push_organization_updated'  => 'sanitize_checkbox',
        ];

        foreach ( $settingDefinitions as $key => $sanitizeCallback ) {
            if ( isset( $settings[ $key ] ) ) {
                if ( is_callable( $sanitizeCallback ) ) {
                    $sanitized[ $key ] = call_user_func( $sanitizeCallback, $settings[ $key ] );
                } else {
                    error_log( "ArtPulse Management: Invalid sanitize callback: $sanitizeCallback" );
                    return new WP_Error( 'invalid_sanitize_callback', sprintf( __( 'Invalid sanitize callback: %s', 'artpulse-management' ), $sanitizeCallback ), [ 'status' => 500 ] );
                }
            }
        }

        return $sanitized;
    }
    /**
     * Sanitize checkbox.
     *
     * @param string $checkbox Checkbox value.
     *
     * @return bool
     */
    public function sanitize_checkbox( $checkbox ) {
        return filter_var( $checkbox, FILTER_VALIDATE_BOOLEAN );
    }
}