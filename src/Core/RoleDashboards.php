<?php

namespace ArtPulse\Core;

use ArtPulse\Community\FavoritesManager;
use ArtPulse\Community\FollowManager;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

class RoleDashboards
{
    private const ROLE_CONFIG = [
        'member' => [
            'shortcode'   => 'ap_member_dashboard',
            'capability'  => 'read',
            'post_types'  => ['artpulse_event', 'artpulse_artwork'],
            'title'       => 'Member Dashboard',
            'layout'      => [
                'default_tab'   => 'overview',
                'quick_actions' => ['artist_profile', 'organization_profile', 'submit_event'],
            ],
        ],
        'artist' => [
            'shortcode'   => 'ap_artist_dashboard',
            'capability'  => 'edit_artpulse_artist',
            'post_types'  => ['artpulse_artist', 'artpulse_artwork'],
            'profile_post_type' => 'artpulse_artist',
            'title'       => 'Artist Dashboard',
            'feature_flag' => 'ap_enable_artist_builder',
            'layout'      => [
                'default_tab'   => 'overview',
                'quick_actions' => ['artist_profile', 'submit_event', 'view_profile'],
            ],
        ],
        'organization' => [
            'shortcode'   => 'ap_organization_dashboard',
            'capability'  => 'edit_artpulse_org',
            'post_types'  => ['artpulse_org', 'artpulse_event'],
            'profile_post_type' => 'artpulse_org',
            'title'       => 'Organization Dashboard',
            'feature_flag' => 'ap_enable_org_builder',
            'layout'      => [
                'default_tab'   => 'overview',
                'quick_actions' => ['organization_profile', 'submit_event', 'view_profile'],
            ],
        ],
    ];

    public static function register(): void
    {
        foreach (self::ROLE_CONFIG as $role => $config) {
            add_shortcode(
                $config['shortcode'],
                static function ($atts = [], $content = '', $tag = '') use ($role) {
                    return self::renderDashboard($role);
                }
            );
        }

        add_shortcode(
            'ap_member_upgrades_widget',
            static function ($atts = [], $content = '', $tag = '') {
                if (!is_user_logged_in()) {
                    return '<div class="ap-dashboard-message">' . esc_html__('Please log in to view available upgrades.', 'artpulse') . '</div>';
                }

                $user_id = get_current_user_id();

                if (!$user_id) {
                    return '';
                }

                $atts = shortcode_atts(
                    [
                        'title'          => __('Membership Upgrades', 'artpulse'),
                        'section_title'  => '',
                        'widget_intro'   => '',
                        'empty_message'  => __('No upgrades available at this time.', 'artpulse'),
                    ],
                    $atts,
                    $tag
                );

                $widget_data = self::getUpgradeWidgetData((int) $user_id);
                $upgrades    = $widget_data['upgrades'] ?? [];

                if (empty($upgrades)) {
                    return sprintf(
                        '<p class="ap-member-upgrades-widget__empty">%s</p>',
                        esc_html($atts['empty_message'])
                    );
                }

                $widget_args = [
                    'title' => (string) $atts['title'],
                ];

                if ($atts['widget_intro'] !== '') {
                    $widget_args['intro'] = (string) $atts['widget_intro'];
                }

                $section_title = $atts['section_title'] !== ''
                    ? (string) $atts['section_title']
                    : null;

                return self::renderUpgradeWidget(
                    $upgrades,
                    (string) ($widget_data['intro'] ?? ''),
                    $section_title,
                    $widget_args
                );
            }
        );

        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueDashboardAssets']);
        add_action('rest_api_init', [self::class, 'registerRoutes']);
        add_action('wp_dashboard_setup', [self::class, 'registerDashboardWidgets']);
    }

    public static function enqueueDashboardAssets(): void
    {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();

        if (!$screen || $screen->base !== 'dashboard') {
            return;
        }

        if (wp_script_is('ap-dashboards-js', 'enqueued')) {
            return;
        }

        self::enqueueAssets();
    }

    public static function registerDashboardWidgets(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();

        if (!$user instanceof WP_User) {
            return;
        }

        $should_register_event_widget          = false;
        $should_register_profile_actions_widget = false;
        $member_dashboard                     = [];

        if (self::userCanViewRole($user, 'member')) {
            $member_dashboard = self::prepareDashboardData('member', (int) $user->ID);

            if (!empty($member_dashboard['upgrades'])) {
                wp_add_dashboard_widget(
                    'artpulse_member_upgrades',
                    esc_html__('Membership Upgrades', 'artpulse'),
                    static function () use ($member_dashboard) {
                        self::enqueueAssets();

                        $upgrades = $member_dashboard['upgrades'] ?? [];

                        if (empty($upgrades)) {
                            echo '<p>' . esc_html__('No upgrades available at this time.', 'artpulse') . '</p>';

                            return;
                        }

                        echo self::renderUpgradeWidget(
                            $upgrades,
                            $member_dashboard['upgrade_intro'] ?? ''
                        );
                    }
                );
            }
        }

        foreach (self::ROLE_CONFIG as $role => $config) {
            if (!self::isRoleEnabled($role)) {
                continue;
            }
            $can_manage = user_can($user, 'manage_options') || user_can($user, 'view_artpulse_dashboard');

            if (!$can_manage && !self::userCanViewRole($user, $role)) {
                continue;
            }

            $widget_id = sprintf('artpulse_dashboard_%s', sanitize_key($role));
            $title     = $config['title'] ?? ucfirst($role);

            wp_add_dashboard_widget(
                $widget_id,
                esc_html($title),
                static function () use ($role) {
                    $data = self::prepareDashboardData($role);

                    if (empty($data)) {
                        echo '<div class="ap-dashboard-message">' . esc_html__('Unable to load dashboard data.', 'artpulse') . '</div>';

                        return;
                    }

                    echo self::renderDashboardWidget($data);
                }
            );

            if (in_array($role, ['artist', 'organization'], true)) {
                $should_register_event_widget          = true;
                $should_register_profile_actions_widget = true;
            }
        }

        if ($should_register_event_widget) {
            wp_add_dashboard_widget(
                'artpulse_event_submission',
                esc_html__('Submit an Event', 'artpulse'),
                [self::class, 'renderEventSubmissionWidget']
            );
        }

        if ($should_register_profile_actions_widget) {
            wp_add_dashboard_widget(
                'artpulse_profile_actions',
                esc_html__('Profile Actions', 'artpulse'),
                [self::class, 'renderProfileActionsWidget']
            );
        }
    }

    public static function renderEventSubmissionWidget(): void
    {
        $submission_url = '';

        if (self::currentUserCanCreateEvents()) {
            $submission_page_id = self::locateFrontendEventSubmissionPage();

            if ($submission_page_id) {
                $permalink = get_permalink($submission_page_id);

                if (is_string($permalink) && $permalink !== '') {
                    $submission_url = $permalink;
                }
            }
        }

        $template        = dirname(__DIR__, 2) . '/templates/dashboard/event-submission-widget.php';

        if (!file_exists($template)) {
            if (empty($submission_url)) {
                echo '<p>' . esc_html__('Event submissions are currently unavailable.', 'artpulse') . '</p>';

                return;
            }

            printf(
                '<div class="ap-dashboard-widget ap-dashboard-widget--event-submission"><div class="ap-dashboard-widget__section ap-dashboard-widget__section--event-submission"><h3 class="ap-dashboard-event-widget__title">%1$s</h3><p class="ap-dashboard-event-widget__description">%2$s</p><a class="ap-dashboard-button ap-dashboard-button--primary" href="%3$s">%4$s</a></div></div>',
                esc_html__('Share a New Event', 'artpulse'),
                esc_html__('Bring the community together by sharing details about your upcoming event.', 'artpulse'),
                esc_url($submission_url),
                esc_html__('Submit Event', 'artpulse')
            );

            return;
        }

        include $template;
    }

