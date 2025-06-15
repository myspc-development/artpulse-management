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
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to upgrade members.', 'artpulse-management' ) );
        }
        $user_id = intval( $_GET['user_id'] ?? 0 );
        if ( $user_id ) {
            update_user_meta( $user_id, 'membership_level', 'pro' );
            update_user_meta( $user_id, 'membership_start_date', current_time( 'mysql' ) );
            update_user_meta( $user_id, 'membership_end_date', date( 'Y-m-d H:i:s', strtotime( '+365 days' ) ) );
            self::update_role( $user_id, 'pro' );
        }
        wp_redirect( admin_url( 'admin.php?page=ead-member-menu' ) );
        exit;
    }

    /**
     * Handle assigning an organization to a user.
     */
    public static function handle_assign_org() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to assign organizations.', 'artpulse-management' ) );
        }
        $user_id = intval( $_GET['user_id'] ?? 0 );
        if ( $user_id ) {
            update_user_meta( $user_id, 'assigned_org', 'default_org' );
        }
        wp_redirect( admin_url( 'admin.php?page=ead-member-menu' ) );
        exit;
    }

    /**
     * Handle deleting a member from the admin page.
     */
    public static function handle_delete_member() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to delete members.', 'artpulse-management' ) );
        }
        $user_id = intval( $_GET['user_id'] ?? 0 );
        if ( $user_id && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'artpulse_delete_' . $user_id ) ) {
            wp_delete_user( $user_id );
        }
        wp_redirect( admin_url( 'admin.php?page=ead-member-menu' ) );
        exit;
    }

    /**
     * Render the Manage Members admin page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to manage members.', 'artpulse-management' ) );
        }

        $search   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $paged    = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $per_page = 20;

        $args = [
            'meta_query' => [
                [ 'key' => 'membership_level', 'compare' => 'EXISTS' ],
            ],
            'number' => $per_page,
            'paged'  => $paged,
        ];

        if ( $search !== '' ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
        }

        $query   = new \WP_User_Query( $args );
        $members = $query->get_results();
        $total   = $query->get_total();
        $total_pages = $total > 0 ? ceil( $total / $per_page ) : 1;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Manage Members', 'artpulse-management' ) . '</h1>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ead-member-menu" />';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="member-search-input">' . esc_html__( 'Search Members', 'artpulse-management' ) . '</label>';
        echo '<input type="search" id="member-search-input" name="s" value="' . esc_attr( $search ) . '" />';
        echo '<input type="submit" id="search-submit" class="button" value="' . esc_attr__( 'Search Members', 'artpulse-management' ) . '" />';
        echo '</p>';
        echo '</form>';

        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Name', 'artpulse-management' ) . '</th>';
        echo '<th>' . esc_html__( 'Email', 'artpulse-management' ) . '</th>';
        echo '<th>' . esc_html__( 'Level', 'artpulse-management' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'artpulse-management' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( $members ) {
            foreach ( $members as $user ) {
                $level      = get_user_meta( $user->ID, 'membership_level', true );
                $upgrade_url = wp_nonce_url(
                    admin_url( 'admin-post.php?action=artpulse_upgrade_member&user_id=' . $user->ID ),
                    'artpulse_upgrade_' . $user->ID
                );
                $assign_url  = wp_nonce_url(
                    admin_url( 'admin-post.php?action=artpulse_assign_org&user_id=' . $user->ID ),
                    'artpulse_assign_' . $user->ID
                );
                $delete_url  = wp_nonce_url(
                    admin_url( 'admin-post.php?action=artpulse_delete_member&user_id=' . $user->ID ),
                    'artpulse_delete_' . $user->ID
                );

                echo '<tr>';
                echo '<td>' . esc_html( $user->display_name ) . '</td>';
                echo '<td>' . esc_html( $user->user_email ) . '</td>';
                echo '<td>' . esc_html( $level ) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url( $upgrade_url ) . '" class="button">' . esc_html__( 'Upgrade to Pro', 'artpulse-management' ) . '</a> ';
                echo '<a href="' . esc_url( $assign_url ) . '" class="button">' . esc_html__( 'Assign Org', 'artpulse-management' ) . '</a> ';
                echo '<a href="' . esc_url( $delete_url ) . '" class="button delete" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this member?', 'artpulse-management' ) ) . '\');">' . esc_html__( 'Delete', 'artpulse-management' ) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">' . esc_html__( 'No members found.', 'artpulse-management' ) . '</td></tr>';
        }

        echo '</tbody></table>';

        if ( $total_pages > 1 ) {
            echo '<div class="tablenav bottom"><div class="tablenav-pages">';
            $base = admin_url( 'admin.php?page=ead-member-menu' );
            if ( $search ) {
                $base = add_query_arg( 's', urlencode( $search ), $base );
            }
            for ( $i = 1; $i <= $total_pages; $i++ ) {
                $url   = add_query_arg( 'paged', $i, $base );
                $class = $i === $paged ? 'button button-primary' : 'button';
                echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . $i . '</a> ';
            }
            echo '</div></div>';
        }

        echo '</div>';
    }

    /** Wrapper expected by Menu class. */
    public static function render_admin_page() {
        self::render_page();
    }
}
