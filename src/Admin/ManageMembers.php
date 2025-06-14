<?php
namespace EAD\Admin;

if ( ! class_exists( '\\WP_List_Table', false ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ManageMembersListTable extends \WP_List_Table {

    /**
     * Available membership levels for filtering and bulk actions.
     * 'Free' was removed to match the simplified dropdown options.
     */
    private array $levels = [
        'basic'   => 'Basic',
        'pro'     => 'Pro',
        'org'     => 'Organization',
        'expired' => 'Expired',
    ];

    public function __construct() {
        parent::__construct([
            'singular' => 'member',
            'plural'   => 'members',
            'ajax'     => false,
        ]);
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="member_ids[]" value="%d" />',
            $item->ID
        );
    }

    public function get_bulk_actions() {
        return [
            'change_level' => __( 'Change Level', 'artpulse-management' ),
            'send_reminder' => __( 'Send Reminder', 'artpulse-management' ),
        ];
    }

    public function process_bulk_action() {
        if ( empty( $_POST['member_ids'] ) || ! is_array( $_POST['member_ids'] ) ) {
            return;
        }

        $ids = array_map( 'intval', (array) $_POST['member_ids'] );

        if ( 'send_reminder' === $this->current_action() ) {
            foreach ( $ids as $id ) {
                ManageMembers::send_reminder( $id );
            }
        }

        if ( 'change_level' === $this->current_action() && ! empty( $_POST['new_level'] ) ) {
            $level = sanitize_text_field( $_POST['new_level'] );
            foreach ( $ids as $id ) {
                update_user_meta( $id, 'membership_level', $level );
                ManageMembers::update_role( $id, $level );
            }
        }
    }

    public function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        $selected_level  = sanitize_text_field( $_REQUEST['filter_level'] ?? '' );
        $selected_status = sanitize_text_field( $_REQUEST['filter_expiration'] ?? '' );

        echo '<div class="alignleft actions">';
        echo '<select name="filter_level">';
        echo '<option value="">' . esc_html__( 'All Levels', 'artpulse-management' ) . '</option>';
        foreach ( $this->levels as $slug => $label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $slug ), selected( $selected_level, $slug, false ), esc_html( $label ) );
        }
        echo '</select>';

        echo '<select name="filter_expiration">';
        echo '<option value="">' . esc_html__( 'All Statuses', 'artpulse-management' ) . '</option>';
        echo '<option value="active" ' . selected( $selected_status, 'active', false ) . '>' . esc_html__( 'Active', 'artpulse-management' ) . '</option>';
        echo '<option value="expired" ' . selected( $selected_status, 'expired', false ) . '>' . esc_html__( 'Expired', 'artpulse-management' ) . '</option>';
        echo '</select>';
        submit_button( __( 'Filter' ), '', 'filter_action', false );
        echo '</div>';

        echo '<div class="alignleft actions" style="margin-left:10px;">';
        echo '<select name="new_level">';
        echo '<option value="">' . esc_html__( 'Change Level To...', 'artpulse-management' ) . '</option>';
        foreach ( $this->levels as $slug => $label ) {
            printf( '<option value="%s">%s</option>', esc_attr( $slug ), esc_html( $label ) );
        }
        echo '</select>';
        echo '</div>';
    }

    public function get_columns() {
        return [
            'name'   => __('Name', 'artpulse-management'),
            'email'  => __('Email', 'artpulse-management'),
            'level'  => __('Level', 'artpulse-management'),
            'start'  => __('Start Date', 'artpulse-management'),
            'expires'=> __('End Date', 'artpulse-management'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'name'   => 'display_name',
            'level'  => 'membership_level',
            'start'  => 'membership_start_date',
            'expires'=> 'membership_end_date',
        ];
    }

    public function prepare_items() {
        $per_page = 20;
        $paged    = max( 1, intval( $_REQUEST['paged'] ?? 1 ) );
        $orderby  = sanitize_text_field( $_REQUEST['orderby'] ?? 'display_name' );
        $order    = sanitize_text_field( $_REQUEST['order'] ?? 'asc' );

        $filter_level  = sanitize_text_field( $_REQUEST['filter_level'] ?? '' );
        $filter_status = sanitize_text_field( $_REQUEST['filter_expiration'] ?? '' );

        $args = [
            'number'     => $per_page,
            'paged'      => $paged,
            'meta_query' => [
                [
                    'key'     => 'membership_level',
                    'compare' => 'EXISTS',
                ],
            ],
            'orderby' => in_array( $orderby, [ 'membership_end_date', 'membership_start_date', 'membership_level' ], true ) ? 'meta_value' : $orderby,
            'order'   => $order,
        ];

        if ( $orderby === 'membership_end_date' ) {
            $args['meta_key']  = 'membership_end_date';
            $args['meta_type'] = 'DATETIME';
        } elseif ( $orderby === 'membership_start_date' ) {
            $args['meta_key']  = 'membership_start_date';
            $args['meta_type'] = 'DATETIME';
        } elseif ( $orderby === 'membership_level' ) {
            $args['meta_key']  = 'membership_level';
        }

        if ( ! empty( $_REQUEST['s'] ) ) {
            $args['search']         = '*' . sanitize_text_field( $_REQUEST['s'] ) . '*';
            $args['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
        }

        if ( $filter_level ) {
            $args['meta_query'][] = [
                'key'   => 'membership_level',
                'value' => $filter_level,
            ];
        }

        if ( $filter_status ) {
            $now = current_time( 'mysql' );
            if ( 'expired' === $filter_status ) {
                $args['meta_query'][] = [
                    'key'     => 'membership_end_date',
                    'value'   => $now,
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ];
            } elseif ( 'active' === $filter_status ) {
                $args['meta_query'][] = [
                    'key'     => 'membership_end_date',
                    'value'   => $now,
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ];
            }
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
                $membership_url = add_query_arg([
                    'page'  => 'artpulse-manage-members',
                    'edit'  => $item->ID,
                ], admin_url('admin.php'));
                $actions = [
                    'edit'         => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit', 'artpulse-management')),
                    'membership'   => sprintf('<a href="%s">%s</a>', esc_url($membership_url), __('Edit Membership', 'artpulse-management')),
                    'resend'       => sprintf('<a href="%s">%s</a>', esc_url($resend_url), __('Resend Reminder', 'artpulse-management')),
                ];
                return sprintf('%s %s', esc_html($item->display_name), $this->row_actions($actions));
            case 'email':
                return esc_html($item->user_email);
            case 'level':
                return esc_html(get_user_meta($item->ID, 'membership_level', true));
            case 'start':
                $start = get_user_meta($item->ID, 'membership_start_date', true);
                return $start ? esc_html( substr( $start, 0, 10 ) ) : '';
            case 'expires':
                $end = get_user_meta($item->ID, 'membership_end_date', true);
                return $end ? esc_html( substr( $end, 0, 10 ) ) : '';
            default:
                return '';
        }
    }
}

class ManageMembers {
    public static function render_admin_page() {
        if ( isset( $_GET['export'] ) && current_user_can( 'manage_options' ) ) {
            self::export_csv();
        }

        if ( isset( $_GET['edit'] ) && current_user_can( 'manage_options' ) ) {
            self::render_edit_form( intval( $_GET['edit'] ) );
            return;
        }

        if ( isset( $_GET['resend'] ) && current_user_can( 'manage_options' ) ) {
            $uid = intval( $_GET['resend'] );
            if ( wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'artpulse_resend_' . $uid ) ) {
                self::send_reminder( $uid );
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html__( 'Reminder email sent.', 'artpulse-management' ) .
                    '</p></div>';
            }
        }

        $list_table = new ManageMembersListTable();
        $list_table->process_bulk_action();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Manage Members', 'artpulse-management'); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="artpulse-manage-members" />
                <?php $list_table->search_box(__('Search Members', 'artpulse-management'), 'member-search'); ?>
            </form>
            <form method="get" style="display:inline-block;margin-right:10px;">
                <input type="hidden" name="page" value="artpulse-manage-members" />
                <input type="hidden" name="export" value="1" />
                <?php submit_button( __('Export CSV', 'artpulse-management'), 'secondary', '', false ); ?>
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

    public static function update_role( int $user_id, string $level ) : void {
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

    private static function export_csv() {
        $filter_level = sanitize_text_field( $_GET['filter_level'] ?? '' );

        $args = [
            'meta_query' => [
                [ 'key' => 'membership_level', 'compare' => 'EXISTS' ],
            ],
            'number' => -1,
        ];

        if ( $filter_level ) {
            $args['meta_query'][] = [
                'key'   => 'membership_level',
                'value' => $filter_level,
            ];
        }

        $users = get_users( $args );
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=members.csv');
        echo "Name,Email,Level,Start,End\n";
        foreach ( $users as $u ) {
            $level   = get_user_meta( $u->ID, 'membership_level', true );
            $start   = get_user_meta( $u->ID, 'membership_start_date', true );
            $expires = get_user_meta( $u->ID, 'membership_end_date', true );
            printf("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $u->display_name,
                $u->user_email,
                $level,
                substr( (string) $start, 0, 10 ),
                substr( (string) $expires, 0, 10 )
            );
        }
        exit;
    }

    private static function render_edit_form( int $user_id ) : void {
        if ( isset( $_POST['save_membership'] ) && check_admin_referer( 'edit_membership_' . $user_id ) ) {
            $level   = sanitize_text_field( $_POST['membership_level'] );
            $expires = sanitize_text_field( $_POST['membership_end_date'] );
            update_user_meta( $user_id, 'membership_level', $level );
            update_user_meta( $user_id, 'membership_end_date', $expires );
            self::update_role( $user_id, $level );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Membership updated.', 'artpulse-management' ) . '</p></div>';
        }

        $level   = get_user_meta( $user_id, 'membership_level', true );
        $expires = get_user_meta( $user_id, 'membership_end_date', true );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Edit Membership', 'artpulse-management' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'edit_membership_' . $user_id ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="membership_level"><?php esc_html_e( 'Level', 'artpulse-management' ); ?></label></th>
                        <td>
                            <select name="membership_level" id="membership_level">
                                <option value="basic" <?php selected( $level, 'basic' ); ?>>Basic</option>
                                <option value="pro" <?php selected( $level, 'pro' ); ?>>Pro</option>
                                <option value="org" <?php selected( $level, 'org' ); ?>>Organization</option>
                                <option value="free" <?php selected( $level, 'free' ); ?>>Free</option>
                                <option value="expired" <?php selected( $level, 'expired' ); ?>>Expired</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="membership_end_date"><?php esc_html_e( 'Expires', 'artpulse-management' ); ?></label></th>
                        <td><input type="date" name="membership_end_date" id="membership_end_date" value="<?php echo esc_attr( substr( $expires, 0, 10 ) ); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Changes', 'artpulse-management' ), 'primary', 'save_membership' ); ?>
            </form>
        </div>
        <?php
    }
}
