<?php

namespace ArtPulse\Community;

class CommunityShortcodeManager
{
    public static function register(): void
    {
        add_shortcode('ap_notifications', [self::class, 'render']);
    }

    public static function render(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your notifications.', 'artpulse') . '</p>';
        }

        $user_id       = get_current_user_id();
        $limit         = isset($atts['limit']) ? max(1, (int) $atts['limit']) : 50;
        $notifications = NotificationManager::get($user_id, $limit);

        ob_start();
        ?>
        <div id="ap-notifications-widget">
            <h3><?php echo esc_html__('Your Notifications', 'artpulse'); ?></h3>
            <button id="ap-refresh-notifications" type="button" class="button">
                <?php echo esc_html__('Refresh', 'artpulse'); ?>
            </button>
            <ul id="ap-notification-list" role="status" aria-live="polite">
                <?php if (empty($notifications)) : ?>
                    <li class="ap-empty"><?php echo esc_html__('No notifications yet.', 'artpulse'); ?></li>
                <?php else : ?>
                    <?php foreach ($notifications as $notification) : ?>
                        <?php
                        $content = $notification->content ?: sprintf(
                            /* translators: %s: notification type */
                            __('%s update', 'artpulse'),
                            ucfirst((string) $notification->type)
                        );
                        $time_ago = human_time_diff(strtotime((string) $notification->created_at), current_time('timestamp'));
                        ?>
                        <li class="ap-notification-item" data-notification-id="<?php echo esc_attr((string) $notification->id); ?>">
                            <span class="ap-notification-content"><?php echo wp_kses_post($content); ?></span>
                            <span class="ap-notification-meta">
                                <?php echo esc_html(sprintf(__('Received %s ago', 'artpulse'), $time_ago)); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }
}
