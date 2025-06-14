<?php
// Register Custom Post Type: organization
function artpulse_register_organization_cpt() {
    register_post_type('organization', [
        'labels' => [
            'name'          => 'Organizations',
            'singular_name' => 'Organization',
        ],
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'supports'     => ['title', 'editor', 'thumbnail'],
        'rewrite'      => ['slug' => 'organizations'],
    ]);
}
add_action('init', 'artpulse_register_organization_cpt');

// Register Custom Fields for Organization Meta
function artpulse_register_organization_meta() {
    register_post_meta('organization', 'org_website', [
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => true,
    ]);
    register_post_meta('organization', 'org_logo_url', [
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => true,
    ]);
    register_post_meta('organization', 'org_mission', [
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => true,
    ]);
    register_post_meta('organization', 'org_admin_users', [
        'type'         => 'array',
        'single'       => true,
        'show_in_rest' => true,
    ]);
}
add_action('init', 'artpulse_register_organization_meta');

// Add Fields to Admin UI (Meta Box)
function artpulse_add_organization_meta_boxes() {
    add_meta_box(
        'org_details',
        'Organization Details',
        'artpulse_org_meta_box_callback',
        'organization',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'artpulse_add_organization_meta_boxes');

function artpulse_org_meta_box_callback($post) {
    $website = get_post_meta($post->ID, 'org_website', true);
    $logo    = get_post_meta($post->ID, 'org_logo_url', true);
    $mission = get_post_meta($post->ID, 'org_mission', true);
    $admins  = get_post_meta($post->ID, 'org_admin_users', true) ?: [];
    ?>
    <p><label>Website: <input type="url" name="org_website" value="<?php echo esc_attr($website); ?>" style="width:100%;" /></label></p>
    <p><label>Logo URL: <input type="text" name="org_logo_url" value="<?php echo esc_attr($logo); ?>" style="width:100%;" /></label></p>
    <p><label>Mission:<br><textarea name="org_mission" rows="4" style="width:100%;"><?php echo esc_textarea($mission); ?></textarea></label></p>
    <p><label>Org Admin Users (comma-separated user IDs):<br>
        <input type="text" name="org_admin_users" value="<?php echo esc_attr(implode(',', $admins)); ?>" style="width:100%;" />
    </label></p>
    <?php
}

function artpulse_save_organization_meta($post_id) {
    if (get_post_type($post_id) !== 'organization') {
        return;
    }
    update_post_meta($post_id, 'org_website', sanitize_text_field($_POST['org_website'] ?? ''));
    update_post_meta($post_id, 'org_logo_url', esc_url_raw($_POST['org_logo_url'] ?? ''));
    update_post_meta($post_id, 'org_mission', sanitize_textarea_field($_POST['org_mission'] ?? ''));
    $admins = array_filter(array_map('intval', explode(',', $_POST['org_admin_users'] ?? '')));
    update_post_meta($post_id, 'org_admin_users', $admins);
}
add_action('save_post', 'artpulse_save_organization_meta');

// Frontend Shortcode: [organization_profile id="123"]
function artpulse_organization_profile_shortcode($atts) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post = get_post($atts['id']);
    if (!$post || $post->post_type !== 'organization') {
        return '<p>Organization not found.</p>';
    }

    $website = get_post_meta($post->ID, 'org_website', true);
    $logo    = get_post_meta($post->ID, 'org_logo_url', true);
    $mission = get_post_meta($post->ID, 'org_mission', true);

    ob_start();
    echo '<div class="organization-profile">';
    if ($logo) {
        echo '<img src="' . esc_url($logo) . '" alt="Logo" style="max-width:150px;" />';
    }
    echo '<h2>' . esc_html($post->post_title) . '</h2>';
    echo '<p>' . esc_html($mission) . '</p>';
    if ($website) {
        echo '<p><a href="' . esc_url($website) . '" target="_blank">Visit Website</a></p>';
    }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('organization_profile', 'artpulse_organization_profile_shortcode');
