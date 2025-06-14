<?php
/**
 * File: src/Rest/SettingsEndpoint.php
 *
 * Description: Handles retrieving and updating plugin settings via the REST API.
 */
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class SettingsEndpoint
 */
class SettingsEndpoint extends WP_REST_Controller {

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
        $this->rest_base  = 'settings';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // GET settings.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getSettings' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );

        // UPDATE settings.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'updateSettings' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    /**
     * Get plugin settings.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getSettings( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view plugin settings.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $settings = get_option( 'artpulse_plugin_settings', [] );

        return new WP_REST_Response( $settings, 200 );
    }

    /**
     * Update plugin settings.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function updateSettings( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to update plugin settings.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $newSettings = $request->get_param( 'settings' );

        if ( ! is_array( $newSettings ) ) {
            return new WP_Error( 'invalid_settings_format', __( 'Invalid settings format. Settings must be an array.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        // Sanitize settings based on type.
        $sanitizedSettings = $this->sanitizeSettings( $newSettings );

        if ( is_wp_error( $sanitizedSettings ) ) {
            return $sanitizedSettings; // Return WP_Error from sanitizeSettings
        }

        $updateResult = update_option( 'artpulse_plugin_settings', $sanitizedSettings );

        if ( ! $updateResult ) {
            error_log( 'ArtPulse Management: Failed to update plugin settings.' );

            return new WP_Error( 'failed_to_update_settings', __( 'Failed to update plugin settings.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        return new WP_REST_Response(
            [
                'success'  => true,
                'message'  => __( 'Settings updated successfully.', 'artpulse-management' ),
                'settings' => $sanitizedSettings,
            ],
            200
        );
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
     * Define endpoint arguments for updating settings.
     *
     * @return array
     */
    public function getEndpointArgs() {
        return [
            'settings' => [
                'required'    => true,
                'type'        => 'array',
                'description' => __( 'Plugin settings to update.', 'artpulse-management' ),
                'validate_callback' => [$this, 'validateSettings'],
                'sanitize_callback' => [$this, 'sanitizeSettings'],
            ],
        ];
    }

    /**
     * Sanitize settings array.
     *
     * @param array $settings Settings array.
     *
     * @return array|WP_Error Sanitized settings array or WP_Error on failure.
     */
    public function sanitizeSettings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return new WP_Error( 'invalid_settings', __( 'Settings must be an array.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        $sanitized = [];

        // Define the settings and their sanitization methods
        $settingDefinitions = [
            'setting_1' => 'sanitize_text_field',
            'setting_2' => 'esc_url_raw',
            // Add more settings and their sanitization methods here
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
     * Validate settings array.
     *
     * @param array $settings Settings array.
     *
     * @return bool
     */
    public function validateSettings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return false;
        }

        // Define the settings and their validation methods
        $settingDefinitions = [
            'setting_1' => 'is_string',
            'setting_2' => 'is_string',
            // Add more settings and their validation methods here
        ];

        foreach ( $settingDefinitions as $key => $validateCallback ) {
            if ( isset( $settings[ $key ] ) ) {
                if ( is_callable( $validateCallback ) ) {
                    $isValid = call_user_func( $validateCallback, $settings[ $key ] );
                    if ( ! $isValid ) {
                        return false;
                    }
                } else {
                    error_log( "ArtPulse Management: Invalid validate callback: $validateCallback" );

                    return false;
                }
            }
        }

        return true;
    }
}