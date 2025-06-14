<?php
add_action('admin_menu', function () {
    add_menu_page(
        'Membership Settings',
        'Membership',
        'manage_options',
        'ead-membership-settings',
        'ead_render_membership_settings'
    );
});

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

// Redirect legacy slug to the actual admin page
add_action('admin_init', function () {
    // Redirect /wp-admin/ead-membership-settings to the real page
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    if (strpos($request_uri, '/wp-admin/ead-membership-settings') !== false) {
        wp_redirect(admin_url('admin.php?page=ead-membership-settings'), 301);
        exit;
    }
});
