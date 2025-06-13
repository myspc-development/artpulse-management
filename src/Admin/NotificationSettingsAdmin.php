<?php
namespace EAD\Admin;

/**
 * Admin page for notification settings including push notifications.
 */
class NotificationSettingsAdmin {
    /**
     * Register hooks.
     */
    public static function register() {
        add_action( 'admin_init', [ self::class, 'register_settings' ] );
    }

    /**
     * Register settings and fields.
     */
    public static function register_settings() {
        register_setting( 'artpulse_notification_settings', 'artpulse_notification_settings', [ self::class, 'sanitize_settings' ] );

        add_settings_section(
            'artpulse_push_section',
            __( 'Push Notifications', 'artpulse-management' ),
            function () {
                echo '<p>' . esc_html__( 'Configure Firebase push notification options.', 'artpulse-management' ) . '</p>';
            },
            'artpulse-notification-settings-page'
        );

        $fields = [
            'firebase_server_key'        => __( 'Firebase Server Key', 'artpulse-management' ),
            'enable_push_notifications'  => __( 'Enable Push Notifications', 'artpulse-management' ),
            'push_event_approved'        => __( 'Notify on Event Approval', 'artpulse-management' ),
            'push_event_updated'         => __( 'Notify on Event Update', 'artpulse-management' ),
            'push_organization_approved' => __( 'Notify on Organization Approval', 'artpulse-management' ),
            'push_organization_updated'  => __( 'Notify on Organization Update', 'artpulse-management' ),
        ];

        foreach ( $fields as $id => $label ) {
            $callback = ( $id === 'firebase_server_key' ) ? 'render_text_field_callback' : 'render_checkbox_field_callback';
            add_settings_field( $id, $label, [ self::class, $callback ], 'artpulse-notification-settings-page', 'artpulse_push_section', [ 'id' => $id ] );
        }
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input values.
     * @return array Sanitized values.
     */
    public static function sanitize_settings( $input ) {
        $output = get_option( 'artpulse_notification_settings', [] );

        $output['firebase_server_key']        = isset( $input['firebase_server_key'] ) ? sanitize_text_field( $input['firebase_server_key'] ) : '';
        $output['enable_push_notifications']  = ! empty( $input['enable_push_notifications'] );
        $output['push_event_approved']        = ! empty( $input['push_event_approved'] );
        $output['push_event_updated']         = ! empty( $input['push_event_updated'] );
        $output['push_organization_approved'] = ! empty( $input['push_organization_approved'] );
        $output['push_organization_updated']  = ! empty( $input['push_organization_updated'] );

        return $output;
    }

    public static function render_text_field_callback( $args ) {
        $options = get_option( 'artpulse_notification_settings', [] );
        $id      = $args['id'];
        $value   = isset( $options[ $id ] ) ? esc_attr( $options[ $id ] ) : '';
        echo '<input type="text" id="' . esc_attr( $id ) . '" name="artpulse_notification_settings[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '" class="regular-text">';
    }

    public static function render_checkbox_field_callback( $args ) {
        $options = get_option( 'artpulse_notification_settings', [] );
        $id      = $args['id'];
        $checked = ! empty( $options[ $id ] ) ? 'checked' : '';
        echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="artpulse_notification_settings[' . esc_attr( $id ) . ']" value="1" ' . $checked . '> </label>';
    }

    /**
     * Render the admin page.
     */
    public static function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'ArtPulse Notification Settings', 'artpulse-management' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'artpulse_notification_settings' );
                do_settings_sections( 'artpulse-notification-settings-page' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
