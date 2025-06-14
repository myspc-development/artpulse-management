<?php
// Add settings page to WP Admin
add_action('admin_menu', function () {
    add_menu_page(
        'ArtPulse Settings',
        'ArtPulse Settings',
        'manage_options',
        'artpulse-settings',
        'render_artpulse_settings_page'
    );
});

// Register settings
add_action('admin_init', function () {
    register_setting('artpulse_settings_group', 'artpulse_pro_price');
    register_setting('artpulse_settings_group', 'artpulse_org_price');
    register_setting('artpulse_settings_group', 'artpulse_membership_duration');
    register_setting('artpulse_settings_group', 'artpulse_upgrade_policy');
});

// Render settings page
function render_artpulse_settings_page() {
    ?>
    <div class="wrap">
        <h1>ArtPulse Membership Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('artpulse_settings_group'); ?>
            <?php do_settings_sections('artpulse_settings_group'); ?>

            <h2>Pricing</h2>
            <table class="form-table">
                <tr>
                    <th><label for="artpulse_pro_price">Pro Plan Price ($)</label></th>
                    <td><input type="text" name="artpulse_pro_price" value="<?php echo esc_attr(get_option('artpulse_pro_price', '10')); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="artpulse_org_price">Org Plan Price ($)</label></th>
                    <td><input type="text" name="artpulse_org_price" value="<?php echo esc_attr(get_option('artpulse_org_price', '25')); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="artpulse_membership_duration">Membership Duration (days)</label></th>
                    <td><input type="number" name="artpulse_membership_duration" value="<?php echo esc_attr(get_option('artpulse_membership_duration', '365')); ?>" /></td>
                </tr>
            </table>

            <h2>Upgrade Policy</h2>
            <table class="form-table">
                <tr>
                    <th><label for="artpulse_upgrade_policy">Upgrade Policy</label></th>
                    <td>
                        <select name="artpulse_upgrade_policy">
                            <option value="auto" <?php selected(get_option('artpulse_upgrade_policy'), 'auto'); ?>>Auto-Approve</option>
                            <option value="manual" <?php selected(get_option('artpulse_upgrade_policy'), 'manual'); ?>>Require Manual Approval</option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
