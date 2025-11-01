<?php
namespace ArtPulse\Core;

class MembershipNotifier
{
    public static function register()
    {
        add_action('ap_user_upgraded_to_pro', [self::class, 'sendUpgradeEmail'], 10, 1);
    }

    public static function sendUpgradeEmail($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) return;

        $to = $user->user_email;
        $subject = __('Welcome to Pro Membership!', 'artpulse-management');
        $message = sprintf(
            __("Hi %s,\n\nYour ArtPulse membership has been upgraded to Pro. Enjoy your new features!\n\nThanks,\nArtPulse Team", 'artpulse-management'),
            $user->display_name
        );

        wp_mail($to, $subject, $message);
    }

    public static function sendExpiryWarningEmail($user)
    {
        $to = $user->user_email;
        $subject = __('Your ArtPulse membership is expiring soon', 'artpulse-management');
        $message = sprintf(
            __("Hi %s,\n\nJust a heads up that your Pro membership will expire on %s.\nPlease renew to avoid interruption.\n\nThanks,\nArtPulse", 'artpulse-management'),
            $user->display_name,
            get_user_meta($user->ID, 'ap_membership_expires', true)
        );

        wp_mail($to, $subject, $message);
    }
}
