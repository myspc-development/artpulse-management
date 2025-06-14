<?php
add_shortcode('ead_user_profile_tab', function () {
    if (!is_user_logged_in()) return '<p>Please log in.</p>';
    $u = wp_get_current_user();
    $id = $u->ID;

    ob_start();
    ?>
    <form method="post">
        <?php wp_nonce_field('ead_profile_update_action', 'ead_profile_nonce'); ?>
        <input name="display_name" value="<?php echo esc_attr($u->display_name); ?>" />
        <input name="user_email" value="<?php echo esc_attr($u->user_email); ?>" />
        <textarea name="description"><?php echo esc_textarea(get_user_meta($id, 'description', true)); ?></textarea>
        <?php if (in_array('member_org', $u->roles)) : ?>
            <input name="org_badge_label" value="<?php echo esc_attr(get_user_meta($id, 'org_badge_label', true)); ?>" />
        <?php endif; ?>
        <button name="ead_profile_update">Save Profile</button>
    </form>
    <?php return ob_get_clean();
});

add_action('init', function () {
    if (
        isset($_POST['ead_profile_update']) &&
        isset($_POST['ead_profile_nonce']) &&
        wp_verify_nonce($_POST['ead_profile_nonce'], 'ead_profile_update_action') &&
        is_user_logged_in()
    ) {
        $id = get_current_user_id();
        $u  = wp_get_current_user();
        wp_update_user([
            'ID'           => $id,
            'display_name' => sanitize_text_field($_POST['display_name']),
            'user_email'   => sanitize_email($_POST['user_email'])
        ]);
        update_user_meta($id, 'description', sanitize_textarea_field($_POST['description']));
        if (in_array('member_org', $u->roles)) {
            update_user_meta($id, 'org_badge_label', sanitize_text_field($_POST['org_badge_label']));
        }
        wp_mail($u->user_email, 'Your ArtPulse Profile Has Been Updated', "Hi {$u->display_name},\n\nYour profile has been updated successfully.");
        wp_redirect(add_query_arg('updated', '1', wp_get_referer()));
        exit;
    }
});
