<?php
namespace EAD\Admin;

if ( ! class_exists( '\\WP_List_Table', false ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ManageMembersListTable extends \WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'member',
            'plural'   => 'members',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'name'    => __('Name', 'artpulse-management'),
            'email'   => __('Email', 'artpulse-management'),
            'level'   => __('Level', 'artpulse-management'),
            'expires' => __('Expires', 'artpulse-management'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'name'    => 'display_name',
            'expires' => 'membership_end_date',
        ];
    }

    public function prepare_items() {
        $per_page = 20;
        $paged    = max(1, intval($_REQUEST['paged'] ?? 1));
        $orderby  = sanitize_text_field($_REQUEST['orderby'] ?? 'display_name');
        $order    = sanitize_text_field($_REQUEST['order'] ?? 'asc');

        $args = [
            'number'     => $per_page,
            'paged'      => $paged,
            'meta_query' => [
                [
                    'key'     => 'membership_level',
                    'compare' => 'EXISTS',
                ],
            ],
            'orderby' => $orderby === 'membership_end_date' ? 'meta_value' : $orderby,
            'order'   => $order,
        ];

        if ( $orderby === 'membership_end_date' ) {
            $args['meta_key']  = 'membership_end_date';
            $args['meta_type'] = 'DATETIME';
        }

        if ( ! empty( $_REQUEST['s'] ) ) {
            $args['search']         = '*' . sanitize_text_field( $_REQUEST['s'] ) . '*';
            $args['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
        }

        $query       = new \WP_User_Query( $args );
        $this->items = $query->get_results();
        $total       = $query->get_total();

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ]);
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'name':
                $edit_url   = admin_url( 'user-edit.php?user_id=' . $item->ID );
                $resend_url = wp_nonce_url(
                    admin_url( 'admin.php?page=artpulse-manage-members&resend=' . $item->ID ),
                    'artpulse_resend_' . $item->ID
                );
                $actions = [
                    'edit'   => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit', 'artpulse-management')),
                    'resend' => sprintf('<a href="%s">%s</a>', esc_url($resend_url), __('Resend Reminder', 'artpulse-management')),
                ];
                return sprintf('%s %s', esc_html($item->display_name), $this->row_actions($actions));
            case 'email':
                return esc_html($item->user_email);
            case 'level':
                return esc_html(get_user_meta($item->ID, 'membership_level', true));
            case 'expires':
                return esc_html(get_user_meta($item->ID, 'membership_end_date', true));
            default:
                return '';
        }
    }
}

class ManageMembers {
    public static function render_admin_page() {
        if ( isset($_GET['resend']) && current_user_can('manage_options') ) {
            $uid = intval($_GET['resend']);
            if ( wp_verify_nonce($_GET['_wpnonce'] ?? '', 'artpulse_resend_' . $uid) ) {
                self::send_reminder($uid);
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html__('Reminder email sent.', 'artpulse-management') .
                    '</p></div>';
            }
        }

        $list_table = new ManageMembersListTable();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Manage Members', 'artpulse-management'); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="artpulse-manage-members" />
                <?php $list_table->search_box(__('Search Members', 'artpulse-management'), 'member-search'); ?>
            </form>
            <?php $list_table->display(); ?>
        </div>
        <?php
    }

    private static function send_reminder( $user_id ) {
        $user = get_user_by('id', $user_id);
        if ( ! $user ) {
            return;
        }
        $subject = 'Your ArtPulse Membership is Expiring Soon';
        $message = "Hi {$user->display_name},\n\nJust a reminder — your membership will expire soon. Please renew or upgrade your membership.\n\nThank you,\nArtPulse Team";
        wp_mail($user->user_email, $subject, $message);
    }
}
