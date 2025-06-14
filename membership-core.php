<?php
// Plugin: ArtPulse Membership Core
// File: membership-core.php

// [previous code above remains unchanged...]

// 6. Shortcode: [membership_plans] - Plan selection/upgrade UI
function artpulse_membership_plan_selector_shortcode() {
    if (!is_user_logged_in()) return '<p>Please <a href="' . wp_login_url() . '">log in</a> to change your membership plan.</p>';

    $user_id = get_current_user_id();
    $current = get_user_meta($user_id, 'membership_level', true);
    $plans = [
        'free' => ['label' => 'Free', 'duration' => 30],
        'pro' => ['label' => 'Pro', 'duration' => 365],
    ];

    if (isset($_POST['artpulse_plan'])) {
        $chosen = sanitize_text_field($_POST['artpulse_plan']);
        if (isset($plans[$chosen])) {
            update_user_meta($user_id, 'membership_level', $chosen);
            update_user_meta($user_id, 'membership_start_date', current_time('mysql'));
            update_user_meta($user_id, 'membership_end_date', date('Y-m-d H:i:s', strtotime("+{$plans[$chosen]['duration']} days")));
            update_user_meta($user_id, 'membership_auto_renew', true);
            echo '<div class="notice success">Membership updated to ' . esc_html($plans[$chosen]['label']) . '.</div>';
            $current = $chosen;
        }
    }

    ob_start();
    echo '<form method="post" class="membership-plans">';
    foreach ($plans as $key => $plan) {
        echo '<div style="margin-bottom:10px;">';
        echo '<label><input type="radio" name="artpulse_plan" value="' . esc_attr($key) . '"' . checked($current, $key, false) . '> ' . esc_html($plan['label']) . ' (' . $plan['duration'] . ' days)</label>';
        echo '</div>';
    }
    echo '<button type="submit">Update Plan</button>';
    echo '</form>';
    return ob_get_clean();
}
add_shortcode('membership_plans', 'artpulse_membership_plan_selector_shortcode');
