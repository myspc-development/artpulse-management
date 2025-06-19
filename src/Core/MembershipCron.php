<?php
namespace ArtPulse\Core;

class MembershipCron
{
    public static function register()
    {
        add_action('ap_daily_expiry_check', [self::class, 'checkExpiries']);
    }

    public static function checkExpiries()
    {
        $users = get_users([
            'meta_key'     => 'ap_membership_expires',
            'meta_compare' => 'EXISTS',
            'number'       => 500,
        ]);

        $today = date('Y-m-d');
        $warning_day = date('Y-m-d', strtotime('+7 days'));

        foreach ($users as $user) {
            $expires = get_user_meta($user->ID, 'ap_membership_expires', true);
            if (!$expires) continue;

            if ($expires === $warning_day) {
                MembershipNotifier::sendExpiryWarningEmail($user);
            }

            if ($expires < $today) {
                update_user_meta($user->ID, 'ap_membership_level', 'free');
            }
        }
    }
}
