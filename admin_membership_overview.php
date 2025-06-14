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
    if ($hook === 'toplevel_page_artpulse-dashboard' || strpos($hook, 'ead-membership-overview') !== false) {
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }

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
    $current_user = wp_get_current_user();
    if (!in_array('administrator', $current_user->roles) && !in_array('org_admin', $current_user->roles)) {
        wp_die('Access denied');
    }

    $selected_level = $_GET['level'] ?? '';
    $selected_exp   = $_GET['expiration'] ?? '';

    echo '<div class="wrap"><h1>Membership Overview</h1>';

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="ead-membership-overview" />';
    echo '<label>Level: <select name="level">';
    echo '<option value="">-- All --</option>';
    foreach (["active", "expired", "pending"] as $level) {
        $sel = ($selected_level === $level) ? 'selected' : '';
        echo "<option value='$level' $sel>$level</option>";
    }
    echo '</select></label> ';
    echo '<label>Expiration Before: <input type="date" name="expiration" value="' . esc_attr($selected_exp) . '" /></label> ';
    echo '<input type="submit" class="button" value="Filter" />';
    echo '</form>';

    $args = ['meta_query' => []];
    if ($selected_level) {
        $args['meta_query'][] = [
            'key'   => 'membership_level',
            'value' => $selected_level,
        ];
    }
    if ($selected_exp) {
        $args['meta_query'][] = [
            'key'     => 'membership_end_date',
            'value'   => $selected_exp,
            'compare' => '<=',
            'type'    => 'DATE'
        ];
    }

    $users = get_users($args);
    echo '<h2>Results: ' . count($users) . '</h2>';

    $level_counts = array_count_values(array_map(fn($u) => get_user_meta($u->ID, 'membership_level', true), $users));
    echo '<canvas id="levelChart" height="100"></canvas>';
    echo "<script>
        document.addEventListener('DOMContentLoaded', function () {
            new Chart(document.getElementById('levelChart').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: " . json_encode(array_keys($level_counts)) . ",
                    datasets: [{
                        data: " . json_encode(array_values($level_counts)) . ",
                        backgroundColor: ['#4CAF50', '#F44336', '#FF9800']
                    }]
                }
            });
        });
    </script>";

    echo '<table class="widefat"><thead><tr><th>Name</th><th>Email</th><th>Level</th><th>End Date</th></tr></thead><tbody>';
    foreach ($users as $user) {
        echo '<tr>';
        echo '<td>' . esc_html($user->display_name) . '</td>';
        echo '<td>' . esc_html($user->user_email) . '</td>';
        echo '<td>' . esc_html(get_user_meta($user->ID, 'membership_level', true)) . '</td>';
        echo '<td>' . esc_html(get_user_meta($user->ID, 'membership_end_date', true)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<form method="post">';
    echo '<input type="hidden" name="export_csv" value="1">';
    echo '<input type="submit" class="button button-primary" value="Export CSV" />';
    echo '</form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_csv'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=membership_export.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Level', 'End Date']);
        foreach ($users as $user) {
            fputcsv($output, [
                $user->display_name,
                $user->user_email,
                get_user_meta($user->ID, 'membership_level', true),
                get_user_meta($user->ID, 'membership_end_date', true)
            ]);
        }
        fclose($output);
        exit;
    }

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
