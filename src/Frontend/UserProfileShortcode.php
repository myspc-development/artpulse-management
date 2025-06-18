<?php
namespace Artpulse\Frontend;

class UserProfileShortcode {

    public static function register() {
        add_shortcode('ap_user_profile', [self::class, 'render']);
    }

    public static function render($atts) {
        $atts = shortcode_atts([
            'id' => get_current_user_id()
        ], $atts);

        $user_id = intval($atts['id']);
        $user = get_userdata($user_id);

        if (!$user) {
            return '<div class="ap-user-profile-error">User not found.</div>';
        }

        $bio = get_user_meta($user_id, 'description', true);
        $followers = self::countFollowers($user_id);
        $avatar = get_user_meta($user_id, 'ap_custom_avatar', true);
        $twitter = get_user_meta($user_id, 'ap_social_twitter', true);
        $instagram = get_user_meta($user_id, 'ap_social_instagram', true);
        $website = get_user_meta($user_id, 'ap_social_website', true);

        ob_start(); ?>
        <div class="ap-user-profile">
            <div class="ap-user-profile-header">
                <img src="<?php echo esc_url($avatar ? $avatar : get_avatar_url($user_id)); ?>" class="ap-user-avatar" alt="User avatar">
                <h2 class="ap-user-name"><?php echo esc_html($user->display_name); ?></h2>
            </div>
            <div class="ap-user-profile-body">
                <?php if ($bio): ?>
                    <p class="ap-user-bio"><?php echo esc_html($bio); ?></p>
                <?php endif; ?>
                <p><strong>Followers:</strong> <?php echo intval($followers); ?></p>

                <div class="ap-user-social-links">
                    <?php if ($twitter): ?>
                        <p><a href="<?php echo esc_url($twitter); ?>" target="_blank">Twitter</a></p>
                    <?php endif; ?>
                    <?php if ($instagram): ?>
                        <p><a href="<?php echo esc_url($instagram); ?>" target="_blank">Instagram</a></p>
                    <?php endif; ?>
                    <?php if ($website): ?>
                        <p><a href="<?php echo esc_url($website); ?>" target="_blank">Website</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function countFollowers($user_id) {
        global $wpdb;
        $meta_key = 'ap_following';
        $like = '%' . $wpdb->esc_like($user_id) . '%';
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->usermeta}
            WHERE meta_key = %s AND meta_value LIKE %s
        ", $meta_key, $like));
    }
} 
