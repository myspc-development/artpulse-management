<?php
// File: rest_endpoints_membership.php

add_action('rest_api_init', function () {

    // GET profile info
    register_rest_route('artpulse/v1', '/user-profile', [
        'methods' => 'GET',
        'callback' => function () {
            $u = wp_get_current_user();
            return [
                'ID' => $u->ID,
                'name' => $u->display_name,
                'email' => $u->user_email,
                'role' => $u->roles[0] ?? 'guest',
                'membership_level' => get_user_meta($u->ID, 'membership_level', true),
                'badge_label' => get_user_meta($u->ID, 'org_badge_label', true),
                'bio' => get_user_meta($u->ID, 'description', true)
            ];
        },
        'permission_callback' => function () {
            return in_array('member_pro', wp_get_current_user()->roles);
        }
    ]);

    // POST update profile info
    register_rest_route('artpulse/v1', '/user-profile', [
        'methods' => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $u = wp_get_current_user();
            $uid = $u->ID;

            $name = sanitize_text_field($request->get_param('name'));
            $bio = sanitize_textarea_field($request->get_param('bio'));
            $badge = sanitize_text_field($request->get_param('badge_label'));

            wp_update_user([
                'ID' => $uid,
                'display_name' => $name
            ]);
            update_user_meta($uid, 'description', $bio);

            if (in_array('member_org', $u->roles)) {
                update_user_meta($uid, 'org_badge_label', $badge);
            }

            return [
                'success' => true,
                'message' => 'Profile updated.',
                'data' => [
                    'name' => $name,
                    'bio' => $bio,
                    'badge_label' => $badge
                ]
            ];
        },
        'permission_callback' => function () {
            return in_array('member_pro', wp_get_current_user()->roles) || in_array('member_org', wp_get_current_user()->roles);
        }
    ]);

    // GET badges
    register_rest_route('artpulse/v1', '/user-badges', [
        'methods' => 'GET',
        'callback' => function () {
            $id = get_current_user_id();
            $count = (int) get_user_meta($id, 'rsvp_count', true);
            $badges = [];
            if ($count >= 3) $badges[] = '3 RSVPs';
            if ($count >= 10) $badges[] = '10 RSVPs';
            if ($count >= 25) $badges[] = 'Super Supporter';
            return ['rsvp_count' => $count, 'badges' => $badges];
        },
        'permission_callback' => function () {
            return in_array('member_pro', wp_get_current_user()->roles);
        }
    ]);

    // GET membership status
    register_rest_route('artpulse/v1', '/membership-status', [
        'methods' => 'GET',
        'callback' => function () {
            $uid = get_current_user_id();
            return [
                'is_member' => get_user_meta($uid, 'is_member', true) === '1',
                'membership_level' => get_user_meta($uid, 'membership_level', true),
                'role' => wp_get_current_user()->roles[0] ?? 'guest'
            ];
        },
        'permission_callback' => function () {
            return in_array('member_pro', wp_get_current_user()->roles);
        }
    ]);
});
