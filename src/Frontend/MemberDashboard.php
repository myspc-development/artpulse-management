<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\RoleUpgradeManager;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\ArtistRequestStatusRoute;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use WP_Error;
use WP_Post;
use WP_User;
use function esc_url;
use function esc_url_raw;
use function sanitize_text_field;
use function wp_strip_all_tags;
use function ArtPulse\Core\add_query_args;
use function ArtPulse\Core\get_missing_page_fallback;
use function ArtPulse\Core\get_page_url;

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
            'cta'         => self::build_cta(
                __('Request artist access', 'artpulse-management'),
                $journey['links']['upgrade'] ?? sprintf('#ap-journey-%s', $journey['slug'] ?? 'artist'),
                'secondary',
                'form',
                ['upgrade_type' => 'artist']
            ),
            'journey'     => $journey,
        ];

        if (!is_user_logged_in() || $user_id <= 0) {
            return $state;
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return $state;
        }

        $dashboard_url = self::get_dashboard_url_for_role('artist');
        $builder_url   = isset($journey['links']['builder'])
            ? (string) $journey['links']['builder']
            : self::get_artist_builder_base_url();

        if (in_array('artist', (array) $user->roles, true)) {
            $state['status']      = 'approved';
            $state['profile_url'] = $dashboard_url;
            $state['cta']         = self::build_cta(
                __('Open artist tools', 'artpulse-management'),
                $dashboard_url,
                'primary'
            );

            return $state;
        }

        [$state, $status_locked] = self::apply_review_context(
            $state,
            self::get_upgrade_context($user_id, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE),
            [
                'status_map' => [
                    UpgradeReviewRepository::STATUS_PENDING  => 'requested',
                    UpgradeReviewRepository::STATUS_DENIED   => 'denied',
                    UpgradeReviewRepository::STATUS_APPROVED => 'approved',
                ],
                'pending_cta' => self::build_cta(
                    __('Check request status', 'artpulse-management'),
                    ArtistRequestStatusRoute::get_status_url('artist'),
                    'secondary'
                ),
                'denied_cta' => self::build_cta(
                    __('Reopen artist builder', 'artpulse-management'),
                    esc_url_raw($builder_url),
                    'primary'
                ),
                'approved_cta' => self::build_cta(
                    __('Open artist tools', 'artpulse-management'),
                    $dashboard_url,
                    'primary'
                ),
                'url_key'              => 'profile_url',
                'approved_profile_url' => $dashboard_url,
            ]
        );

        if ($status_locked) {
            return $state;
        }

        $portfolio_status = $journey['portfolio']['status'] ?? '';

        if (in_array($portfolio_status, ['draft', 'pending'], true)) {
            $state['status'] = 'in_progress';
            $state['cta']    = self::build_cta(
                __('Continue artist builder', 'artpulse-management'),
                esc_url_raw($builder_url),
                'primary'
            );
        } elseif (in_array($portfolio_status, ['published', 'scheduled'], true)) {
            $state['status']      = 'approved';
            $state['profile_url'] = $dashboard_url;
            $state['cta']         = self::build_cta(
                __('Open artist tools', 'artpulse-management'),
                $dashboard_url,
                'primary'
            );
        }

        return $state;
    }

    private static function build_org_state(int $user_id, array $journey): array
    {
        $state = [
            'status'  => 'not_started',
            'reason'  => '',
            'org_id'  => 0,
            'org_url' => '',
            'cta'     => self::build_cta(
                __('Request organization access', 'artpulse-management'),
                $journey['links']['upgrade'] ?? sprintf('#ap-journey-%s', $journey['slug'] ?? 'organization'),
                'secondary',
                'form',
                ['upgrade_type' => 'organization']
            ),
            'journey' => $journey,
        ];

        if (!is_user_logged_in() || $user_id <= 0) {
            return $state;
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return $state;
        }

        $dashboard_url = self::get_dashboard_url_for_role('organization');
        $builder_url   = isset($journey['links']['builder'])
            ? (string) $journey['links']['builder']
            : self::get_org_builder_base_url();

        $builder_profile_url = esc_url_raw(add_query_args($builder_url, ['step' => 'profile']));

        if (in_array('organization', (array) $user->roles, true)) {
            $state['status']  = 'approved';
            $state['org_url'] = $dashboard_url;
            $state['cta']     = self::build_cta(
                __('Open organization tools', 'artpulse-management'),
                $dashboard_url,
                'primary'
            );

            return $state;
        }

        [$state, $status_locked] = self::apply_review_context(
            $state,
            self::get_upgrade_context($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE),
            [
                'status_map' => [
                    UpgradeReviewRepository::STATUS_PENDING  => 'requested',
                    UpgradeReviewRepository::STATUS_DENIED   => 'denied',
                    UpgradeReviewRepository::STATUS_APPROVED => 'approved',
                ],
                'pending_cta' => self::build_cta(
                    __('Check request status', 'artpulse-management'),
                    ArtistRequestStatusRoute::get_status_url('organization'),
                    'secondary'
                ),
                'denied_cta' => self::build_cta(
                    __('Reopen organization builder', 'artpulse-management'),
                    $builder_profile_url,
                    'primary'
                ),
                'approved_cta' => self::build_cta(
                    __('Open organization tools', 'artpulse-management'),
                    $dashboard_url,
                    'primary'
                ),
                'url_key'              => 'org_url',
                'id_key'               => 'org_id',
                'request_id_key'       => 'request_id',
                'approved_profile_url' => $dashboard_url,
            ]
        );

        if ($status_locked) {
            return $state;
        }

        $portfolio_status = $journey['portfolio']['status'] ?? '';
        if (in_array($portfolio_status, ['draft', 'pending'], true)) {
            $state['cta'] = self::build_cta(
                __('Continue organization builder', 'artpulse-management'),
                $builder_profile_url,
                'primary'
            );
        } elseif (in_array($portfolio_status, ['published', 'scheduled'], true)) {
            $state['cta'] = self::build_cta(
                __('Open organization tools', 'artpulse-management'),
                $dashboard_url,
                'primary'
            );
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
            wp_safe_redirect(self::get_dashboard_base_url());
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
            wp_safe_redirect(wp_get_referer() ?: self::get_dashboard_base_url());
            exit;
        }

        $requested_upgrade = isset($_POST['upgrade_type']) ? sanitize_key((string) $_POST['upgrade_type']) : '';
        $redirect_base     = wp_get_referer() ?: self::get_dashboard_base_url();

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

                wp_safe_redirect(add_query_args($redirect_base, ['ap_artist_upgrade' => $target]));
                exit;
            }

            AuditLogger::info('artist.upgrade.requested', [
                'user_id' => $user_id,
                'post_id' => $result['artist_id'] ?? 0,
                'request_id' => $result['request_id'] ?? 0,
            ]);

            wp_safe_redirect(add_query_args($redirect_base, ['ap_artist_upgrade' => 'pending']));
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

            wp_safe_redirect(add_query_args($redirect_base, ['ap_org_upgrade' => $target]));
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

        wp_safe_redirect(add_query_args($redirect_base, ['ap_org_upgrade' => 'pending']));
        exit;
    }

    /**
     * @param array{
     *     role:string,
     *     review_type:string,
     *     invalid:array{code:string,message:string},
     *     exists:array{code:string,message:string},
     *     pending:array{code:string,message:string}
     * } $config
     */
    private static function guard_upgrade_request(WP_User $user, array $config): ?WP_Error
    {
        $user_id = (int) $user->ID;

        if ($user_id <= 0) {
            return new WP_Error($config['invalid']['code'], $config['invalid']['message']);
        }

        if (in_array($config['role'], (array) $user->roles, true)) {
            return new WP_Error($config['exists']['code'], $config['exists']['message']);
        }

        $existing = UpgradeReviewRepository::get_latest_for_user($user_id, $config['review_type']);

        if ($existing instanceof WP_Post && UpgradeReviewRepository::STATUS_PENDING === UpgradeReviewRepository::get_status($existing)) {
            return new WP_Error($config['pending']['code'], $config['pending']['message']);
        }

        return null;
    }

    /**
     * Grant artist capabilities when requested by a member.
     */
    private static function process_artist_upgrade_request(WP_User $user)
    {
        $guard = self::guard_upgrade_request($user, [
            'role'        => 'artist',
            'review_type' => UpgradeReviewRepository::TYPE_ARTIST_UPGRADE,
            'invalid'     => [
                'code'    => 'ap_artist_upgrade_invalid',
                'message' => __('Invalid user.', 'artpulse-management'),
            ],
            'exists'      => [
                'code'    => 'ap_artist_upgrade_exists',
                'message' => __('You already have access to the artist tools.', 'artpulse-management'),
            ],
            'pending'     => [
                'code'    => 'ap_artist_upgrade_pending',
                'message' => __('Your previous request is still pending.', 'artpulse-management'),
            ],
        ]);

        if ($guard instanceof WP_Error) {
            return $guard;
        }

        $user_id = (int) $user->ID;

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
        $guard = self::guard_upgrade_request($user, [
            'role'        => 'organization',
            'review_type' => UpgradeReviewRepository::TYPE_ORG,
            'invalid'     => [
                'code'    => 'ap_org_upgrade_invalid_user',
                'message' => __('Invalid user.', 'artpulse-management'),
            ],
            'exists'      => [
                'code'    => 'ap_org_upgrade_exists',
                'message' => __('You already manage an organisation.', 'artpulse-management'),
            ],
            'pending'     => [
                'code'    => 'ap_org_upgrade_pending',
                'message' => __('Your previous request is still pending.', 'artpulse-management'),
            ],
        ]);

        if ($guard instanceof WP_Error) {
            return $guard;
        }

        $user_id = (int) $user->ID;

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

    /**
     * @param array<string, mixed> $extra
     */
    private static function build_cta(string $label, string $url, string $variant = 'secondary', string $mode = 'link', array $extra = []): array
    {
        return array_merge([
            'label'    => $label,
            'url'      => $url,
            'variant'  => $variant,
            'disabled' => false,
            'mode'     => $mode,
        ], $extra);
    }

    /**
     * @return array{request_id:int, post_id:int, permalink:string, status:?string, reason:string}
     */
    private static function get_upgrade_context(int $user_id, string $review_type): array
    {
        $request = UpgradeReviewRepository::get_latest_for_user($user_id, $review_type);

        if (!$request instanceof WP_Post) {
            return [
                'request_id' => 0,
                'post_id'    => 0,
                'permalink'  => '',
                'status'     => null,
                'reason'     => '',
            ];
        }

        $post_id = UpgradeReviewRepository::get_post_id($request);
        $permalink = '';

        if ($post_id > 0) {
            $link = get_permalink($post_id);
            if ($link) {
                $permalink = esc_url_raw($link);
            }
        }

        return [
            'request_id' => (int) $request->ID,
            'post_id'    => $post_id,
            'permalink'  => $permalink,
            'status'     => UpgradeReviewRepository::get_status($request),
            'reason'     => UpgradeReviewRepository::get_reason($request),
        ];
    }

    /**
     * @param array<string, mixed>                   $state
     * @param array{request_id:int, post_id:int, permalink:string, status:?string, reason:string} $context
     * @param array<string, mixed>                   $config
     *
     * @return array{0:array<string, mixed>,1:bool}
     */
    private static function apply_review_context(array $state, array $context, array $config): array
    {
        if ($context['request_id'] <= 0) {
            return [$state, false];
        }

        $state['reason'] = $context['reason'];

        if (isset($config['request_id_key'])) {
            $state[$config['request_id_key']] = $context['request_id'];
        }

        if (isset($config['id_key'])) {
            $state[$config['id_key']] = $context['post_id'];
        }

        if (isset($config['url_key']) && '' !== $context['permalink']) {
            $state[$config['url_key']] = $context['permalink'];
        }

        $status = $context['status'] ?? '';

        if (isset($config['status_map'][$status])) {
            $state['status'] = (string) $config['status_map'][$status];
        }

        if ($status === UpgradeReviewRepository::STATUS_PENDING && isset($config['pending_cta'])) {
            $state['cta'] = $config['pending_cta'];

            return [$state, true];
        }

        if ($status === UpgradeReviewRepository::STATUS_DENIED && isset($config['denied_cta'])) {
            $state['cta'] = $config['denied_cta'];

            return [$state, true];
        }

        if ($status === UpgradeReviewRepository::STATUS_APPROVED && isset($config['approved_cta'])) {
            $state['cta'] = $config['approved_cta'];

            if (isset($config['approved_profile_url'], $config['url_key'])) {
                $state[$config['url_key']] = (string) $config['approved_profile_url'];
            }

            return [$state, true];
        }

        return [$state, false];
    }

    public static function send_member_email(string $slug, WP_User $user, array $context = []): void
    {
        $email = $user->user_email;
        if (!$email) {
            return;
        }

        $context['user'] = $user;

        $default_dashboard_urls = [
            'upgrade_requested' => self::get_dashboard_url_for_role('organization'),
            'upgrade_approved'  => self::get_dashboard_url_for_role('organization'),
            'upgrade_denied'    => self::get_dashboard_url_for_role('organization'),
        ];

        if (empty($context['dashboard_url']) && isset($default_dashboard_urls[$slug])) {
            $context['dashboard_url'] = $default_dashboard_urls[$slug];
        }

        if (empty($context['dashboard_url'])) {
            $context['dashboard_url'] = esc_url_raw(self::get_dashboard_base_url());
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

    private static function get_dashboard_base_url(): string
    {
        $url = get_page_url('dashboard_page_id');

        if ($url) {
            return $url;
        }

        return get_missing_page_fallback('dashboard_page_id');
    }

    private static function get_dashboard_url_for_role(string $role): string
    {
        return esc_url_raw(add_query_args(self::get_dashboard_base_url(), ['role' => $role]));
    }

    private static function get_artist_builder_base_url(): string
    {
        $url = get_page_url('artist_builder_page_id');

        if ($url) {
            return $url;
        }

        return get_missing_page_fallback('artist_builder_page_id');
    }

    private static function get_org_builder_base_url(): string
    {
        $url = get_page_url('org_builder_page_id');

        if ($url) {
            return $url;
        }

        return get_missing_page_fallback('org_builder_page_id');
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
