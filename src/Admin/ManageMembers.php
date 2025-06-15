<?php
namespace EAD\Admin;

if ( ! class_exists( '\\WP_List_Table', false ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// === Admin Post Handlers ===
add_action('admin_post_artpulse_upgrade_member', function () {
    $user_id = intval($_GET['user_id'] ?? 0);
    if ($user_id) {
        update_user_meta($user_id, 'membership_level', 'pro');
        update_user_meta($user_id, 'membership_start_date', current_time('mysql'));
        update_user_meta($user_id, 'membership_end_date', date('Y-m-d H:i:s', strtotime('+365 days')));
        ManageMembers::update_role($user_id, 'pro');
    }
    wp_redirect(admin_url('admin.php?page=artpulse-manage-members'));
    exit;
});

add_action('admin_post_artpulse_assign_org', function () {
    $user_id = intval($_GET['user_id'] ?? 0);
    if ($user_id) {
        update_user_meta($user_id, 'assigned_org', 'default_org');
    }
    wp_redirect(admin_url('admin.php?page=artpulse-manage-members'));
    exit;
});

add_action('admin_post_artpulse_delete_member', function () {
    $user_id = intval($_GET['user_id'] ?? 0);
    if ($user_id && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'artpulse_delete_' . $user_id)) {
        wp_delete_user($user_id);
    }
    wp_redirect(admin_url('admin.php?page=artpulse-manage-members'));
    exit;
