<?php

namespace ArtPulse\Admin;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\Capabilities;
use ArtPulse\Core\RoleUpgradeManager;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\MemberDashboard;
use WP_Post;
use WP_User;
use function esc_url_raw;
use function ArtPulse\Core\add_query_args;
use function ArtPulse\Core\get_missing_page_fallback;
use function ArtPulse\Core\get_page_url;

class UpgradeReviewsController
{
    private const CAPABILITY_VIEW = Capabilities::CAP_REVIEW_VIEW;
    private const CAPABILITY_MANAGE = Capabilities::CAP_REVIEW_MANAGE;

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_post_ap_upgrade_review_action', [self::class, 'handle_action']);
        add_filter('post_row_actions', [self::class, 'filter_row_actions'], 10, 2);
    }

    public static function add_menu(): void
    {
        add_submenu_page(
            'artpulse-settings',
            __('Upgrade Reviews', 'artpulse-management'),
            __('Upgrade Reviews', 'artpulse-management'),
            self::CAPABILITY_VIEW,
            'artpulse-upgrade-reviews',
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        $user = wp_get_current_user();

        if (!$user instanceof WP_User || !user_can($user, self::CAPABILITY_VIEW)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'artpulse-management'));
        }

        if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['filter_action'])) {
            self::redirect_with_filters();
        }

        self::maybe_process_bulk_action($user);

        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        $review_id = isset($_GET['review']) ? absint($_GET['review']) : 0;
        $bulk_reviews_raw = isset($_GET['reviews']) ? sanitize_text_field(wp_unslash($_GET['reviews'])) : '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Upgrade Reviews', 'artpulse-management') . '</h1>';

        if ('bulk-confirm' === $view) {
            $operation = isset($_GET['operation']) ? sanitize_key(wp_unslash($_GET['operation'])) : '';
            $review_ids = self::normalise_review_ids(explode(',', $bulk_reviews_raw));
            $bulk_nonce = isset($_GET['bulk_nonce']) ? sanitize_text_field(wp_unslash($_GET['bulk_nonce'])) : '';

            if (!in_array($operation, ['approve', 'deny'], true) || empty($review_ids) || '' === $bulk_nonce || !wp_verify_nonce($bulk_nonce, self::build_bulk_nonce_action($review_ids))) {
                echo '<p>' . esc_html__('The selected reviews could not be loaded or the request has expired.', 'artpulse-management') . '</p>';
                echo '</div>';
                return;
            }

            $reviews = self::get_reviews($review_ids);

            if (empty($reviews)) {
                echo '<p>' . esc_html__('No matching upgrade reviews were found.', 'artpulse-management') . '</p>';
                echo '</div>';
                return;
            }

            $heading = 'approve' === $operation
                ? esc_html__('Approve selected upgrade requests?', 'artpulse-management')
                : esc_html__('Deny selected upgrade requests?', 'artpulse-management');
            $description = 'approve' === $operation
                ? esc_html__('Please confirm you want to approve the following upgrade requests.', 'artpulse-management')
                : esc_html__('Please confirm you want to deny the following upgrade requests.', 'artpulse-management');

            echo '<h2>' . $heading . '</h2>';
            echo '<p>' . $description . '</p>';
            echo '<ul class="ap-upgrade-review-deny__list">';
            foreach ($reviews as $review_post) {
                $user_id = UpgradeReviewRepository::get_user_id($review_post);
                $user = $user_id ? get_user_by('id', $user_id) : null;
                $label = $user ? $user->display_name : '#' . $user_id;
                echo '<li><strong>' . esc_html($label) . '</strong></li>';
            }
            echo '</ul>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ap-upgrade-review-bulk-confirm">';
            wp_nonce_field(self::build_bulk_nonce_action($review_ids), 'bulk_nonce');
            echo '<input type="hidden" name="action" value="ap_upgrade_review_action" />';
            echo '<input type="hidden" name="operation" value="' . esc_attr($operation) . '" />';
            echo '<input type="hidden" name="bulk" value="1" />';

            foreach ($review_ids as $id) {
                echo '<input type="hidden" name="review[]" value="' . esc_attr($id) . '" />';
            }

            foreach (['status', 'ap_filter_type', 'ap_filter_status'] as $persist_key) {
                if (!isset($_GET[$persist_key])) {
                    continue;
                }

                $value = sanitize_text_field(wp_unslash($_GET[$persist_key]));
                if ('' !== $value) {
                    echo '<input type="hidden" name="' . esc_attr($persist_key) . '" value="' . esc_attr($value) . '" />';
                }
            }

            if ('deny' === $operation) {
                echo '<p><label for="ap-bulk-reason">' . esc_html__('Reason for denial (optional)', 'artpulse-management') . '</label></p>';
                echo '<textarea id="ap-bulk-reason" name="reason" rows="4" class="large-text"></textarea>';
            }

            $button_label = 'approve' === $operation
                ? esc_html__('Confirm approvals', 'artpulse-management')
                : esc_html__('Confirm denials', 'artpulse-management');
            submit_button($button_label);

            $cancel_url = esc_url(self::build_redirect_url());
            echo '<a class="button-link" href="' . $cancel_url . '">' . esc_html__('Cancel', 'artpulse-management') . '</a>';

            echo '</form>';
            echo '</div>';
            return;
        }

        if ('deny' === $view && $review_id) {
            $review_ids = [$review_id];
            check_admin_referer('ap-upgrade-review-' . $review_id);

            $reviews = self::get_reviews($review_ids);

            if (empty($reviews)) {
                echo '<p>' . esc_html__('Review not found.', 'artpulse-management') . '</p>';
            } else {
                $review_post = array_shift($reviews);
                $user_id = $review_post instanceof WP_Post ? UpgradeReviewRepository::get_user_id($review_post) : 0;
                $user = $user_id ? get_user_by('id', $user_id) : null;

                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ap-upgrade-review-deny">';
                wp_nonce_field('ap-upgrade-review-' . $review_id);
                echo '<input type="hidden" name="review" value="' . esc_attr($review_id) . '" />';
                echo '<input type="hidden" name="action" value="ap_upgrade_review_action" />';
                echo '<input type="hidden" name="operation" value="deny" />';
                foreach (['status', 'ap_filter_type', 'ap_filter_status'] as $persist_key) {
                    if (!isset($_GET[$persist_key])) {
                        continue;
                    }

                    $value = sanitize_text_field(wp_unslash($_GET[$persist_key]));
                    if ('' !== $value) {
                        echo '<input type="hidden" name="' . esc_attr($persist_key) . '" value="' . esc_attr($value) . '" />';
                    }
                }
                echo '<p>' . esc_html__('Deny upgrade request for', 'artpulse-management') . ' <strong>' . esc_html($user ? $user->display_name : '#' . $user_id) . '</strong></p>';
                echo '<p><label for="ap-deny-reason">' . esc_html__('Reason for denial (optional)', 'artpulse-management') . '</label></p>';
                echo '<textarea id="ap-deny-reason" name="reason" rows="4" class="large-text"></textarea>';
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
            } elseif ('bulk_approved' === $status) {
                $message = esc_html__('Selected upgrade requests approved.', 'artpulse-management');
            } elseif ('bulk_denied' === $status) {
                $message = esc_html__('Selected upgrade requests denied.', 'artpulse-management');
            } elseif ('error' === $status) {
                $message = esc_html__('Unable to process the action. Please try again.', 'artpulse-management');
            }

            if ($message !== '') {
                printf('<div class="notice notice-info"><p>%s</p></div>', esc_html($message));
            }
        }

        echo '<form method="post">';
        echo '<input type="hidden" name="page" value="artpulse-upgrade-reviews" />';
        $current_status = $list_table->get_current_view();
        if ('' !== $current_status) {
            echo '<input type="hidden" name="status" value="' . esc_attr($current_status) . '" />';
        }
        $list_table->display();
        wp_nonce_field('ap-upgrade-review-bulk', 'ap_bulk_nonce');
        echo '</form>';
        echo '</div>';
    }

    public static function handle_action(): void
    {
        $user = wp_get_current_user();

        if (!$user instanceof WP_User || !user_can($user, self::CAPABILITY_MANAGE)) {
            wp_die(esc_html__('Insufficient permissions.', 'artpulse-management'));
        }

        $review_ids = [];
        if (isset($_REQUEST['review'])) {
            $review_ids = self::normalise_review_ids((array) $_REQUEST['review']);
        }
        $operation = isset($_REQUEST['operation']) ? sanitize_key($_REQUEST['operation']) : '';
        $redirect = self::build_redirect_url();

        if (empty($review_ids) || !in_array($operation, ['approve', 'deny'], true)) {
            wp_safe_redirect(add_query_arg('ap_status', 'error', $redirect));
            exit;
        }

        $is_bulk = count($review_ids) > 1 || isset($_REQUEST['bulk']);

        if ($is_bulk) {
            check_admin_referer(self::build_bulk_nonce_action($review_ids), 'bulk_nonce');
        } else {
            $review_id = $review_ids[0];
            check_admin_referer('ap-upgrade-review-' . $review_id);
        }

        if ('approve' === $operation) {
            $result = self::process_reviews($review_ids, 'approve');
            $status = $result['all_success'] ? ($is_bulk ? 'bulk_approved' : 'approved') : 'error';
        } else {
            $reason_raw = isset($_POST['reason']) ? wp_unslash($_POST['reason']) : '';
            $result = self::process_reviews($review_ids, 'deny', $reason_raw);
            $status = $result['all_success'] ? ($is_bulk ? 'bulk_denied' : 'denied') : 'error';
        }

        wp_safe_redirect(add_query_arg('ap_status', $status, $redirect));
        exit;
    }

    private static function build_dashboard_url(string $role): string
    {
        $base = get_page_url('dashboard_page_id');

        if (!$base) {
            $base = get_missing_page_fallback('dashboard_page_id');
        }

        $url = add_query_args($base, ['role' => $role]);

        return esc_url_raw($url);
    }

    public static function filter_row_actions(array $actions, $post): array
    {
        if (!$post instanceof WP_Post || UpgradeReviewRepository::POST_TYPE !== $post->post_type) {
            return $actions;
        }

        if (!current_user_can(self::CAPABILITY_MANAGE)) {
            return $actions;
        }

        $review_id = (int) $post->ID;
        $custom_actions = [];

        $approve_url = wp_nonce_url(
            add_query_arg(
                [
                    'action'    => 'ap_upgrade_review_action',
                    'review'    => $review_id,
                    'operation' => 'approve',
                ],
                admin_url('admin-post.php')
            ),
            'ap-upgrade-review-' . $review_id
        );

        $custom_actions['ap_review_approve'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($approve_url),
            esc_html__('Quick approve', 'artpulse-management')
        );

        $deny_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'   => 'artpulse-upgrade-reviews',
                    'view'   => 'deny',
                    'review' => $review_id,
                ],
                admin_url('admin.php')
            ),
            'ap-upgrade-review-' . $review_id
        );

        $custom_actions['ap_review_deny'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($deny_url),
            esc_html__('Deny', 'artpulse-management')
        );

        $user_id = UpgradeReviewRepository::get_user_id($post);
        $user_link = $user_id > 0 ? get_edit_user_link($user_id) : '';
        if ($user_link) {
            $custom_actions['ap_review_user'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($user_link),
                esc_html__('View user', 'artpulse-management')
            );
        }

        $target_post_id = UpgradeReviewRepository::get_post_id($post);
        $target_link = $target_post_id > 0 ? get_permalink($target_post_id) : '';
        if ($target_link) {
            $custom_actions['ap_review_post'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($target_link),
                esc_html__('Open profile', 'artpulse-management')
            );
        }

        return array_merge($custom_actions, $actions);
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
            'dashboard_url' => self::build_dashboard_url('organization'),
            'org_id'        => $org_id,
            'role_label'    => __('Organization', 'artpulse-management'),
        ];
        $audit_action    = 'org.upgrade.approved';
        $approved_post_id = $org_id;

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
            $artist_post_id = RoleUpgradeManager::grant_role_if_missing($user_id, 'artist', [
                'source'    => 'upgrade_review',
                'review_id' => $review->ID,
            ]);

            $email_context = [
                'dashboard_url' => self::build_dashboard_url('artist'),
                'role_label'    => __('Artist', 'artpulse-management'),
            ];
            $audit_action = 'artist.upgrade.approved';

            if ($artist_post_id) {
                $approved_post_id = $artist_post_id;
                update_post_meta($review->ID, UpgradeReviewRepository::META_POST, $artist_post_id);
                $email_context['post_id']     = $artist_post_id;
                $email_context['builder_url'] = esc_url_raw(home_url(add_query_arg([
                    'ap_builder' => 'artist',
                    'post_id'    => $artist_post_id,
                ], '/')));
            } else {
                $approved_post_id = 0;
            }
        }

        UpgradeReviewRepository::set_status($review->ID, UpgradeReviewRepository::STATUS_APPROVED);

        if ($user instanceof WP_User) {
            MemberDashboard::send_member_email('upgrade_approved', $user, $email_context);
        }

        AuditLogger::info($audit_action, [
            'user_id'   => $user_id,
            'post_id'   => $approved_post_id,
            'review_id' => $review->ID,
            'type'      => $type,
            'action'    => 'approved',
        ]);

        return true;
    }

    private static function deny(WP_Post $review, string $reason): bool
    {
        $sanitized_reason = trim(sanitize_textarea_field($reason));

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
        } elseif (UpgradeReviewRepository::TYPE_ARTIST_UPGRADE === $type) {
            $artist_draft_id = (int) get_post_meta($review->ID, '_ap_placeholder_artist_id', true);
            if ($artist_draft_id > 0) {
                wp_trash_post($artist_draft_id);
                delete_post_meta($artist_draft_id, '_ap_owner_user');
                delete_post_meta($review->ID, '_ap_placeholder_artist_id');

                AuditLogger::info('upgrade.deny.cleanup', [
                    'review_id'       => (int) $review->ID,
                    'artist_draft_id' => $artist_draft_id,
                    'user_id'         => (int) $user_id,
                ]);
            }
        }

        $user = get_user_by('id', $user_id);
        if ($user instanceof WP_User) {
            $role_label = __('Organization', 'artpulse-management');
            $dashboard_url = self::build_dashboard_url('organization');

            if (UpgradeReviewRepository::TYPE_ARTIST_UPGRADE === $type) {
                $role_label = __('Artist', 'artpulse-management');
                $dashboard_url = self::build_dashboard_url('artist');
            }

            MemberDashboard::send_member_email('upgrade_denied', $user, [
                'reason'        => $sanitized_reason,
                'org_id'        => $org_id,
                'role_label'    => $role_label,
                'dashboard_url' => $dashboard_url,
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

    private static function process_reviews(array $review_ids, string $operation, string $reason = ''): array
    {
        $all_success = true;
        $processed = 0;

        foreach ($review_ids as $review_id) {
            $post = get_post($review_id);
            if (!$post instanceof WP_Post || $post->post_type !== UpgradeReviewRepository::POST_TYPE) {
                $all_success = false;
                continue;
            }

            if ('approve' === $operation) {
                $result = self::approve($post);
            } else {
                $result = self::deny($post, $reason);
            }

            if ($result) {
                $processed++;
            } else {
                $all_success = false;
            }
        }

        if ($processed === 0) {
            $all_success = false;
        }

        return [
            'all_success' => $all_success && $processed === count($review_ids),
            'processed'   => $processed,
        ];
    }

    private static function maybe_process_bulk_action(WP_User $user): void
    {
        if ('POST' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        if (!isset($_POST['ap_bulk_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ap_bulk_nonce'])), 'ap-upgrade-review-bulk')) {
            return;
        }

        if (!user_can($user, self::CAPABILITY_MANAGE)) {
            return;
        }

        $operation = self::get_bulk_operation_from_request();

        if (!in_array($operation, ['approve', 'deny'], true)) {
            return;
        }

        $review_ids = [];
        if (isset($_POST['review'])) {
            $review_ids = self::normalise_review_ids((array) $_POST['review']);
        }

        if (empty($review_ids)) {
            wp_safe_redirect(add_query_arg('ap_status', 'error', self::build_redirect_url()));
            exit;
        }

        $action = self::build_bulk_nonce_action($review_ids);
        $redirect = self::build_redirect_url([
            'view'      => 'bulk-confirm',
            'operation' => $operation,
            'reviews'   => implode(',', $review_ids),
        ]);

        $redirect = wp_nonce_url($redirect, $action, 'bulk_nonce');

        wp_safe_redirect($redirect);
        exit;
    }

    private static function get_bulk_operation_from_request(): string
    {
        foreach (['action', 'action2'] as $key) {
            if (!isset($_POST[$key])) {
                continue;
            }

            $value = wp_unslash($_POST[$key]);

            if ('' === $value || '-1' === $value || 'bulk' === $value) {
                continue;
            }

            return sanitize_key($value);
        }

        return '';
    }

    private static function normalise_review_ids(array $ids): array
    {
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    private static function build_bulk_nonce_action(array $review_ids): string
    {
        return 'ap-deny-bulk-' . implode(',', $review_ids);
    }

    /**
     * @param int[] $review_ids
     *
     * @return WP_Post[]
     */
    private static function get_reviews(array $review_ids): array
    {
        $reviews = [];

        foreach ($review_ids as $id) {
            $post = get_post($id);
            if ($post instanceof WP_Post && $post->post_type === UpgradeReviewRepository::POST_TYPE) {
                $reviews[] = $post;
            }
        }

        return $reviews;
    }

    private static function redirect_with_filters(): void
    {
        $args = ['page' => 'artpulse-upgrade-reviews'];

        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        if ('' !== $status) {
            $args['status'] = $status;
        }

        $filter_type = isset($_POST['ap_filter_type']) ? sanitize_key(wp_unslash($_POST['ap_filter_type'])) : '';
        if ('' !== $filter_type) {
            $args['ap_filter_type'] = $filter_type;
        }

        $filter_status = isset($_POST['ap_filter_status']) ? sanitize_key(wp_unslash($_POST['ap_filter_status'])) : '';
        if ('' !== $filter_status) {
            $args['ap_filter_status'] = $filter_status;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private static function build_redirect_url(array $additional = []): string
    {
        $args = ['page' => 'artpulse-upgrade-reviews'];

        $persisted = ['status', 'ap_filter_type', 'ap_filter_status'];
        foreach ($persisted as $key) {
            if (!isset($_REQUEST[$key])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash($_REQUEST[$key]));
            if ('' !== $value) {
                $args[$key] = $value;
            }
        }

        $args = array_merge($args, $additional);

        return add_query_arg($args, admin_url('admin.php'));
    }
}
