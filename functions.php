<?php
// Generic helper functions for ArtPulse plugin.

/**
 * Send welcome email after member registration.
 *
 * @param int $user_id User ID of the newly registered member.
 */
function artpulse_send_welcome_email( $user_id ) {
    $user    = get_userdata( $user_id );
    $email   = $user->user_email;
    $name    = $user->display_name;

    $subject = 'Welcome to ArtPulse!';
    $message = "Hi $name,\n\nThank you for registering.\n\nYour membership is active. You can now log in and explore ArtPulse.\n\nVisit: " . home_url() . "\n\nRegards,\nArtPulse Team";

    wp_mail( $email, $subject, $message );
}

// === Simple Membership Manager ===
add_action('admin_menu', function () {
    add_submenu_page(
        'artpulse-settings',
        'Membership Manager',
        'Membership Manager',
        'manage_options',
        'artpulse-membership-manager',
        'artpulse_render_membership_manager'
    );
});

function artpulse_render_membership_manager() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    $users = get_users([
        'fields' => ['ID', 'user_login', 'user_email', 'display_name'],
        'number' => 100,
    ]);

    echo '<div class="wrap"><h1>Membership Manager</h1>';
    echo '<table class="widefat striped"><thead>
        <tr>
            <th>User</th>
            <th>Email</th>
            <th>Level</th>
            <th>Expiry</th>
            <th>Auto Renew</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead><tbody>';

    foreach ($users as $user) {
        $level = get_user_meta($user->ID, 'membership_level', true);
        $expiry = get_user_meta($user->ID, 'membership_end_date', true);
        $auto_renew = get_user_meta($user->ID, 'membership_auto_renew', true);
        $status = ($level === 'expired' || (strtotime($expiry) < time())) ? 'Expired' : 'Active';

        echo '<tr data-user-id="' . esc_attr($user->ID) . '">';
        echo '<td>' . esc_html($user->display_name) . '</td>';
        echo '<td>' . esc_html($user->user_email) . '</td>';
        echo '<td class="editable" data-field="level">' . esc_html(ucfirst($level)) . '</td>';
        echo '<td class="editable" data-field="expiry">' . esc_html($expiry) . '</td>';
        echo '<td class="editable" data-field="auto_renew">' . ($auto_renew ? 'ON' : 'OFF') . '</td>';
        echo '<td>' . esc_html($status) . '</td>';
        echo '<td><button class="button button-primary artpulse-edit-member">Edit</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p><em>Click "Edit" to inline-edit fields (JS coming soon!)</em></p>';
    echo '</div>';
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'artpulse-settings_page_artpulse-membership-manager') {
        // wp_enqueue_script('artpulse-membership-manager', plugins_url('assets/js/membership-manager.js', __FILE__), ['jquery'], null, true);
    }
});

