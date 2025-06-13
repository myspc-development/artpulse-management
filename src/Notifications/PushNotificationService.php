<?php
namespace EAD\Notifications;

/**
 * Simple Firebase Cloud Messaging helper.
 */
class PushNotificationService {
    /**
     * Send a push notification via Firebase.
     *
     * @param string $title   Notification title.
     * @param string $message Notification body.
     * @param string $topic   Topic to publish to.
     * @param array  $tokens  Specific device tokens.
     */
    public static function send( $title, $message, $topic = '', $tokens = [] ) {
        $settings = get_option( 'artpulse_notification_settings', [] );
        if ( empty( $settings['enable_push_notifications'] ) ) {
            return;
        }

        $server_key = isset( $settings['firebase_server_key'] ) ? trim( $settings['firebase_server_key'] ) : '';
        if ( empty( $server_key ) ) {
            return;
        }

        $payload = [
            'notification' => [
                'title' => $title,
                'body'  => $message,
            ],
        ];

        if ( ! empty( $topic ) ) {
            $payload['to'] = '/topics/' . $topic;
        } elseif ( ! empty( $tokens ) ) {
            $payload['registration_ids'] = $tokens;
        } else {
            return;
        }

        $args = [
            'headers' => [
                'Authorization' => 'key=' . $server_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ];

        wp_remote_post( 'https://fcm.googleapis.com/fcm/send', $args );
    }
}
