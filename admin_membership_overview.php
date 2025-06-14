<?php
// File: admin_membership_overview.php

add_action('admin_menu', function () {
    add_submenu_page(
        'artpulse-settings',
        'Membership Overview',
        'Membership Overview',
        'manage_options',
        'ead-membership-overview',
        'ead_membership_overview_page'
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'ead-membership-overview') !== false) {
        wp_enqueue_style(
            'ead-membership-admin-style',
            EAD_PLUGIN_DIR_URL . 'assets/css/ead-admin-dashboard.css',
            [],
            defined('EAD_MANAGEMENT_VERSION') ? EAD_MANAGEMENT_VERSION : null
        );
    }
});

function ead_membership_overview_page() {
    echo '<div class="wrap">';
    echo '<h1>Membership Overview</h1>';

    echo '<div class="admin-dashboard-container">';
    echo '<div class="admin-card"><h3>Total Members</h3><p>' . ead_count_members() . '</p></div>';
    echo '<div class="admin-card"><h3>Organizations Pending</h3><p>' . ead_count_pending_orgs() . '</p></div>';
    echo '<div class="admin-card"><h3>Pro Artists</h3><p>' . ead_count_role('member_pro') . '</p></div>';
    echo '<div class="admin-card"><h3>Approved Uploads</h3><p>' . ead_count_approved_uploads() . '</p></div>';
    echo '</div>';

    // Additional dashboard sections (e.g., user table) can go here...

    echo '</div>';
}

function ead_count_members() {
    $users = get_users(['role__in' => ['member_basic', 'member_pro', 'member_org']]);
    return count($users);
}

function ead_count_pending_orgs() {
    global $wpdb;
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'pending_org_request' AND meta_value = '1'");
}

function ead_count_role($role) {
    $users = get_users(['role' => $role]);
    return count($users);
}

function ead_count_approved_uploads() {
    $args = [
        'post_type'      => 'artwork',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ];
    $query = new WP_Query($args);
    return $query->found_posts;
}
