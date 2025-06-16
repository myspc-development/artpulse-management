<?php
namespace EAD\Shortcodes;

class MembershipSignupForm {
    public static function register() {
        add_shortcode( 'ead_membership_status', [ self::class, 'render' ] );
        // Handle form submission during the init hook so user redirects work
        // before template output.
        add_action( 'init', [ self::class, 'handle_submit' ] );
        // Display notices for missing dashboard pages if needed.
        add_action( 'admin_notices', [ self::class, 'maybe_display_missing_page_notice' ] );
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

    /**
     * Get the dashboard URL for the given membership level.
     *
     * @param string $level Membership level slug.
     * @return string URL to redirect to.
     */
    private static function get_dashboard_url_for_level( string $level ): string {
        $settings = get_option( 'artpulse_plugin_settings', [] );

        $defaults = [
            'basic' => 'dashboard',
            'pro'   => 'artist-dashboard',
            'org'   => 'organization-dashboard',
        ];

        $key  = $level . '_dashboard_slug';
        $slug = isset( $settings[ $key ] ) ? trim( $settings[ $key ], '/' ) : $defaults[ $level ];

        $page = get_page_by_path( $slug );
        if ( $page ) {
            return get_permalink( $page );
        }

        // Persist notice for admins about the missing page.
        set_transient( 'ead_missing_dashboard_page', $slug, DAY_IN_SECONDS );

        return home_url( '/' );
    }

    /**
     * Display an admin notice if a dashboard page is missing.
     */
    public static function maybe_display_missing_page_notice() {
        $slug = get_transient( 'ead_missing_dashboard_page' );
        if ( ! $slug || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        delete_transient( 'ead_missing_dashboard_page' );

        echo '<div class="notice notice-warning is-dismissible"><p>' .
            sprintf(
                esc_html__( 'Dashboard page "%s" not found. Please create a page with this slug and add the appropriate shortcode.', 'artpulse-management' ),
                esc_html( $slug )
            ) .
            '</p></div>';
    }

    public static function render() {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in to manage your membership.</p>';
        }

        $u     = wp_get_current_user();
        $level = get_user_meta( $u->ID, 'membership_level', true );
        $bio   = get_user_meta( $u->ID, 'description', true );
        $badge = get_user_meta( $u->ID, 'org_badge_label', true );

        do_action( 'artpulse_before_form' );
        ob_start();
        ?>
        <div id="membership-message"></div>
        <form id="membership-form">
            <?php wp_nonce_field( 'ead_membership_ajax_action', 'ead_membership_nonce' ); ?>
            <label>Name: <input type="text" name="name" value="<?php echo esc_attr( $u->display_name ); ?>"></label><br>
            <label>Bio: <textarea name="bio"><?php echo esc_textarea( $bio ); ?></textarea></label><br>
            <?php if ( in_array( 'member_org', $u->roles ) ) : ?>
                <label>Badge Label: <input type="text" name="badge_label" value="<?php echo esc_attr( $badge ); ?>"></label><br>
            <?php endif; ?>
            <label>Membership Level:
                <select name="membership_level">
                    <option value="basic" <?php selected( $level, 'basic' ); ?>>Basic</option>
                    <option value="pro" <?php selected( $level, 'pro' ); ?>>Pro</option>
                    <option value="org" <?php selected( $level, 'org' ); ?>>Organization</option>
                </select>
            </label><br>
            <button type="submit">Update</button>
        </form>
        <script>
            document.getElementById('membership-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                const form = e.target;
                const data = new FormData(form);
                const res = await fetch('<?php echo esc_url( rest_url( 'artpulse/v1/membership/update' ) ); ?>', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce( 'wp_rest' ); ?>' },
                    body: data
                });
                const json = await res.json();
                document.getElementById('membership-message').innerText = json.message || 'Saved!';
            });
        </script>
        <?php
        $html = ob_get_clean();
        $html = apply_filters( 'artpulse_form_output', $html );
        return $html;
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

        // Update profile fields.
        wp_update_user([
            'ID'           => $uid,
            'display_name' => sanitize_text_field( $_POST['name'] ?? '' ),
        ]);
        update_user_meta( $uid, 'description', sanitize_textarea_field( $_POST['bio'] ?? '' ) );

        update_user_meta( $uid, 'is_member', '1' );
        update_user_meta( $uid, 'membership_level', $level );

        self::assign_role( $uid, $level );

        $redirect_url = self::get_dashboard_url_for_level( $level );

        wp_redirect( add_query_arg( 'joined', '1', $redirect_url ) );
        exit;
    }

    public static function handle_rest_submit( \WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new \WP_Error( 'forbidden', 'Not logged in', [ 'status' => 403 ] );
        }

        $name  = sanitize_text_field( $request['name'] );
        $bio   = sanitize_textarea_field( $request['bio'] );
        $level = sanitize_text_field( $request['membership_level'] );
        $badge = sanitize_text_field( $request['badge_label'] );

        wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
        update_user_meta( $user_id, 'description', $bio );
        update_user_meta( $user_id, 'membership_level', $level );
        update_user_meta( $user_id, 'is_member', 1 );
        update_user_meta( $user_id, 'membership_joined', current_time( 'mysql' ) );
        $expires = strtotime( '+1 year' );
        update_user_meta( $user_id, 'membership_expires', date( 'Y-m-d H:i:s', $expires ) );
        if ( $badge ) {
            update_user_meta( $user_id, 'org_badge_label', $badge );
        }

        self::assign_role( $user_id, $level );

        return [ 'success' => true, 'message' => 'Membership updated successfully.' ];
    }
}
