<?php
add_action('template_redirect', function () {
    if (!is_user_logged_in() || is_admin()) return;
    $u = wp_get_current_user();
    $url = $_SERVER['REQUEST_URI'];

    if (in_array('member_org', $u->roles) && strpos($url, '/organization-dashboard') === false) {
        wp_redirect('/organization-dashboard');
        exit;
    } elseif (in_array('member_pro', $u->roles) && strpos($url, '/artist-dashboard') === false) {
        wp_redirect('/artist-dashboard');
        exit;
    } elseif (in_array('member_basic', $u->roles) && strpos($url, '/dashboard') === false) {
        wp_redirect('/dashboard');
        exit;
    }
});
