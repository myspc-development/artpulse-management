<?php
namespace EAD\Integration;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Automatically post published content to configured social networks.
 */
class SocialAutoPoster {

    /**
     * Register hooks.
     */
    public static function register() {
        add_action( 'transition_post_status', [ self::class, 'maybe_post' ], 10, 3 );
    }

    /**
     * Trigger auto-posting when eligible posts are published.
     *
     * @param string   $new_status New status.
     * @param string   $old_status Old status.
     * @param \WP_Post $post       Post object.
     */
    public static function maybe_post( $new_status, $old_status, $post ) {
        if ( $new_status !== 'publish' || $old_status === 'publish' ) {
            return;
        }

        if ( ! $post instanceof \WP_Post ) {
            return;
        }

        $allowed = [ 'ead_event', 'ead_artist', 'ead_organization', 'ead_artwork' ];
        if ( ! in_array( $post->post_type, $allowed, true ) ) {
            return;
        }

        $settings = get_option( 'artpulse_plugin_settings', [] );
        $title    = get_the_title( $post->ID );
        $url      = get_permalink( $post->ID );

        self::maybe_post_to_platform( 'facebook', $settings, $post->ID, $title, $url );
        self::maybe_post_to_platform( 'instagram', $settings, $post->ID, $title, $url );
        self::maybe_post_to_platform( 'twitter', $settings, $post->ID, $title, $url );
        self::maybe_post_to_platform( 'pinterest', $settings, $post->ID, $title, $url );
    }

    /**
     * Conditionally post to a single platform.
     *
     * @param string $platform Platform key (facebook, instagram, twitter, pinterest).
     * @param array  $settings Plugin settings array.
     * @param int    $post_id  Post ID.
     * @param string $title    Post title.
     * @param string $url      Post URL.
     */
    private static function maybe_post_to_platform( $platform, array $settings, $post_id, $title, $url ) {
        $enable_key = 'social_' . $platform . '_enable';
        $token_key  = 'social_' . $platform . '_token';

        $enabled = ! empty( $settings[ $enable_key ] );
        $token   = isset( $settings[ $token_key ] ) ? $settings[ $token_key ] : '';

        if ( ! $enabled || empty( $token ) ) {
            return;
        }

        $result = self::send_placeholder_request( $platform, $title, $url, $token );

        if ( is_wp_error( $result ) ) {
            error_log( sprintf( 'ArtPulse Management: Failed posting to %s for post %d - %s', $platform, $post_id, $result->get_error_message() ) );
        } elseif ( $result === true ) {
            error_log( sprintf( 'ArtPulse Management: Posted to %s for post %d', $platform, $post_id ) );
        }
    }

    /**
     * Placeholder for sending the social post.
     * Replace with real API integration.
     *
     * @param string $platform Platform name.
     * @param string $title    Post title.
     * @param string $url      Post URL.
     * @param string $token    Access token.
     *
     * @return true|\WP_Error
     */
    private static function send_placeholder_request( $platform, $title, $url, $token ) {
        $endpoint = 'https://example.com/api/' . $platform;
        $body     = [
            'message' => sprintf( '%s %s', $title, $url ),
            'token'   => $token,
        ];

        $response = wp_remote_post( $endpoint, [ 'timeout' => 15, 'body' => $body ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new \WP_Error( 'social_autopost_http', 'Unexpected response code: ' . $code );
        }

        return true;
    }
}