    public static function enqueueAssets(): void
    {
        $version = defined('ARTPULSE_VERSION') ? ARTPULSE_VERSION : '1.0.0';
        $api_root  = esc_url_raw(rest_url());
        $api_nonce = wp_create_nonce('wp_rest');

        if (!wp_script_is('ap-social-js', 'enqueued')) {
            wp_enqueue_script(
                'ap-social-js',
                plugins_url('assets/js/ap-social.js', dirname(__DIR__, 2)),
                [],
                $version,
                true
            );
        }

        if (!wp_script_is('ap-dashboards-js', 'registered')) {
            wp_register_script(
                'ap-dashboards-js',
                plugins_url('assets/js/ap-dashboards.js', dirname(__DIR__, 2)),
                ['wp-api-fetch', 'wp-dom-ready', 'ap-social-js'],
                $version,
                true
            );
        }

        wp_enqueue_script('ap-dashboards-js');

        $admin_email    = sanitize_email((string) get_option('admin_email'));
        $default_support = $admin_email !== ''
            ? sprintf('mailto:%s', $admin_email)
            : home_url('/contact/');
        $support_url = (string) apply_filters('artpulse/support_contact_url', $default_support);
        $support_url = esc_url_raw($support_url);

        wp_localize_script(
            'ap-dashboards-js',
            'ArtPulseDashboards',
            [
                'root'   => $api_root,
                'nonce'  => $api_nonce,
                'supportUrl' => $support_url,
                'labels' => self::getRoleLabels(),
                'strings' => [
                    'loading'                => __('Loading dashboard…', 'artpulse-management'),
                    'error'                  => __('Unable to load dashboard data.', 'artpulse-management'),
                    'empty'                  => __('Nothing to display yet.', 'artpulse-management'),
                    'profile'                => __('Profile Summary', 'artpulse-management'),
                    'metrics'                => __('Metrics', 'artpulse-management'),
                    'favorites'              => __('Favorites', 'artpulse-management'),
                    'follows'                => __('Follows', 'artpulse-management'),
                    'submissions'            => __('Submissions', 'artpulse-management'),
                    'favoritesMetric'        => __('Favorites', 'artpulse-management'),
                    'followsMetric'          => __('Follows', 'artpulse-management'),
                    'submissionsMetric'      => __('Submissions', 'artpulse-management'),
                    'pendingMetric'          => __('Pending', 'artpulse-management'),
                    'publishedMetric'        => __('Published', 'artpulse-management'),
                    'favorite'               => __('Favorite', 'artpulse-management'),
                    'unfavorite'             => __('Unfavorite', 'artpulse-management'),
                    'follow'                 => __('Follow', 'artpulse-management'),
                    'unfollow'               => __('Unfollow', 'artpulse-management'),
                    'updated'                => __('Updated', 'artpulse-management'),
                    'viewProfile'            => __('View profile', 'artpulse-management'),
                    'createProfile'          => __('Create profile', 'artpulse-management'),
                    'editProfile'            => __('Edit profile', 'artpulse-management'),
                    'upgrades'               => __('Membership Upgrades', 'artpulse-management'),
                    'upgradeIntro'           => __('Upgrade to unlock additional features and visibility.', 'artpulse-management'),
                    'upgradeCta'             => __('Upgrade now', 'artpulse-management'),
                    'upgradeLearnMore'       => __('Learn more', 'artpulse-management'),
                    'upgradeReopen'          => __('Re-request review', 'artpulse-management'),
                    'upgradePendingBadge'    => __('Pending', 'artpulse-management'),
                    'upgradeApprovedBadge'   => __('Approved', 'artpulse-management'),
                    'upgradeDeniedBadge'     => __('Denied', 'artpulse-management'),
                    'upgradePendingMessage'  => __('Your upgrade request is pending review.', 'artpulse-management'),
                    'upgradeApprovedMessage' => __('Approved — you now have the {role} role.', 'artpulse-management'),
                    'upgradeApprovedGeneric' => __('upgraded', 'artpulse-management'),
                    'upgradeDeniedMessage'   => __('Denied.', 'artpulse-management'),
                    'upgradeError'           => __('Unable to submit your request. Please try again.', 'artpulse-management'),
                    'artistRoleLabel'        => __('Artist', 'artpulse-management'),
                    'organizationRoleLabel'  => __('Organization', 'artpulse-management'),
                    'roleSwitcherLabel'      => __('Select a dashboard role', 'artpulse-management'),
                    'currentRoleLabel'       => __('Current dashboard', 'artpulse-management'),
                    'orgRequestNotice'       => __('Your organization upgrade request is pending review.', 'artpulse-management'),
                    'orgRequestHistoryCta'   => __('View request history', 'artpulse-management'),
                    'orgRequestSupportCta'   => __('Contact support', 'artpulse-management'),
                    'orgRequestDismiss'      => __('Dismiss', 'artpulse-management'),
                    'orgRequestModalTitle'   => __('Organization request history', 'artpulse-management'),
                    'orgRequestModalDescription' => __('Review the status of your organization upgrade requests.', 'artpulse-management'),
                    'orgRequestModalEmpty'   => __('No organization upgrade requests yet.', 'artpulse-management'),
                    'orgRequestHistoryError' => __('We were unable to load your request history. Please try again.', 'artpulse-management'),
                    'orgRequestModalClose'   => __('Close', 'artpulse-management'),
                    'orgRequestStatusPending'  => __('Pending', 'artpulse-management'),
                    'orgRequestStatusApproved' => __('Approved', 'artpulse-management'),
                    'orgRequestStatusDenied'   => __('Denied', 'artpulse-management'),
                    'orgRequestSubmittedOn'    => __('Submitted on %s', 'artpulse-management'),
                    'orgRequestReasonLabel'    => __('Reason', 'artpulse-management'),
                ],
            ]
        );

        wp_localize_script(
            'ap-dashboards-js',
            'ArtPulseApi',
            [
                'root'  => $api_root,
                'nonce' => $api_nonce,
            ]
        );

        if (wp_script_is('ap-social-js', 'enqueued')) {
            wp_localize_script(
                'ap-social-js',
                'APSocial',
                [
                    'root'     => $api_root,
                    'nonce'    => $api_nonce,
                    'messages' => [
                        'favoriteError' => __('Unable to update favorite. Please try again.', 'artpulse-management'),
                        'followError'   => __('Unable to update follow. Please try again.', 'artpulse-management'),
                    ],
                ]
            );
        }

        wp_enqueue_style(
            'ap-user-dashboard-css',
            plugins_url('assets/css/ap-user-dashboard.css', dirname(__DIR__, 2)),
            [],
            $version
        );
    }

