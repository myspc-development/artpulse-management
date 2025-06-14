<?php
// File: admin_membership_settings.php

add_action('admin_menu', function () {
    add_submenu_page(
        'artpulse-main-menu',
        'Membership Settings',
        'Membership Settings',
        'manage_options',
        'ead-membership-settings',
        'ead_membership_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting(
        'ead_membership_settings',
        'ead_membership_fees',
        ['sanitize_callback' => 'ead_sanitize_membership_fees']
    );

    add_settings_section('ead_fees_section', 'Membership Fee Settings', null, 'ead_membership_settings');

    add_settings_field('basic_fee', 'Basic Member Fee ($)', function () {
        $opts = get_option('ead_membership_fees');
        echo '<input type="number" name="ead_membership_fees[basic_fee]" value="' . esc_attr($opts['basic_fee'] ?? '') . '" step="0.01">';
    }, 'ead_membership_settings', 'ead_fees_section');

    add_settings_field('pro_fee', 'Pro Artist Fee ($)', function () {
        $opts = get_option('ead_membership_fees');
        echo '<input type="number" name="ead_membership_fees[pro_fee]" value="' . esc_attr($opts['pro_fee'] ?? '') . '" step="0.01">';
    }, 'ead_membership_settings', 'ead_fees_section');

    add_settings_field('org_fee', 'Organization Fee ($)', function () {
        $opts = get_option('ead_membership_fees');
        echo '<input type="number" name="ead_membership_fees[org_fee]" value="' . esc_attr($opts['org_fee'] ?? '') . '" step="0.01">';
    }, 'ead_membership_settings', 'ead_fees_section');

    add_settings_field('enable_stripe', 'Enable Stripe Integration', function () {
        $opts = get_option('ead_membership_fees');
        echo '<input type="checkbox" name="ead_membership_fees[enable_stripe]" value="1" ' . checked($opts['enable_stripe'] ?? '', '1', false) . '> Yes';
    }, 'ead_membership_settings', 'ead_fees_section');

    add_settings_field('enable_woocommerce', 'Enable WooCommerce Integration', function () {
        $opts = get_option('ead_membership_fees');
        echo '<input type="checkbox" name="ead_membership_fees[enable_woocommerce]" value="1" ' . checked($opts['enable_woocommerce'] ?? '', '1', false) . '> Yes';
    }, 'ead_membership_settings', 'ead_fees_section');

    add_settings_field('notify_on_change', 'Email Notification on Fee Change', function () {
        $opts = get_option('ead_membership_fees');
        echo '<input type="checkbox" name="ead_membership_fees[notify_on_change]" value="1" ' . checked($opts['notify_on_change'] ?? '', '1', false) . '> Notify admins';
    }, 'ead_membership_settings', 'ead_fees_section');
});

function ead_sanitize_membership_fees($input) {
    $sanitized = [];
    $sanitized['basic_fee'] = isset($input['basic_fee']) ? floatval($input['basic_fee']) : 0;
    $sanitized['pro_fee']   = isset($input['pro_fee'])   ? floatval($input['pro_fee'])   : 0;
    $sanitized['org_fee']   = isset($input['org_fee'])   ? floatval($input['org_fee'])   : 0;
    $sanitized['enable_stripe'] = empty($input['enable_stripe']) ? '' : '1';
    $sanitized['enable_woocommerce'] = empty($input['enable_woocommerce']) ? '' : '1';
    $sanitized['notify_on_change'] = empty($input['notify_on_change']) ? '' : '1';
    return $sanitized;
}

function ead_membership_settings_page() {
    echo '<div class="wrap">';
    echo '<h1>Membership Settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('ead_membership_settings');
    do_settings_sections('ead_membership_settings');
    submit_button();
    echo '</form>';

    echo '<h2>Export Membership Fees</h2>';
    echo '<form method="post">';
    echo '<button name="export_membership_json" class="button">Export as JSON</button>'; 
    echo '</form>';

    if (isset($_POST['export_membership_json'])) {
        $opts = get_option('ead_membership_fees');
        header('Content-disposition: attachment; filename=membership_settings.json');
        header('Content-type: application/json');
        echo wp_json_encode($opts);
        exit;
    }

    echo '</div>';
}

// Notify on setting change
add_action('update_option_ead_membership_fees', function ($old, $new) {
    if (!empty($new['notify_on_change'])) {
        $diff = array_diff_assoc($new, $old);
        if (!empty($diff)) {
            $message = "Membership settings have been updated:\n\n";
            foreach ($diff as $key => $val) {
                $message .= ucfirst(str_replace('_', ' ', $key)) . ": " . esc_html($old[$key] ?? 'N/A') . " → " . esc_html($val) . "\n";
            }
            wp_mail(get_option('admin_email'), '🔔 Membership Settings Updated', $message);
        }
    }
}, 10, 2);
