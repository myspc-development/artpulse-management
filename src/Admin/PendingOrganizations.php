<?php
namespace EAD\Admin;

class PendingOrganizations {

    /**
     * Register hooks and actions.
     */
    public static function register() {
        add_action('admin_action_ead_approve_organization', [self::class, 'handle_approve_organization']);
        add_action('admin_action_ead_reject_organization', [self::class, 'handle_reject_organization']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_pending_orgs_js']);
        add_action('wp_ajax_ead_ajax_approve_org', [self::class, 'ajax_approve_org']);
        add_action('wp_ajax_ead_ajax_reject_org', [self::class, 'ajax_reject_org']);
    }

    /**
     * Only enqueue our admin JS on the pending organizations admin page.
     */
    public static function enqueue_pending_orgs_js($hook) {
        // Update this slug if needed to match your menu (see Menu.php)
        if (
            $hook === 'toplevel_page_artpulse-main-menu' || 
            $hook === 'artpulse_page_artpulse-pending-organizations'
        ) {
            wp_enqueue_script(
                'ead-admin-pending-orgs',
                plugins_url('../../assets/js/ead-admin-pending-orgs.js', __FILE__), // adjust as needed
                ['jquery'],
                defined('EAD_PLUGIN_VERSION') ? EAD_PLUGIN_VERSION : '1.0.0',
                true
            );
            wp_localize_script('ead-admin-pending-orgs', 'EAD_PendingOrgs', [
                'nonce' => wp_create_nonce('ead_ajax_approve_org'),
            ]);
        }
    }

    /**
     * Renders the admin page listing pending organizations.
     */
    public static function render_admin_page() {
        self::handle_bulk_actions();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Pending Organizations', 'artpulse-management') . '</h1>';

        // Admin notices
        if (isset($_GET['approved']) && (int) $_GET['approved']) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(_n('%d organization approved.', '%d organizations approved.', (int)$_GET['approved'], 'artpulse-management'), (int)$_GET['approved']))
            );
        }
        if (isset($_GET['rejected']) && (int) $_GET['rejected']) {
            printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(_n('%d organization rejected and moved to trash.', '%d organizations rejected and moved to trash.', (int)$_GET['rejected'], 'artpulse-management'), (int)$_GET['rejected']))
            );
        }
        if (isset($_GET['bulk_action_status']) && $_GET['bulk_action_status'] === 'none_selected') {
            printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html__('No organizations were selected for the bulk action.', 'artpulse-management'));
        }

        $args = [
            'post_type'      => 'ead_organization',
            'post_status'    => 'pending',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        $pending_orgs_query = new \WP_Query($args);

        ?>
        <form method="post" id="ead-pending-orgs-form">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page'] ?? 'artpulse-pending-organizations'); ?>" />
            <?php wp_nonce_field('ead_pending_orgs_bulk_action_nonce', 'ead_pending_orgs_bulk_nonce'); ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'artpulse-management'); ?></label>
                    <select name="ead_bulk_action" id="bulk-action-selector-top">
                        <option value="-1"><?php esc_html_e('Bulk Actions', 'artpulse-management'); ?></option>
                        <option value="approve_selected"><?php esc_html_e('Approve', 'artpulse-management'); ?></option>
                        <option value="reject_selected"><?php esc_html_e('Reject (Move to Trash)', 'artpulse-management'); ?></option>
                    </select>
                    <input type="submit" name="ead_do_bulk_action" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'artpulse-management'); ?>">
                </div>
                <?php
                $total_pages = $pending_orgs_query->max_num_pages;
                if ($total_pages > 1) {
                    $current_page = max(1, get_query_var('paged'));
                    echo '<div class="tablenav-pages"><span class="displaying-num">' . sprintf(_n('%s item', '%s items', $pending_orgs_query->found_posts, 'artpulse-management'), number_format_i18n($pending_orgs_query->found_posts)) . '</span>';
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    echo '</div>';
                }
                ?>
                <br class="clear">
            </div>

            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" /></td>
                    <th scope="col" class="manage-column column-thumbnail"><?php esc_html_e('Thumbnail', 'artpulse-management'); ?></th>
                    <th scope="col" id="title" class="manage-column column-title column-primary"><?php esc_html_e('Organization Name', 'artpulse-management'); ?></th>
                    <th scope="col" id="author" class="manage-column column-author"><?php esc_html_e('Registered By', 'artpulse-management'); ?></th>
                    <th scope="col" id="date" class="manage-column column-date"><?php esc_html_e('Date Submitted', 'artpulse-management'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Actions', 'artpulse-management'); ?></th>
                </tr>
                </thead>
                <tbody id="the-list">
                <?php if ($pending_orgs_query->have_posts()) : ?>
                    <?php while ($pending_orgs_query->have_posts()) : $pending_orgs_query->the_post();
                        $post_id = get_the_ID();
                        $user_info = get_userdata(get_post_field('post_author', $post_id));
                        ?>
                        <tr id="post-<?php echo esc_attr($post_id); ?>">
                            <th scope="row" class="check-column"><input type="checkbox" name="org_ids[]" value="<?php echo esc_attr($post_id); ?>"></th>
                            <?php
                            $thumb = get_the_post_thumbnail($post_id, [60, 60]);
                            if (!$thumb) {
                                $gallery_ids = ead_get_meta($post_id, 'ead_org_gallery_images');
                                if (is_array($gallery_ids) && !empty($gallery_ids[0])) {
                                    $thumb = wp_get_attachment_image(($gallery_ids[0] ?: 0), 'nectar_thumb');
                                }
                            }
                            ?>
                            <td data-colname="<?php esc_attr_e('Thumbnail', 'artpulse-management'); ?>" class="column-thumbnail"><?php echo $thumb; ?></td>
                            <td class="title column-title has-row-actions column-primary" data-colname="<?php esc_attr_e('Organization Name', 'artpulse-management'); ?>">
                                <strong><a class="row-title" href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php the_title(); ?></a></strong>
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php esc_html_e('Edit', 'artpulse-management'); ?></a> | </span>
                                    <span class="approve"><a href="#" class="ead-org-approve-btn" data-org-id="<?php echo esc_attr($post_id); ?>" style="color:green;"><?php esc_html_e('Approve', 'artpulse-management'); ?></a> | </span>
                                    <span class="reject"><a href="#" class="ead-org-reject-btn" data-org-id="<?php echo esc_attr($post_id); ?>" style="color:red;"><?php esc_html_e('Reject', 'artpulse-management'); ?></a></span>
                                </div>
                            </td>
                            <td data-colname="<?php esc_attr_e('Registered By', 'artpulse-management'); ?>">
                                <?php echo $user_info ? esc_html($user_info->display_name) . ' (<a href="mailto:' . esc_attr($user_info->user_email) . '">' . esc_html($user_info->user_email) . '</a>)' : __('N/A', 'artpulse-management'); ?>
                            </td>
                            <td data-colname="<?php esc_attr_e('Date Submitted', 'artpulse-management'); ?>"><?php echo esc_html(get_the_date()); ?></td>
                            <td data-colname="<?php esc_attr_e('Actions', 'artpulse-management'); ?>">
                                <a href="#" class="button button-primary button-small ead-org-approve-btn" data-org-id="<?php echo esc_attr($post_id); ?>"><?php esc_html_e('Approve', 'artpulse-management'); ?></a>
                                <a href="#" class="button button-secondary button-small ead-org-reject-btn" data-org-id="<?php echo esc_attr($post_id); ?>" style="color:red; border-color:red;"><?php esc_html_e('Reject', 'artpulse-management'); ?></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No pending organizations found.', 'artpulse-management'); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" /></td>
                        <th scope="col" class="manage-column column-thumbnail"><?php esc_html_e('Thumbnail', 'artpulse-management'); ?></th>
                        <th scope="col" class="manage-column column-title column-primary"><?php esc_html_e('Organization Name', 'artpulse-management'); ?></th>
                        <th scope="col" class="manage-column"><?php esc_html_e('Registered By', 'artpulse-management'); ?></th>
                        <th scope="col" class="manage-column"><?php esc_html_e('Date Submitted', 'artpulse-management'); ?></th>
                        <th scope="col" class="manage-column"><?php esc_html_e('Actions', 'artpulse-management'); ?></th>
                    </tr>
                </tfoot>
            </table>
        </form>
        <?php
        wp_reset_postdata();
        echo '</div>'; // .wrap
    }

    /**
     * Handle single organization approval (fallback for non-JS).
     */
    public static function handle_approve_organization() {
        $org_id = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;
        check_admin_referer('ead_approve_organization_' . $org_id);

        if (!current_user_can('publish_posts')) {
            wp_die(__('You do not have permission to approve organizations.', 'artpulse-management'));
        }

        if ($org_id && get_post_type($org_id) === 'ead_organization') {
            wp_update_post(['ID' => $org_id, 'post_status' => 'publish']);
            wp_redirect(admin_url('admin.php?page=' . ($_REQUEST['page'] ?? 'artpulse-pending-organizations') . '&approved=1'));
            exit;
        }
        wp_redirect(admin_url('admin.php?page=' . ($_REQUEST['page'] ?? 'artpulse-pending-organizations')));
        exit;
    }

    /**
     * Handle single organization rejection (move to trash, fallback for non-JS).
     */
    public static function handle_reject_organization() {
        $org_id = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;
        check_admin_referer('ead_reject_organization_' . $org_id);

        if (!current_user_can('delete_posts')) {
            wp_die(__('You do not have permission to reject organizations.', 'artpulse-management'));
        }

        if ($org_id && get_post_type($org_id) === 'ead_organization') {
            wp_trash_post($org_id);
            wp_redirect(admin_url('admin.php?page=' . ($_REQUEST['page'] ?? 'artpulse-pending-organizations') . '&rejected=1'));
            exit;
        }
        wp_redirect(admin_url('admin.php?page=' . ($_REQUEST['page'] ?? 'artpulse-pending-organizations')));
        exit;
    }

    /**
     * AJAX: Approve org.
     */
    public static function ajax_approve_org() {
        check_ajax_referer('ead_ajax_approve_org', 'security');
        if (!current_user_can('publish_posts')) {
            wp_send_json_error('Permission denied.');
        }
        $org_id = intval($_POST['org_id'] ?? 0);
        if ($org_id && get_post_type($org_id) === 'ead_organization') {
            wp_update_post(['ID' => $org_id, 'post_status' => 'publish']);
            wp_send_json_success(true);
        }
        wp_send_json_error('Invalid organization.');
    }

    /**
     * AJAX: Reject org.
     */
    public static function ajax_reject_org() {
        check_ajax_referer('ead_ajax_approve_org', 'security');
        if (!current_user_can('delete_posts')) {
            wp_send_json_error('Permission denied.');
        }
        $org_id = intval($_POST['org_id'] ?? 0);
        if ($org_id && get_post_type($org_id) === 'ead_organization') {
            wp_trash_post($org_id);
            wp_send_json_success(true);
        }
        wp_send_json_error('Invalid organization.');
    }

    /**
     * Handle bulk actions for pending organizations.
     */
    private static function handle_bulk_actions() {
        if (isset($_POST['ead_do_bulk_action'])) {
            check_admin_referer('ead_pending_orgs_bulk_action_nonce', 'ead_pending_orgs_bulk_nonce');

            $action = isset($_POST['ead_bulk_action']) ? sanitize_text_field($_POST['ead_bulk_action']) : '-1';
            $org_ids = isset($_POST['org_ids']) && is_array($_POST['org_ids']) ? array_map('intval', $_POST['org_ids']) : [];

            if (empty($org_ids)) {
                wp_redirect(admin_url('admin.php?page=' . ($_REQUEST['page'] ?? 'artpulse-pending-organizations') . '&bulk_action_status=none_selected'));
                exit;
            }

            $approved_count = 0;
            $rejected_count = 0;

            if ($action === 'approve_selected') {
                if (!current_user_can('publish_posts')) {
                    wp_die(__('You do not have permission to approve organizations.', 'artpulse-management'));
                }
                foreach ($org_ids as $org_id) {
                    if (get_post_type($org_id) === 'ead_organization' && get_post_status($org_id) === 'pending') {
                        wp_update_post(['ID' => $org_id, 'post_status' => 'publish']);
                        $approved_count++;
                    }
                }
            } elseif ($action === 'reject_selected') {
                if (!current_user_can('delete_posts')) {
                    wp_die(__('You do not have permission to reject organizations.', 'artpulse-management'));
                }
                foreach ($org_ids as $org_id) {
                    if (get_post_type($org_id) === 'ead_organization') {
                        wp_trash_post($org_id);
                        $rejected_count++;
                    }
                }
            }

            $redirect_args = ['page' => $_REQUEST['page'] ?? 'artpulse-pending-organizations'];
            if ($approved_count > 0) $redirect_args['approved'] = $approved_count;
            if ($rejected_count > 0) $redirect_args['rejected'] = $rejected_count;

            wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }
    }
}
