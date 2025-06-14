<?php
namespace EAD\Shortcodes;

class MembershipSignupForm {
    public static function register() {
        add_shortcode('ead_membership_status', [self::class, 'render']);
        add_action('wp_loaded', [self::class, 'handle_submit']);
    }

    public static function render() {
        if (!is_user_logged_in()) {
            return '<p>Login to select your membership.</p>';
        }

        ob_start();
        ?>
        <form method="post">
            <?php wp_nonce_field('ead_membership_join_action', 'ead_membership_nonce'); ?>
            <select name="membership_level">
                <option value="basic">Basic</option>
                <option value="pro">Pro Artist</option>
                <option value="org">Organization</option>
            </select>
            <button type="submit" name="ead_join_membership">Join</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function handle_submit() {
        if (empty($_POST['ead_join_membership'])) {
            return;
        }
        if (empty($_POST['ead_membership_nonce']) || !wp_verify_nonce($_POST['ead_membership_nonce'], 'ead_membership_join_action')) {
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }

        $level = sanitize_text_field($_POST['membership_level'] ?? '');
        $mapping = [
            'basic' => 'member_basic',
            'pro'   => 'member_pro',
            'org'   => 'member_org',
        ];

        if (!isset($mapping[$level])) {
            return;
        }

        $user = wp_get_current_user();
        $user->set_role($mapping[$level]);
    }
}
