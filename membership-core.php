<?php
// Plugin: ArtPulse Membership Core
// File: membership-core.php

// 1. Register membership-related user meta
function artpulse_register_membership_meta() {
    register_meta('user', 'membership_level', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_meta('user', 'membership_start_date', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_meta('user', 'membership_end_date', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_meta('user', 'membership_auto_renew', ['type' => 'boolean', 'single' => true, 'show_in_rest' => true]);
}
add_action('init', 'artpulse_register_membership_meta');

// 2. Assign default membership level on registration
function artpulse_assign_default_membership($user_id) {
    update_user_meta($user_id, 'membership_level', 'free');
    update_user_meta($user_id, 'membership_start_date', current_time('mysql'));
    update_user_meta($user_id, 'membership_end_date', date('Y-m-d H:i:s', strtotime('+30 days')));
    update_user_meta($user_id, 'membership_auto_renew', true);
}
add_action('user_register', 'artpulse_assign_default_membership');

// 3. Daily cron: expire memberships if auto-renew is off
function artpulse_schedule_membership_check() {
    if (!wp_next_scheduled('artpulse_check_memberships')) {
        wp_schedule_event(time(), 'daily', 'artpulse_check_memberships');
    }
}
add_action('wp', 'artpulse_schedule_membership_check');

// Extend cron: Expiration warnings (3 days before end)
add_action('artpulse_check_memberships', function () {
    $now = current_time('timestamp');
    $expire_users = get_users([
        'meta_query' => [
            [
                'key' => 'membership_end_date',
                'value' => date('Y-m-d H:i:s', strtotime('+3 days', $now)),
                'compare' => 'LIKE'
            ]
        ]
    ]);

    foreach ($expire_users as $user) {
        $email = $user->user_email;
        $name = $user->display_name;
        $subject = 'Your ArtPulse Membership is Expiring Soon';
        $message = "Hi $name,\n\nJust a reminder \xE2\x80\x94 your membership will expire in 3 days.\n\nTo avoid interruption, please renew or upgrade your membership.\n\nVisit your account to take action.\n\nThank you,\nArtPulse Team";

        wp_mail($email, $subject, $message);
    }

    // Expiration enforcement
    $users = get_users([
        'meta_key' => 'membership_end_date',
        'meta_compare' => '<=',
        'meta_value' => current_time('mysql'),
    ]);
    foreach ($users as $user) {
        $auto_renew = get_user_meta($user->ID, 'membership_auto_renew', true);
        if (!$auto_renew) {
            update_user_meta($user->ID, 'membership_level', 'expired');
        }
    }
});

// 4. Admin settings page for membership fees & config
function artpulse_membership_settings_init() {
    add_options_page('Membership Settings', 'Membership Settings', 'manage_options', 'artpulse-membership-settings', 'artpulse_membership_settings_page');
    add_action('admin_init', function () {
        register_setting('artpulse_membership', 'artpulse_membership_options');
        add_settings_section('artpulse_membership_main', 'Membership Options', null, 'artpulse-membership');

        add_settings_field('basic_fee', 'Basic Member Fee ($)', function () {
            $options = get_option('artpulse_membership_options');
            echo '<input type="number" name="artpulse_membership_options[basic_fee]" value="' . esc_attr($options['basic_fee'] ?? '') . '" />';
        }, 'artpulse-membership', 'artpulse_membership_main');

        add_settings_field('pro_fee', 'Pro Artist Fee ($)', function () {
            $options = get_option('artpulse_membership_options');
            echo '<input type="number" name="artpulse_membership_options[pro_fee]" value="' . esc_attr($options['pro_fee'] ?? '') . '" />';
        }, 'artpulse-membership', 'artpulse_membership_main');

        add_settings_field('currency', 'Currency', function () {
            $options = get_option('artpulse_membership_options');
            echo '<input type="text" name="artpulse_membership_options[currency]" value="' . esc_attr($options['currency'] ?? 'USD') . '" />';
        }, 'artpulse-membership', 'artpulse_membership_main');
    });
}
add_action('admin_menu', 'artpulse_membership_settings_init');

function artpulse_membership_settings_page() {
    echo '<div class="wrap"><h1>Membership Settings</h1><form method="post" action="options.php">';
    settings_fields('artpulse_membership');
    do_settings_sections('artpulse-membership');
    submit_button();
    echo '</form></div>';
}

// 5. Shortcode: [membership_status]
function artpulse_membership_status_shortcode() {
    if (!is_user_logged_in()) return '<p>Please <a href="' . wp_login_url() . '">log in</a> to view your membership status.</p>';

    $user_id = get_current_user_id();
    $level = get_user_meta($user_id, 'membership_level', true);
    $end = get_user_meta($user_id, 'membership_end_date', true);
    $renew = get_user_meta($user_id, 'membership_auto_renew', true);

    ob_start();
    echo '<div class="membership-status">';
    echo '<p><strong>Membership Level:</strong> ' . esc_html(ucfirst($level)) . '</p>';
    echo '<p><strong>Valid Until:</strong> ' . esc_html(date('F j, Y_
