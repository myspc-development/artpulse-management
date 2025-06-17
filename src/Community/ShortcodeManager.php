namespace ArtPulse\Core;

use ArtPulse\Community\NotificationManager;

class NotificationShortcode {
    public static function register() {
        add_shortcode('ap_notifications', [self::class, 'render']);
    }

    public static function render($atts = []) {
        if (!is_user_logged_in()) return '<p>Please log in to view your notifications.</p>';

        $user_id = get_current_user_id();
        $notifications = NotificationManager::get($user_id, 50);

        ob_start();
        ?>
        <div id="ap-notifications-widget">
            <h3>Your Notifications</h3>
            <button id="ap-refresh-notifications">🔄 Refresh</button>
            <ul id="ap-notification-list">
                <li>Loading...</li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
