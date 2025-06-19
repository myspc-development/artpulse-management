<?php
namespace ArtPulse\Community;

class EmailNotifier {
    /**
     * Trigger an email if the notification type is configured for email.
     */
    public static function maybe_send($user_id, $type, $object_id = null, $related_id = null, $content = '') {
        // Types that should also send an email
        $email_types = [
            'link_request_sent',
            'link_request_approved',
            'link_request_denied',
            'follower',
            'favorite',
            'comment',
            'membership_upgrade',
            'membership_downgrade',
            'membership_expired',
            'payment_paid',
            'payment_failed',
            'payment_refunded'
        ];

        if (!in_array($type, $email_types, true)) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user || !is_email($user->user_email)) {
            return;
        }

        $subject = self::generate_subject($type, $content);
        $body    = self::generate_body($user, $content);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($user->user_email, $subject, $body, $headers);
    }

    /**
     * Create email subject from notification type.
     */
    private static function generate_subject($type, $fallback) {
        $map = [
            'link_request_sent'    => 'New Profile Link Request',
            'link_request_approved'=> 'Your Profile Link Was Approved',
            'link_request_denied'  => 'Your Profile Link Was Denied',
            'follower'             => 'You Have a New Follower',
            'favorite'             => 'Your Work Was Favorited',
            'comment'              => 'New Comment Received',
            'membership_upgrade'   => 'Membership Upgraded',
            'membership_downgrade' => 'Membership Downgraded',
            'membership_expired'   => 'Membership Expired',
            'payment_paid'         => 'Payment Received',
            'payment_failed'       => 'Payment Failed',
            'payment_refunded'     => 'Payment Refunded',
        ];
        return $map[$type] ?? wp_strip_all_tags($fallback);
    }

    /**
     * Generate simple HTML email body.
     */
    private static function generate_body($user, $content) {
        return sprintf(
            '<p>Hi %s,</p><p>%s</p><p>Thanks,<br/>ArtPulse Team</p>',
            esc_html($user->display_name),
            nl2br(esc_html($content))
        );
    }
}
