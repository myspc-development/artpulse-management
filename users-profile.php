<?php
function artpulse_register_user_profile_meta() {
    register_meta('user', 'user_bio', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_meta('user', 'profile_links', ['type' => 'array', 'single' => true, 'show_in_rest' => true]);
    register_meta('user', 'featured_artist', ['type' => 'boolean', 'single' => true, 'show_in_rest' => true]);
    register_meta('user', 'profile_visibility', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
}
add_action('init', 'artpulse_register_user_profile_meta');

function artpulse_extra_user_profile_fields($user) {
    $bio = get_user_meta($user->ID, 'user_bio', true);
    $links = get_user_meta($user->ID, 'profile_links', true) ?: [];
    $featured = get_user_meta($user->ID, 'featured_artist', true);
    $visibility = get_user_meta($user->ID, 'profile_visibility', true);
    ?>
    <h3>ArtPulse Profile</h3>
    <table class="form-table">
        <tr><th><label for="user_bio">Bio</label></th>
            <td><textarea name="user_bio" rows="5" cols="50"><?php echo esc_textarea($bio); ?></textarea></td>
        </tr>
        <tr><th>Profile Links</th>
            <td>
                <input type="url" name="profile_links[website]" placeholder="Website" value="<?php echo esc_attr($links['website'] ?? ''); ?>"><br>
                <input type="url" name="profile_links[instagram]" placeholder="Instagram" value="<?php echo esc_attr($links['instagram'] ?? ''); ?>">
            </td>
        </tr>
        <tr><th><label><input type="checkbox" name="featured_artist" value="1" <?php checked($featured, true); ?>> Featured Artist</label></th></tr>
        <tr><th><label for="profile_visibility">Profile Visibility</label></th>
            <td>
                <select name="profile_visibility">
                    <option value="public" <?php selected($visibility, 'public'); ?>>Public</option>
                    <option value="private" <?php selected($visibility, 'private'); ?>>Private</option>
                </select>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'artpulse_extra_user_profile_fields');
add_action('edit_user_profile', 'artpulse_extra_user_profile_fields');

function artpulse_save_extra_user_profile_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;

    update_user_meta($user_id, 'user_bio', sanitize_textarea_field($_POST['user_bio'] ?? ''));
    update_user_meta($user_id, 'profile_links', array_map('esc_url_raw', $_POST['profile_links'] ?? []));
    update_user_meta($user_id, 'featured_artist', isset($_POST['featured_artist']) ? 1 : 0);
    update_user_meta($user_id, 'profile_visibility', sanitize_text_field($_POST['profile_visibility'] ?? 'public'));
}
add_action('personal_options_update', 'artpulse_save_extra_user_profile_fields');
add_action('edit_user_profile_update', 'artpulse_save_extra_user_profile_fields');

function artpulse_user_profile_shortcode($atts) {
    $atts = shortcode_atts(['user_id' => get_current_user_id()], $atts);
    $user = get_user_by('id', $atts['user_id']);
    if (!$user) return '<p>User not found.</p>';

    $bio = get_user_meta($user->ID, 'user_bio', true);
    $links = get_user_meta($user->ID, 'profile_links', true);
    $featured = get_user_meta($user->ID, 'featured_artist', true);

    ob_start();
    echo '<div class="user-profile space-y-4 p-4 border rounded">';
    echo '<h2 class="text-xl font-bold">' . esc_html($user->display_name) . ($featured ? ' 🌟' : '') . '</h2>';
    if ($bio) echo '<p>' . esc_html($bio) . '</p>';
    if (!empty($links)) {
        echo '<ul class="list-disc list-inside">';
        foreach ($links as $label => $url) {
            echo '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . ucfirst(esc_html($label)) . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('user_profile', 'artpulse_user_profile_shortcode');

