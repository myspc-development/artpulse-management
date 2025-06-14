<?php
// File: admin_membership_overview.php

add_action('admin_menu', function () {
    add_menu_page(
        'Membership Overview',
        'Membership',
        'manage_options',
        'membership-overview',
        'ead_membership_admin_page',
        'dashicons-groups',
        50
    );
});

function ead_membership_admin_page() {
    if (!current_user_can('manage_options')) return;

    $filter_level = $_GET['filter_level'] ?? '';
    $args = ['meta_key' => 'is_member', 'meta_value' => '1'];
    if ($filter_level) {
        $args['meta_query'] = [
            ['key' => 'membership_level', 'value' => sanitize_text_field($filter_level)]
        ];
    }

    $users = get_users($args);
    echo '<div class="wrap">';
    if (isset($_GET['updated']) && $_GET['updated'] == '1') {
        echo '<div class="notice notice-success is-dismissible"><p>✅ User role updated successfully.</p></div>';
    }
    echo '<h1>📋 Membership Overview</h1>';

    echo '<form method="get" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="membership-overview" />
            <select name="filter_level">
                <option value="">All Levels</option>
                <option value="basic"' . selected($filter_level, 'basic', false) . '>Basic</option>
                <option value="pro"' . selected($filter_level, 'pro', false) . '>Pro Artist</option>
                <option value="org"' . selected($filter_level, 'org', false) . '>Organization</option>
            </select>
            <button class="button">Filter</button>
          </form>';

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>User</th><th>Email</th><th>Role</th><th>Level</th><th>Badge</th><th>Joined</th><th>Actions</th></tr></thead><tbody>';

    foreach ($users as $user) {
        $level = get_user_meta($user->ID, 'membership_level', true);
        $badge = get_user_meta($user->ID, 'org_badge_label', true);
        $joined = $user->user_registered;

        echo '<tr>';
        echo '<td>' . esc_html($user->display_name) . '</td>';
        echo '<td>' . esc_html($user->user_email) . '</td>';
        echo '<td>' . esc_html(implode(', ', $user->roles)) . '</td>';
        echo '<td>' . esc_html(ucfirst($level)) . '</td>';
        echo '<td>' . esc_html($badge) . '</td>';
        echo '<td>' . esc_html(date('Y-m-d', strtotime($joined))) . '</td>';
        echo '<td>';
        echo '<form method="post" style="display:inline;">
                " . wp_nonce_field('promote_member_action_' . $user->ID, '_wpnonce', true, false) . "
                <input type="hidden" name="promote_user_id" value="" . $user->ID . "" />
                <select name="new_role">
                    <option value="member_basic">Basic</option>
                    <option value="member_pro">Pro</option>
                    <option value="member_org">Org</option>
                </select>
                <button class="button small">Promote</button>
              </form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

add_action('admin_init', function () {
    if (
        isset($_POST['promote_user_id'], $_POST['new_role'], $_POST['_wpnonce']) &&
        wp_verify_nonce($_POST['_wpnonce'], 'promote_member_action_' . $_POST['promote_user_id']) &&
        current_user_can('manage_options')
    ) {
        $user = new WP_User((int) $_POST['promote_user_id']);
        $role = sanitize_text_field($_POST['new_role']);
        $user->set_role($role);
        wp_safe_redirect(admin_url('admin.php?page=membership-overview&updated=1'));
        exit;
    }
});
