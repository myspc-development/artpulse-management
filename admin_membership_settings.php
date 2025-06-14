<?php
// Admin membership settings are now handled by the ManageMembers class.
// The previous submenu registration is kept here for reference only and
// has been commented out to prevent duplicate menu items.
/*
add_action('admin_menu', function () {
    add_submenu_page(
        'artpulse-main-menu',
        'Membership Settings',
        'Membership',
        'manage_options',
        'artpulse-membership-management',
        'ead_render_membership_settings'
    );
});
*/

function ead_render_membership_settings() {
    echo '<div class="wrap"><h1>Membership Management</h1>';

    $members = get_users([
        'meta_key'   => 'is_member',
        'meta_value' => 1,
    ]);

    echo '<table class="widefat"><thead><tr><th>Name</th><th>Role</th><th>Level</th></tr></thead><tbody>';
    foreach ($members as $user) {
        echo '<tr>';
        echo '<td>' . esc_html($user->display_name) . '</td>';
        echo '<td>' . esc_html(implode(', ', $user->roles)) . '</td>';
        echo '<td>' . esc_html(get_user_meta($user->ID, 'membership_level', true)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

