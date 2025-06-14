<?php
add_action('admin_menu', function () {
  add_menu_page('Membership Settings', 'Membership', 'manage_options', 'membership-settings', function () {
    echo '<div class="wrap"><h1>Membership Settings</h1>';
    echo '<form method="post">';
    wp_nonce_field('membership_settings_save', 'membership_nonce');
    echo '<label>Pro Label: <input name="pro_label" value="' . esc_attr(get_option('pro_label', 'Pro Artist')) . '" /></label><br>';
    echo '<label>Org Label: <input name="org_label" value="' . esc_attr(get_option('org_label', 'Organization')) . '" /></label><br>';
    submit_button('Save');
    echo '</form></div>';
  });
});

add_action('admin_init', function () {
  register_setting('general', 'pro_label');
  register_setting('general', 'org_label');
  if (
    isset($_POST['membership_nonce']) &&
    wp_verify_nonce($_POST['membership_nonce'], 'membership_settings_save')
  ) {
    if (isset($_POST['pro_label'])) update_option('pro_label', sanitize_text_field($_POST['pro_label']));
    if (isset($_POST['org_label'])) update_option('org_label', sanitize_text_field($_POST['org_label']));
  }
});
