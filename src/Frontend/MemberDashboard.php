<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\RoleUpgradeManager;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use WP_Error;
use WP_Post;
use WP_User;

class MemberDashboard
{
    public static function register(): void
    {
        add_action('init', [self::class, 'register_actions']);
        add_filter('artpulse/dashboard/data', [self::class, 'inject_dashboard_card'], 10, 3);
        add_filter('artpulse/dashboard/member_upgrade_widget_data', [self::class, 'remove_org_upgrade_option'], 10, 2);
    }

    public static function register_actions(): void
    {
        add_action('admin_post_ap_dashboard_upgrade', [self::class, 'handle_dashboard_upgrade_request']);
    }

    public static function inject_dashboard_card(array $data, string $role, int $user_id): array
    {
        if ('member' !== $role || $user_id <= 0) {
            return $data;
        }

        $data['org_upgrade'] = self::get_upgrade_state($user_id);

        return $data;
    }

    /**
     * Remove the organization upgrade option from the shared upgrade widget.
     *
     * The member dashboard renders a dedicated organization upgrade card, so this
     * prevents duplicate CTAs from appearing in the membership upgrade list.
     */
    public static function remove_org_upgrade_option(array $data, int $user_id): array
    {
        if (empty($data['upgrades']) || !is_array($data['upgrades'])) {
            return $data;
        }

        $upgrades = array_filter(
            $data['upgrades'],
            static fn(array $upgrade): bool => ($upgrade['slug'] ?? '') !== 'organization'
        );

        if (count($upgrades) === count($data['upgrades'])) {
            return $data;
        }

        $data['upgrades'] = array_values($upgrades);

        if (empty($data['upgrades'])) {
            $data['intro'] = '';
        }

        return $data;
    }

    private static function get_upgrade_state(int $user_id): array
    {
        $state = [
            'artist'       => [
                'status' => 'not_started',
                'reason' => '',
            ],
            'organization' => [
                'status'  => 'not_started',
                'reason'  => '',
                'org_id'  => 0,
                'org_url' => '',
            ],
        ];

        if (!is_user_logged_in() || $user_id <= 0) {
            return $state;
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return $state;
        }

        if (in_array('artist', (array) $user->roles, true)) {
            $state['artist']['status'] = 'approved';
            $state['artist']['profile_url'] = esc_url_raw(add_query_arg('role', 'artist', home_url('/dashboard/')));
        }

        if (in_array('organization', (array) $user->roles, true)) {
            $state['organization']['status'] = 'approved';
            $state['organization']['org_url'] = esc_url_raw(add_query_arg('role', 'organization', home_url('/dashboard/')));

            return $state;
        }

        $request = UpgradeReviewRepository::get_latest_for_user($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);
        if (!$request instanceof WP_Post) {
            return $state;
        }

        $status = UpgradeReviewRepository::get_status($request);
        $org_id = UpgradeReviewRepository::get_post_id($request);

        $state['organization']['org_id'] = $org_id;
        $state['organization']['request_id'] = (int) $request->ID;
        $state['organization']['reason'] = UpgradeReviewRepository::get_reason($request);

        if ($org_id > 0) {
            $state['organization']['org_url'] = get_permalink($org_id);
        }

        switch ($status) {
            case UpgradeReviewRepository::STATUS_APPROVED:
                $state['organization']['status'] = 'approved';
                break;
            case UpgradeReviewRepository::STATUS_DENIED:
                $state['organization']['status'] = 'denied';
                break;
            default:
                $state['organization']['status'] = 'requested';
        }

        return $state;
    }

    public static function handle_dashboard_upgrade_request(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/login/'));
            exit;
        }

        $user_id = get_current_user_id();

        if (!current_user_can('read')) {
            wp_safe_redirect(home_url('/dashboard/'));
            exit;
        }

        $nonce_valid = isset($_POST['_ap_nonce'])
            && check_admin_referer('ap-member-upgrade-request', '_ap_nonce', false);

        if (!$nonce_valid) {
            status_header(400);
            wp_die(esc_html__('Invalid request. Please refresh and try again.', 'artpulse-management'));
        }

