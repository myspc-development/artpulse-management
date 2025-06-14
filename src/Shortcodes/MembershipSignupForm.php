<?php
namespace EAD\Shortcodes;

class MembershipSignupForm {
    public static function register() {
        add_shortcode( 'ead_membership_status', [ self::class, 'render' ] );
        add_action( 'wp_loaded', [ self::class, 'handle_submit' ] );
    }

    /**
     * Assign a membership role to the given user based on the level.
     *
     * @param int    $user_id User ID.
     * @param string $level   Membership level slug.
     */
    private static function assign_role( $user_id, $level ) {
        $user = new \WP_User( $user_id );

        switch ( $level ) {
            case 'basic':
                $user->set_role( 'member_basic' );
                break;
            case 'pro':
                $user->set_role( 'member_pro' );
                break;
            case 'org':
                $user->set_role( 'member_org' );
                break;
            default:
                $user->set_role( 'member_registered' );
        }
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
        if ( empty( $_POST['ead_join_membership'] ) ) {
            return;
        }
        if ( empty( $_POST['ead_membership_nonce'] ) || ! wp_verify_nonce( $_POST['ead_membership_nonce'], 'ead_membership_join_action' ) ) {
            return;
        }
        if ( ! is_user_logged_in() ) {
            return;
        }

        $level = sanitize_text_field( $_POST['membership_level'] ?? '' );

        $uid = get_current_user_id();

        update_user_meta( $uid, 'is_member', '1' );
        update_user_meta( $uid, 'membership_level', $level );

        self::assign_role( $uid, $level );

        wp_redirect( add_query_arg( 'joined', '1', wp_get_referer() ) );
        exit;
    }
}
