<?php

namespace ArtPulse\Frontend;

class ProfileEditShortcode {

    public static function register() {
        add_shortcode('ap_profile_edit', [self::class, 'render_form']);
        add_action('init', [self::class, 'handle_form_submission']);
    }

    public static function render_form() {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to edit your profile.</p>';
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $bio = get_user_meta($user_id, 'description', true);
        $avatar = get_user_meta($user_id, 'ap_custom_avatar', true);
        $twitter = get_user_meta($user_id, 'ap_social_twitter', true);
        $instagram = get_user_meta($user_id, 'ap_social_instagram', true);
        $website = get_user_meta($user_id, 'ap_social_website', true);

        $output = '';
        if (isset($_GET['ap_updated'])) {
            $output .= '<div class="notice success">Profile updated successfully.</div>';
        }

        ob_start();
        ?>
        <form method="post" enctype="multipart/form-data" class="ap-profile-edit-form">
            <?php wp_nonce_field('ap_profile_edit_action', 'ap_profile_nonce'); ?>
            <p>
                <label for="display_name">Display Name</label><br>
                <input type="text" name="display_name" id="display_name" value="<?php echo esc_attr($user->display_name); ?>" required>
            </p>
            <p>
                <label for="description">Bio</label><br>
                <textarea name="description" id="description" rows="5"><?php echo esc_textarea($bio); ?></textarea>
            </p>
            <p>
                <label for="ap_avatar">Custom Avatar</label><br>
                <?php if ($avatar): ?>
                    <img src="<?php echo esc_url($avatar); ?>" alt="Current Avatar" style="max-width: 100px;" /><br>
                <?php endif; ?>
                <input type="file" name="ap_avatar" id="ap_avatar" accept="image/*">
            </p>
            <p>
                <label for="ap_social_twitter">Twitter URL</label><br>
                <input type="url" name="ap_social_twitter" id="ap_social_twitter" value="<?php echo esc_url($twitter); ?>">
            </p>
            <p>
                <label for="ap_social_instagram">Instagram URL</label><br>
                <input type="url" name="ap_social_instagram" id="ap_social_instagram" value="<?php echo esc_url($instagram); ?>">
            </p>
            <p>
                <label for="ap_social_website">Website URL</label><br>
                <input type="url" name="ap_social_website" id="ap_social_website" value="<?php echo esc_url($website); ?>">
            </p>
            <p>
                <input type="submit" name="ap_profile_submit" value="Update Profile">
            </p>
        </form>
        <?php
        return $output . ob_get_clean();
    }

    public static function handle_form_submission() {
        if (!isset($_POST['ap_profile_submit']) || !is_user_logged_in()) {
            return;
        }

        if (!isset($_POST['ap_profile_nonce']) || !wp_verify_nonce($_POST['ap_profile_nonce'], 'ap_profile_edit_action')) {
            return;
        }

        $user_id = get_current_user_id();
        $display_name = sanitize_text_field($_POST['display_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $twitter = esc_url_raw($_POST['ap_social_twitter']);
        $instagram = esc_url_raw($_POST['ap_social_instagram']);
        $website = esc_url_raw($_POST['ap_social_website']);

        wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name
        ]);

        update_user_meta($user_id, 'description', $description);
        update_user_meta($user_id, 'ap_social_twitter', $twitter);
        update_user_meta($user_id, 'ap_social_instagram', $instagram);
        update_user_meta($user_id, 'ap_social_website', $website);

        // Handle Avatar Upload
        if (!empty($_FILES['ap_avatar']['tmp_name'])) {
            $uploaded = media_handle_upload('ap_avatar', 0);
            if (!is_wp_error($uploaded)) {
                $avatar_url = wp_get_attachment_url($uploaded);
                update_user_meta($user_id, 'ap_custom_avatar', $avatar_url);
            }
        }

        wp_redirect(add_query_arg('ap_updated', '1', wp_get_referer()));
        exit;
    }
}
