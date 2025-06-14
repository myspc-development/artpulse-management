<?php
namespace EAD\Admin;

if ( ! class_exists( '\WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BookingsListTable extends \WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'booking',
            'plural'   => 'bookings',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'     => '<input type="checkbox" />',
            'title'  => __( 'Title', 'artpulse-management' ),
            'author' => __( 'User', 'artpulse-management' ),
            'date'   => __( 'Booking Date', 'artpulse-management' ),
            'status' => __( 'Status', 'artpulse-management' ),
        ];
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="booking_ids[]" value="%s" />',
            $item->ID
        );
    }

    protected function column_title( $item ) {
        $actions = [];
        $edit_link = get_edit_post_link( $item->ID );
        if ( $edit_link ) {
            $actions['edit'] = sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), __( 'Edit', 'artpulse-management' ) );
        }
        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url( $edit_link ),
            esc_html( $item->post_title ),
            $this->row_actions( $actions )
        );
    }

    public function get_sortable_columns() {
        return [ 'date' => 'date' ];
    }

    public function prepare_items() {
        $per_page = 20;
        $status   = isset( $_REQUEST['status'] ) ? sanitize_text_field( $_REQUEST['status'] ) : 'all';
        $paged    = max( 1, intval( $_REQUEST['paged'] ?? 1 ) );

        $args = [
            'post_type'      => 'ead_booking',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
        ];

        if ( 'pending' === $status ) {
            $args['post_status'] = 'pending';
        } elseif ( 'approved' === $status ) {
            $args['post_status'] = 'publish';
        } else {
            $args['post_status'] = [ 'publish', 'pending', 'draft' ];
        }

        $query       = new \WP_Query( $args );
        $this->items = $query->posts;

        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages,
        ]);
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'author':
                $user = get_user_by( 'id', $item->post_author );
                return $user ? esc_html( $user->display_name ) : '';
            case 'date':
                $date = get_post_meta( $item->ID, '_ead_booking_date', true );
                return $date ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) ) : '';
            case 'status':
                $status = get_post_status_object( $item->post_status );
                return $status ? esc_html( $status->label ) : esc_html( $item->post_status );
            default:
                return '';
        }
    }

    public function get_bulk_actions() {
        return [
            'approve' => __( 'Approve', 'artpulse-management' ),
            'trash'   => __( 'Move to Trash', 'artpulse-management' ),
        ];
    }

    public function process_bulk_action() {
        if ( empty( $_POST['booking_ids'] ) || ! is_array( $_POST['booking_ids'] ) ) {
            return;
        }

        $ids = array_map( 'intval', (array) $_POST['booking_ids'] );

        if ( 'approve' === $this->current_action() ) {
            foreach ( $ids as $id ) {
                if ( get_post_type( $id ) === 'ead_booking' ) {
                    wp_update_post( [ 'ID' => $id, 'post_status' => 'publish' ] );
                }
            }
        }

        if ( 'trash' === $this->current_action() ) {
            foreach ( $ids as $id ) {
                if ( get_post_type( $id ) === 'ead_booking' ) {
                    wp_trash_post( $id );
                }
            }
        }
    }

    public function views() {
        $current  = isset( $_REQUEST['status'] ) ? sanitize_text_field( $_REQUEST['status'] ) : 'all';
        $statuses = [
            'all'     => __( 'All', 'artpulse-management' ),
            'pending' => __( 'Pending', 'artpulse-management' ),
            'approved' => __( 'Approved', 'artpulse-management' ),
        ];
        $links = [];
        foreach ( $statuses as $key => $label ) {
            $class = ( $current === $key ) ? 'class="current"' : '';
            $url   = add_query_arg( 'status', $key );
            $links[ $key ] = sprintf( '<a href="%s" %s>%s</a>', esc_url( $url ), $class, esc_html( $label ) );
        }
        return $links;
    }
}
