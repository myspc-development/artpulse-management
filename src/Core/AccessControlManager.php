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
        if ( is_singular(['artpulse_event','artpulse_artwork']) ) {
            $level = get_user_meta(get_current_user_id(),'ap_membership_level',true);
            if ( $level === 'Free' ) {
                wp_redirect(home_url());
                exit;
            }
        }
    }
}
