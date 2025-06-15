<?php
namespace EAD\Admin;

if ( ! class_exists( '\WP_List_Table', false ) ) {
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
     * Register the Manage Members admin menu.
     */
    public static function admin_menu() {
        add_menu_page(
            'Manage Members',                // Page title
            'Members',                       // Menu title
            'manage_options',                // Capability
            'artpulse-manage-members',       // Menu slug
            [self::class, 'render_page'],    // Callback function
            'dashicons-groups',              // Icon
            56                               // Position
        );
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
    public static function render_page() {
        echo '<div class="wrap"><h1>Manage Members</h1>';

        // TODO: Output your member table and admin controls here.
        echo '<p>This is your Manage Members admin page. Add your UI code here.</p>';

        // Example: Show all members with a quick upgrade form
        $members = get_users([
            'meta_query' => [
                ['key' => 'membership_level', 'compare' => 'EXISTS']
            ],
            'number' => 30
        ]);
        echo '<table class="widefat"><tr><th>Name</th><th>Email</th><th>Level</th><th>Upgrade</th></tr>';
        foreach ($members as $user) {
            $level = get_user_meta($user->ID, 'membership_level', true);
            echo '<tr>';
            echo '<td>' . esc_html($user->display_name) . '</td>';
            echo '<td>' . esc_html($user->user_email) . '</td>';
            echo '<td>' . esc_html($level) . '</td>';
            echo '<td><a class="button" href="' . esc_url( wp_nonce_url(
                admin_url('admin-post.php?action=artpulse_upgrade_member&user_id=' . $user->ID), 'artpulse_upgrade_' . $user->ID
            )) . '">Upgrade to Pro</a></td>';
            echo '</tr>';
        }
        echo '</table>';

        echo '</div>';
    }

    /** Wrapper expected by Menu class. */
    public static function render_admin_page() {
        self::render_page();
    }
}
