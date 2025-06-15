<?php
// Module: ArtPulse Organizations

// 1. Register Custom Post Type: Organization
function artpulse_register_organization_cpt() {
    register_post_type('organization', [
        'labels' => [
            'name' => 'Organizations',
            'singular_name' => 'Organization',
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'thumbnail'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'rewrite' => ['slug' => 'organizations'],
    ]);
}
add_action('init', 'artpulse_register_organization_cpt');

// 2. Register Meta Fields
function artpulse_register_organization_meta() {
    register_post_meta('organization', 'org_website', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_post_meta('organization', 'org_logo_url', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_post_meta('organization', 'org_mission', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_post_meta('organization', 'org_admin_users', [
        'type'        => 'array',
        'single'      => true,
        'show_in_rest' => [
            'schema' => [
                'type'  => 'array',
                'items' => [ 'type' => 'integer' ],
            ],
        ],
    ]);
    register_post_meta('organization', 'org_team_members', [
        'type'        => 'array',
        'single'      => true,
        'show_in_rest' => [
            'schema' => [
                'type'  => 'array',
                'items' => [ 'type' => 'integer' ],
            ],
        ],
    ]);
    register_post_meta('organization', 'org_address', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_post_meta('organization', 'org_phone', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_post_meta('organization', 'org_country', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_post_meta('organization', 'org_state', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_post_meta('organization', 'org_city', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
}
add_action('init', 'artpulse_register_organization_meta');

// 3. Meta Boxes
function artpulse_add_organization_meta_boxes() {
    add_meta_box('org_details', 'Organization Details', 'artpulse_org_meta_box_callback', 'organization', 'normal', 'high');
}
add_action('add_meta_boxes', 'artpulse_add_organization_meta_boxes');

function artpulse_org_meta_box_callback($post) {
    $website = get_post_meta($post->ID, 'org_website', true);
    $logo = get_post_meta($post->ID, 'org_logo_url', true);
    $mission = get_post_meta($post->ID, 'org_mission', true);
    $admins = get_post_meta($post->ID, 'org_admin_users', true) ?: [];
    $team = get_post_meta($post->ID, 'org_team_members', true) ?: [];
    $address = get_post_meta($post->ID, 'org_address', true);
    $phone = get_post_meta($post->ID, 'org_phone', true);
    $country = get_post_meta($post->ID, 'org_country', true);
    $state   = get_post_meta($post->ID, 'org_state', true);
    $city    = get_post_meta($post->ID, 'org_city', true);
    ?>
    <p><label>Website: <input type="url" name="org_website" value="<?php echo esc_attr($website); ?>" style="width:100%;" /></label></p>
    <p><label>Logo URL: <input type="text" name="org_logo_url" value="<?php echo esc_attr($logo); ?>" style="width:100%;" /></label></p>
    <p><label>Mission:<br><textarea name="org_mission" rows="4" style="width:100%;"><?php echo esc_textarea($mission); ?></textarea></label></p>
    <p><label>Address:<br><input type="text" name="org_address" value="<?php echo esc_attr($address); ?>" style="width:100%;" /></label></p>
    <p><label>Country:<br><input type="text" name="org_country" value="<?php echo esc_attr($country); ?>" style="width:100%;" /></label></p>
    <p><label>State:<br><input type="text" name="org_state" value="<?php echo esc_attr($state); ?>" style="width:100%;" /></label></p>
    <p><label>City:<br><input type="text" name="org_city" value="<?php echo esc_attr($city); ?>" style="width:100%;" /></label></p>
    <p><label>Phone:<br><input type="text" name="org_phone" value="<?php echo esc_attr($phone); ?>" style="width:100%;" /></label></p>
    <p><label>Org Admin User IDs (comma-separated):<br>
        <input type="text" name="org_admin_users" value="<?php echo esc_attr(implode(',', $admins)); ?>" style="width:100%;" />
    </label></p>
    <p><label>Team Member User IDs (comma-separated):<br>
        <input type="text" name="org_team_members" value="<?php echo esc_attr(implode(',', $team)); ?>" style="width:100%;" />
    </label></p>
    <?php
}

function artpulse_save_organization_meta($post_id) {
    if (get_post_type($post_id) !== 'organization') return;
    update_post_meta($post_id, 'org_website', sanitize_text_field($_POST['org_website'] ?? ''));
    update_post_meta($post_id, 'org_logo_url', esc_url_raw($_POST['org_logo_url'] ?? ''));
    update_post_meta($post_id, 'org_mission', sanitize_textarea_field($_POST['org_mission'] ?? ''));
    update_post_meta($post_id, 'org_address', sanitize_text_field($_POST['org_address'] ?? ''));
    update_post_meta($post_id, 'org_country', sanitize_text_field($_POST['org_country'] ?? ''));
    update_post_meta($post_id, 'org_state', sanitize_text_field($_POST['org_state'] ?? ''));
    update_post_meta($post_id, 'org_city', sanitize_text_field($_POST['org_city'] ?? ''));
    update_post_meta($post_id, 'org_phone', sanitize_text_field($_POST['org_phone'] ?? ''));
    update_post_meta($post_id, 'org_admin_users', array_filter(array_map('intval', explode(',', $_POST['org_admin_users'] ?? ''))));
    update_post_meta($post_id, 'org_team_members', array_filter(array_map('intval', explode(',', $_POST['org_team_members'] ?? ''))));
}
add_action('save_post', 'artpulse_save_organization_meta');

// 4. Shortcode to Render Organization Profile
function artpulse_organization_profile_shortcode($atts) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post = get_post($atts['id']);
    if (!$post || $post->post_type !== 'organization') return '<p>Organization not found.</p>';

    $website = get_post_meta($post->ID, 'org_website', true);
    $logo    = get_post_meta($post->ID, 'org_logo_url', true);
    $mission = get_post_meta($post->ID, 'org_mission', true);
    $address = get_post_meta($post->ID, 'org_address', true);
    $phone   = get_post_meta($post->ID, 'org_phone', true);
    $email   = get_post_meta($post->ID, 'org_email', true);
    $social  = get_post_meta($post->ID, 'org_social_links', true) ?: [];
    $team    = get_post_meta($post->ID, 'org_team_members', true);

    ob_start();
    echo '<div class="organization-profile p-4 border rounded">';
    if ($logo) echo '<img src="' . esc_url($logo) . '" alt="Logo" class="mb-2" style="max-width:150px;" />';
    echo '<h2 class="text-xl font-bold">' . esc_html($post->post_title) . '</h2>';
    echo '<p class="mb-3">' . esc_html($mission) . '</p>';
    if ($address) echo '<p><strong>Address:</strong> ' . esc_html($address) . '</p>';
    if ($phone) echo '<p><strong>Phone:</strong> <a href="tel:' . esc_attr($phone) . '" class="text-blue-600 underline">' . esc_html($phone) . '</a></p>';
    if ($email) echo '<p><strong>Email:</strong> <a href="mailto:' . antispambot($email) . '" class="text-blue-600 underline">' . antispambot($email) . '</a></p>';
    if ($website) echo '<p><a href="' . esc_url($website) . '" class="text-blue-500 underline" target="_blank">Visit Website</a></p>';

    if (!empty($social)) {
        echo '<div class="mt-2"><strong>Social:</strong><div class="flex gap-2 mt-1">';
        foreach ($social as $link) {
            $icon = '';
            if (strpos($link, 'facebook.com') !== false) {
                $icon = '📘';
            } elseif (strpos($link, 'twitter.com') !== false || strpos($link, 'x.com') !== false) {
                $icon = '🐦';
            } elseif (strpos($link, 'instagram.com') !== false) {
                $icon = '📸';
            } elseif (strpos($link, 'linkedin.com') !== false) {
                $icon = '💼';
            } else {
                $icon = '🔗';
            }
            echo '<a href="' . esc_url($link) . '" target="_blank" class="text-blue-600 text-lg">' . $icon . '</a>';
        }
        echo '</div></div>';
    }

    if (!empty($team)) {
        echo '<h3 class="mt-4 font-semibold">Team Members</h3><ul class="list-disc list-inside">';
        foreach ($team as $uid) {
            $u = get_user_by('ID', $uid);
            if ($u) echo '<li>' . esc_html($u->display_name) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('organization_profile', 'artpulse_organization_profile_shortcode');

// 5. Permission filter: allow only org admins to edit their orgs
add_filter('user_has_cap', function ($caps, $cap, $args) {
    if (is_array($cap) && isset($cap[0]) && $cap[0] === 'edit_post') {
        $post_id = $args[2] ?? null;
        if ($post_id && get_post_type($post_id) === 'organization') {
            $org_admins = get_post_meta($post_id, 'org_admin_users', true) ?: [];
            if (in_array(get_current_user_id(), $org_admins)) {
                $caps[$cap[0]] = true;
            }
        }
    }
    return $caps;
}, 10, 3);

// 6. Shortcode: Frontend Org Selector for current user
function artpulse_organization_selector_shortcode() {
    if (!is_user_logged_in()) return '<p>Please log in to manage your organization.</p>';
    $user_id = get_current_user_id();

    $orgs = get_posts([
        'post_type' => 'organization',
        'posts_per_page' => -1,
        'meta_query' => [[
            'key' => 'org_admin_users',
            'value' => $user_id,
            'compare' => 'LIKE'
        ]]
    ]);

    if (empty($orgs)) return '<p>You are not an administrator of any organization.</p>';

    ob_start();
    echo '<ul class="list-disc list-inside">';
    foreach ($orgs as $org) {
        echo '<li><a href="' . get_edit_post_link($org->ID) . '">' . esc_html($org->post_title) . '</a></li>';
    }
    echo '</ul>';
    return ob_get_clean();
}
add_shortcode('organization_selector', 'artpulse_organization_selector_shortcode');

// 7. Frontend Form to Edit Organization with Logo Upload
function artpulse_organization_edit_form_shortcode($atts) {
    if (!is_user_logged_in()) return '<p>Please log in to edit your organization.</p>';

    $atts    = shortcode_atts(['id' => null], $atts);
    $post_id = intval($atts['id']);
    $user_id = get_current_user_id();

    if (!$post_id || get_post_type($post_id) !== 'organization') return '<p>Invalid organization.</p>';

    $admins = get_post_meta($post_id, 'org_admin_users', true) ?: [];
    if (!in_array($user_id, $admins)) return '<p>You are not an admin for this organization.</p>';

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['artpulse_org_edit_nonce']) && wp_verify_nonce($_POST['artpulse_org_edit_nonce'], 'edit_org')) {
        update_post_meta($post_id, 'org_website', sanitize_text_field($_POST['org_website'] ?? ''));
        update_post_meta($post_id, 'org_mission', sanitize_textarea_field($_POST['org_mission'] ?? ''));
        update_post_meta($post_id, 'org_address', sanitize_text_field($_POST['org_address'] ?? ''));
        update_post_meta($post_id, 'org_country', sanitize_text_field($_POST['org_country'] ?? ''));
        update_post_meta($post_id, 'org_state', sanitize_text_field($_POST['org_state'] ?? ''));
        update_post_meta($post_id, 'org_city', sanitize_text_field($_POST['org_city'] ?? ''));
        update_post_meta($post_id, 'org_phone', sanitize_text_field($_POST['org_phone'] ?? ''));

        // Handle logo upload
        if (!empty($_FILES['org_logo_file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded = media_handle_upload('org_logo_file', 0);
            if (!is_wp_error($uploaded)) {
                $logo_url = wp_get_attachment_url($uploaded);
                update_post_meta($post_id, 'org_logo_url', esc_url_raw($logo_url));
            } else {
                echo '<div class="p-2 bg-red-100 text-red-700 border border-red-300 rounded">Error uploading logo: ' . esc_html($uploaded->get_error_message()) . '</div>';
            }
        } elseif (!empty($_POST['org_logo_url'])) {
            update_post_meta($post_id, 'org_logo_url', esc_url_raw($_POST['org_logo_url']));
        }

        echo '<div class="p-2 bg-green-100 text-green-700 border border-green-300 rounded">Changes saved.</div>';
    }

    $website = get_post_meta($post_id, 'org_website', true);
    $logo    = get_post_meta($post_id, 'org_logo_url', true);
    $mission = get_post_meta($post_id, 'org_mission', true);
    $address = get_post_meta($post_id, 'org_address', true);
    $country = get_post_meta($post_id, 'org_country', true);
    $state   = get_post_meta($post_id, 'org_state', true);
    $city    = get_post_meta($post_id, 'org_city', true);
    $phone   = get_post_meta($post_id, 'org_phone', true);

    ob_start();
    ?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('edit_org', 'artpulse_org_edit_nonce'); ?>
        <p><label>Website:<br><input type="url" name="org_website" value="<?php echo esc_attr($website); ?>" style="width:100%"></label></p>
        <p><label>Logo URL (optional):<br><input type="text" name="org_logo_url" value="<?php echo esc_attr($logo); ?>" style="width:100%"></label></p>
        <p><label>Or Upload Logo Image:<br><input type="file" name="org_logo_file" accept="image/*"></label></p>
        <?php if ($logo): ?>
            <p><img src="<?php echo esc_url($logo); ?>" alt="Current Logo" style="max-height:100px;"></p>
        <?php endif; ?>
        <p><label>Mission:<br><textarea name="org_mission" rows="4" style="width:100%"><?php echo esc_textarea($mission); ?></textarea></label></p>
        <p><label>Address:<br><input type="text" name="org_address" value="<?php echo esc_attr($address); ?>" style="width:100%"></label></p>
        <p><label>Country:<br><input type="text" name="org_country" value="<?php echo esc_attr($country); ?>" style="width:100%"></label></p>
        <p><label>State:<br><input type="text" name="org_state" value="<?php echo esc_attr($state); ?>" style="width:100%"></label></p>
        <p><label>City:<br><input type="text" name="org_city" value="<?php echo esc_attr($city); ?>" style="width:100%"></label></p>
        <p><label>Phone:<br><input type="text" name="org_phone" value="<?php echo esc_attr($phone); ?>" style="width:100%"></label></p>
        <p><button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save Changes</button></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('organization_edit_form', 'artpulse_organization_edit_form_shortcode');
