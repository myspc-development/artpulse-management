<?php

namespace ArtPulse\Artists;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\Capabilities;
use WP_Error;

/**
 * Handles seeding starter artist portfolio drafts for the builder.
 */
final class ArtistDraftCreator
{
    /**
     * Create a starter artist draft for the provided user.
     *
     * @param int $user_id User identifier.
     *
     * @return int|WP_Error Post identifier on success or error on failure.
     */
    public static function create_for_user(int $user_id)
    {
        if ($user_id <= 0) {
            return new WP_Error(
                'auth_required',
                __('You must be logged in to create an artist profile.', 'artpulse-management'),
                ['status' => 401]
            );
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error(
                'auth_required',
                __('You must be logged in to create an artist profile.', 'artpulse-management'),
                ['status' => 401]
            );
        }

        if (!user_can($user_id, Capabilities::CAP_MANAGE_PORTFOLIO)) {
            return new WP_Error(
                'forbidden',
                __('You do not have permission to create an artist profile.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        $display_name = trim($user->display_name ?: $user->user_login);
        if ('' === $display_name) {
            $title = __('New artist profile', 'artpulse-management');
        } else {
            $title = sprintf(
                /* translators: %s: User display name. */
                __('Artist profile for %s', 'artpulse-management'),
                $display_name
            );
        }

        $post_id = wp_insert_post(
            [
                'post_type'    => 'artpulse_artist',
                'post_status'  => 'draft',
                'post_title'   => $title,
                'post_content' => '',
                'post_author'  => $user_id,
                'meta_input'   => [
                    '_ap_owner_user' => $user_id,
                ],
            ],
            true
        );

        if (is_wp_error($post_id)) {
            $post_id->add_data(['status' => 500]);

            return $post_id;
        }

        AuditLogger::info('artist.draft.created', [
            'post_id' => $post_id,
            'user_id' => $user_id,
        ]);

        return (int) $post_id;
    }
}
