<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\RoleUpgradeManager;
use ArtPulse\Core\UpgradeReviewRepository;
use WP_Post;
use WP_User;

class MemberDashboard
{
    public static function register(): void
    {
        add_action('init', [self::class, 'register_actions']);
        add_filter('artpulse/dashboard/data', [self::class, 'inject_dashboard_card'], 10, 3);
    }

    public static function register_actions(): void
    {
        add_action('admin_post_ap_org_upgrade_request', [self::class, 'handle_upgrade_request']);
        add_action('admin_post_ap_org_upgrade_resubmit', [self::class, 'handle_upgrade_request']);
    }

    public static function inject_dashboard_card(array $data, string $role, int $user_id): array
    {
        if ('member' !== $role || $user_id <= 0) {
            return $data;
        }

        $data['org_upgrade'] = self::get_upgrade_state($user_id);

        return $data;
    }

    private static function get_upgrade_state(int $user_id): array
    {
        $state = [
            'status' => '',
            'org_id' => 0,
        ];

        if (!is_user_logged_in()) {
            return $state;
        }

        $user = get_user_by('id', $user_id);
        if ($user instanceof WP_User && in_array('organization', (array) $user->roles, true)) {
            $state['status'] = 'approved';
            $state['org_url'] = esc_url_raw(add_query_arg('role', 'organization', home_url('/dashboard/')));
            return $state;
        }

        $request = UpgradeReviewRepository::get_latest_for_user($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);
        if (!$request instanceof WP_Post) {
            return $state;
        }

        $status = UpgradeReviewRepository::get_status($request);
        $org_id = UpgradeReviewRepository::get_post_id($request);

        $state['status'] = $status;
        $state['org_id'] = $org_id;
        $state['request_id'] = (int) $request->ID;
        $state['reason'] = UpgradeReviewRepository::get_reason($request);

        if ($org_id > 0) {
            $state['org_url'] = get_permalink($org_id);
        }

        return $state;
    }

    public static function handle_upgrade_request(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/login/'));
            exit;
        }

        check_admin_referer('ap-member-upgrade-request');

        $user_id = get_current_user_id();
        $user    = get_user_by('id', $user_id);

        if (!$user instanceof WP_User) {
            wp_safe_redirect(wp_get_referer() ?: home_url('/dashboard/'));
            exit;
        }

        if (in_array('organization', (array) $user->roles, true)) {
            wp_safe_redirect(add_query_arg('ap_org_upgrade', 'exists', wp_get_referer() ?: home_url('/dashboard/')));
            exit;
        }

        $existing = UpgradeReviewRepository::get_latest_for_user($user_id);
        if ($existing instanceof WP_Post && 'pending' === UpgradeReviewRepository::get_status($existing)) {
            wp_safe_redirect(add_query_arg('ap_org_upgrade', 'pending', wp_get_referer() ?: home_url('/dashboard/')));
            exit;
        }

        $org_id = self::create_placeholder_org($user_id, $user);

        if (!$org_id) {
            wp_safe_redirect(add_query_arg('ap_org_upgrade', 'failed', wp_get_referer() ?: home_url('/dashboard/')));
            exit;
        }

        $request_id = UpgradeReviewRepository::create_org_upgrade($user_id, $org_id);

        if (!$request_id) {
            wp_delete_post($org_id, true);
            wp_safe_redirect(add_query_arg('ap_org_upgrade', 'failed', wp_get_referer() ?: home_url('/dashboard/')));
            exit;
        }

        AuditLogger::info('org.upgrade.requested', [
            'user_id'   => $user_id,
            'post_id'   => $org_id,
            'request_id'=> $request_id,
        ]);

        self::send_member_email('upgrade_requested', $user, [
            'org_id' => $org_id,
        ]);

        wp_safe_redirect(add_query_arg('ap_org_upgrade', 'pending', wp_get_referer() ?: home_url('/dashboard/')));
        exit;
    }

    private static function create_placeholder_org(int $user_id, WP_User $user): ?int
    {
        $title = $user->display_name ?: $user->user_login;
        $org_id = wp_insert_post([
            'post_type'   => 'artpulse_org',
            'post_status' => 'draft',
            'post_title'  => sprintf(
                /* translators: %s member display name. */
                __('%s Organization', 'artpulse-management'),
                $title
            ),
            'post_author' => $user_id,
        ]);

        if (!$org_id || is_wp_error($org_id)) {
            return null;
        }

        RoleUpgradeManager::attach_owner((int) $org_id, $user_id);

        return (int) $org_id;
    }

    public static function send_member_email(string $slug, WP_User $user, array $context = []): void
    {
        $email = $user->user_email;
        if (!$email) {
            return;
        }

        $context['user'] = $user;

        $subject = '';
        $template_file = '';

        switch ($slug) {
            case 'upgrade_requested':
                $subject = __('We have received your organization upgrade request', 'artpulse-management');
                $template_file = 'upgrade-requested';
                break;
            case 'upgrade_approved':
                $subject = __('Your organization upgrade has been approved', 'artpulse-management');
                $template_file = 'upgrade-approved';
                break;
            case 'upgrade_denied':
                $subject = __('Update on your organization upgrade request', 'artpulse-management');
                $template_file = 'upgrade-denied';
                break;
            default:
                $template_file = $slug;
        }

        $message = self::load_email_template($template_file, $context);

        /**
         * Filter the email content before sending.
         *
         * @param array $email {
         *     @type string $subject Email subject.
         *     @type string $message Email message body.
         * }
         * @param WP_User $user   Target user.
         * @param array   $context Additional context.
         */
        $filtered = apply_filters('artpulse/email/' . $slug, [
            'subject' => $subject,
            'message' => $message,
        ], $user, $context);

        $subject = $filtered['subject'] ?? $subject;
        $message = $filtered['message'] ?? $message;

        wp_mail($email, $subject, $message);
    }

    private static function load_email_template(string $slug, array $context = []): string
    {
        $template = trailingslashit(ARTPULSE_PLUGIN_DIR) . 'templates/emails/' . $slug . '.php';

        if (!file_exists($template)) {
            return '';
        }

        ob_start();
        extract($context, EXTR_SKIP);
        include $template;

        return (string) ob_get_clean();
    }
}
