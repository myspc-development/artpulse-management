<?php
// Plugin: ArtPulse Membership Core
// File: membership-core.php

// 1. Register user meta on init
function artpulse_register_membership_meta() {
    register_meta('user', 'membership_level', [ 'type' => 'string', 'single' => true, 'show_in_rest' => true ]);
    register_meta('user', 'membership_start_date', [ 'type' => 'string', 'single' => true, 'show_in_rest' => true ]);
    register_meta('user', 'membership_end_date', [ 'type' => 'string', 'single' => true, 'show_in_rest' => true ]);
    register_meta('user', 'membership_auto_renew', [ 'type' => 'boolean', 'single' => true, 'show_in_rest' => true ]);
}
add_action('init', 'artpulse_register_membership_meta');

// 2. On user registration, assign default membership level
function artpulse_assign_default_membership($user_id) {
    update_user_meta($user_id, 'membership_level', 'free');
    update_user_meta($user_id, 'membership_start_date', current_time('mysql'));
    update_user_meta($user_id, 'membership_end_date', date('Y-m-d H:i:s', strtotime('+30 days')));
    update_user_meta($user_id, 'membership_auto_renew', true);
}
add_action('user_register', 'artpulse_assign_default_membership');

// 3. Scheduled event: Check for expired memberships daily
function artpulse_schedule_membership_check() {
    if (!wp_next_scheduled('artpulse_check_memberships')) {
        wp_schedule_event(time(), 'daily', 'artpulse_check_memberships');
    }
}
add_action('wp', 'artpulse_schedule_membership_check');

add_action('artpulse_check_memberships', function () {
    $users = get_users([
        'meta_key' => 'membership_end_date',
        'meta_compare' => '<=',
        'meta_value' => current_time('mysql'),
    ]);

    foreach ($users as $user) {
        $auto_renew = get_user_meta($user->ID, 'membership_auto_renew', true);
        if (!$auto_renew) {
            update_user_meta($user->ID, 'membership_level', 'expired');
            // Optionally change role or send notification
        }
    }
});
