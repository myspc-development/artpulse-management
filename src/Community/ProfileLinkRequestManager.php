<?php

namespace ArtPulse\Community;

class ProfileLinkRequestManager {
    // CPT name must be <= 20 characters!
    const CPT = 'ap_profile_link';

    public static function register() {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => 'Profile Links',
                'singular_name' => 'Profile Link'
            ],
            'public' => false,
            'show_ui' => false,
            'supports' => ['custom-fields'],
        ]);
    }

    public static function install_link_request_table() { /* ... same as before ... */ }

    // Create a new link request (CPT-based, for WP UI)
    public static function create($artist_user_id, $org_id, $message = '') {
        $post_id = wp_insert_post([
            'post_type'    => self::CPT,
            'post_status'  => 'publish',
            'post_title'   => 'Profile Link Request',
            'post_content' => '',
        ]);
        if ($post_id) {
            update_post_meta($post_id, 'artist_user_id', $artist_user_id);
            update_post_meta($post_id, 'org_id', $org_id);
            update_post_meta($post_id, 'status', 'pending');
            update_post_meta($post_id, 'message', $message);
            update_post_meta($post_id, 'requested_on', current_time('mysql'));
            
            // 🔔 Notify each org admin
            foreach (self::get_org_admin_user_ids($org_id) as $admin_id) {
                NotificationManager::add(
                    $admin_id,
                    'link_request_sent',
                    $post_id,
                    $artist_user_id,
                    sprintf('New link request from user #%d', $artist_user_id)
                );
            }
        }
        return $post_id;
    }

    public static function approve($request_id, $admin_user_id) {
        update_post_meta($request_id, 'status', 'approved');
        update_post_meta($request_id, 'responded_by', $admin_user_id);
        update_post_meta($request_id, 'responded_on', current_time('mysql'));

        $artist_user_id = get_post_meta($request_id, 'artist_user_id', true);
        NotificationManager::add(
            $artist_user_id,
            'link_request_approved',
            $request_id,
            $admin_user_id,
            'Your profile link request has been approved'
        );
    }

    public static function deny($request_id, $admin_user_id) {
        update_post_meta($request_id, 'status', 'denied');
        update_post_meta($request_id, 'responded_by', $admin_user_id);
        update_post_meta($request_id, 'responded_on', current_time('mysql'));

        $artist_user_id = get_post_meta($request_id, 'artist_user_id', true);
        NotificationManager::add(
            $artist_user_id,
            'link_request_denied',
            $request_id,
            $admin_user_id,
            'Your profile link request has been denied'
        );
    }

    public static function get_pending_for_org($org_id) { /* ... same ... */ }

    // 🔽 Helper: Return array of user IDs who are org admins (update to your schema)
    private static function get_org_admin_user_ids($org_id) {
        $admins = get_post_meta($org_id, 'org_admin_user_ids', true);
        if (empty($admins)) {
            $admins = [1]; // fallback: site admin
        }
        return (array)$admins;
    }
}