    public static function renderProfileActionsWidget(): void
    {
        if (!is_user_logged_in()) {
            echo '<p>' . esc_html__('Please log in to manage your profile.', 'artpulse') . '</p>';

            return;
        }

        $user = wp_get_current_user();

        if (!$user instanceof WP_User) {
            echo '<p>' . esc_html__('Profile actions are currently unavailable.', 'artpulse') . '</p>';

            return;
        }

        $profile_actions = self::getProfileActionsForUser($user);

        self::enqueueAssets();

        $template = dirname(__DIR__, 2) . '/templates/dashboard/profile-actions-widget.php';

        if (!file_exists($template)) {
            if (empty($profile_actions)) {
                echo '<p>' . esc_html__('Profile actions are currently unavailable.', 'artpulse') . '</p>';

                return;
            }

            foreach ($profile_actions as $profile_action) {
                $label      = $profile_action['label'] ?? '';
                $create_url = $profile_action['create_url'] ?? '';
                $edit_url   = $profile_action['edit_url'] ?? '';

                if ($label !== '') {
                    printf('<h3 class="ap-dashboard-widget__section-title">%s</h3>', esc_html($label));
                }

                if ($create_url) {
                    printf(
                        '<p><a class="ap-dashboard-button ap-dashboard-button--primary" href="%1$s">%2$s</a></p>',
                        esc_url($create_url),
                        esc_html__('Create profile', 'artpulse')
                    );
                } else {
                    echo '<p>' . esc_html__('Profile creation is currently unavailable.', 'artpulse') . '</p>';
                }

                if ($edit_url) {
                    printf(
                        '<p><a class="ap-dashboard-button ap-dashboard-button--secondary" href="%1$s">%2$s</a></p>',
                        esc_url($edit_url),
                        esc_html__('Edit profile', 'artpulse')
                    );
                } else {
                    echo '<p>' . esc_html__('A profile has not been created yet.', 'artpulse') . '</p>';
                }
            }

            return;
        }

        $profile_actions_data = $profile_actions;

        include $template;
    }

