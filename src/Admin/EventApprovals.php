<?php

namespace ArtPulse\Admin;

use ArtPulse\Community\NotificationManager;
use ArtPulse\Core\AuditLogger;
use WP_List_Table;
use WP_Post;
use WP_Query;
use WP_User;
use function wp_strip_all_tags;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EventApprovals
{
    private const MENU_SLUG = 'artpulse-event-approvals';

    private static ?EventApprovals $instance = null;

    private bool $hooks_initialized = false;

    public static function register(): void
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        self::$instance->initialize_hooks();
    }

    private function initialize_hooks(): void
    {
        if ( $this->hooks_initialized ) {
            return;
        }

        $this->hooks_initialized = true;

        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_notices', [ $this, 'render_notices' ] );
        add_action( 'admin_post_artpulse_approve_event', [ $this, 'handle_single_approve' ] );
        add_action( 'admin_post_artpulse_reject_event', [ $this, 'handle_single_reject' ] );
    }

    public function register_menu(): void
    {
        $hook = add_submenu_page(
            'artpulse-settings',
            __( 'Event Approvals', 'artpulse' ),
            __( 'Event Approvals', 'artpulse' ),
            'publish_artpulse_events',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );

        if ( $hook ) {
            add_action( "load-{$hook}", [ $this, 'handle_page_load' ] );
        }
    }

    public function handle_page_load(): void
    {
        $table  = $this->create_table();
        $action = $table->current_action();

        if ( ! $action ) {
            return;
        }

        if ( ! $this->current_user_can_manage() ) {
            $this->redirect_with_notice( 'no-cap' );
        }

        check_admin_referer( 'bulk-artpulse-event-approvals' );

        $ids = isset( $_REQUEST['post'] ) ? array_map( 'absint', (array) $_REQUEST['post'] ) : [];
        $ids = array_filter( $ids );

        if ( empty( $ids ) ) {
            $this->redirect_with_notice( 'bulk-none' );
        }

        if ( 'approve' === $action ) {
            $count  = $this->approve_events( $ids );
            $notice = $count ? 'bulk-approved' : 'bulk-error';
        } elseif ( 'reject' === $action ) {
            $count  = $this->reject_events( $ids );
            $notice = $count ? 'bulk-rejected' : 'bulk-error';
        } else {
            return;
        }

        $this->redirect_with_notice( $notice, $count );
    }

    public function render_page(): void
    {
        $table = $this->create_table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Event Approvals', 'artpulse' ); ?></h1>
            <form method="post">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
                <?php
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_notices(): void
    {
        if ( ! isset( $_GET['page'] ) || self::MENU_SLUG !== $_GET['page'] ) {
            return;
        }

        $notice = isset( $_GET['artpulse_notice'] ) ? sanitize_key( wp_unslash( $_GET['artpulse_notice'] ) ) : '';

        if ( ! $notice ) {
            return;
        }

        $count = isset( $_GET['processed'] ) ? absint( $_GET['processed'] ) : 0;

        $class   = 'notice notice-success';
        $message = '';

        switch ( $notice ) {
            case 'no-cap':
                $class   = 'notice notice-error';
                $message = __( 'You do not have permission to manage event approvals.', 'artpulse' );
                break;
            case 'invalid-nonce':
                $class   = 'notice notice-error';
                $message = __( 'Security check failed. Please try again.', 'artpulse' );
                break;
            case 'invalid-event':
                $class   = 'notice notice-error';
                $message = __( 'The requested event could not be found.', 'artpulse' );
                break;
            case 'single-approved':
                $message = __( 'Event approved.', 'artpulse' );
                break;
            case 'single-rejected':
                $class   = 'notice notice-warning';
                $message = __( 'Event moved to the trash.', 'artpulse' );
                break;
            case 'single-error':
                $class   = 'notice notice-error';
                $message = __( 'Unable to update the event. Please try again.', 'artpulse' );
                break;
            case 'bulk-approved':
                /* translators: %d is the number of events approved. */
                $message = sprintf( __( 'Approved %d event(s).', 'artpulse' ), $count );
                break;
            case 'bulk-rejected':
                $class   = 'notice notice-warning';
                /* translators: %d is the number of events rejected. */
                $message = sprintf( __( 'Rejected %d event(s).', 'artpulse' ), $count );
                break;
            case 'bulk-none':
                $class   = 'notice notice-warning';
                $message = __( 'No events were selected.', 'artpulse' );
                break;
            case 'bulk-error':
                $class   = 'notice notice-error';
                $message = __( 'Unable to process the selected events. Please try again.', 'artpulse' );
                break;
        }

        if ( ! $message ) {
            return;
        }
        ?>
        <div class="<?php echo esc_attr( $class ); ?>">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
        <?php
    }

    public function handle_single_approve(): void
    {
        $this->process_single_action( 'approve' );
    }

    public function handle_single_reject(): void
    {
        $this->process_single_action( 'reject' );
    }

    private function process_single_action( string $action ): void
    {
        $event_id = isset( $_REQUEST['event_id'] ) ? absint( $_REQUEST['event_id'] ) : 0;

        if ( ! $event_id ) {
            $this->redirect_with_notice( 'invalid-event' );
        }

        $nonce_action = $this->get_single_nonce_action( $action, $event_id );
        $nonce        = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            $this->redirect_with_notice( 'invalid-nonce' );
        }

        if ( ! $this->current_user_can_manage() ) {
            $this->redirect_with_notice( 'no-cap' );
        }

        if ( 'approve' === $action ) {
            $count  = $this->approve_events( [ $event_id ] );
            $notice = $count ? 'single-approved' : 'single-error';
        } else {
            $count  = $this->reject_events( [ $event_id ] );
            $notice = $count ? 'single-rejected' : 'single-error';
        }

        $this->redirect_with_notice( $notice, $count );
    }

    private function approve_events( array $event_ids ): int
    {
        $processed = 0;
        $reason    = $this->get_request_reason();

        foreach ( $event_ids as $event_id ) {
            $post = get_post( $event_id );

            if ( ! $post instanceof WP_Post ) {
                continue;
            }

            if ( ! in_array( $post->post_status, [ 'pending', 'draft' ], true ) ) {
                continue;
            }

            $updated = wp_update_post(
                [
                    'ID'          => $event_id,
                    'post_status' => 'publish',
                ],
                true
            );

            if ( is_wp_error( $updated ) || 0 === $updated ) {
                continue;
            }

            $processed++;

            AuditLogger::info(
                'event.approve',
                [
                    'event_id'   => $event_id,
                    'user_id'    => get_current_user_id(),
                    'owner_id'   => $owner_id,
                    'context'    => 'dashboard',
                    'reason'     => $reason,
                    'state'      => 'approved',
                    'changed_at' => $changed_at,
                ]
            );
            $this->send_status_email( get_post( $event_id ), 'approved', $reason );
            $this->notify_owner( $event_id, 'approved', $reason );
        }

        return $processed;
    }

    private function reject_events( array $event_ids ): int
    {
        $processed = 0;
        $reason    = $this->get_request_reason();

        foreach ( $event_ids as $event_id ) {
            $post = get_post( $event_id );

            if ( ! $post instanceof WP_Post ) {
                continue;
            }

            if ( ! in_array( $post->post_status, [ 'pending', 'draft' ], true ) ) {
                continue;
            }

            $trashed = wp_trash_post( $event_id );

            if ( ! $trashed instanceof WP_Post ) {
                continue;
            }

            $processed++;

            AuditLogger::info(
                'event.deny',
                [
                    'event_id'   => $event_id,
                    'user_id'    => get_current_user_id(),
                    'owner_id'   => $owner_id,
                    'context'    => 'dashboard',
                    'reason'     => $reason,
                    'state'      => 'denied',
                    'changed_at' => $changed_at,
                ]
            );

        }

        return $processed;
    }

    private function send_status_email( ?WP_Post $post, string $status, string $reason ): void
    {
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        $author = get_user_by( 'id', $post->post_author );

        if ( ! $author instanceof WP_User || empty( $author->user_email ) ) {
            return;
        }

        $blog_name   = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        $author_name = $author->display_name ? $author->display_name : $author->user_login;

        if ( 'approved' === $status ) {
            $subject = sprintf( __( 'Your event "%s" has been approved', 'artpulse' ), $post->post_title );
            $message = sprintf(
                __( "Hi %1\$s,\n\nYour event \"%2\$s\" has been approved and is now published on %3\$s.%4\$s\n\nThanks,\n%5\$s", 'artpulse' ),
                $author_name,
                $post->post_title,
                esc_url( home_url() ),
                $reason ? '\n\n' . sprintf( __( 'Moderator note: %s', 'artpulse' ), $reason ) : '',
                $blog_name
            );
            $message = apply_filters( 'artpulse_event_approval_email_body', $message, $post, $author );
        } elseif ( 'changes_requested' === $status ) {
            $subject = sprintf( __( 'Updates requested for "%s"', 'artpulse' ), $post->post_title );
            $message = sprintf(
                __( "Hi %1\$s,\n\nWe need a few updates to your event \"%2\$s\" before it can be approved.%3\$s\n\nThanks,\n%4\$s", 'artpulse' ),
                $author_name,
                $post->post_title,
                $reason ? '\n\n' . sprintf( __( 'Moderator note: %s', 'artpulse' ), $reason ) : '',
                $blog_name
            );
            $message = apply_filters( 'artpulse_event_changes_requested_email_body', $message, $post, $author );
        } else {
            $subject = sprintf( __( 'Your event "%s" was not approved', 'artpulse' ), $post->post_title );
            $message = sprintf(
                __( "Hi %1\$s,\n\nYour event \"%2\$s\" was not approved. You can review the submission and try again.%3\$s\n\nThanks,\n%4\$s", 'artpulse' ),
                $author_name,
                $post->post_title,
                $reason ? '\n\n' . sprintf( __( 'Moderator note: %s', 'artpulse' ), $reason ) : '',
                $blog_name
            );
            $message = apply_filters( 'artpulse_event_rejection_email_body', $message, $post, $author );
        }

        wp_mail( $author->user_email, $subject, $message );
    }


    }

    private function notify_owner( int $event_id, string $state, string $reason ): void
    {
        $post = get_post( $event_id );

        if ( ! $post instanceof WP_Post || ! $post->post_author ) {
            return;
        }

        $title   = $post->post_title ? wp_strip_all_tags( $post->post_title ) : __( 'Event', 'artpulse' );


        if ( '' !== $reason ) {
            $message .= ' ' . sprintf( __( 'Reason: %s', 'artpulse' ), $reason );
        }

        NotificationManager::add(
            (int) $post->post_author,
            'event_moderated',
            $event_id,
            0,
            $message
        );
    }

    private function redirect_with_notice( string $notice, int $count = 0 ): void
    {
        $args = [
            'page'            => self::MENU_SLUG,
            'artpulse_notice' => $notice,
        ];

        if ( $count > 0 ) {
            $args['processed'] = $count;
        }

        $location = add_query_arg( $args, admin_url( 'admin.php' ) );

        wp_safe_redirect( $location );
        exit;
    }

    private function current_user_can_manage(): bool
    {
        return current_user_can( 'artpulse_approve_event' ) || current_user_can( 'publish_artpulse_events' );
    }

    private function get_request_reason(): string
    {
        if ( ! isset( $_REQUEST['reason'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return '';
        }

        return sanitize_textarea_field( wp_unslash( (string) $_REQUEST['reason'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    private function get_single_nonce_action( string $action, int $event_id ): string
    {
        return 'approve' === $action
            ? 'artpulse_approve_event_' . $event_id
            : 'artpulse_reject_event_' . $event_id;
    }

    private function create_table(): EventApprovalListTable
    {
        if ( ! class_exists( '\WP_List_Table' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        return new EventApprovalListTable( $this );
    }

    public function get_single_action_url( string $action, int $event_id ): string
    {
        $args = [
            'action'   => 'artpulse_' . $action . '_event',
            'event_id' => $event_id,
        ];

        $url = add_query_arg( $args, admin_url( 'admin-post.php' ) );

        return wp_nonce_url( $url, $this->get_single_nonce_action( $action, $event_id ) );
    }
}

class EventApprovalListTable extends WP_List_Table
{
    private EventApprovals $approvals;

    public function __construct( EventApprovals $approvals )
    {
        $this->approvals = $approvals;

        parent::__construct(
            [
                'singular' => 'artpulse-event-approval',
                'plural'   => 'artpulse-event-approvals',
                'ajax'     => false,
            ]
        );
    }

    public function get_columns(): array
    {
        return [
            'cb'      => '<input type="checkbox" />',
            'title'   => __( 'Title', 'artpulse' ),
            'author'  => __( 'Author', 'artpulse' ),
            'date'    => __( 'Date Submitted', 'artpulse' ),
            'status'  => __( 'Status', 'artpulse' ),
            'actions' => __( 'Actions', 'artpulse' ),
        ];
    }

    protected function column_cb( $item ): string
    {
        return sprintf(
            '<input type="checkbox" name="post[]" value="%d" />',
            absint( $item->ID )
        );
    }

    protected function column_title( $item ): string
    {
        $title = sprintf(
            '<strong><a href="%s">%s</a></strong>',
            esc_url( get_edit_post_link( $item->ID ) ),
            esc_html( get_the_title( $item ) )
        );

        $actions = [
            'approve' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $this->approvals->get_single_action_url( 'approve', $item->ID ) ),
                esc_html__( 'Approve', 'artpulse' )
            ),
            'reject'  => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $this->approvals->get_single_action_url( 'reject', $item->ID ) ),
                esc_html__( 'Reject', 'artpulse' )
            ),
            'view'    => sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url( get_permalink( $item ) ),
                esc_html__( 'View', 'artpulse' )
            ),
        ];

        return $title . $this->row_actions( $actions );
    }

    protected function column_author( $item ): string
    {
        $author = get_userdata( $item->post_author );

        return $author instanceof WP_User ? esc_html( $author->display_name ?: $author->user_login ) : esc_html__( 'Unknown', 'artpulse' );
    }

    protected function column_date( $item ): string
    {
        $timestamp = get_post_time( 'U', true, $item );

        return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
    }

    protected function column_status( $item ): string
    {
        $status_object = get_post_status_object( $item->post_status );

        return $status_object ? esc_html( $status_object->label ) : esc_html( $item->post_status );
    }

    protected function column_actions( $item ): string
    {
        $links = [
            sprintf(
                '<a class="button button-primary" href="%s">%s</a>',
                esc_url( $this->approvals->get_single_action_url( 'approve', $item->ID ) ),
                esc_html__( 'Approve', 'artpulse' )
            ),
            sprintf(
                '<a class="button" href="%s">%s</a>',
                esc_url( $this->approvals->get_single_action_url( 'reject', $item->ID ) ),
                esc_html__( 'Reject', 'artpulse' )
            ),
            sprintf(
                '<a class="button" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url( get_permalink( $item ) ),
                esc_html__( 'View', 'artpulse' )
            ),
        ];

        return implode( ' ', $links );
    }

    protected function get_bulk_actions(): array
    {
        return [
            'approve' => __( 'Approve', 'artpulse' ),
            'reject'  => __( 'Reject', 'artpulse' ),
        ];
    }

    public function prepare_items(): void
    {
        $per_page     = 20;
        $current_page = $this->get_pagenum();

        $args = [
            'post_type'      => 'artpulse_event',
            'post_status'    => [ 'pending', 'draft' ],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
        ];

        $query = new WP_Query( $args );

        $this->_column_headers = [ $this->get_columns(), [], [] ];
        $this->items           = $query->posts;

        $this->set_pagination_args(
            [
                'total_items' => (int) $query->found_posts,
                'per_page'    => $per_page,
                'total_pages' => (int) ceil( $query->found_posts / $per_page ),
            ]
        );

        wp_reset_postdata();
    }
}
