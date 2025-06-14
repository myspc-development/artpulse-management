<?php
// File: membership_signup.php

add_shortcode('ead_membership_status', function () {
    if (!is_user_logged_in()) return '<p>Please log in to select your membership.</p>';

    $message = '';
    if (isset($_GET['joined']) && $_GET['joined'] == '1') {
        $message = '<p style="color: green; font-weight: bold;">✅ Membership updated successfully!</p>';
    }

    ob_start();
    echo $message;
    ?>
    <form method="post">
        <?php wp_nonce_field('ead_membership_join_action', 'ead_membership_nonce'); ?>
        <label><strong>Name:</strong></label><br>
        <input type="text" name="display_name" value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" required><br><br>

        <label><strong>Short Bio:</strong></label><br>
        <textarea name="user_bio" rows="3"><?php echo esc_textarea(get_user_meta(get_current_user_id(), 'description', true)); ?></textarea><br><br>

        <label for="membership_level"><strong>Select Membership Level:</strong></label><br>
        <select name="membership_level" id="membership_level" required>
            <option value="basic">Basic Member</option>
            <option value="pro">Pro Artist</option>
            <option value="org">Organization</option>
        </select>
        <br><br>
        <button type="submit" name="ead_join_membership">Join Membership</button>
    </form>
    <?php
    return ob_get_clean();
});

// Assigns WordPress role based on level
function ead_assign_membership_role($user_id, $level) {
    $user = new WP_User($user_id);
    switch ($level) {
        case 'basic': $user->set_role('member_basic'); break;
        case 'pro':   $user->set_role('member_pro'); break;
        case 'org':   $user->set_role('member_org'); break;
        default:      $user->set_role('member_registered');
    }
}

add_action('init', function () {
    if (
        isset($_POST['ead_join_membership']) &&
        isset($_POST['ead_membership_nonce']) &&
        wp_verify_nonce($_POST['ead_membership_nonce'], 'ead_membership_join_action') &&
        is_user_logged_in()
    ) {
        $uid = get_current_user_id();

        wp_update_user([
            'ID' => $uid,
            'display_name' => sanitize_text_field($_POST['display_name'])
        ]);
        update_user_meta($uid, 'description', sanitize_textarea_field($_POST['user_bio']));

        $level = sanitize_text_field($_POST['membership_level']);
        update_user_meta($uid, 'is_member', '1');
        update_user_meta($uid, 'membership_level', $level);

        $fees = get_option('ead_membership_fees');
        $fee_amount = 0;
        if ($level === 'pro') $fee_amount = floatval($fees['pro_fee'] ?? 0);
        if ($level === 'org') $fee_amount = floatval($fees['org_fee'] ?? 0);

        if ($fee_amount > 0 && !empty($fees['enable_stripe'])) {
            update_user_meta($uid, 'pending_membership_level', $level);
            wp_redirect('/mock-stripe-checkout?membership=' . $level); // placeholder redirect
            exit;
        }

        ead_assign_membership_role($uid, $level);

        $redirect = '/dashboard';
        if ($level === 'pro') $redirect = '/artist-dashboard';
        if ($level === 'org') $redirect = '/organization-dashboard';

        wp_redirect(add_query_arg('joined', '1', $redirect));
        exit;
    }
});
