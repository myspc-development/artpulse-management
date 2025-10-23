<?php

namespace ArtPulse\Admin;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\RoleUpgradeManager;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\MemberDashboard;
use WP_Post;
use WP_User;

class UpgradeReviewsController
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_post_ap_upgrade_review_action', [self::class, 'handle_action']);
    }

    public static function add_menu(): void
    {
        add_submenu_page(
            'artpulse-settings',
            __('Upgrade Reviews', 'artpulse-management'),
            __('Upgrade Reviews', 'artpulse-management'),
            'manage_options',
            'artpulse-upgrade-reviews',
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'artpulse-management'));
        }

        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        $review_id = isset($_GET['review']) ? absint($_GET['review']) : 0;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Organization Upgrade Reviews', 'artpulse-management') . '</h1>';

        if ('deny' === $view && $review_id) {
            check_admin_referer('ap-upgrade-review-' . $review_id);
            $post = get_post($review_id);
            if (!$post instanceof WP_Post) {
                echo '<p>' . esc_html__('Review not found.', 'artpulse-management') . '</p>';
            } else {
                $user_id = UpgradeReviewRepository::get_user_id($post);
                $user = $user_id ? get_user_by('id', $user_id) : null;

                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ap-upgrade-review-deny">';
                wp_nonce_field('ap-upgrade-review-' . $review_id);
                echo '<input type="hidden" name="action" value="ap_upgrade_review_action" />';
                echo '<input type="hidden" name="review" value="' . esc_attr($review_id) . '" />';
                echo '<input type="hidden" name="operation" value="deny" />';
                echo '<p>' . esc_html__('Deny upgrade request for', 'artpulse-management') . ' <strong>' . esc_html($user ? $user->display_name : '#' . $user_id) . '</strong></p>';
                echo '<p><label for="ap-deny-reason">' . esc_html__('Reason for denial', 'artpulse-management') . '</label></p>';
                echo '<textarea id="ap-deny-reason" name="reason" rows="4" class="large-text" required></textarea>';
                submit_button(__('Send denial', 'artpulse-management'));
                echo '</form>';
            }
            echo '</div>';
            return;
        }

        $list_table = new UpgradeReviewsTable();
        $list_table->prepare_items();

        if (!empty($_GET['ap_status'])) {
            $status = sanitize_text_field(wp_unslash($_GET['ap_status']));
            $message = '';
            if ('approved' === $status) {
                $message = esc_html__('Upgrade request approved.', 'artpulse-management');
            } elseif ('denied' === $status) {
                $message = esc_html__('Upgrade request denied.', 'artpulse-management');
            } elseif ('error' === $status) {
                $message = esc_html__('Unable to process the action. Please try again.', 'artpulse-management');
            }

            if ($message !== '') {
                printf('<div class="notice notice-info"><p>%s</p></div>', esc_html($message));
            }
        }

        echo '<form method="post">';
        $list_table->display();
        echo '</form>';
        echo '</div>';
    }

    public static function handle_action(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'artpulse-management'));
        }

        $review_id = isset($_REQUEST['review']) ? absint($_REQUEST['review']) : 0;
        $operation = isset($_REQUEST['operation']) ? sanitize_key($_REQUEST['operation']) : '';

        check_admin_referer('ap-upgrade-review-' . $review_id);

        $redirect = add_query_arg('page', 'artpulse-upgrade-reviews', admin_url('admin.php'));

        if (!$review_id || !in_array($operation, ['approve', 'deny'], true)) {
            wp_safe_redirect(add_query_arg('ap_status', 'error', $redirect));
            exit;
        }

        $post = get_post($review_id);
        if (!$post instanceof WP_Post || $post->post_type !== UpgradeReviewRepository::POST_TYPE) {
            wp_safe_redirect(add_query_arg('ap_status', 'error', $redirect));
            exit;
        }

        if ('approve' === $operation) {
            $result = self::approve($post);
            $status = $result ? 'approved' : 'error';
        } else {
            $reason_raw = isset($_POST['reason']) ? wp_unslash($_POST['reason']) : '';
            $result = self::deny($post, $reason_raw);
            $status = $result ? 'denied' : 'error';
        }

        wp_safe_redirect(add_query_arg('ap_status', $status, $redirect));
        exit;
    }

    private static function approve(WP_Post $review): bool
    {
        $user_id = UpgradeReviewRepository::get_user_id($review);
        $org_id  = UpgradeReviewRepository::get_post_id($review);
        $type    = UpgradeReviewRepository::get_type($review);

        if ($user_id <= 0) {
            return false;
        }

        $target_role    = UpgradeReviewRepository::TYPE_ORG_UPGRADE === $type ? 'organization' : 'artist';
        $current_status = UpgradeReviewRepository::get_status($review);
        $user           = get_user_by('id', $user_id);

        $already_upgraded = false;

        if ($user instanceof WP_User) {
            $already_upgraded = in_array($target_role, (array) $user->roles, true);
        }

        if (UpgradeReviewRepository::TYPE_ORG_UPGRADE === $type && $org_id > 0) {
            $current_owner = (int) get_post_meta($org_id, '_ap_owner_user', true);
            if ($current_owner === $user_id) {
                $already_upgraded = true;
            }
        }

        $already_resolved = UpgradeReviewRepository::STATUS_PENDING !== $current_status;

        if ($already_upgraded || $already_resolved) {
            AuditLogger::info('upgrade.approve.noop', [
                'review_id' => (int) $review->ID,
                'user_id'   => (int) $user_id,
                'role'      => $target_role,
            ]);

            return true;
        }

        $email_context = [
            'dashboard_url' => add_query_arg('role', 'organization', home_url('/dashboard/')),
            'org_id'        => $org_id,
        ];
        $audit_action  = 'org.upgrade.approved';

        if (UpgradeReviewRepository::TYPE_ORG_UPGRADE === $type) {
            if ($org_id <= 0) {
                return false;
            }

            $org = get_post($org_id);
            if (!$org instanceof WP_Post) {
                return false;
            }

            RoleUpgradeManager::attach_owner($org_id, $user_id);

            wp_update_post([
                'ID'          => $org_id,
                'post_status' => 'publish',
            ]);

            RoleUpgradeManager::grant_role_if_missing($user_id, 'organization', [
                'source'    => 'upgrade_review',
                'post_id'   => $org_id,
                'review_id' => $review->ID,
            ]);
        } else {
            RoleUpgradeManager::grant_role_if_missing($user_id, 'artist', [
                'source'    => 'upgrade_review',
                'review_id' => $review->ID,
            ]);

            $email_context = [
                'dashboard_url' => add_query_arg('role', 'artist', home_url('/dashboard/')),
            ];
            $audit_action = 'artist.upgrade.approved';
        }

        UpgradeReviewRepository::set_status($review->ID, UpgradeReviewRepository::STATUS_APPROVED);

        if ($user instanceof WP_User) {
            MemberDashboard::send_member_email('upgrade_approved', $user, $email_context);
        }

        AuditLogger::info($audit_action, [
            'user_id'   => $user_id,
            'post_id'   => $org_id,
            'review_id' => $review->ID,
            'type'      => $type,
            'action'    => 'approved',
        ]);

        return true;
    }

    private static function deny(WP_Post $review, string $reason): bool
    {
        $sanitized_reason = trim(sanitize_textarea_field($reason));

        if ($sanitized_reason === '') {
            return false;
        }

        $user_id = UpgradeReviewRepository::get_user_id($review);
        $org_id  = UpgradeReviewRepository::get_post_id($review);
        $type    = UpgradeReviewRepository::get_type($review);

        UpgradeReviewRepository::set_status($review->ID, UpgradeReviewRepository::STATUS_DENIED, $sanitized_reason);

        if (UpgradeReviewRepository::TYPE_ORG_UPGRADE === $type) {
            $org_draft_id = (int) get_post_meta($review->ID, '_ap_placeholder_org_id', true);
            if ($org_draft_id > 0) {
                wp_trash_post($org_draft_id);
                delete_post_meta($org_draft_id, '_ap_owner_user');
                delete_post_meta($review->ID, '_ap_placeholder_org_id');

                AuditLogger::info('upgrade.deny.cleanup', [
                    'review_id'    => (int) $review->ID,
                    'org_draft_id' => $org_draft_id,
                    'user_id'      => (int) $user_id,
                ]);
            }
        }

        $user = get_user_by('id', $user_id);
        if ($user instanceof WP_User) {
            MemberDashboard::send_member_email('upgrade_denied', $user, [
                'reason' => $sanitized_reason,
                'org_id' => $org_id,
            ]);
        }

        $audit_action = UpgradeReviewRepository::TYPE_ORG_UPGRADE === $type
            ? 'org.upgrade.denied'
            : 'artist.upgrade.denied';

        AuditLogger::info($audit_action, [
            'user_id'   => $user_id,
            'post_id'   => $org_id,
            'review_id' => $review->ID,
            'reason'    => $sanitized_reason,
            'action'    => 'denied',
            'type'      => $type,
        ]);

        return true;
    }
}
