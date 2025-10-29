<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\RoleUpgradeManager;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use WP_Error;
use WP_Post;
use WP_User;
use function esc_url;
use function sanitize_text_field;
use function wp_strip_all_tags;

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

        $journeys = $data['journeys'] ?? [];

        $data['org_upgrade'] = self::get_upgrade_state($user_id, $journeys);

        if (!isset($data['journeys']['artist']) && isset($journeys['artist'])) {
            $data['journeys']['artist'] = $journeys['artist'];
        }

        if (!isset($data['journeys']['organization']) && isset($journeys['organization'])) {
            $data['journeys']['organization'] = $journeys['organization'];
        }

        return $data;
    }

    /**
     * Remove the artist/organisation upgrade options from the shared upgrade widget.
     *
     * The member dashboard renders a dedicated upgrade card that handles both
     * artist and organisation flows, so we avoid duplicating the same journeys in
     * the generic membership widget output.
     */
    public static function remove_org_upgrade_option(array $data, int $user_id): array
    {
        if (empty($data['upgrades']) || !is_array($data['upgrades'])) {
            return $data;
        }

        $upgrades = array_filter(
            $data['upgrades'],
            static fn(array $upgrade): bool => !in_array($upgrade['slug'] ?? '', ['organization', 'artist'], true)
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

    private static function get_upgrade_state(int $user_id, array $journeys = []): array
    {
        $artist_journey = $journeys['artist'] ?? [];
        $org_journey    = $journeys['organization'] ?? [];

        return [
            'artist'       => self::build_artist_state($user_id, $artist_journey),
            'organization' => self::build_org_state($user_id, $org_journey),
        ];
    }

    private static function build_artist_state(int $user_id, array $journey): array
    {
        $state = [
            'status'      => 'not_started',
            'reason'      => '',
            'profile_url' => '',
            'cta'         => [
                'label'    => __('Request artist access', 'artpulse-management'),
                'url'      => $journey['links']['upgrade'] ?? sprintf('#ap-journey-%s', $journey['slug'] ?? 'artist'),
                'variant'  => 'secondary',
                'disabled' => false,
                'mode'     => 'form',
                'upgrade_type' => 'artist',
            ],
            'journey'     => $journey,
        ];

        if (!is_user_logged_in() || $user_id <= 0) {
            return $state;
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return $state;
        }

        $dashboard_url = esc_url_raw(add_query_arg('role', 'artist', home_url('/dashboard/')));
        $builder_url   = isset($journey['links']['builder']) ? (string) $journey['links']['builder'] : home_url('/artist-builder/');

        if (in_array('artist', (array) $user->roles, true)) {
            $state['status']      = 'approved';
            $state['profile_url'] = $dashboard_url;
            $state['cta']         = [
                'label'    => __('Open artist tools', 'artpulse-management'),
                'url'      => $dashboard_url,
                'variant'  => 'primary',
                'disabled' => false,
                'mode'     => 'link',
            ];

            return $state;
        }

        $request = UpgradeReviewRepository::get_latest_for_user($user_id, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE);
        $status_locked = false;

        if ($request instanceof WP_Post) {
            $state['reason'] = UpgradeReviewRepository::get_reason($request);

            $profile_id = UpgradeReviewRepository::get_post_id($request);
            if ($profile_id > 0) {
                $permalink = get_permalink($profile_id);
                if ($permalink) {
                    $state['profile_url'] = esc_url_raw($permalink);
                }
            }

            $review_status = UpgradeReviewRepository::get_status($request);

            if ($review_status === UpgradeReviewRepository::STATUS_PENDING) {
                $state['status'] = 'requested';
                $state['cta']    = [
                    'label'    => __('Check request status', 'artpulse-management'),
                    'url'      => sprintf('#ap-journey-%s', $journey['slug'] ?? 'artist'),
                    'variant'  => 'secondary',
                    'disabled' => false,
                    'mode'     => 'link',
                ];
                $status_locked = true;
            } elseif ($review_status === UpgradeReviewRepository::STATUS_DENIED) {
                $state['status'] = 'denied';
                $state['cta']    = [
                    'label'    => __('Reopen artist builder', 'artpulse-management'),
                    'url'      => esc_url_raw($builder_url),
                    'variant'  => 'primary',
                    'disabled' => false,
                    'mode'     => 'link',
                ];
                $status_locked = true;
            } elseif ($review_status === UpgradeReviewRepository::STATUS_APPROVED) {
                $state['status']      = 'approved';
                $state['profile_url'] = $dashboard_url;
                $state['cta']         = [
                    'label'    => __('Open artist tools', 'artpulse-management'),
                    'url'      => $dashboard_url,
                    'variant'  => 'primary',
                    'disabled' => false,
                    'mode'     => 'link',
                ];
                $status_locked = true;
            }
        }

        if ($status_locked) {
            return $state;
        }

        $portfolio_status = $journey['portfolio']['status'] ?? '';

        if ('draft' === $portfolio_status || 'pending' === $portfolio_status) {
            $state['status'] = 'in_progress';
            $state['cta']    = [
                'label'    => __('Continue artist builder', 'artpulse-management'),
                'url'      => esc_url_raw($builder_url),
                'variant'  => 'primary',
                'disabled' => false,
                'mode'     => 'link',
            ];
        } elseif ('published' === $portfolio_status || 'scheduled' === $portfolio_status) {
            $state['status']      = 'approved';
            $state['profile_url'] = $dashboard_url;
            $state['cta']         = [
                'label'    => __('Open artist tools', 'artpulse-management'),
                'url'      => $dashboard_url,
                'variant'  => 'primary',
                'disabled' => false,
                'mode'     => 'link',
            ];
        }

        return $state;
    }

    private static function build_org_state(int $user_id, array $journey): array
    {
        $state = [
            'status'   => 'not_started',
            'reason'   => '',
            'org_id'   => 0,
            'org_url'  => '',
            'cta'      => [
                'label'    => __('Request organization access', 'artpulse-management'),
                'url'      => $journey['links']['upgrade'] ?? sprintf('#ap-journey-%s', $journey['slug'] ?? 'organization'),
                'variant'  => 'secondary',
                'disabled' => false,
                'mode'     => 'form',
                'upgrade_type' => 'organization',
            ],
            'journey'  => $journey,
        ];

        if (!is_user_logged_in() || $user_id <= 0) {
            return $state;
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return $state;
        }

        $dashboard_url = esc_url_raw(add_query_arg('role', 'organization', home_url('/dashboard/')));
        $builder_url   = isset($journey['links']['builder']) ? (string) $journey['links']['builder'] : home_url('/org-builder/');

        if (in_array('organization', (array) $user->roles, true)) {
            $state['status']  = 'approved';
            $state['org_url'] = $dashboard_url;
            $state['cta']     = [
                'label'    => __('Open organization tools', 'artpulse-management'),
                'url'      => $dashboard_url,
                'variant'  => 'primary',
                'disabled' => false,
                'mode'     => 'link',
            ];

            return $state;
        }

        $request = UpgradeReviewRepository::get_latest_for_user($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);
        if ($request instanceof WP_Post) {
            $state['org_id']     = UpgradeReviewRepository::get_post_id($request);
            $state['request_id'] = (int) $request->ID;
            $state['reason']     = UpgradeReviewRepository::get_reason($request);

            if ($state['org_id'] > 0) {
                $permalink = get_permalink($state['org_id']);
                if ($permalink) {
                    $state['org_url'] = esc_url_raw($permalink);
                }
            }

            $status = UpgradeReviewRepository::get_status($request);

            if ($status === UpgradeReviewRepository::STATUS_APPROVED) {
                $state['status'] = 'approved';
                $state['cta']    = [
                    'label'    => __('Open organization tools', 'artpulse-management'),
                    'url'      => $dashboard_url,
                    'variant'  => 'primary',
                    'disabled' => false,
                    'mode'     => 'link',
                ];
            } elseif ($status === UpgradeReviewRepository::STATUS_DENIED) {
                $state['status'] = 'denied';
                $state['cta']    = [
                    'label'    => __('Reopen organization builder', 'artpulse-management'),
                    'url'      => esc_url_raw(add_query_arg('step', 'profile', $builder_url)),
                    'variant'  => 'primary',
                    'disabled' => false,
                    'mode'     => 'link',
                ];
            } else {
                $state['status'] = 'requested';
                $state['cta']    = [
                    'label'    => __('Check request status', 'artpulse-management'),
                    'url'      => sprintf('#ap-journey-%s', $journey['slug'] ?? 'organization'),
                    'variant'  => 'secondary',
                    'disabled' => false,
                    'mode'     => 'link',
                ];
            }
        }

        $portfolio_status = $journey['portfolio']['status'] ?? '';
        if (in_array($portfolio_status, ['draft', 'pending'], true)) {
            $state['cta'] = [
                'label'    => __('Continue organization builder', 'artpulse-management'),
                'url'      => esc_url_raw(add_query_arg('step', 'profile', $builder_url)),
                'variant'  => 'primary',
                'disabled' => false,
                'mode'     => 'link',
            ];
        } elseif (in_array($portfolio_status, ['published', 'scheduled'], true)) {
            $state['cta'] = [
                'label'    => __('Open organization tools', 'artpulse-management'),
                'url'      => $dashboard_url,
                'variant'  => 'primary',
                'disabled' => false,
                'mode'     => 'link',
            ];
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
                    'ap_artist_upgrade_exists'   => 'exists',
                    'ap_artist_upgrade_pending'  => 'pending',
                    'ap_artist_upgrade_invalid'  => 'failed',
                    default                      => 'failed',
                };

                wp_safe_redirect(add_query_arg('ap_artist_upgrade', $target, $redirect_base));
                exit;
            }

            AuditLogger::info('artist.upgrade.requested', [
                'user_id' => $user_id,
                'post_id' => $result['artist_id'] ?? 0,
                'request_id' => $result['request_id'] ?? 0,
            ]);

            wp_safe_redirect(add_query_arg('ap_artist_upgrade', 'pending', $redirect_base));
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

        $existing_request = UpgradeReviewRepository::get_latest_for_user($user_id, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE);
        if ($existing_request instanceof WP_Post && UpgradeReviewRepository::STATUS_PENDING === UpgradeReviewRepository::get_status($existing_request)) {
            return new WP_Error('ap_artist_upgrade_pending', __('Your previous request is still pending.', 'artpulse-management'));
        }

        $artist_id = self::create_placeholder_artist($user_id, $user);
        if (!$artist_id) {
            return new WP_Error('ap_artist_upgrade_profile_failed', __('Unable to create the artist draft.', 'artpulse-management'));
        }

        $result = UpgradeReviewRepository::upsert_pending($user_id, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE, $artist_id);
        $request_id = (int) ($result['request_id'] ?? 0);

        if ($request_id <= 0) {
            wp_delete_post($artist_id, true);

            return new WP_Error('ap_artist_upgrade_request_failed', __('Unable to create the review request.', 'artpulse-management'));
        }

        update_post_meta($request_id, '_ap_placeholder_artist_id', $artist_id);

        return [
            'artist_id'  => $artist_id,
            'request_id' => $request_id,
        ];
    }

    private static function create_placeholder_artist(int $user_id, WP_User $user): ?int
    {
        $display_name = trim($user->display_name ?: $user->user_login);

        if ('' === $display_name) {
            $title = __('New artist profile', 'artpulse-management');
        } else {
            $title = sprintf(
                /* translators: %s member display name. */
                __('Artist profile for %s', 'artpulse-management'),
                $display_name
            );
        }

        $artist_id = wp_insert_post([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'draft',
            'post_title'  => $title,
            'post_author' => $user_id,
        ]);

        if (!$artist_id || is_wp_error($artist_id)) {
            return null;
        }

        RoleUpgradeManager::attach_owner((int) $artist_id, $user_id);

        return (int) $artist_id;
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

        $lock_key = 'ap_upgrade_lock_user_' . $user_id;

        if (get_transient($lock_key)) {
            return new WP_Error(
                'ap_upgrade_request_in_flight',
                __('Your upgrade request is already being processed. Please wait a moment and refresh.', 'artpulse-management')
            );
        }

        set_transient($lock_key, 1, 15);

        try {
            $org_id = self::create_placeholder_org($user_id, $user);

            if (!$org_id) {
                return new WP_Error('ap_org_upgrade_org_failed', __('Unable to create the organisation draft.', 'artpulse-management'));
            }

            $request_id = UpgradeReviewRepository::create_org_upgrade($user_id, $org_id);

            if (!$request_id) {
                wp_delete_post($org_id, true);

                return new WP_Error('ap_org_upgrade_request_failed', __('Unable to create the review request.', 'artpulse-management'));
            }

            update_post_meta($request_id, '_ap_placeholder_org_id', $org_id);

            return [
                'org_id'     => $org_id,
                'request_id' => $request_id,
            ];
        } finally {
            delete_transient($lock_key);
        }
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

        $default_dashboard_urls = [
            'upgrade_requested' => esc_url_raw(add_query_arg('role', 'organization', home_url('/dashboard/'))),
            'upgrade_approved'  => esc_url_raw(add_query_arg('role', 'organization', home_url('/dashboard/'))),
            'upgrade_denied'    => esc_url_raw(add_query_arg('role', 'organization', home_url('/dashboard/'))),
        ];

        if (empty($context['dashboard_url']) && isset($default_dashboard_urls[$slug])) {
            $context['dashboard_url'] = $default_dashboard_urls[$slug];
        }

        if (empty($context['dashboard_url'])) {
            $context['dashboard_url'] = esc_url_raw(home_url('/dashboard/'));
        }

        if (empty($context['dual_role_message'])) {
            $context['dual_role_message'] = __('Remember: you can keep both artist and organization access active—switch roles from your dashboard.', 'artpulse-management');
        }

        if (empty($context['role_label']) && in_array($slug, ['upgrade_approved', 'upgrade_denied'], true)) {
            $context['role_label'] = __('Organization', 'artpulse-management');
        }

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
        $message = self::replace_template_placeholders($message, $context);

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

    private static function replace_template_placeholders(string $message, array $context): string
    {
        if ('' === $message) {
            return $message;
        }

        $replacements = [
            '{dashboard_url}' => isset($context['dashboard_url']) ? esc_url((string) $context['dashboard_url']) : '',
            '{role_label}'    => isset($context['role_label']) ? sanitize_text_field((string) $context['role_label']) : '',
            '{reason}'        => isset($context['reason']) ? wp_strip_all_tags((string) $context['reason']) : '',
            '{builder_url}'   => isset($context['builder_url']) ? esc_url((string) $context['builder_url']) : '',
        ];

        $message = strtr($message, $replacements);
        $message = preg_replace("/\n{3,}/", "\n\n", $message);

        return is_string($message) ? trim($message) : '';
    }
}
