<?php
namespace EAD\Admin;

if ( ! class_exists( '\\WP_List_Table', false ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ManageMembers {
    /**
     * Register hooks for member management actions.
     */
    public static function register() {
        add_action( 'admin_post_artpulse_upgrade_member', [ self::class, 'handle_upgrade_member' ] );
        add_action( 'admin_post_artpulse_assign_org', [ self::class, 'handle_assign_org' ] );
        add_action( 'admin_post_artpulse_delete_member', [ self::class, 'handle_delete_member' ] );
    }

    /**
     * Update the WordPress role based on membership level.
     */
    public static function update_role( int $user_id, string $level ) {
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
     * Handle upgrade action from the admin page.
     */
    public static function handle_upgrade_member() {
        $user_id = intval( $_GET['user_id'] ?? 0 );
        if ( $user_id ) {
            update_user_meta( $user_id, 'membership_level', 'pro' );
            update_user_meta( $user_id, 'membership_start_date', current_time( 'mysql' ) );
            update_user_meta( $user_id, 'membership_end_date', date( 'Y-m-d H:i:s', strtotime( '+365 days' ) ) );
            self::update_role( $user_id, 'pro' );
        }
        wp_redirect( admin_url( 'admin.php?page=artpulse-manage-members' ) );
        exit;
    }

    /**
     * Handle assigning an organization to a user.
     */
    public static function handle_assign_org() {
        $user_id = intval( $_GET['user_id'] ?? 0 );
        if ( $user_id ) {
            update_user_meta( $user_id, 'assigned_org', 'default_org' );
        }
        wp_redirect( admin_url( 'admin.php?page=artpulse-manage-members' ) );
        exit;
    }

    /**
     * Handle deleting a member from the admin page.
     */
    public static function handle_delete_member() {
        $user_id = intval( $_GET['user_id'] ?? 0 );
        if ( $user_id && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'artpulse_delete_' . $user_id ) ) {
            wp_delete_user( $user_id );
        }
        wp_redirect( admin_url( 'admin.php?page=artpulse-manage-members' ) );
        exit;
    }

    /**
     * Render the Manage Members admin page.
     */
    public static function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'artpulse-management' ) );
        }

        $users = get_users([
            'fields' => [ 'ID', 'display_name', 'user_email' ],
            'number' => 50,
        ]);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Manage Members', 'artpulse-management' ) . '</h1>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'User', 'artpulse-management' ) . '</th>';
        echo '<th>' . esc_html__( 'Email', 'artpulse-management' ) . '</th>';
        echo '<th>' . esc_html__( 'Level', 'artpulse-management' ) . '</th>';
        echo '<th>' . esc_html__( 'Expires', 'artpulse-management' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $users as $user ) {
            $level  = get_user_meta( $user->ID, 'membership_level', true );
            $expiry = get_user_meta( $user->ID, 'membership_end_date', true );
            echo '<tr>';
            echo '<td>' . esc_html( $user->display_name ) . '</td>';
            echo '<td>' . esc_html( $user->user_email ) . '</td>';
            echo '<td class="column-level">' . esc_html( $level ) . '</td>';
            echo '<td class="column-expires">' . esc_html( $expiry ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<div id="mm-add-row-container"></div>';
        echo '</div>';

        wp_enqueue_script( 'ead-manage-members', plugins_url( '../../assets/js/ead-manage-members.js', __FILE__ ), [ 'jquery' ], EAD_PLUGIN_VERSION, true );
        wp_localize_script( 'ead-manage-members', 'manageMembersData', [
            'restUrl'            => esc_url_raw( rest_url( 'artpulse/v1/manage-members/' ) ),
            'manageMembersNonce' => wp_create_nonce( 'wp_rest' ),
        ] );
    }
}

