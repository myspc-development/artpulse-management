<?php
namespace EAD\Admin;

class PendingEvents {

    public static function register() {
        // Hooks to handle custom approve/reject actions
        add_action('admin_init', [self::class, 'handle_event_actions']);
        add_action('admin_init', [self::class, 'handle_reject_event']);
    }

    /**
     * Handle custom actions like 'approve' for single events from the pending list.
     */
    public static function handle_event_actions() {
        // Check if our specific action is being performed
        if (
            isset($_GET['page']) && $_GET['page'] === 'artpulse-pending-events' && // Ensure it's from our page
            isset($_GET['action']) && $_GET['action'] === 'approve' &&
            isset($_GET['post_id']) && isset($_GET['nonce'])
        ) {
            $post_id = intval($_GET['post_id']);
            $nonce = sanitize_text_field($_GET['nonce']);

            // Verify nonce
            if (!wp_verify_nonce($nonce, 'ead_approve_event_' . $post_id)) {
                wp_die(__('Security check failed. Please try again.', 'artpulse-management'));
            }

            // Check user capabilities
            if (!current_user_can('publish_post', $post_id)) {
                wp_die(__('You do not have permission to approve this event.', 'artpulse-management'));
            }

            // Approve the event
            $updated = wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);

            if (is_wp_error($updated) || !$updated) {
                // Handle error, maybe redirect with an error message
                wp_redirect(admin_url('admin.php?page=artpulse-pending-events&approval_error=1&post_id=' . $post_id));
            } else {
                // Success! Redirect back with a success message
                // The transition_post_status hook in the main Plugin class should handle notifications
                wp_redirect(admin_url('admin.php?page=artpulse-pending-events&event_approved=1&post_id=' . $post_id));
            }
            exit;
        }
    }

    /**
     * Handle single event rejection (move to trash).
     */
    public static function handle_reject_event() {
        if (
            isset($_GET['page']) && $_GET['page'] === 'artpulse-pending-events' &&
            isset($_GET['action']) && $_GET['action'] === 'reject' &&
            isset($_GET['post_id']) && isset($_GET['nonce'])
        ) {
            $post_id = intval($_GET['post_id']);
            $nonce   = sanitize_text_field($_GET['nonce']);

            if (!wp_verify_nonce($nonce, 'ead_reject_event_' . $post_id)) {
                wp_die(__('Security check failed. Please try again.', 'artpulse-management'));
            }

            if (!current_user_can('delete_post', $post_id)) {
                wp_die(__('You do not have permission to reject this event.', 'artpulse-management'));
            }

            $trashed = wp_trash_post($post_id);

            if (is_wp_error($trashed) || !$trashed) {
                wp_redirect(admin_url('admin.php?page=artpulse-pending-events&rejection_error=1&post_id=' . $post_id));
            } else {
                wp_redirect(admin_url('admin.php?page=artpulse-pending-events&event_rejected=1&post_id=' . $post_id));
            }
            exit;
        }
    }

    /**
     * Render the Pending Events page.
     */
    public static function render_admin_page() {
        self::handle_bulk_actions();

        // Display admin notices for actions
        if (isset($_GET['event_approved']) && (int) $_GET['event_approved'] && isset($_GET['post_id'])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(esc_html__('Event "%s" (ID: %d) has been approved.', 'artpulse-management'), get_the_title(intval($_GET['post_id'])), intval($_GET['post_id']))
            );
        }
        if (isset($_GET['approval_error']) && isset($_GET['post_id'])) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                sprintf(esc_html__('Could not approve event (ID: %d).', 'artpulse-management'), intval($_GET['post_id']))
            );
        }
        if (isset($_GET['event_rejected']) && (int) $_GET['event_rejected'] && isset($_GET['post_id'])) {
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                sprintf(esc_html__('Event "%s" (ID: %d) has been rejected and moved to trash.', 'artpulse-management'), get_the_title(intval($_GET['post_id'])), intval($_GET['post_id']))
            );
        }
        if (isset($_GET['rejection_error']) && isset($_GET['post_id'])) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                sprintf(esc_html__('Could not reject event (ID: %d).', 'artpulse-management'), intval($_GET['post_id']))
            );
        }
        if (isset($_GET['bulk_action_status']) && $_GET['bulk_action_status'] === 'none_selected') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('No events were selected for the bulk action.', 'artpulse-management') . '</p></div>';
        }
        if (isset($_GET['approved']) && (int) $_GET['approved']) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(_n('%d event approved.', '%d events approved.', (int)$_GET['approved'], 'artpulse-management'), (int)$_GET['approved']))
            );
        }
        if (isset($_GET['rejected']) && (int) $_GET['rejected']) {
            printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(_n('%d event rejected and moved to trash.', '%d events rejected and moved to trash.', (int)$_GET['rejected'], 'artpulse-management'), (int)$_GET['rejected']))
            );
        }


        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Pending Events', 'artpulse-management') . '</h1>';

        echo '<form method="post" id="ead-pending-events-form">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page'] ?? 'artpulse-pending-events') . '" />';
        wp_nonce_field('ead_pending_events_bulk_action_nonce', 'ead_pending_events_bulk_nonce');

        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions bulkactions">';
        echo '<label for="bulk-action-selector-top" class="screen-reader-text">' . esc_html__('Select bulk action', 'artpulse-management') . '</label>';
        echo '<select name="ead_bulk_action" id="bulk-action-selector-top">';
        echo '<option value="-1">' . esc_html__('Bulk Actions', 'artpulse-management') . '</option>';
        echo '<option value="approve_selected">' . esc_html__('Approve', 'artpulse-management') . '</option>';
        echo '<option value="reject_selected">' . esc_html__('Reject (Move to Trash)', 'artpulse-management') . '</option>';
        echo '</select>';
        echo '<input type="submit" name="ead_do_bulk_action" id="doaction" class="button action" value="' . esc_attr__('Apply', 'artpulse-management') . '">';
        echo '</div>';
        echo '<br class="clear">';
        echo '</div>';

        $args = [
            'post_type'      => 'ead_event',
            'post_status'    => ['draft', 'pending'], // Include draft if admins should see them here
            'posts_per_page' => 20, // Consider pagination for many pending items
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        $pending_events = new \WP_Query($args);

        if ($pending_events->have_posts()) {
            echo '<table class="wp-list-table widefat fixed striped posts">';
            echo '<thead><tr>';
            echo '<td id="cb" class="manage-column column-cb check-column"><input type="checkbox" /></td>';
            echo '<th scope="col" class="manage-column column-thumbnail">' . esc_html__('Thumbnail', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column column-title column-primary">' . esc_html__('Title', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column column-author">' . esc_html__('Author', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column column-date">' . esc_html__('Date Submitted', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column">' . esc_html__('Status', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column">' . esc_html__('Actions', 'artpulse-management') . '</th>';
            echo '</tr></thead>';
            echo '<tbody id="the-list">';

            while ($pending_events->have_posts()) {
                $pending_events->the_post();
                $post_id = get_the_ID();
                $title = get_the_title();
                $date = get_the_date(); // Date submitted
                $status = get_post_status_object(get_post_status($post_id)); // Get status object for label
                $status_label = $status ? $status->label : ucfirst(get_post_status($post_id));
                $author_id = get_post_field('post_author', $post_id);
                $author_name = get_the_author_meta('display_name', $author_id);


                // Review button
                $review_url = get_edit_post_link($post_id);

                // Approve button
                $approve_nonce = wp_create_nonce('ead_approve_event_' . $post_id);
                $approve_url = add_query_arg([
                    'page'    => 'artpulse-pending-events',
                    'action'  => 'approve',
                    'post_id' => $post_id,
                    'nonce'   => $approve_nonce
                ], admin_url('admin.php'));

                $reject_nonce = wp_create_nonce('ead_reject_event_' . $post_id);
                $reject_url   = add_query_arg([
                    'page'    => 'artpulse-pending-events',
                    'action'  => 'reject',
                    'post_id' => $post_id,
                    'nonce'   => $reject_nonce
                ], admin_url('admin.php'));

                echo '<tr id="post-' . esc_attr($post_id) . '">';
                echo '<th scope="row" class="check-column"><input type="checkbox" name="event_ids[]" value="' . esc_attr($post_id) . '"></th>';
                $thumb = get_the_post_thumbnail($post_id, [60, 60]);
                if (!$thumb) {
                    $gallery_ids = ead_get_meta($post_id, 'event_gallery');
                    if (is_array($gallery_ids) && !empty($gallery_ids[0])) {
                        $thumb = wp_get_attachment_image($gallery_ids[0], [60, 60]);
                    }
                }
                echo '<td data-colname="' . esc_attr__('Thumbnail', 'artpulse-management') . '" class="column-thumbnail">' . $thumb . '</td>';
                echo '<td class="title column-title has-row-actions column-primary" data-colname="' . esc_attr__('Title', 'artpulse-management') . '">';
                echo '<strong><a class="row-title" href="' . esc_url($review_url) . '">' . esc_html($title) . '</a></strong>';
                echo '<div class="row-actions">';
                echo '<span class="edit"><a href="' . esc_url($review_url) . '">' . esc_html__('Review/Edit', 'artpulse-management') . '</a> | </span>';
                echo '<span class="approve"><a href="' . esc_url($approve_url) . '" style="color:green;">' . esc_html__('Approve', 'artpulse-management') . '</a> | </span>';
                echo '<span class="reject"><a href="' . esc_url($reject_url) . '" style="color:red;">' . esc_html__('Reject', 'artpulse-management') . '</a></span>';
                echo '</div>';
                echo '</td>';
                echo '<td data-colname="' . esc_attr__('Author', 'artpulse-management') . '">' . esc_html($author_name) . '</td>';
                echo '<td data-colname="' . esc_attr__('Date Submitted', 'artpulse-management') . '">' . esc_html($date) . '</td>';
                echo '<td data-colname="' . esc_attr__('Status', 'artpulse-management') . '">' . esc_html($status_label) . '</td>';
                echo '<td data-colname="' . esc_attr__('Actions', 'artpulse-management') . '">';
                echo '<a href="' . esc_url($review_url) . '" class="button button-small">' . esc_html__('Review/Edit', 'artpulse-management') . '</a> ';
                echo '<a href="' . esc_url($approve_url) . '" class="button button-primary button-small">' . esc_html__('Approve', 'artpulse-management') . '</a> ';
                echo '<a href="' . esc_url($reject_url) . '" class="button button-secondary button-small" style="color:red; border-color:red;">' . esc_html__('Reject', 'artpulse-management') . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '<tfoot><tr>';
            echo '<td class="manage-column column-cb check-column"><input type="checkbox" /></td>';
            echo '<th scope="col" class="manage-column column-thumbnail">' . esc_html__('Thumbnail', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column column-title column-primary">' . esc_html__('Title', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column">' . esc_html__('Author', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column">' . esc_html__('Date Submitted', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column">' . esc_html__('Status', 'artpulse-management') . '</th>';
            echo '<th scope="col" class="manage-column">' . esc_html__('Actions', 'artpulse-management') . '</th>';
            echo '</tr></tfoot></table>';
        } else {
            echo '<p>' . esc_html__('No pending events found.', 'artpulse-management') . '</p>';
        }

        echo '</form>';

        wp_reset_postdata();
        echo '</div>'; // .wrap
    }

    /**
     * Handle bulk actions for pending events.
     */
    private static function handle_bulk_actions() {
        if (isset($_POST['ead_do_bulk_action'])) {
            check_admin_referer('ead_pending_events_bulk_action_nonce', 'ead_pending_events_bulk_nonce');

            $action = isset($_POST['ead_bulk_action']) ? sanitize_text_field($_POST['ead_bulk_action']) : '-1';
            $event_ids = isset($_POST['event_ids']) && is_array($_POST['event_ids']) ? array_map('intval', $_POST['event_ids']) : [];

            if (empty($event_ids)) {
                wp_redirect(admin_url('admin.php?page=' . ($_REQUEST['page'] ?? 'artpulse-pending-events') . '&bulk_action_status=none_selected'));
                exit;
            }

            $approved_count = 0;
            $rejected_count = 0;

            if ($action === 'approve_selected') {
                if (!current_user_can('publish_posts')) {
                    wp_die(__('You do not have permission to approve events.', 'artpulse-management'));
                }
                foreach ($event_ids as $event_id) {
                    if (get_post_type($event_id) === 'ead_event' && get_post_status($event_id) === 'pending') {
                        wp_update_post(['ID' => $event_id, 'post_status' => 'publish']);
                        $approved_count++;
                    }
                }
            } elseif ($action === 'reject_selected') {
                if (!current_user_can('delete_posts')) {
                    wp_die(__('You do not have permission to reject events.', 'artpulse-management'));
                }
                foreach ($event_ids as $event_id) {
                    if (get_post_type($event_id) === 'ead_event') {
                        wp_trash_post($event_id);
                        $rejected_count++;
                    }
                }
            }

            $redirect_args = ['page' => $_REQUEST['page'] ?? 'artpulse-pending-events'];
            if ($approved_count > 0) {
                $redirect_args['approved'] = $approved_count;
            }
            if ($rejected_count > 0) {
                $redirect_args['rejected'] = $rejected_count;
            }

            wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }
    }
}