<?php
namespace EAD\Admin;

class AdminRedirects {
    public static function init() {
        add_action('admin_init', [self::class, 'redirect_clean_admin_urls']);
    }

    public static function redirect_clean_admin_urls() {
        $routes = [
            'ead-membership-settings'  => 'ead-membership-settings',
            'ead-membership-overview' => 'ead-membership-overview',
        ];

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        foreach ($routes as $fake => $slug) {
            if (strpos($uri, "/wp-admin/$fake") !== false) {
                wp_redirect(admin_url("admin.php?page=$slug"), 301);
                exit;
            }
        }
    }
}