    public static function registerRoutes(): void
    {
        register_rest_route(
            'artpulse/v1',
            '/dashboard',
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'getDashboard'],
                'permission_callback' => static function (WP_REST_Request $request): bool {
                    $role = sanitize_key($request->get_param('role'));

                    return self::currentUserCanAccess($role);
                },
                'args' => [
                    'role' => [
                        'type'     => 'string',
                        'required' => true,
                        'enum'     => self::enabledRoleSlugs(),
                    ],
                ],
            ]
        );
    }

    public static function getDashboard(WP_REST_Request $request): WP_REST_Response
    {
        $role = sanitize_key($request->get_param('role'));

        if (!self::currentUserCanAccess($role)) {
            return new WP_REST_Response(['message' => __('Access denied.', 'artpulse')], 403);
        }

        return rest_ensure_response(self::prepareDashboardData($role));
    }

    public static function prepareDashboardData(string $role, ?int $user_id = null): array
    {
        if (!array_key_exists($role, self::ROLE_CONFIG)) {
            return [];
        }

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return [];
        }

        $role_config = self::ROLE_CONFIG[$role] ?? [];
        $layout      = $role_config['layout'] ?? [];

        $favorites   = self::getFavorites($user_id);
        $follows     = self::getFollows($user_id);
        $post_types  = $role_config['post_types'] ?? [];
        $submissions = self::getSubmissions($user_id, $post_types, $role_config);

        $raw_upgrades   = [];
        $upgrades       = [];
        $upgrade_intro  = '';

        if ($role === 'member') {
            $raw_upgrades = self::getUpgradeOptions($user_id);

            $upgrade_data  = self::getUpgradeWidgetData($user_id);
            $upgrades      = $upgrade_data['upgrades'] ?? [];
            $upgrade_intro = $upgrade_data['intro'] ?? '';
        }

        $upgrade_links = self::mapUpgradeLinks($raw_upgrades);
        $profile       = self::getProfileSummary($user_id, $role);
        $journeys      = self::collectJourneys($role, $user_id, $upgrade_links);
        $quick_actions = self::buildQuickActions($role, $user_id, $role_config, $journeys);
        $notifications = self::buildNotifications($role, $journeys);

        $data = [
            'role'            => $role,
            'layout'          => $layout,
            'favorites'       => $favorites,
            'follows'         => $follows,
            'submissions'     => $submissions,
            'metrics'         => self::buildMetrics($favorites, $follows, $submissions),
            'profile'         => $profile,
            'journeys'        => $journeys,
            'quick_actions'   => $quick_actions,
            'notifications'   => $notifications,
            'upgrades'        => $upgrades,
            'upgrade_intro'   => $upgrade_intro,
            'available_roles' => self::collectAvailableRoles($user_id, $role),
        ];

        /**
         * Filter the prepared dashboard payload before it is rendered or returned via REST.
         *
         * @param array  $data    Dashboard payload.
         * @param string $role    Role slug.
         * @param int    $user_id Current user identifier.
         */
        return apply_filters('artpulse/dashboard/data', $data, $role, $user_id);
    }

    /**
     * Retrieve the upgrade widget data for a member dashboard context.
     */
    public static function getUpgradeWidgetData(int $user_id): array
    {
        if ($user_id <= 0) {
            return [
                'intro'    => '',
                'upgrades' => [],
            ];
        }

        $upgrades = self::getUpgradeOptions($user_id);
        $intro    = '';

        if (!empty($upgrades)) {
            $intro = __('Ready to take the next step? Unlock publishing tools tailored for artists and organizations.', 'artpulse');
        }

        $data = [
            'intro'    => $intro,
            'upgrades' => $upgrades,
        ];

        /**
         * Filters the upgrade widget data shown to members.
         *
         * @param array $data    {
         *     @type string $intro    Introductory copy shown above the upgrade options.
         *     @type array  $upgrades Upgrade option data structures.
         * }
         * @param int   $user_id The user identifier the data was generated for.
         */
        return apply_filters('artpulse/dashboard/member_upgrade_widget_data', $data, $user_id);
    }

    public static function userCanAccessRole(string $role, ?int $user_id = null): bool
    {
        if (!array_key_exists($role, self::ROLE_CONFIG)) {
            return false;
        }

        if (!self::isRoleEnabled($role)) {
            return false;
        }

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $user = get_user_by('id', $user_id);

        if (!$user instanceof WP_User) {
            return false;
        }

        if (user_can($user, 'manage_options') || user_can($user, 'view_artpulse_dashboard')) {
            return true;
        }

        return self::userCanViewRole($user, $role);
    }

    public static function getDefaultRoleForUser(?int $user_id = null): ?string
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return null;
        }

        $user = get_user_by('id', $user_id);

        if (!$user instanceof WP_User) {
            return null;
        }

        $roles   = array_keys(self::ROLE_CONFIG);
        $default = null;

        foreach ($roles as $role) {
            if (self::userCanViewRole($user, $role)) {
                $default = $role;
                break;
            }
        }

        if (!$default && (user_can($user, 'manage_options') || user_can($user, 'view_artpulse_dashboard'))) {
            $default = $roles[0] ?? null;
        }

        /**
         * Filters the default dashboard role for a user.
         *
         * @param string|null $default Default role slug or null if none detected.
         * @param WP_User     $user    The user object.
         */
        return apply_filters('artpulse/dashboard/default_role', $default, $user);
    }

    private static function renderDashboard(string $role): string
    {
        if (!self::isRoleEnabled($role)) {
            return '';
        }

        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        $user = wp_get_current_user();
        if (!self::userCanViewRole($user, $role)) {
            return '<div class="ap-dashboard-message">' . esc_html__('You do not have permission to view this dashboard.', 'artpulse') . '</div>';
        }

        $classes = sprintf('ap-role-dashboard ap-role-dashboard--%s', esc_attr($role));
        $loading = esc_html__('Loading dashboard…', 'artpulse');

        return sprintf('<div class="%1$s" data-ap-dashboard-role="%2$s"><div class="ap-dashboard-loading">%3$s</div></div>', $classes, esc_attr($role), $loading);
    }

    /**
     * Build a list of dashboard roles the user can switch between.
     */
    private static function collectAvailableRoles(int $user_id, string $active_role): array
    {
        $user = get_user_by('id', $user_id);

        if (!$user instanceof WP_User) {
            return [];
        }

        $available    = [];
        $labels       = self::getRoleLabels();
        $can_override = user_can($user, 'manage_options') || user_can($user, 'view_artpulse_dashboard');

        foreach (self::ROLE_CONFIG as $role => $config) {
            if (!self::isRoleEnabled($role)) {
                continue;
            }

            if (!$can_override && !self::userCanViewRole($user, $role)) {
                continue;
            }

            $available[] = [
                'role'    => $role,
                'label'   => $labels[$role]['title'] ?? ucfirst($role),
                'url'     => add_query_arg('role', $role, home_url('/dashboard/')),
                'current' => $role === $active_role,
            ];
        }

        return $available;
    }

    private static function getProfileActionsForUser(WP_User $user): array
    {
        $user_id      = (int) $user->ID;
        $actions      = [];
        $can_override = user_can($user, 'manage_options') || user_can($user, 'view_artpulse_dashboard');

        foreach (self::ROLE_CONFIG as $role => $config) {
            if (!self::isRoleEnabled($role)) {
                continue;
            }
            $post_type = $config['profile_post_type'] ?? '';

            if ($post_type === '') {
                continue;
            }

            if (!$can_override && !self::userCanViewRole($user, $role)) {
                continue;
            }

            $create_url = self::getSubmissionCreateUrl($post_type);
            $profile    = self::getUserProfilePost($user_id, $post_type);
            $edit_url   = '';

            if ($profile instanceof WP_Post) {
                $edit_link = get_edit_post_link($profile, '');

                if (is_string($edit_link)) {
                    $edit_url = $edit_link;
                }
            }

            $post_type_object = get_post_type_object($post_type);

            $actions[] = [
                'role'        => $role,
                'label'       => $post_type_object && isset($post_type_object->labels->singular_name)
                    ? $post_type_object->labels->singular_name
                    : ($config['title'] ?? ucfirst($role)),
                'post_type'   => $post_type,
                'create_url'  => $create_url,
                'edit_url'    => $edit_url,
                'has_profile' => $profile instanceof WP_Post,
            ];
        }

        return $actions;
    }

    private static function getUserProfilePost(int $user_id, string $post_type): ?WP_Post
    {
        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['publish', 'pending', 'draft', 'future'],
            'author'         => $user_id,
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        if (empty($posts) || !isset($posts[0]) || !$posts[0] instanceof WP_Post) {
            return null;
        }

        return $posts[0];
    }

    private static function renderDashboardWidget(array $data): string
    {
        $template = dirname(__DIR__, 2) . '/templates/dashboard/widget.php';

        if (!file_exists($template)) {
            return '<pre class="ap-dashboard-widget__data">' . esc_html(wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }

        ob_start();

        $dashboard = $data;
        $role      = $data['role'] ?? '';

        include $template;

        return (string) ob_get_clean();
    }

    /**
     * Render the upgrade widget container that wraps the shared upgrade section.
     *
     * @param array       $upgrades    Upgrades to display.
     * @param string      $intro       Introductory copy shown within the section.
     * @param string|null $title       Optional title for the upgrade section.
     * @param array       $widget_args Optional arguments for the widget wrapper. Supports 'title' and 'intro'.
     */
    public static function renderUpgradeWidget(array $upgrades, string $intro = '', ?string $title = null, array $widget_args = []): string
    {
        if (empty($upgrades)) {
            return '';
        }

        $template = dirname(__DIR__, 2) . '/templates/dashboard/upgrade-widget.php';

        $widget_title = isset($widget_args['title']) && is_string($widget_args['title'])
            ? $widget_args['title']
            : '';
        $widget_intro = isset($widget_args['intro']) && is_string($widget_args['intro'])
            ? $widget_args['intro']
            : '';

        if (!file_exists($template)) {
            $output = '<div class="ap-upgrade-widget ap-upgrade-widget--standalone">';

            if ($widget_title !== '') {
                $output .= sprintf('<h2 class="ap-upgrade-widget__title">%s</h2>', esc_html($widget_title));
            }

            if ($widget_intro !== '') {
                $output .= sprintf('<p class="ap-upgrade-widget__intro">%s</p>', esc_html($widget_intro));
            }

            $output .= self::renderUpgradeWidgetSection($upgrades, $intro, $title);
            $output .= '</div>';

            return $output;
        }

        ob_start();

        $widget_upgrades      = $upgrades;
        $widget_section_intro = $intro;
        $widget_section_title = $title;

        include $template;

        return (string) ob_get_clean();
    }

    /**
     * Render the shared upgrade widget section.
     */
    public static function renderUpgradeWidgetSection(array $upgrades, string $intro = '', ?string $title = null): string
    {
        if (empty($upgrades)) {
            return '';
        }

        $template = dirname(__DIR__, 2) . '/templates/dashboard/partials/upgrade-section.php';

        if (!file_exists($template)) {
            $title_text = $title ?? esc_html__('Membership Upgrades', 'artpulse');

            $output = sprintf(
                '<div class="ap-dashboard-widget__section ap-dashboard-widget__section--upgrades ap-upgrade-widget ap-upgrade-widget--inline"><h3 class="ap-upgrade-widget__heading">%s</h3>',
                esc_html($title_text)
            );

            if ($intro !== '') {
                $output .= sprintf('<p class="ap-upgrade-widget__intro">%s</p>', esc_html($intro));
            }

            $output .= '<div class="ap-upgrade-widget__list">';

            foreach ($upgrades as $upgrade) {
                $url = $upgrade['url'] ?? '';

                if ($url === '') {
                    continue;
                }

                $title_markup = '';
                if (!empty($upgrade['title'])) {
                    $title_markup = sprintf('<h4 class="ap-upgrade-widget__card-title">%s</h4>', esc_html($upgrade['title']));
                }

                $description_markup = '';
                if (!empty($upgrade['description'])) {
                    $description_markup = sprintf('<p class="ap-upgrade-widget__card-description">%s</p>', esc_html($upgrade['description']));
                }

                $actions_markup = sprintf(
                    '<a class="ap-dashboard-button ap-dashboard-button--primary ap-upgrade-widget__cta" href="%1$s">%2$s</a>',
                    esc_url($url),
                    esc_html($upgrade['cta'] ?? __('Upgrade now', 'artpulse'))
                );

                if (!empty($upgrade['secondary_actions']) && is_array($upgrade['secondary_actions'])) {
                    foreach ($upgrade['secondary_actions'] as $secondary_action) {
                        $secondary_url = $secondary_action['url'] ?? '';

                        if ($secondary_url === '') {
                            continue;
                        }

                        $secondary_label = $secondary_action['label'] ?? __('Learn more', 'artpulse');

                        $secondary_markup  = '<div class="ap-upgrade-widget__secondary-action">';

                        if (!empty($secondary_action['title'])) {
                            $secondary_markup .= sprintf('<h5 class="ap-upgrade-widget__secondary-title">%s</h5>', esc_html($secondary_action['title']));
                        }

                        if (!empty($secondary_action['description'])) {
                            $secondary_markup .= sprintf('<p class="ap-upgrade-widget__secondary-description">%s</p>', esc_html($secondary_action['description']));
                        }

                        $secondary_markup .= sprintf(
                            '<a class="ap-dashboard-button ap-dashboard-button--secondary ap-upgrade-widget__cta ap-upgrade-widget__cta--secondary" href="%1$s">%2$s</a>',
                            esc_url($secondary_url),
                            esc_html($secondary_label)
                        );

                        $secondary_markup .= '</div>';

                        $actions_markup .= $secondary_markup;
                    }
                }

                $output .= sprintf(
                    '<article class="ap-dashboard-card ap-upgrade-widget__card"><div class="ap-dashboard-card__body ap-upgrade-widget__card-body">%1$s%2$s</div><div class="ap-dashboard-card__actions ap-upgrade-widget__card-actions">%3$s</div></article>',
                    $title_markup,
                    $description_markup,
                    $actions_markup
                );
            }

            $output .= '</div></div>';

            return $output;
        }

        ob_start();

        $section_title    = $title ?? esc_html__('Membership Upgrades', 'artpulse');
        $section_intro    = $intro;
        $section_upgrades = $upgrades;

        include $template;

        return (string) ob_get_clean();
    }

    private static function currentUserCanCreateEvents(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $capabilities = [
            'create_artpulse_events',
            'create_artpulse_event',
            'edit_artpulse_event',
            'edit_artpulse_events',
        ];

        foreach ($capabilities as $capability) {
            if (current_user_can($capability)) {
                return true;
            }
        }

        return false;
    }

    private static function locateFrontendEventSubmissionPage(): int
    {
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        if (empty($pages)) {
            return 0;
        }

        foreach ($pages as $page_id) {
            $content = get_post_field('post_content', $page_id);

            if (!is_string($content) || $content === '') {
                continue;
            }

            if (has_shortcode($content, 'ap_submit_event')) {
                return (int) $page_id;
            }

            if (!has_shortcode($content, 'ap_submission_form')) {
                continue;
            }

            $needs_match = ['post_type="artpulse_event"', "post_type='artpulse_event'", 'post_type=artpulse_event'];

            foreach ($needs_match as $needle) {
                if (stripos($content, $needle) !== false) {
                    return (int) $page_id;
                }
            }
        }

        return 0;
    }

    private static function getSubmissionCreateUrl(string $post_type): string
    {
        $page_id = self::locateFrontendSubmissionPageForPostType($post_type);

        if ($page_id) {
            $permalink = get_permalink($page_id);

            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        $post_type_object = get_post_type_object($post_type);

        if ($post_type_object && $post_type_object->show_ui) {
            return admin_url(add_query_arg('post_type', $post_type, 'post-new.php'));
        }

        return '';
    }

    private static function locateFrontendSubmissionPageForPostType(string $post_type): int
    {
        static $cache = [];

        if (array_key_exists($post_type, $cache)) {
            return $cache[$post_type];
        }

        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        if (!empty($pages)) {
            $pattern = sprintf('/\\[ap_submission_form\\b[^\]]*post_type\\s*=\\s*(\"|\')?%s\1?/i', preg_quote($post_type, '/'));

            foreach ($pages as $page_id) {
                $content = get_post_field('post_content', $page_id);

                if (!is_string($content) || $content === '') {
                    continue;
                }

                if (!has_shortcode($content, 'ap_submission_form')) {
                    continue;
                }

                if (preg_match($pattern, $content)) {
                    $cache[$post_type] = (int) $page_id;

                    return $cache[$post_type];
                }
            }
        }

        $cache[$post_type] = 0;

        return $cache[$post_type];
    }

    private static function currentUserCanAccess(string $role): bool
    {
        return self::userCanAccessRole($role);
    }

    private static function userCanViewRole(WP_User $user, string $role): bool
    {
        $config = self::ROLE_CONFIG[$role] ?? null;
        if (!$config) {
            return false;
        }

        if (!self::isRoleEnabled($role)) {
            return false;
        }

        if (in_array($role, (array) $user->roles, true)) {
            return user_can($user, $config['capability']);
        }

        return false;
    }

    private static function isRoleEnabled(string $role): bool
    {
        $config = self::ROLE_CONFIG[$role] ?? [];
        $flag   = $config['feature_flag'] ?? '';

        if ($flag === '') {
            return true;
        }

        return (bool) get_option($flag, true);
    }

    private static function enabledRoleSlugs(): array
    {
        $slugs = [];

        foreach (array_keys(self::ROLE_CONFIG) as $role) {
            if (self::isRoleEnabled($role)) {
                $slugs[] = $role;
            }
        }

        return $slugs;
    }

    private static function getFavorites(int $user_id): array
    {
        if (!class_exists(FavoritesManager::class)) {
            return [];
        }

        $favorites = FavoritesManager::get_user_favorites($user_id) ?: [];
        $output    = [];

        foreach ($favorites as $favorite) {
            $post = get_post($favorite->object_id);
            if (!$post) {
                continue;
            }

            $output[] = array_merge(
                self::formatPostForResponse($post),
                [
                    'object_type'  => $favorite->object_type,
                    'favorited_on' => $favorite->favorited_on ? mysql2date(DATE_ATOM, $favorite->favorited_on, false) : null,
                ]
            );
        }

        return $output;
    }

    private static function getFollows(int $user_id): array
    {
        if (!class_exists(FollowManager::class)) {
            return [];
        }

        $follows = FollowManager::get_user_follows($user_id) ?: [];
        $output  = [];

        foreach ($follows as $follow) {
            $post = get_post($follow->object_id);
            if (!$post) {
                continue;
            }

            $output[] = array_merge(
                self::formatPostForResponse($post),
                [
                    'object_type' => $follow->object_type,
                    'followed_on' => $follow->followed_on ? mysql2date(DATE_ATOM, $follow->followed_on, false) : null,
                    'following'   => true,
                ]
            );
        }

        return $output;
    }

    private static function getSubmissions(int $user_id, array $post_types, array $role_config = []): array
    {
        $submissions = [];
        $profile_post_type = $role_config['profile_post_type'] ?? null;

        foreach ($post_types as $post_type) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'post_status'    => ['publish', 'pending', 'draft', 'future'],
                'author'         => $user_id,
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);

            $items         = [];
            $status_counts = [];
            $create_url    = '';

            if ($profile_post_type && $profile_post_type === $post_type) {
                $create_url = self::getSubmissionCreateUrl($post_type);
            }

            foreach ($posts as $post) {
                $items[] = self::formatPostForResponse($post);
                $status  = $post->post_status;
                $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
            }

            $post_type_object = get_post_type_object($post_type);

            $submissions[$post_type] = [
                'label'  => $post_type_object ? $post_type_object->labels->name : $post_type,
                'items'  => $items,
                'counts' => $status_counts,
            ];

            if ($create_url) {
                $submissions[$post_type]['create_url'] = $create_url;
            }
        }

        return $submissions;
    }

    private static function buildMetrics(array $favorites, array $follows, array $submissions): array
    {
        $total_submissions   = 0;
        $pending_submissions = 0;
        $published           = 0;

        foreach ($submissions as $submission) {
            $items = $submission['items'] ?? [];
            $total_submissions += count($items);
            $counts = $submission['counts'] ?? [];
            $pending_submissions += (int) ($counts['pending'] ?? 0);
            $published           += (int) ($counts['publish'] ?? 0);
        }

        return [
            'favorites'            => count($favorites),
            'follows'              => count($follows),
            'submissions'          => $total_submissions,
            'pending_submissions'  => $pending_submissions,
            'published_submissions'=> $published,
        ];
    }

    private static function mapUpgradeLinks(array $raw_upgrades): array
    {
        $links = [];

        foreach ($raw_upgrades as $upgrade) {
            $slug = isset($upgrade['slug']) ? (string) $upgrade['slug'] : '';

            if ($slug === '') {
                continue;
            }

            $url = isset($upgrade['url']) ? (string) $upgrade['url'] : '';

            if ($url === '') {
                continue;
            }

            $links[$slug] = $url;
        }

        return $links;
    }

    private static function collectJourneys(string $role, int $user_id, array $upgrade_links): array
    {
        $journeys = [];

        if (in_array($role, ['member', 'artist'], true)) {
            $journeys['artist'] = self::buildJourneyState(
                'artist',
                $user_id,
                'artpulse_artist',
                [
                    'builder'   => home_url('/artist-builder/'),
                    'dashboard' => add_query_arg('role', 'artist', home_url('/dashboard/')),
                    'public'    => '',
                    'upgrade'   => $upgrade_links['artist'] ?? '',
                ]
            );
        }

        if (in_array($role, ['member', 'organization'], true)) {
            $journeys['organization'] = self::buildJourneyState(
                'organization',
                $user_id,
                'artpulse_org',
                [
                    'builder'   => home_url('/org-builder/'),
                    'dashboard' => add_query_arg('role', 'organization', home_url('/dashboard/')),
                    'public'    => '',
                    'upgrade'   => $upgrade_links['organization'] ?? '',
                ]
            );
        }

        return $journeys;
    }

    private static function buildQuickActions(string $role, int $user_id, array $role_config, array $journeys): array
    {
        $actions     = [];
        $action_keys = $role_config['layout']['quick_actions'] ?? [];

        foreach ($action_keys as $key) {
            switch ($key) {
                case 'artist_profile':
                    if (isset($journeys['artist'])) {
                        $actions[] = self::formatJourneyQuickAction($journeys['artist'], $role);
                    }
                    break;
                case 'organization_profile':
                    if (isset($journeys['organization'])) {
                        $actions[] = self::formatJourneyQuickAction($journeys['organization'], $role);
                    }
                    break;
                case 'submit_event':
                    $submit_event = self::buildSubmitEventAction($role, $user_id, $journeys);
                    if ($submit_event !== null) {
                        $actions[] = $submit_event;
                    }
                    break;
                case 'view_profile':
                    $view_profile = self::buildViewProfileAction($role, $journeys);
                    if ($view_profile !== null) {
                        $actions[] = $view_profile;
                    }
                    break;
            }
        }

        return array_values(array_filter($actions));
    }

    private static function buildNotifications(string $role, array $journeys): array
    {
        $notifications = [];

        if (isset($journeys['organization']['review'])) {
            $review = $journeys['organization']['review'];
            $anchor = $journeys['organization']['anchor'] ?? '';

            if (($review['status'] ?? '') === 'pending') {
                $notifications[] = [
                    'type'    => 'info',
                    'message' => __('Your organization upgrade request is pending review.', 'artpulse-management'),
                    'anchor'  => $anchor,
                ];
            } elseif (($review['status'] ?? '') === 'denied') {
                $message = __('Your organization upgrade request was denied.', 'artpulse-management');

                if (!empty($review['reason'])) {
                    $message .= ' ' . sprintf(__('Reason: %s', 'artpulse-management'), $review['reason']);
                }

                $notifications[] = [
                    'type'    => 'warning',
                    'message' => $message,
                    'anchor'  => $anchor,
                ];
            }
        }

        return $notifications;
    }

    private static function summarizePortfolio(int $user_id, string $post_type): array
    {
        $snapshot = [
            'post_type'        => $post_type,
            'status'           => 'missing',
            'status_label'     => __('Not started', 'artpulse-management'),
            'progress_percent' => 0,
            'badge_variant'    => 'muted',
            'post_id'          => null,
            'post_ids'         => [],
            'post_status'      => '',
            'title'            => '',
            'permalink'        => '',
            'total'            => 0,
            'has_published'    => false,
            'has_unpublished'  => false,
        ];

        $ids = PortfolioAccess::get_owned_portfolio_ids($user_id, $post_type);
        $snapshot['post_ids'] = $ids;
        $snapshot['total']    = count($ids);

        if (empty($ids)) {
            return $snapshot;
        }

        $posts = array_filter(
            array_map('get_post', $ids),
            static fn($post) => $post instanceof WP_Post
        );

        if (empty($posts)) {
            return $snapshot;
        }

        $status_priority = ['publish', 'future', 'pending', 'draft'];
        $selected        = null;

        foreach ($status_priority as $status) {
            foreach ($posts as $post) {
                if ($post->post_status === $status) {
                    $selected = $post;
                    break 2;
                }
            }
        }

        if (!$selected) {
            $selected = $posts[0];
        }

        $status_meta = [
            'publish' => [
                'status'        => 'published',
                'label'         => __('Published', 'artpulse-management'),
                'progress'      => 100,
                'badge_variant' => 'success',
            ],
            'future' => [
                'status'        => 'scheduled',
                'label'         => __('Scheduled', 'artpulse-management'),
                'progress'      => 95,
                'badge_variant' => 'info',
            ],
            'pending' => [
                'status'        => 'pending',
                'label'         => __('Pending review', 'artpulse-management'),
                'progress'      => 80,
                'badge_variant' => 'warning',
            ],
            'draft' => [
                'status'        => 'draft',
                'label'         => __('Draft', 'artpulse-management'),
                'progress'      => 45,
                'badge_variant' => 'info',
            ],
        ];

        $selected_status = $selected->post_status;
        $meta            = $status_meta[$selected_status] ?? [
            'status'        => 'draft',
            'label'         => __('In progress', 'artpulse-management'),
            'progress'      => 45,
            'badge_variant' => 'info',
        ];

        $snapshot['status']           = $meta['status'];
        $snapshot['status_label']     = $meta['label'];
        $snapshot['progress_percent'] = (int) $meta['progress'];
        $snapshot['badge_variant']    = $meta['badge_variant'];
        $snapshot['post_id']          = (int) $selected->ID;
        $snapshot['post_status']      = $selected_status;
        $snapshot['title']            = get_the_title($selected);

        $permalink = get_permalink($selected);
        if (is_string($permalink)) {
            $snapshot['permalink'] = $permalink;
        }

        $statuses = array_map(
            static fn(WP_Post $post): string => $post->post_status,
            $posts
        );

        $snapshot['has_published']   = in_array('publish', $statuses, true);
        $snapshot['has_unpublished'] = !empty(array_intersect($statuses, ['draft', 'pending']));

        return $snapshot;
    }

    private static function buildJourneyState(string $journey, int $user_id, string $post_type, array $links): array
    {
        $snapshot = self::summarizePortfolio($user_id, $post_type);

        if (!empty($snapshot['permalink'])) {
            $links['public'] = $snapshot['permalink'];
        }

        $has_access = 'artist' === $journey
            ? user_can($user_id, 'edit_artpulse_artist')
            : user_can($user_id, 'edit_artpulse_org');

        $status       = 'locked';
        $status_label = __('Access required', 'artpulse-management');
        $badge        = [
            'label'   => __('Locked', 'artpulse-management'),
            'variant' => 'muted',
        ];
        $description  = 'artist' === $journey
            ? __('Request artist access to create and publish your portfolio.', 'artpulse-management')
            : __('Request organization access to manage collective profiles and events.', 'artpulse-management');
        $cta = [
            'label'    => __('Request access', 'artpulse-management'),
            'url'      => $links['upgrade'] ?? '',
            'variant'  => 'secondary',
            'disabled' => empty($links['upgrade']),
        ];
        $anchor = sprintf('#ap-journey-%s', $journey);
        $review = [];

        if ('organization' === $journey) {
            $review = self::getOrgUpgradeReviewState($user_id);
        }

        if ($has_access) {
            $status       = $snapshot['status'];
            $status_label = $snapshot['status_label'];
            $badge        = [
                'label'   => $snapshot['status_label'],
                'variant' => $snapshot['badge_variant'],
            ];
            $description = 'artist' === $journey
                ? __('Use the builder to fine-tune your story, media, and links.', 'artpulse-management')
                : __('Complete your organization profile and collaborate on upcoming events.', 'artpulse-management');
            $cta = [
                'label'    => __('Open builder', 'artpulse-management'),
                'url'      => $links['builder'] ?? '',
                'variant'  => 'primary',
                'disabled' => empty($links['builder']),
            ];

            if (in_array($snapshot['status'], ['published', 'scheduled'], true)) {
                $status       = 'published';
                $status_label = __('Published', 'artpulse-management');
                $badge        = [
                    'label'   => __('Published', 'artpulse-management'),
                    'variant' => 'success',
                ];
                $cta = [
                    'label'    => __('Open dashboard', 'artpulse-management'),
                    'url'      => $links['dashboard'] ?? '',
                    'variant'  => 'primary',
                    'disabled' => empty($links['dashboard']),
                ];
            } elseif ('pending' === $snapshot['status']) {
                $status       = 'pending_review';
                $status_label = __('Pending review', 'artpulse-management');
                $badge        = [
                    'label'   => __('Pending', 'artpulse-management'),
                    'variant' => 'warning',
                ];
                $cta['label'] = __('Review details', 'artpulse-management');
            } elseif ('draft' === $snapshot['status']) {
                $status       = 'in_progress';
                $status_label = __('Draft in progress', 'artpulse-management');
                $badge        = [
                    'label'   => __('Draft', 'artpulse-management'),
                    'variant' => 'info',
                ];
                $cta['label'] = __('Continue in builder', 'artpulse-management');
            }
        } else {
            if ('organization' === $journey) {
                if (($review['status'] ?? '') === 'pending') {
                    $status       = 'pending_request';
                    $status_label = __('Upgrade request pending', 'artpulse-management');
                    $badge        = [
                        'label'   => __('Pending review', 'artpulse-management'),
                        'variant' => 'warning',
                    ];
                    $description = __('We are reviewing your organization request. We will email you once it is approved.', 'artpulse-management');
                    $cta = [
                        'label'    => __('Check request status', 'artpulse-management'),
                        'url'      => $anchor,
                        'variant'  => 'secondary',
                        'disabled' => false,
                    ];
                } elseif (($review['status'] ?? '') === 'denied') {
                    $status       = 'denied';
                    $status_label = __('Request denied', 'artpulse-management');
                    $badge        = [
                        'label'   => __('Action required', 'artpulse-management'),
                        'variant' => 'danger',
                    ];
                    $description = __('Your last organization request was denied. Review the feedback and resubmit when you are ready.', 'artpulse-management');
                    $cta = [
                        'label'    => __('Review feedback', 'artpulse-management'),
                        'url'      => $anchor,
                        'variant'  => 'secondary',
                        'disabled' => false,
                    ];
                }
            }
        }

        return [
            'slug'             => $journey,
            'label'            => 'artist' === $journey
                ? __('Artist profile', 'artpulse-management')
                : __('Organization profile', 'artpulse-management'),
            'status'           => $status,
            'status_label'     => $status_label,
            'badge'            => $badge,
            'progress_percent' => (int) ($snapshot['progress_percent'] ?? 0),
            'portfolio'        => $snapshot,
            'links'            => $links,
            'description'      => $description,
            'cta'              => $cta,
            'anchor'           => $anchor,
            'review'           => $review,
        ];
    }

    private static function getOrgUpgradeReviewState(int $user_id): array
    {
        $state = [
            'status'     => 'none',
            'reason'     => '',
            'request_id' => 0,
            'org_id'     => 0,
            'updated_at' => null,
        ];

        $request = UpgradeReviewRepository::get_latest_for_user($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);

        if (!$request instanceof WP_Post) {
            return $state;
        }

        $state['request_id'] = (int) $request->ID;
        $state['org_id']     = UpgradeReviewRepository::get_post_id($request);
        $state['reason']     = UpgradeReviewRepository::get_reason($request);
        $state['updated_at'] = get_post_modified_time('U', true, $request);

        $status = UpgradeReviewRepository::get_status($request);

        if ($status === UpgradeReviewRepository::STATUS_APPROVED) {
            $state['status'] = 'approved';
        } elseif ($status === UpgradeReviewRepository::STATUS_DENIED) {
            $state['status'] = 'denied';
        } else {
            $state['status'] = 'pending';
        }

        return $state;
    }

    private static function formatJourneyQuickAction(array $journey, string $role): array
    {
        $progress = max(0, min(100, (int) ($journey['progress_percent'] ?? 0)));
        $cta       = $journey['cta'] ?? [];
        $badge     = $journey['badge'] ?? [];

        $cta += [
            'label'    => '',
            'url'      => '',
            'variant'  => 'secondary',
            'disabled' => false,
        ];

        $badge += [
            'label'   => '',
            'variant' => 'muted',
        ];

        return [
            'slug'             => sprintf('journey_%s', $journey['slug'] ?? 'profile'),
            'title'            => $journey['label'] ?? '',
            'description'      => $journey['description'] ?? '',
            'status'           => $journey['status'] ?? 'locked',
            'status_label'     => $journey['status_label'] ?? '',
            'badge'            => $badge,
            'progress_percent' => $progress,
            'cta'              => $cta,
            'anchor'           => $journey['anchor'] ?? '',
            'links'            => $journey['links'] ?? [],
            'meta'             => [
                'role'    => $role,
                'journey' => $journey['slug'] ?? '',
            ],
        ];
    }

    private static function buildSubmitEventAction(string $role, int $user_id, array $journeys): ?array
    {
        $base_url        = home_url('/submit-event/');
        $description     = __('Share upcoming events with the ArtPulse community.', 'artpulse-management');
        $enabled         = false;
        $status_label    = __('Locked', 'artpulse-management');
        $badge           = [
            'label'   => __('Locked', 'artpulse-management'),
            'variant' => 'muted',
        ];
        $disabled_reason = __('Publish a profile to unlock event submissions.', 'artpulse-management');
        $target_journey  = null;

        if ('organization' === $role) {
            $target_journey = $journeys['organization'] ?? null;
        } elseif ('artist' === $role) {
            $target_journey = $journeys['artist'] ?? null;
        } else {
            $organization = $journeys['organization'] ?? null;
            $artist       = $journeys['artist'] ?? null;

            if ($organization && in_array($organization['portfolio']['status'] ?? '', ['published', 'scheduled'], true)) {
                $target_journey = $organization;
            } elseif ($artist && in_array($artist['portfolio']['status'] ?? '', ['published', 'scheduled'], true)) {
                $target_journey = $artist;
            }
        }

        $cta_url = $base_url;

        if ($target_journey) {
            $status = $target_journey['portfolio']['status'] ?? 'missing';

            if (in_array($status, ['published', 'scheduled'], true)) {
                $enabled      = true;
                $status_label = __('Ready', 'artpulse-management');
                $badge        = [
                    'label'   => __('Ready', 'artpulse-management'),
                    'variant' => 'success',
                ];
                $portfolio = $target_journey['portfolio'] ?? [];
                $post_id   = $portfolio['post_id'] ?? 0;

                if ($post_id) {
                    $cta_url = add_query_arg(
                        'organization' === ($target_journey['slug'] ?? '') ? 'org_id' : 'artist_id',
                        (int) $post_id,
                        $base_url
                    );
                }

                $disabled_reason = '';
            } else {
                $disabled_reason = __('Publish your profile to unlock event submissions.', 'artpulse-management');
            }
        }

        return [
            'slug'             => 'submit_event',
            'title'            => __('Submit an event', 'artpulse-management'),
            'description'      => $description,
            'badge'            => $badge,
            'status'           => $enabled ? 'ready' : 'locked',
            'status_label'     => $status_label,
            'progress_percent' => $enabled ? 100 : 0,
            'cta'              => [
                'label'    => __('Submit an event', 'artpulse-management'),
                'url'      => $cta_url,
                'variant'  => $enabled ? 'primary' : 'secondary',
                'disabled' => !$enabled,
            ],
            'disabled_reason'  => $disabled_reason,
        ];
    }

    private static function buildViewProfileAction(string $role, array $journeys): ?array
    {
        $target = null;

        if ('organization' === $role) {
            $target = $journeys['organization'] ?? null;
        } elseif ('artist' === $role) {
            $target = $journeys['artist'] ?? null;
        } else {
            return null;
        }

        if (!$target) {
            return null;
        }

        $portfolio = $target['portfolio'] ?? [];
        $status    = $portfolio['status'] ?? 'missing';
        $permalink = $portfolio['permalink'] ?? '';

        $enabled = $permalink !== '' && in_array($status, ['published', 'scheduled'], true);

        $badge = [
            'label'   => $enabled ? __('Live', 'artpulse-management') : __('Preview only', 'artpulse-management'),
            'variant' => $enabled ? 'success' : 'muted',
        ];

        return [
            'slug'             => 'view_profile',
            'title'            => __('View public profile', 'artpulse-management'),
            'description'      => __('Open your public page to confirm how the community sees your profile.', 'artpulse-management'),
            'badge'            => $badge,
            'status'           => $enabled ? 'ready' : 'locked',
            'status_label'     => $enabled ? __('Live', 'artpulse-management') : __('Not yet published', 'artpulse-management'),
            'progress_percent' => $enabled ? 100 : 0,
            'cta'              => [
                'label'    => __('View profile', 'artpulse-management'),
                'url'      => $enabled ? $permalink : ($target['links']['builder'] ?? ''),
                'variant'  => 'secondary',
                'disabled' => !$enabled,
            ],
            'disabled_reason'  => $enabled ? '' : __('Publish your profile to share it publicly.', 'artpulse-management'),
        ];
    }

    private static function getProfileSummary(int $user_id, string $role): array
    {
        $user    = get_user_by('id', $user_id);
        $expires = get_user_meta($user_id, 'ap_membership_expires', true);

        return [
            'id'            => $user_id,
            'display_name'  => $user ? $user->display_name : '',
            'email'         => $user ? $user->user_email : '',
            'roles'         => $user ? array_values((array) $user->roles) : [],
            'role'          => $role,
            'avatar'        => get_avatar_url($user_id, ['size' => 128]),
            'bio'           => get_user_meta($user_id, 'description', true),
            'profile_url'   => get_author_posts_url($user_id),
            'membership'    => [
                'level'              => get_user_meta($user_id, 'ap_membership_level', true),
                'expires_timestamp'  => $expires ? (int) $expires : null,
                'expires_display'    => $expires ? date_i18n(get_option('date_format'), (int) $expires) : null,
            ],
        ];
    }

    private static function getUpgradeOptions(int $user_id): array
    {
        $current_level = strtolower((string) get_user_meta($user_id, 'ap_membership_level', true));

        $options = [
            'artist' => [
                'level'       => 'Pro',
                'title'       => __('Upgrade to Artist', 'artpulse'),
                'description' => __('Showcase your portfolio, get discovered, and unlock artist-only publishing tools.', 'artpulse'),
                'cta'         => __('Become an Artist', 'artpulse'),
            ],
            'organization' => [
                'level'       => 'Org',
                'title'       => __('Upgrade to Organization', 'artpulse'),
                'description' => __('Promote your events, highlight members, and grow your creative community.', 'artpulse'),
                'cta'         => __('Upgrade to Organization', 'artpulse'),
            ],
        ];

        $upgrades = [];

        foreach ($options as $slug => $option) {
            $level = $option['level'] ?? '';

            if ($level && strtolower($level) === $current_level) {
                continue;
            }

            $url = MembershipUrls::getPurchaseUrl($level);

            if ($url === '') {
                continue;
            }

            $upgrades[] = [
                'slug'        => $slug,
                'title'       => $option['title'] ?? '',
                'description' => $option['description'] ?? '',
                'cta'         => $option['cta'] ?? __('Upgrade now', 'artpulse'),
                'url'         => $url,
            ];
        }

        return self::mergeOrganizationUpgradeIntoArtistCard($upgrades);
    }

    private static function mergeOrganizationUpgradeIntoArtistCard(array $upgrades): array
    {
        $organizationIndex = null;
        $artistIndex       = null;

        foreach ($upgrades as $index => $upgrade) {
            $slug = $upgrade['slug'] ?? '';

            if ($slug === 'organization') {
                $organizationIndex = $index;
            }

            if ($slug === 'artist') {
                $artistIndex = $index;
            }
        }

        if ($organizationIndex === null || $artistIndex === null) {
            return array_values($upgrades);
        }

        $organizationUpgrade = $upgrades[$organizationIndex] ?? [];
        $organizationUrl     = $organizationUpgrade['url'] ?? '';

        if ($organizationUrl === '') {
            return array_values($upgrades);
        }

        $secondaryAction = [
            'label' => $organizationUpgrade['cta'] ?? __('Upgrade to Organization', 'artpulse'),
            'url'   => $organizationUrl,
        ];

        if (!empty($organizationUpgrade['title'])) {
            $secondaryAction['title'] = $organizationUpgrade['title'];
        }

        if (!empty($organizationUpgrade['description'])) {
            $secondaryAction['description'] = $organizationUpgrade['description'];
        }

        $existingSecondary = $upgrades[$artistIndex]['secondary_actions'] ?? [];
        $existingSecondary[] = $secondaryAction;
        $upgrades[$artistIndex]['secondary_actions'] = $existingSecondary;

        unset($upgrades[$organizationIndex]);

        return array_values($upgrades);
    }

    private static function formatPostForResponse($post): array
    {
        $post      = get_post($post);
        $type      = $post ? get_post_type_object($post->post_type) : null;
        $thumbnail = $post ? get_the_post_thumbnail_url($post, 'medium') : false;
        $edit_link = $post ? get_edit_post_link($post->ID, '') : false;

        if (!$post) {
            return [];
        }

        return [
            'id'         => (int) $post->ID,
            'title'      => get_the_title($post),
            'permalink'  => get_permalink($post),
            'status'     => $post->post_status,
            'date'       => get_post_time(DATE_ATOM, true, $post),
            'post_type'  => $post->post_type,
            'type_label' => $type ? $type->labels->singular_name : $post->post_type,
            'thumbnail'  => $thumbnail ?: null,
            'edit_url'   => $edit_link ?: null,
        ];
    }

    private static function getRoleLabels(): array
    {
        return [
            'member' => [
                'title'       => __('Member Dashboard', 'artpulse'),
                'profile'     => __('Member Profile', 'artpulse'),
                'metrics'     => __('Member Metrics', 'artpulse'),
                'favorites'   => __('Saved Favorites', 'artpulse'),
                'follows'     => __('Following', 'artpulse'),
                'submissions' => __('Your Submissions', 'artpulse'),
                'upgrades'    => __('Membership Upgrades', 'artpulse'),
            ],
            'artist' => [
                'title'       => __('Artist Dashboard', 'artpulse'),
                'profile'     => __('Artist Profile', 'artpulse'),
                'metrics'     => __('Artist Metrics', 'artpulse'),
                'favorites'   => __('Collector Favorites', 'artpulse'),
                'follows'     => __('Followers', 'artpulse'),
                'submissions' => __('Portfolio Updates', 'artpulse'),
            ],
            'organization' => [
                'title'       => __('Organization Dashboard', 'artpulse'),
                'profile'     => __('Organization Profile', 'artpulse'),
                'metrics'     => __('Organization Metrics', 'artpulse'),
                'favorites'   => __('Audience Favorites', 'artpulse'),
                'follows'     => __('Supporters', 'artpulse'),
                'submissions' => __('Organization Posts', 'artpulse'),
            ],
        ];
    }
}
