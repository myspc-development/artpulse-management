<?php
namespace EAD\Admin;

class ReviewsModerator {
    public static function register() {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_post_ead_bulk_reviews_action', [self::class, 'handle_bulk_action']);
        add_action('admin_post_ead_single_review_action', [self::class, 'handle_single_action']);
        add_action('admin_head', [self::class, 'admin_menu_css']);
    }

    public static function add_menu() {
        $count = wp_count_posts('ead_org_review')->pending ?? 0;
        $menu_label = 'Moderate Reviews' . ($count ? " <span class=\"awaiting-mod count-$count\"><span class=\"pending-count\">$count</span></span>" : '');

        global $submenu;
        if (isset($submenu['artpulse-main-menu'])) {
            foreach ($submenu['artpulse-main-menu'] as &$item) {
                if ($item[2] === 'ead-moderate-reviews') {
                    $item[0] = $menu_label;
                    break;
                }
            }
        }
    }

    public static function admin_menu_css() {
        echo '<style>.awaiting-mod {background:#e43d4f;color:#fff;border-radius:10px;padding:2px 7px 2px 7px;font-size:13px;margin-left:8px;vertical-align:middle;}</style>';
    }

    public static function moderate_reviews_page() {
        if (!current_user_can('edit_posts')) return;

        // Status filter
        $status = isset($_GET['review_status']) ? sanitize_text_field($_GET['review_status']) : 'pending';
        $allowed_statuses = ['pending','publish','all'];
        if (!in_array($status, $allowed_statuses)) $status = 'pending';

        echo '<div class="wrap"><h1>Moderate Organization Reviews</h1>';

        // Filter dropdown
        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="post_type" value="ead_organization">';
        echo '<input type="hidden" name="page" value="ead_moderate_reviews">';
        echo '<select name="review_status">';
        foreach(['pending'=>'Pending','publish'=>'Published','all'=>'All'] as $k=>$label) {
            echo '<option value="'.$k.'"'.selected($status,$k,false).'>'.$label.'</option>';
        }
        echo '</select> <button type="submit" class="button">Filter</button></form>';

        // Query reviews
        $query_args = [
            'post_type' => 'ead_org_review',
            'numberposts' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
        ];
        if ($status !== 'all') $query_args['post_status'] = $status;
        $reviews = get_posts($query_args);

        // Bulk form
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('ead_bulk_reviews_action');
        echo '<input type="hidden" name="action" value="ead_bulk_reviews_action">';
        echo '<table class="widefat fixed striped"><thead><tr>
            <th style="width:30px;"><input type="checkbox" id="ead-select-all"></th>
            <th>Reviewer</th>
            <th>Rating</th>
            <th>Comment</th>
            <th>Org</th>
            <th>Date</th>
            <th>Action</th>
            </tr></thead><tbody>';
        foreach ($reviews as $review):
            $org_id = get_post_meta($review->ID, 'organization_id', true);
            $reviewer = get_post_meta($review->ID, 'reviewer_name', true);
            $rating = get_post_meta($review->ID, 'review_rating', true);
            echo '<tr>';
            echo '<td><input type="checkbox" name="review_ids[]" value="'.esc_attr($review->ID).'"></td>';
            echo '<td>' . esc_html($reviewer) . '</td>';
            echo '<td>' . esc_html($rating) . '</td>';
            echo '<td>' . esc_html($review->post_content) . '</td>';
            echo '<td>';
            if ($org_id) {
                $org = get_post($org_id);
                if ($org) echo '<a href="' . get_edit_post_link($org_id) . '">' . esc_html($org->post_title) . '</a>';
            }
            echo '</td>';
            echo '<td>' . esc_html(get_the_date('', $review)) . '</td>';
            // Single review quick action
            echo '<td>
            <form method="post" action="' . admin_url('admin-post.php') . '" style="display:inline;">
                '.wp_nonce_field('ead_single_review_action_'.$review->ID, '_wpnonce', true, false).'
                <input type="hidden" name="action" value="ead_single_review_action">
                <input type="hidden" name="review_id" value="' . esc_attr($review->ID) . '">
                <button name="single_action" value="approve" class="button" title="Approve">&#10003;</button>
                <button name="single_action" value="trash" class="button" title="Trash" style="color:#a00;">&#10005;</button>
            </form>
            </td>';
            echo '</tr>';
        endforeach;
        echo '</tbody></table>
        <p style="margin-top:10px;">
            <select name="bulk_action" required>
                <option value="">Bulk Actions</option>
                <option value="approve">Approve</option>
                <option value="trash">Reject (Trash)</option>
            </select>
            <button type="submit" class="button button-primary">Apply</button>
        </p>
        </form>
        <script>
        document.getElementById("ead-select-all").addEventListener("click",function(){
            document.querySelectorAll(\'input[name="review_ids[]"]\').forEach(cb => cb.checked = this.checked);
        });
        </script></div>';
    }

    public static function handle_bulk_action() {
        if (!current_user_can('edit_posts')) wp_die('Nope');
        check_admin_referer('ead_bulk_reviews_action');
        $ids = array_map('intval', $_POST['review_ids'] ?? []);
        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        foreach ($ids as $id) {
            if ($action === 'approve') {
                wp_update_post(['ID'=>$id, 'post_status'=>'publish']);
                self::notify_reviewer($id);
            }
            elseif ($action === 'trash') {
                wp_trash_post($id);
            }
        }
        wp_redirect(admin_url('edit.php?post_type=ead_organization&page=ead_moderate_reviews'));
        exit;
    }

    public static function handle_single_action() {
        $id = intval($_POST['review_id'] ?? 0);
        $action = $_POST['single_action'] ?? '';
        check_admin_referer('ead_single_review_action_' . $id);
        if (!current_user_can('edit_posts')) wp_die('Nope');
        if ($id) {
            if ($action === 'approve') {
                wp_update_post(['ID'=>$id, 'post_status'=>'publish']);
                self::notify_reviewer($id);
            }
            elseif ($action === 'trash') {
                wp_trash_post($id);
            }
        }
        wp_redirect(admin_url('edit.php?post_type=ead_organization&page=ead_moderate_reviews'));
        exit;
    }

    /**
     * Notify reviewer by email if review is approved and email exists
     */
    public static function notify_reviewer($id) {
        $email = get_post_meta($id, 'reviewer_email', true);
        if ($email) {
            $org_id = get_post_meta($id, 'organization_id', true);
            $org_title = get_the_title($org_id);
            $subject = "Your review for $org_title is now live!";
            $message = "Thank you for reviewing $org_title. Your review has been approved and is now visible on the site.\n\nView organization: " . get_permalink($org_id);
            wp_mail($email, $subject, $message);
        }
    }
}
