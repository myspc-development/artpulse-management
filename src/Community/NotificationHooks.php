<?php
namespace ArtPulse\Community;

class NotificationHooks {
    /**
     * Register all action/event hooks.
     */
    public static function register() {
        // 🔔 Notify post authors of new approved comments
        add_action('comment_post', [self::class, 'notify_on_comment'], 10, 3);

        // 🔔 Membership changes (upgrade/downgrade/expired)
        add_action('ap_membership_level_changed', [self::class, 'notify_on_membership_change'], 10, 4);

        // 🔔 Membership payment events
        add_action('ap_membership_payment', [self::class, 'notify_on_payment'], 10, 4);
    }

    /**
     * Notify post author of a new comment.
     */
    public static function notify_on_comment($comment_ID, $comment_approved, $commentdata) {
        if ($comment_approved != 1) return;

        $post = get_post($commentdata['comment_post_ID']);
        if (!$post) return;

        $author_id = $post->post_author;
        $commenter_id = intval($commentdata['user_id']);

        if ($author_id && $author_id !== $commenter_id) {
            NotificationManager::add(
                $author_id,
                'comment',
                $post->ID,
                $commenter_id,
                sprintf('New comment on your post "%s".', $post->post_title)
            );
        }
    }

    /**
     * Notify user on membership level change.
     */
    public static function notify_on_membership_change($user_id, $old_level, $new_level, $change_type) {
        NotificationManager::add(
            $user_id,
            'membership_' . $change_type,
            null,
            null,
            sprintf(
                'Your membership was %s: %s → %s.',
                esc_html($change_type),
                esc_html($old_level),
                esc_html($new_level)
            )
        );
    }

    /**
     * Notify user of payment-related events.
     */
    public static function notify_on_payment($user_id, $amount, $currency, $event_type) {
        $amount_display = number_format_i18n($amount, 2) . ' ' . strtoupper($currency);
        NotificationManager::add(
            $user_id,
            'payment_' . $event_type,
            null,
            null,
            sprintf('Payment %s: %s.', esc_html($event_type), esc_html($amount_display))
        );
    }
}