        $rate_error = FormRateLimiter::enforce($user_id, 'dashboard_upgrade', 30, 60);
        if ($rate_error instanceof WP_Error) {
            $data   = (array) $rate_error->get_error_data();
            $status = (int) ($data['status'] ?? 429);

            if ($status > 0) {
                status_header($status);
            }

            $headers = $data['headers'] ?? [];
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if ('' === $name) {
                        continue;
                    }

                    header(trim((string) $name) . ': ' . trim((string) $value));
                }
            }

            echo esc_html($rate_error->get_error_message());
            exit;
        }

        $user    = get_user_by('id', $user_id);

        if (!$user instanceof WP_User) {
            wp_safe_redirect(wp_get_referer() ?: home_url('/dashboard/'));
            exit;
        }

        $requested_upgrade = isset($_POST['upgrade_type']) ? sanitize_key((string) $_POST['upgrade_type']) : '';
        $redirect_base     = wp_get_referer() ?: home_url('/dashboard/');

        if ('' === $requested_upgrade) {
            wp_safe_redirect($redirect_base);
            exit;
        }

        if ('artist' === $requested_upgrade) {
            $result = self::process_artist_upgrade_request($user);

            if (is_wp_error($result)) {
                $code = $result->get_error_code();
                $target = match ($code) {
                    'ap_artist_upgrade_exists'  => 'exists',
                    'ap_artist_upgrade_invalid' => 'failed',
                    default                     => 'failed',
                };

                wp_safe_redirect(add_query_arg('ap_artist_upgrade', $target, $redirect_base));
                exit;
            }

            AuditLogger::info('artist.upgrade.requested', [
                'user_id' => $user_id,
            ]);

            wp_safe_redirect(add_query_arg('ap_artist_upgrade', 'approved', $redirect_base));
            exit;
        }

        if ('organization' !== $requested_upgrade) {
            wp_safe_redirect($redirect_base);
            exit;
        }

        $result = self::process_upgrade_request_for_user($user);

        if (is_wp_error($result)) {
            $code = $result->get_error_code();
            $target = match ($code) {
                'ap_org_upgrade_exists'  => 'exists',
                'ap_org_upgrade_pending' => 'pending',
                default                  => 'failed',
            };

            wp_safe_redirect(add_query_arg('ap_org_upgrade', $target, $redirect_base));
            exit;
        }

        AuditLogger::info('org.upgrade.requested', [
            'user_id'    => $user_id,
            'post_id'    => $result['org_id'],
            'request_id' => $result['request_id'],
        ]);

        self::send_member_email('upgrade_requested', $user, [
            'org_id' => $result['org_id'],
        ]);

        wp_safe_redirect(add_query_arg('ap_org_upgrade', 'pending', $redirect_base));
        exit;
    }

    /**
     * Grant artist capabilities when requested by a member.
     */
    private static function process_artist_upgrade_request(WP_User $user)
    {
        $user_id = (int) $user->ID;

        if ($user_id <= 0) {
            return new WP_Error('ap_artist_upgrade_invalid', __('Invalid user.', 'artpulse-management'));
        }

        if (in_array('artist', (array) $user->roles, true)) {
            return new WP_Error('ap_artist_upgrade_exists', __('You already have access to the artist tools.', 'artpulse-management'));
        }

        RoleUpgradeManager::grant_role($user_id, 'artist', [
            'source' => 'dashboard_request',
        ]);

        return ['role' => 'artist'];
    }

    /**
     * Validate and prepare an organisation upgrade request for a user.
     *
     * @return array{org_id:int, request_id:int}|WP_Error
     */
    public static function process_upgrade_request_for_user(WP_User $user)
    {
        $user_id = (int) $user->ID;

        if ($user_id <= 0) {
            return new WP_Error('ap_org_upgrade_invalid_user', __('Invalid user.', 'artpulse-management'));
        }

        if (in_array('organization', (array) $user->roles, true)) {
            return new WP_Error('ap_org_upgrade_exists', __('You already manage an organisation.', 'artpulse-management'));
        }

        $existing = UpgradeReviewRepository::get_latest_for_user($user_id);
        if ($existing instanceof WP_Post && UpgradeReviewRepository::STATUS_PENDING === UpgradeReviewRepository::get_status($existing)) {
            return new WP_Error('ap_org_upgrade_pending', __('Your previous request is still pending.', 'artpulse-management'));
        }

        $org_id = self::create_placeholder_org($user_id, $user);

        if (!$org_id) {
            return new WP_Error('ap_org_upgrade_org_failed', __('Unable to create the organisation draft.', 'artpulse-management'));
        }

        $request_id = UpgradeReviewRepository::create_org_upgrade($user_id, $org_id);

        if (!$request_id) {
            wp_delete_post($org_id, true);

            return new WP_Error('ap_org_upgrade_request_failed', __('Unable to create the review request.', 'artpulse-management'));
        }

        return [
            'org_id'    => $org_id,
            'request_id'=> $request_id,
        ];
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
