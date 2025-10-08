<?php
namespace ArtPulse\Core;

class AccessControlManager
{
    public static function register()
    {
        add_action('template_redirect', [self::class,'checkAccess']);
    }

    public static function checkAccess()
    {
        $dashboard_pages = ['dashboard', 'artist-dashboard', 'org-dashboard'];

        if (is_page($dashboard_pages)) {
            return;
        }

        if ( is_singular(['artpulse_event','artpulse_artwork']) ) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_redirect(home_url());
                exit;
            }

            $level      = get_user_meta($user_id, 'ap_membership_level', true);
            $user       = wp_get_current_user();
            $has_upgrade = $user && array_intersect(['artist', 'organization'], (array) $user->roles);

            if ('Free' === $level && !$has_upgrade) {
                wp_redirect(home_url());
                exit;
            }
        }
    }
}
