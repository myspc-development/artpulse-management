<?php

namespace ArtPulse\Community;

use ArtPulse\Core\RoleUpgradeManager;
use WP_Error;

class ProfileLinkRequestManager
{
    /** Post type used for storing link requests */
    public const POST_TYPE = 'ap_link_request';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED   = 'denied';

    public static function register(): void
    {
        ProfileLinkRequestRestController::register();
    }

    /**
     * Create a new profile link request.
     *
     * @return int|WP_Error
     */
    public static function create(int $artist_user_id, int $org_id, string $message)
    {
        if ($artist_user_id <= 0 || !get_userdata($artist_user_id)) {
            return new WP_Error('invalid_artist', 'Artist user not found.', ['status' => 400]);
        }

        if ($org_id <= 0 || !get_post($org_id)) {
            return new WP_Error('invalid_org', 'Organization not found.', ['status' => 404]);
        }

        $now      = current_time('mysql');
        $post_arr = [
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => sprintf('Link Request: User %d to %d', $artist_user_id, $org_id),
            'post_author' => $artist_user_id,
        ];

        $request_id = wp_insert_post($post_arr, true);

        if (is_wp_error($request_id)) {
            return new WP_Error('request_create_failed', 'Unable to create link request.', ['status' => 500]);
        }

        update_post_meta($request_id, 'artist_user_id', $artist_user_id);
        update_post_meta($request_id, 'org_id', $org_id);
        update_post_meta($request_id, 'message', $message);
        update_post_meta($request_id, 'status', self::STATUS_PENDING);
        update_post_meta($request_id, 'requested_on', $now);
        update_post_meta($request_id, 'updated_on', $now);
        delete_post_meta($request_id, 'moderated_by');
        delete_post_meta($request_id, 'moderated_on');

        return (int) $request_id;
    }

    /**
     * Approve a link request.
     *
     * @return int|WP_Error
     */
    public static function approve(int $request_id, int $moderator_id)
    {
        return self::transition($request_id, $moderator_id, self::STATUS_APPROVED);
    }

    /**
     * Deny a link request.
     *
     * @return int|WP_Error
     */
    public static function deny(int $request_id, int $moderator_id)
    {
        return self::transition($request_id, $moderator_id, self::STATUS_DENIED);
    }

    /**
     * @return int|WP_Error
     */
    private static function transition(int $request_id, int $moderator_id, string $status)
    {
        if ($request_id <= 0) {
            return new WP_Error('invalid_request', 'Invalid request.', ['status' => 404]);
        }

        $post = get_post($request_id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            return new WP_Error('invalid_request', 'Request not found.', ['status' => 404]);
        }

        if ($moderator_id <= 0 || !get_userdata($moderator_id)) {
            return new WP_Error('invalid_moderator', 'Moderator not found.', ['status' => 400]);
        }

        $current_status = get_post_meta($request_id, 'status', true) ?: self::STATUS_PENDING;

        if ($current_status === $status) {
            return $request_id;
        }

        if (self::STATUS_PENDING !== $current_status) {
            return new WP_Error('invalid_status', 'Request already moderated.', ['status' => 409]);
        }

        $now = current_time('mysql');

        update_post_meta($request_id, 'status', $status);
        update_post_meta($request_id, 'updated_on', $now);
        update_post_meta($request_id, 'moderated_by', $moderator_id);
        update_post_meta($request_id, 'moderated_on', $now);

        if ($status === self::STATUS_APPROVED) {
            update_post_meta($request_id, 'approved_by', $moderator_id);
            update_post_meta($request_id, 'approved_on', $now);
            delete_post_meta($request_id, 'denied_by');
            delete_post_meta($request_id, 'denied_on');

            $target_id      = (int) get_post_meta($request_id, 'org_id', true);
            $artist_user_id = (int) get_post_meta($request_id, 'artist_user_id', true);

            if ($target_id > 0 && $artist_user_id > 0) {
                $target_post = get_post($target_id);

                if ($target_post) {
                    RoleUpgradeManager::attach_owner($target_id, $artist_user_id);

                    $role = match ($target_post->post_type) {
                        'artpulse_artist' => 'artist',
                        'artpulse_org'    => 'organization',
                        default           => null,
                    };

                    if ($role) {
                        RoleUpgradeManager::grant_role($artist_user_id, $role, [
                            'source'       => 'profile_link_request',
                            'request_id'   => $request_id,
                            'moderator_id' => $moderator_id,
                            'post_id'      => $target_id,
                        ]);
                    }
                }
            }
        } elseif ($status === self::STATUS_DENIED) {
            update_post_meta($request_id, 'denied_by', $moderator_id);
            update_post_meta($request_id, 'denied_on', $now);
            delete_post_meta($request_id, 'approved_by');
            delete_post_meta($request_id, 'approved_on');
        }

        return $request_id;
    }
}
