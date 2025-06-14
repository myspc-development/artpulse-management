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

function ead_membership_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['updated']) && $_GET['updated'] == '1') {
        echo '<div class="notice notice-success is-dismissible"><p>✅ User role updated successfully.</p></div>';
    } elseif (isset($_GET['reverted']) && $_GET['reverted'] == '1') {
        echo '<div class="notice notice-info is-dismissible"><p>↩️ User role reverted successfully.</p></div>';
    }

    $filter_level = $_GET['filter_level'] ?? '';
    $args         = ['meta_key' => 'is_member', 'meta_value' => '1'];
    if ($filter_level) {
        $args['meta_query'] = [
            [
                'key'   => 'membership_level',
                'value' => sanitize_text_field($filter_level),
            ],
        ];
    }
    $users = get_users($args);

    echo '<div class="wrap">';
    echo '<h1>📋 Membership Overview</h1>';
    echo '<form method="get" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="page" value="membership-overview" />';
    echo '<select name="filter_level">';
    echo '<option value="">All Levels</option>';
    echo '<option value="basic"' . selected($filter_level, 'basic', false) . '>Basic</option>';
    echo '<option value="pro"' . selected($filter_level, 'pro', false) . '>Pro Artist</option>';
    echo '<option value="org"' . selected($filter_level, 'org', false) . '>Organization</option>';
    echo '</select>';
    echo '<button class="button">Filter</button>';
    echo '</form>';

    echo '<form method="post">';
    submit_button('📤 Export CSV', 'primary', 'export_csv', false);
    echo '</form>';

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>User</th><th>Email</th><th>Role</th><th>Level</th><th>Badge</th><th>Joined</th><th>Actions</th></tr></thead><tbody>';

    foreach ($users as $user) {
        $level  = get_user_meta($user->ID, 'membership_level', true);
        $badge  = get_user_meta($user->ID, 'org_badge_label', true);
        $joined = $user->user_registered;

        echo '<tr>';
        echo '<td>' . esc_html($user->display_name) . '</td>';
        echo '<td>' . esc_html($user->user_email) . '</td>';
        echo '<td>' . esc_html(implode(', ', $user->roles)) . '</td>';
        echo '<td>' . esc_html(ucfirst($level)) . '</td>';
        echo '<td>';
        if (in_array('member_org', $user->roles)) {
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field('edit_badge_' . $user->ID);
            echo '<input type="text" name="badge_label" value="' . esc_attr($badge) . '" size="12" />';
            echo '<input type="hidden" name="badge_user_id" value="' . $user->ID . '" />';
            echo '<button class="button small">💾</button>';
            echo '</form>';
        } else {
            echo esc_html($badge);
        }
        echo '</td>';
        echo '<td>' . esc_html(date('Y-m-d', strtotime($joined))) . '</td>';
        echo '<td>';
        echo '<form method="post" style="display:inline;">';
        wp_nonce_field('promote_member_action_' . $user->ID);
        echo '<input type="hidden" name="promote_user_id" value="' . $user->ID . '" />';
        echo '<select name="new_role">';
        echo '<option value="member_basic">Basic</option>';
        echo '<option value="member_pro">Pro</option>';
        echo '<option value="member_org">Org</option>';
        echo '</select>';
        echo '<button class="button small">Promote</button>';
        echo '</form>';

        if (get_user_meta($user->ID, 'previous_role', true)) {
            echo '<form method="post" style="display:inline; margin-left: 5px;">';
            wp_nonce_field('revert_role_action_' . $user->ID);
            echo '<input type="hidden" name="revert_user_id" value="' . $user->ID . '" />';
            echo '<button class="button small">↩️ Undo</button>';
            echo '</form>';
        }
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
        update_user_meta($user->ID, 'previous_role', implode(',', $user->roles));
        $user->set_role($role);
        wp_safe_redirect(admin_url('admin.php?page=membership-overview&updated=1'));
        exit;
    }

    if (
        isset($_POST['revert_user_id'], $_POST['_wpnonce']) &&
        wp_verify_nonce($_POST['_wpnonce'], 'revert_role_action_' . $_POST['revert_user_id']) &&
        current_user_can('manage_options')
    ) {
        $user = new WP_User((int) $_POST['revert_user_id']);
        $prev = explode(',', get_user_meta($user->ID, 'previous_role', true));
        if ($prev) {
            $user->set_role($prev[0]);
        }
        delete_user_meta($user->ID, 'previous_role');
        wp_safe_redirect(admin_url('admin.php?page=membership-overview&reverted=1'));
        exit;
    }

    if (
        isset($_POST['badge_user_id'], $_POST['badge_label'], $_POST['_wpnonce']) &&
        wp_verify_nonce($_POST['_wpnonce'], 'edit_badge_' . $_POST['badge_user_id']) &&
        current_user_can('manage_options')
    ) {
        update_user_meta((int) $_POST['badge_user_id'], 'org_badge_label', sanitize_text_field($_POST['badge_label']));
        wp_safe_redirect(admin_url('admin.php?page=membership-overview&updated=1'));
        exit;
    }

    if (isset($_POST['export_csv']) && current_user_can('manage_options')) {
        $filename = 'membership_export_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        fputcsv($output, ['User', 'Email', 'Role', 'Level', 'Badge', 'Joined']);
        $members = get_users(['meta_key' => 'is_member', 'meta_value' => '1']);
        foreach ($members as $m) {
            fputcsv($output, [
                $m->display_name,
                $m->user_email,
                implode(',', $m->roles),
                get_user_meta($m->ID, 'membership_level', true),
                get_user_meta($m->ID, 'org_badge_label', true),
                $m->user_registered,
            ]);
        }
        fclose($output);
        exit;
    }
});
