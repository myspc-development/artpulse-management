<?php

namespace ArtPulse\Community;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ArtPulse\Community\NotificationManager;

class ProfileLinkRequestManager
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route('artpulse/v1', '/link-requests', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_create_request'],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => [
                'target_id' => [
                    'type'        => 'integer',
                    'required'    => true,
                    'description' => 'Target organization or artist post ID.',
                ]
            ]
        ]);
    }

    public static function handle_create_request(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id   = get_current_user_id();
        $target_id = absint($request['target_id']);

        if (!get_post($target_id)) {
            return new WP_Error('invalid_target', 'Target post not found.', ['status' => 404]);
        }

        $message    = sanitize_text_field($request['message'] ?? '');
        $request_id = self::create($user_id, $target_id, $message);

        return rest_ensure_response([
            'request_id' => $request_id,
            'status'     => 'pending',
        ]);
    }

    /**
     * Create a profile link request programmatically.
     */
    public static function create(int $artist_user_id, int $org_id, string $message = ''): int
    {
        $request_id = wp_insert_post([
            'post_type'   => 'ap_profile_link_request',
            'post_status' => 'publish',
            'post_title'  => "Link Request: User {$artist_user_id} to {$org_id}",
            'post_author' => $artist_user_id,
            'meta_input'  => [
                'artist_user_id' => $artist_user_id,
                'org_id'         => $org_id,
                'message'        => $message,
                'requested_on'   => current_time('mysql'),
                'status'         => 'pending',
            ],
        ]);

        if ($request_id && !is_wp_error($request_id)) {
            $org_post  = get_post($org_id);
            $owner_id  = $org_post ? (int) $org_post->post_author : 0;
            if ($owner_id && class_exists(__NAMESPACE__ . '\\NotificationManager')) {
                NotificationManager::add(
                    $owner_id,
                    'link_request_sent',
                    $request_id,
                    $artist_user_id,
                    $message
                );
            }
            return (int) $request_id;
        }

        return 0;
    }

    /**
     * Approve a pending link request.
     */
    public static function approve(int $request_id, int $approver_id): bool
    {
        $post = get_post($request_id);
        if (!$post || $post->post_type !== 'ap_profile_link_request') {
            return false;
        }

        update_post_meta($request_id, 'status', 'approved');
        update_post_meta($request_id, 'approved_by', $approver_id);
        update_post_meta($request_id, 'approved_on', current_time('mysql'));

        // Create a persistent link post
        $artist_id = get_post_meta($request_id, 'artist_user_id', true);
        $org_id    = get_post_meta($request_id, 'org_id', true);

        wp_insert_post([
            'post_type'   => 'ap_profile_link',
            'post_status' => 'publish',
            'post_title'  => "Profile Link: {$artist_id} -> {$org_id}",
            'post_author' => $approver_id,
            'meta_input'  => [
                'artist_user_id' => $artist_id,
                'org_id'         => $org_id,
                'requested_on'   => get_post_meta($request_id, 'requested_on', true),
                'status'         => 'approved',
            ],
        ]);

        if (class_exists(__NAMESPACE__ . '\\NotificationManager')) {
            NotificationManager::add(
                (int) $artist_id,
                'link_request_approved',
                $request_id,
                $org_id,
                'Your profile link request was approved.'
            );
        }

        return true;
    }

    /**
     * Deny a pending link request.
     */
    public static function deny(int $request_id, int $denier_id): bool
    {
        $post = get_post($request_id);
        if (!$post || $post->post_type !== 'ap_profile_link_request') {
            return false;
        }

        update_post_meta($request_id, 'status', 'denied');
        update_post_meta($request_id, 'denied_by', $denier_id);
        update_post_meta($request_id, 'denied_on', current_time('mysql'));

        $artist_id = get_post_meta($request_id, 'artist_user_id', true);
        $org_id    = get_post_meta($request_id, 'org_id', true);

        if (class_exists(__NAMESPACE__ . '\\NotificationManager')) {
            NotificationManager::add(
                (int) $artist_id,
                'link_request_denied',
                $request_id,
                $org_id,
                'Your profile link request was denied.'
            );
        }

        return true;
    }
}
