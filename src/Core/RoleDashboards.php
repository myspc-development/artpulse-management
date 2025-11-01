<?php

namespace ArtPulse\Core;

use ArtPulse\Community\FavoritesManager;
use ArtPulse\Community\FollowManager;
use ArtPulse\Core\ProfileLinkHelpers;
use ArtPulse\Core\ProfileState;
use ArtPulse\Frontend\ArtistRequestStatusRoute;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;
use function add_query_arg;
use function array_filter;
use function array_map;
use function esc_url_raw;
use function get_post;
use function get_post_meta;
use function get_permalink;
use function get_the_title;
use function in_array;
use function is_array;
use function is_user_logged_in;
use function rest_authorization_required_code;
use function sanitize_key;
use function wp_verify_nonce;

class RoleDashboards
{
    private static $shortcode_assets_registered = false;

    public static function init(): void
    {
        remove_shortcode('ap_member_dashboard');
        remove_shortcode('ap_artist_dashboard');
        remove_shortcode('ap_organization_dashboard');

        add_action('wp_enqueue_scripts', [self::class, 'register_shortcode_assets']);

        add_shortcode('ap_member_dashboard', [self::class, 'render_member_dashboard_shortcode']);
        add_shortcode('ap_artist_dashboard', [self::class, 'render_artist_dashboard_shortcode']);
        add_shortcode('ap_org_dashboard', [self::class, 'render_org_dashboard_shortcode']);
        add_shortcode('ap_organization_dashboard', [self::class, 'render_org_dashboard_shortcode']);
        add_shortcode('ap_dashboard', [self::class, 'render_router_shortcode']);
    }

    public static function register_shortcode_assets(): void
    {
        if (self::$shortcode_assets_registered) {
            return;
        }

        $plugin_dir  = defined('ARTPULSE_PLUGIN_DIR') ? ARTPULSE_PLUGIN_DIR : dirname(dirname(__DIR__)) . '/';
        $plugin_file = defined('ARTPULSE_PLUGIN_FILE') ? ARTPULSE_PLUGIN_FILE : $plugin_dir . 'artpulse-management.php';

        $base_dir = $plugin_dir . 'assets/dashboard/';
        $base_url = plugins_url('assets/dashboard/', $plugin_file);

        $js_path  = $base_dir . 'ap-dashboard.js';
        $css_path = $base_dir . 'ap-dashboard.css';

        $version = defined('ARTPULSE_VERSION') ? ARTPULSE_VERSION : '1.0.0';

        wp_register_script(
            'ap-dashboard',
            $base_url . 'ap-dashboard.js',
            ['wp-api-fetch'],
            file_exists($js_path) ? filemtime($js_path) : $version,
            true
        );

        wp_register_style(
            'ap-dashboard',
            $base_url . 'ap-dashboard.css',
            [],
            file_exists($css_path) ? filemtime($css_path) : $version
        );

        self::$shortcode_assets_registered = true;
    }

    public static function render_member_dashboard_shortcode($atts = [], $content = '', $tag = ''): string
    {
        return self::render_dashboard_shortcode('member');
    }

    public static function render_artist_dashboard_shortcode($atts = [], $content = '', $tag = ''): string
    {
        return self::render_dashboard_shortcode('artist');
    }

    public static function render_org_dashboard_shortcode($atts = [], $content = '', $tag = ''): string
    {
        return self::render_dashboard_shortcode('org');
    }

    public static function render_router_shortcode($atts = [], $content = '', $tag = ''): string
    {
        $roles = ['org', 'artist', 'member'];

        foreach ($roles as $role) {
            if (RoleGate::user_can_access($role)) {
                return self::render_dashboard_shortcode($role);
            }
        }

        return self::render_unauthorized_message();
    }

    private static function render_dashboard_shortcode(string $role): string
    {
        if (!RoleGate::user_can_access($role)) {
            return self::render_unauthorized_message();
        }

        self::register_shortcode_assets();

        $payload = self::build_boot_payload($role);
        wp_localize_script('ap-dashboard', 'AP_BOOT', $payload);
        wp_enqueue_script('ap-dashboard');
        wp_enqueue_style('ap-dashboard');

        return sprintf(
            '<div id="ap-dashboard" data-role="%s"></div>',
            esc_attr($role)
        );
    }

    private static function render_unauthorized_message(): string
    {
        if (!is_user_logged_in()) {
            $redirect = function_exists('get_permalink') ? get_permalink() : home_url();
            $redirect = $redirect ?: home_url();
            $login_url = wp_login_url((string) $redirect);
            $login_link = sprintf(
                '<a href="%s">%s</a>',
                esc_url($login_url),
                esc_html__('Log in', 'artpulse-management')
            );

            $content = sprintf(
                '<p>%s %s</p>',
                esc_html__('Please log in to access this dashboard.', 'artpulse-management'),
                $login_link
            );

            return wp_kses_post($content);
        }

        return sprintf(
            '<p>%s</p>',
            esc_html__('You do not have access to this dashboard.', 'artpulse-management')
        );
    }

    private static function build_boot_payload(string $role): array
    {
        $user_id = get_current_user_id();
        $user    = wp_get_current_user();

        return [
            'role'      => $role,
            'user'      => [
                'id'     => $user_id,
                'name'   => $user instanceof \WP_User ? $user->display_name : '',
                'avatar' => $user_id ? get_avatar_url($user_id, ['size' => 64]) : '',
            ],
            'nonces'    => [
                'rest'      => wp_create_nonce('wp_rest'),
                'dashboard' => wp_create_nonce('ap_dashboard'),
            ],
            'endpoints' => [
                'root' => esc_url_raw(rest_url('artpulse/v1/')),
                'me'   => esc_url_raw(rest_url('artpulse/v1/me')),
            ],
            'i18n'      => [
                'loading'  => __('Loading dashboard…', 'artpulse-management'),
                'noAccess' => __('You do not have access to this dashboard.', 'artpulse-management'),
                'dashboardError' => __('We could not load your dashboard. Please try again.', 'artpulse-management'),
                'favorites'      => __('Favorites', 'artpulse-management'),
                'follows'        => __('Follows', 'artpulse-management'),
                'upcoming'       => __('Upcoming', 'artpulse-management'),
                'notifications'  => __('Notifications', 'artpulse-management'),
                'upcomingEvents' => __('Upcoming Events', 'artpulse-management'),
                'noUpcoming'     => __('No upcoming events yet.', 'artpulse-management'),
            ],
        ];
    }

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
                    return '<div class="ap-dashboard-message">' . esc_html__('Please log in to view available upgrades.', 'artpulse-management') . '</div>';
                }

                $user_id = get_current_user_id();

                if (!$user_id) {
                    return '';
                }

                $atts = shortcode_atts(
                    [
                        'title'          => __('Membership Upgrades', 'artpulse-management'),
                        'section_title'  => '',
                        'widget_intro'   => '',
                        'empty_message'  => __('No upgrades available at this time.', 'artpulse-management'),
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
                    esc_html__('Membership Upgrades', 'artpulse-management'),
                    static function () use ($member_dashboard) {
                        self::enqueueAssets();

                        $upgrades = $member_dashboard['upgrades'] ?? [];

                        if (empty($upgrades)) {
                            echo '<p>' . esc_html__('No upgrades available at this time.', 'artpulse-management') . '</p>';

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
                        echo '<div class="ap-dashboard-message">' . esc_html__('Unable to load dashboard data.', 'artpulse-management') . '</div>';

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
                esc_html__('Submit an Event', 'artpulse-management'),
                [self::class, 'renderEventSubmissionWidget']
            );
        }

        if ($should_register_profile_actions_widget) {
            wp_add_dashboard_widget(
                'artpulse_profile_actions',
                esc_html__('Profile Actions', 'artpulse-management'),
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
                echo '<p>' . esc_html__('Event submissions are currently unavailable.', 'artpulse-management') . '</p>';

                return;
            }

            printf(
                '<div class="ap-dashboard-widget ap-dashboard-widget--event-submission"><div class="ap-dashboard-widget__section ap-dashboard-widget__section--event-submission"><h3 class="ap-dashboard-event-widget__title">%1$s</h3><p class="ap-dashboard-event-widget__description">%2$s</p><a class="ap-dashboard-button ap-dashboard-button--primary" href="%3$s">%4$s</a></div></div>',
                esc_html__('Share a New Event', 'artpulse-management'),
                esc_html__('Bring the community together by sharing details about your upcoming event.', 'artpulse-management'),
                esc_url($submission_url),
                esc_html__('Submit Event', 'artpulse-management')
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

        $support_url = esc_url_raw(get_support_url());

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
                    'upgradePrimaryAria'     => __('View details for the {role} upgrade option', 'artpulse-management'),
                    'upgradePrimaryAriaGeneric' => __('View upgrade details', 'artpulse-management'),
                    'upgradeReopenAria'      => __('Re-request the {role} upgrade review', 'artpulse-management'),
                    'upgradeReopenAriaGeneric' => __('Re-request upgrade review', 'artpulse-management'),
                    'upgradeSecondaryAria'   => __('Learn more about the {role} upgrade', 'artpulse-management'),
                    'upgradeSecondaryAriaGeneric' => __('Learn more: {label}', 'artpulse-management'),
                    'upgradeDeniedMessage'   => __('Denied.', 'artpulse-management'),
                    'upgradeError'           => __('Unable to submit your request. Please try again.', 'artpulse-management'),
                    'artistRoleLabel'        => __('Artist', 'artpulse-management'),
                    'organizationRoleLabel'  => __('Organization', 'artpulse-management'),
                    'roleSwitcherLabel'      => __('Select a dashboard role', 'artpulse-management'),
                    'currentRoleLabel'       => __('Current dashboard', 'artpulse-management'),
                    'orgRequestNotice'       => __('Your organization upgrade request is pending review.', 'artpulse-management'),
                    'artistRequestNotice'    => __('Your artist upgrade request is pending review.', 'artpulse-management'),
                    'artistRequestDenied'    => __('Your artist upgrade request was denied.', 'artpulse-management'),
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
            echo '<p>' . esc_html__('Please log in to manage your profile.', 'artpulse-management') . '</p>';

            return;
        }

        $user = wp_get_current_user();

        if (!$user instanceof WP_User) {
            echo '<p>' . esc_html__('Profile actions are currently unavailable.', 'artpulse-management') . '</p>';

            return;
        }

        $profile_actions = self::getProfileActionsForUser($user);

        self::enqueueAssets();

        $template = dirname(__DIR__, 2) . '/templates/dashboard/profile-actions-widget.php';

        if (!file_exists($template)) {
            if (empty($profile_actions)) {
                echo '<p>' . esc_html__('Profile actions are currently unavailable.', 'artpulse-management') . '</p>';

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
                        esc_html__('Create profile', 'artpulse-management')
                    );
                } else {
                    echo '<p>' . esc_html__('Profile creation is currently unavailable.', 'artpulse-management') . '</p>';
                }

                if ($edit_url) {
                    printf(
                        '<p><a class="ap-dashboard-button ap-dashboard-button--secondary" href="%1$s">%2$s</a></p>',
                        esc_url($edit_url),
                        esc_html__('Edit profile', 'artpulse-management')
                    );
                } else {
                    echo '<p>' . esc_html__('A profile has not been created yet.', 'artpulse-management') . '</p>';
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
                'permission_callback' => [self::class, 'permissionsCheck'],
                'args'                => [
                    'role' => [
                        'type'     => 'string',
                        'required' => true,
                        'enum'     => self::enabledRoleSlugs(),
                    ],
                ] + self::getCommonArgs(),
            ]
        );
    }

    public static function permissionsCheck(WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required to access dashboard data.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!self::verifyNonce($request)) {
            return new WP_Error(
                'rest_invalid_nonce',
                __('Security check failed. Please refresh and try again.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        $role = sanitize_key($request->get_param('role'));

        if (!self::currentUserCanAccess($role)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this dashboard.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        return true;
    }

    private static function verifyNonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (!$nonce) {
            $nonce = (string) $request->get_param('_wpnonce');
        }

        if (!$nonce && $request->get_param('nonce')) {
            $nonce = (string) $request->get_param('nonce');
        }

        return is_string($nonce) && '' !== $nonce && wp_verify_nonce($nonce, 'wp_rest');
    }

    private static function getCommonArgs(): array
    {
        return [
            'nonce' => [
                'type'        => 'string',
                'required'    => false,
                'description' => __('Nonce generated via wp_create_nonce("wp_rest").', 'artpulse-management'),
            ],
            '_wpnonce' => [
                'type'        => 'string',
                'required'    => false,
                'description' => __('Nonce generated via wp_create_nonce("wp_rest").', 'artpulse-management'),
            ],
        ];
    }

    public static function getDashboard(WP_REST_Request $request): WP_REST_Response
    {
        $role = sanitize_key($request->get_param('role'));

        if (!self::currentUserCanAccess($role)) {
            return new WP_REST_Response(['message' => __('Access denied.', 'artpulse-management')], 403);
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
            $raw_upgrades = self::getUpgradeOptions($user_id, false);

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
            $intro = __('Ready to take the next step? Unlock publishing tools tailored for artists and organizations.', 'artpulse-management');
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
            return '<div class="ap-dashboard-message">' . esc_html__('You do not have permission to view this dashboard.', 'artpulse-management') . '</div>';
        }

        $classes = sprintf('ap-role-dashboard ap-role-dashboard--%s', esc_attr($role));
        $loading = esc_html__('Loading dashboard…', 'artpulse-management');

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
                'url'     => self::getDashboardUrlForRole($role),
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
            $title_text = $title ?? esc_html__('Membership Upgrades', 'artpulse-management');

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
                    esc_html($upgrade['cta'] ?? __('Upgrade now', 'artpulse-management'))
                );

                if (!empty($upgrade['secondary_actions']) && is_array($upgrade['secondary_actions'])) {
                    foreach ($upgrade['secondary_actions'] as $secondary_action) {
                        $secondary_url = $secondary_action['url'] ?? '';

                        if ($secondary_url === '') {
                            continue;
                        }

                        $secondary_label = $secondary_action['label'] ?? __('Learn more', 'artpulse-management');

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

        $section_title    = $title ?? esc_html__('Membership Upgrades', 'artpulse-management');
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
        if ('artpulse_artist' === $post_type && get_option('ap_enable_artist_builder', true)) {
            $page_url = get_page_url('artist_builder_page_id');

            if (is_string($page_url) && $page_url !== '') {
                $builder_url = add_query_args($page_url, [
                    'ap_builder' => 'artist',
                    'autocreate' => '1',
                ]);

                return esc_url_raw($builder_url);
            }

            return get_missing_page_fallback('artist_builder_page_id');
        }

        if ('artpulse_org' === $post_type && get_option('ap_enable_org_builder', true)) {
            $page_url = get_page_url('org_builder_page_id');

            if (is_string($page_url) && $page_url !== '') {
                $builder_url = add_query_args($page_url, [
                    'ap_builder' => 'organization',
                ]);

                return esc_url_raw($builder_url);
            }

            return get_missing_page_fallback('org_builder_page_id');
        }

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
            $artist_review = self::getArtistUpgradeReviewState($user_id);
            $journeys['artist'] = self::buildJourneyState(
                'artist',
                $user_id,
                'artpulse_artist',
                [
                    'builder'   => self::getBuilderUrlForJourney('artist'),
                    'dashboard' => self::getDashboardUrlForRole('artist'),
                    'public'    => '',
                    'upgrade'   => $upgrade_links['artist'] ?? '',
                ],
                $artist_review
            );
        }

        if (in_array($role, ['member', 'organization'], true)) {
            $org_review = self::getOrgUpgradeReviewState($user_id);
            $journeys['organization'] = self::buildJourneyState(
                'organization',
                $user_id,
                'artpulse_org',
                [
                    'builder'   => self::getBuilderUrlForJourney('organization'),
                    'dashboard' => self::getDashboardUrlForRole('organization'),
                    'public'    => '',
                    'upgrade'   => $upgrade_links['organization'] ?? '',
                ],
                $org_review
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

        if (isset($journeys['artist']['review'])) {
            $review = $journeys['artist']['review'];
            $anchor = $journeys['artist']['anchor'] ?? '';

            if (($review['status'] ?? '') === 'pending') {
                $notifications[] = [
                    'type'    => 'info',
                    'message' => __('Your artist upgrade request is pending review.', 'artpulse-management'),
                    'anchor'  => $anchor,
                ];
            } elseif (($review['status'] ?? '') === 'denied') {
                $message = __('Your artist upgrade request was denied.', 'artpulse-management');

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
        $type_key = 'artpulse_org' === $post_type ? 'org' : 'artist';
        $state    = ProfileState::for_user($type_key, $user_id);

        $profile_status = $state['exists'] ? (string) ($state['status'] ?? 'draft') : 'none';

        $snapshot = [
            'post_type'        => $post_type,
            'status'           => $profile_status,
            'status_label'     => $state['exists'] ? self::status_label_from_state($profile_status) : __('Not started', 'artpulse-management'),
            'progress_percent' => (int) ($state['complete'] ?? 0),
            'badge_variant'    => self::badge_variant_from_state($profile_status),
            'post_id'          => $state['post_id'] ? (int) $state['post_id'] : null,
            'post_ids'         => [],
            'post_status'      => $state['status'] ?? '',
            'title'            => '',
            'permalink'        => (string) ($state['public_url'] ?? ''),
            'total'            => 0,
            'has_published'    => 'publish' === ($state['status'] ?? ''),
            'has_unpublished'  => in_array($state['status'] ?? '', ['draft', 'pending'], true),
            'visibility'       => $state['visibility'] ?? null,
            'state'            => $state,
        ];

        $ids = PortfolioAccess::get_owned_portfolio_ids($user_id, $post_type);
        $snapshot['post_ids'] = $ids;
        $snapshot['total']    = count($ids);

        if (!$state['exists'] || empty($state['post_id'])) {
            return $snapshot;
        }

        $post = get_post((int) $state['post_id']);
        if (!$post instanceof WP_Post) {
            return $snapshot;
        }

        $snapshot['post_id']     = (int) $post->ID;
        $snapshot['post_status'] = $post->post_status;
        $snapshot['title']       = get_the_title($post);

        $permalink = $state['public_url'] ?: get_permalink($post);
        if (is_string($permalink)) {
            $snapshot['permalink'] = $permalink;
        }

        if ('artpulse_org' === $post_type) {
            $logo_id  = (int) get_post_meta($post->ID, '_ap_logo_id', true);
            $cover_id = (int) get_post_meta($post->ID, '_ap_cover_id', true);
            $gallery  = get_post_meta($post->ID, '_ap_gallery_ids', true);

            if (!is_array($gallery)) {
                $gallery = (array) $gallery;
            }

            $gallery_ids = array_values(array_filter(array_map('intval', $gallery)));

            $snapshot['media'] = [
                'logo_id'     => $logo_id,
                'cover_id'    => $cover_id,
                'gallery_ids' => $gallery_ids,
                'has_images'  => $logo_id > 0 || $cover_id > 0 || !empty($gallery_ids),
            ];
        }

        return $snapshot;
    }

    private static function status_label_from_state(string $status): string
    {
        switch ($status) {
            case 'publish':
                return __('Published', 'artpulse-management');
            case 'pending':
                return __('Pending review', 'artpulse-management');
            case 'none':
                return __('Not started', 'artpulse-management');
            case 'draft':
            default:
                return __('Draft', 'artpulse-management');
        }
    }

    private static function badge_variant_from_state(string $status): string
    {
        switch ($status) {
            case 'publish':
                return 'success';
            case 'pending':
                return 'warning';
            case 'none':
                return 'info';
            case 'draft':
            default:
                return 'info';
        }
    }

    private static function buildJourneyState(string $journey, int $user_id, string $post_type, array $links, array $review = []): array
    {
        $snapshot     = self::summarizePortfolio($user_id, $post_type);
        $profile_type = 'artpulse_org' === $post_type ? 'org' : 'artist';
        $state        = $snapshot['state'] ?? ProfileState::for_user($profile_type, $user_id);

        $builder_url     = isset($state['builder_url']) ? (string) $state['builder_url'] : ($links['builder'] ?? '');
        $links['builder'] = self::enrichBuilderUrl($journey, $builder_url, $snapshot);

        $state_for_links = is_array($state) ? $state : [];
        $state_for_links['builder_url'] = $links['builder'] ?? ($state_for_links['builder_url'] ?? '');
        $state_for_links['public_url'] = $snapshot['permalink'] ?? ($state_for_links['public_url'] ?? '');

        $profile_links = ProfileLinkHelpers::assemble_links($state_for_links);
        $links = array_merge($links, $profile_links);

        if (($state_for_links['public_url'] ?? '') !== '') {
            $links['public'] = $state_for_links['public_url'];
        }

        $state = $state_for_links;

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
        $profile_link = $profile_links['view'] ?? '';
        if ($profile_link === '') {
            $profile_link = $profile_links['preview'] ?? '';
        }
        $anchor       = sprintf('#ap-journey-%s', $journey);

        if ($has_access) {
            $profile_status = isset($state['status']) ? (string) $state['status'] : 'none';
            $visibility     = isset($state['visibility']) ? (string) $state['visibility'] : null;
            $status_label   = self::status_label_from_state($profile_status);
            $badge          = [
                'label'   => $status_label,
                'variant' => self::badge_variant_from_state($profile_status),
            ];
            $description = 'artist' === $journey
                ? __('Share your story, media, and links to reach more supporters.', 'artpulse-management')
                : __('Highlight your organization\'s mission, media, and events.', 'artpulse-management');
            $cta = [
                'label'    => __('Start your profile', 'artpulse-management'),
                'url'      => $links['builder'] ?? '',
                'variant'  => 'primary',
                'disabled' => empty($links['builder']),
            ];
            $status = 'not_started';

            if ('draft' === $profile_status) {
                $status       = 'in_progress';
                $status_label = __('Draft in progress', 'artpulse-management');
                $badge        = [
                    'label'   => __('Draft', 'artpulse-management'),
                    'variant' => 'info',
                ];
                $cta['label'] = __('Continue editing', 'artpulse-management');
                $description = 'artist' === $journey
                    ? __('Keep refining your artist profile to reach 100% completeness.', 'artpulse-management')
                    : __('Keep refining your organization profile to reach 100% completeness.', 'artpulse-management');
            } elseif ('pending' === $profile_status) {
                $status       = 'pending_review';
                $status_label = __('Under review', 'artpulse-management');
                $badge        = [
                    'label'   => __('Pending review', 'artpulse-management'),
                    'variant' => 'warning',
                ];
                $cta['label'] = __('Edit & re-submit', 'artpulse-management');
                $description = __('We are reviewing your submission. Update any details if requested by moderators.', 'artpulse-management');
            } elseif ('publish' === $profile_status) {
                $status = 'published';

                if ('private' === $visibility) {
                    $status_label = __('Published (private)', 'artpulse-management');
                    $badge        = [
                        'label'   => __('Private', 'artpulse-management'),
                        'variant' => 'info',
                    ];
                    $description = __('Your profile is hidden. Change visibility to public so members can discover you.', 'artpulse-management');
                    $cta['label'] = __('Change to public', 'artpulse-management');
                } else {
                    $status_label = __('Published', 'artpulse-management');
                    $badge        = [
                        'label'   => __('Published', 'artpulse-management'),
                        'variant' => 'success',
                    ];
                    $description = __('Your profile is live. Keep it fresh with new media and updates.', 'artpulse-management');
                    $cta['label'] = __('Edit profile', 'artpulse-management');
                    $profile_link = $profile_links['view'] ?? '';
                }
            } else {
                $status       = 'not_started';
                $status_label = __('Not started', 'artpulse-management');
                $badge        = [
                    'label'   => __('Not started', 'artpulse-management'),
                    'variant' => 'info',
                ];
                $description = 'artist' === $journey
                    ? __('Start your artist profile to appear in the ArtPulse community.', 'artpulse-management')
                    : __('Start your organization profile to share upcoming events and programs.', 'artpulse-management');
            }

            if (empty($state['exists'])) {
                $status = 'not_started';
            }

            if (empty($links['builder'])) {
                $cta['disabled'] = true;
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
                        'url'      => ArtistRequestStatusRoute::get_status_url('organization'),
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
            } elseif ('artist' === $journey) {
                if (($review['status'] ?? '') === 'pending') {
                    $status       = 'pending_request';
                    $status_label = __('Upgrade request pending', 'artpulse-management');
                    $badge        = [
                        'label'   => __('Pending review', 'artpulse-management'),
                        'variant' => 'warning',
                    ];
                    $description = __('We are reviewing your artist request. We will email you once it is approved.', 'artpulse-management');
                    $cta = [
                        'label'    => __('Check request status', 'artpulse-management'),
                        'url'      => ArtistRequestStatusRoute::get_status_url('artist'),
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
                    $description = __('Your last artist request was denied. Review the feedback and resubmit when you are ready.', 'artpulse-management');
                    $cta = [
                        'label'    => __('Review feedback', 'artpulse-management'),
                        'url'      => $anchor,
                        'variant'  => 'secondary',
                        'disabled' => false,
                    ];
                }
            }
        }

        if (empty($cta['url'])) {
            $cta['disabled'] = true;
        }

        return [
            'slug'             => $journey,
            'label'            => 'artist' === $journey
                ? __('Artist profile', 'artpulse-management')
                : __('Organization profile', 'artpulse-management'),
            'status'           => $status,
            'status_label'     => $status_label,
            'badge'            => $badge,
            'progress_percent' => (int) ($state['complete'] ?? $snapshot['progress_percent'] ?? 0),
            'portfolio'        => $snapshot,
            'links'            => $links,
            'description'      => $description,
            'cta'              => $cta,
            'anchor'           => $anchor,
            'review'           => $review,
            'profile_url'      => $profile_link,
        ];
    }

    private static function getArtistUpgradeReviewState(int $user_id): array
    {
        $state = [
            'status'     => 'none',
            'reason'     => '',
            'request_id' => 0,
            'artist_id'  => 0,
            'updated_at' => null,
        ];

        $request = UpgradeReviewRepository::get_latest_for_user($user_id, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE);

        if (!$request instanceof WP_Post) {
            return $state;
        }

        $state['request_id'] = (int) $request->ID;
        $state['artist_id']  = UpgradeReviewRepository::get_post_id($request);
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
        $submission_page_id = self::locateFrontendEventSubmissionPage();
        $base_url            = '';

        if ($submission_page_id) {
            $permalink = get_permalink($submission_page_id);

            if (is_string($permalink) && $permalink !== '') {
                $base_url = $permalink;
            }
        }

        if ($base_url === '') {
            $base_url = self::getSubmissionCreateUrl('artpulse_event');
        }

        if ($base_url === '') {
            $base_url = admin_url('post-new.php?post_type=artpulse_event');
        }

        $description     = __('Share upcoming events with the ArtPulse community.', 'artpulse-management');
        $enabled         = false;
        $status_label    = __('Locked', 'artpulse-management');
        $badge           = [
            'label'   => __('Locked', 'artpulse-management'),
            'variant' => 'muted',
        ];
        $disabled_reason = __('Publish a profile to unlock event submissions.', 'artpulse-management');
        $action_status   = 'locked';
        $target_journey  = null;

        if ('organization' === $role) {
            $target_journey = $journeys['organization'] ?? null;
        } elseif ('artist' === $role) {
            $target_journey = $journeys['artist'] ?? null;
        } else {
            $organization = $journeys['organization'] ?? null;
            $artist       = $journeys['artist'] ?? null;

            if ($organization && in_array($organization['portfolio']['status'] ?? '', ['publish'], true)) {
                $target_journey = $organization;
            } elseif ($artist && in_array($artist['portfolio']['status'] ?? '', ['publish'], true)) {
                $target_journey = $artist;
            }
        }

        $cta_url       = $base_url;
        $state         = [];
        $portfolio     = [];
        $journey_slug  = '';
        $journey_status = 'locked';

        if ($target_journey) {
            $journey_slug   = (string) ($target_journey['slug'] ?? '');
            $journey_status = (string) ($target_journey['status'] ?? 'locked');
            $portfolio      = is_array($target_journey['portfolio'] ?? null)
                ? $target_journey['portfolio']
                : [];

            if (isset($portfolio['state']) && is_array($portfolio['state'])) {
                $state = $portfolio['state'];
            }

            if (empty($state) && in_array($journey_slug, ['artist', 'organization'], true)) {
                $state = ProfileState::for_user($journey_slug, $user_id);
            }
        }

        if (ProfileState::can_submit_events($state)) {
            $enabled      = true;
            $status_label = __('Ready', 'artpulse-management');
            $badge        = [
                'label'   => __('Ready', 'artpulse-management'),
                'variant' => 'success',
            ];
            $action_status   = 'ready';
            $disabled_reason = '';

            $post_id = (int) ($state['post_id'] ?? ($portfolio['post_id'] ?? 0));

            if ($post_id) {
                $cta_url = add_query_arg(
                    'organization' === $journey_slug ? 'org_id' : 'artist_id',
                    $post_id,
                    $base_url
                );
            }
        } else {
            $status     = (string) ($state['status'] ?? '');
            $visibility = (string) ($state['visibility'] ?? '');
            $complete   = (int) ($state['complete'] ?? 0);

            if ($journey_status === 'pending_review') {
                $status_label    = __('Pending review', 'artpulse-management');
                $badge           = [
                    'label'   => __('Pending review', 'artpulse-management'),
                    'variant' => 'warning',
                ];
                $disabled_reason = __('Your profile is pending review. We will email you once it is approved.', 'artpulse-management');
                $action_status   = 'pending';
            } elseif ($journey_status === 'pending_request') {
                $status_label  = __('Pending review', 'artpulse-management');
                $badge         = [
                    'label'   => __('Pending review', 'artpulse-management'),
                    'variant' => 'warning',
                ];
                $action_status = 'pending';

                if ($journey_slug === 'artist') {
                    $disabled_reason = __('We are reviewing your artist request. We will email you once it is approved.', 'artpulse-management');
                } else {
                    $disabled_reason = __('We are reviewing your organization request. We will email you once it is approved.', 'artpulse-management');
                }
            } elseif ($journey_status === 'denied') {
                $status_label    = __('Action required', 'artpulse-management');
                $badge           = [
                    'label'   => __('Action required', 'artpulse-management'),
                    'variant' => 'danger',
                ];
                $disabled_reason = __('Review the feedback on your upgrade request to unlock event submissions.', 'artpulse-management');
            } elseif (in_array($journey_status, ['in_progress', 'not_started'], true)) {
                $status_label    = __('Finish your profile to continue', 'artpulse-management');
                $badge           = [
                    'label'   => __('In progress', 'artpulse-management'),
                    'variant' => 'info',
                ];
                $disabled_reason = __('Publish your profile to unlock event submissions.', 'artpulse-management');
            } elseif ($status === 'pending') {
                $status_label    = __('Pending review', 'artpulse-management');
                $badge           = [
                    'label'   => __('Pending review', 'artpulse-management'),
                    'variant' => 'warning',
                ];
                $disabled_reason = __('Your profile is pending review. We will email you once it is approved.', 'artpulse-management');
                $action_status   = 'pending';
            } elseif ($status === 'publish' && $visibility !== 'public') {
                $status_label    = __('Profile is private', 'artpulse-management');
                $badge           = [
                    'label'   => __('Private', 'artpulse-management'),
                    'variant' => 'info',
                ];
                $disabled_reason = __('Make your profile public to unlock event submissions.', 'artpulse-management');
            } elseif ($status === 'publish' && $visibility === 'public' && $complete < 80) {
                $status_label    = __('Keep going', 'artpulse-management');
                $badge           = [
                    'label'   => __('In progress', 'artpulse-management'),
                    'variant' => 'info',
                ];
                $disabled_reason = __('Complete at least 80% of your profile to unlock event submissions.', 'artpulse-management');
            } elseif (!$state || !($state['exists'] ?? false)) {
                $status_label    = __('Not started', 'artpulse-management');
                $badge           = [
                    'label'   => __('Locked', 'artpulse-management'),
                    'variant' => 'muted',
                ];
                $disabled_reason = __('Publish your profile to unlock event submissions.', 'artpulse-management');
            } else {
                $status_label    = __('Locked', 'artpulse-management');
                $badge           = [
                    'label'   => __('Locked', 'artpulse-management'),
                    'variant' => 'muted',
                ];
                $disabled_reason = __('Publish your profile to unlock event submissions.', 'artpulse-management');
            }
        }

        return [
            'slug'             => 'submit_event',
            'title'            => __('Submit an event', 'artpulse-management'),
            'description'      => $description,
            'badge'            => $badge,
            'status'           => $action_status,
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
        $state     = isset($portfolio['state']) && is_array($portfolio['state']) ? $portfolio['state'] : [];
        $status    = $state['status'] ?? ($portfolio['status'] ?? 'none');
        $visibility = $state['visibility'] ?? '';

        $builder_url = $target['links']['builder'] ?? '';

        $state_for_links = $state;
        $state_for_links['builder_url'] = $builder_url;
        $state_for_links['public_url'] = $portfolio['permalink'] ?? ($state_for_links['public_url'] ?? '');

        $links     = ProfileLinkHelpers::assemble_links($state_for_links);
        $is_public = ProfileLinkHelpers::is_public($state_for_links);

        $view_url = $links['view'] ?? '';
        $edit_url = $links['edit'] ?? $builder_url;

        if ($is_public && $view_url !== '') {
            $badge = [
                'label'   => __('Live', 'artpulse-management'),
                'variant' => 'success',
            ];
            $status_value = 'ready';
            $status_label = __('Live', 'artpulse-management');
            $cta_label    = __('View profile', 'artpulse-management');
            $cta_url      = $view_url;
            $description  = __('Open your public page to confirm how the community sees your profile.', 'artpulse-management');
        } else {
            $badge = [
                'label'   => __('Draft', 'artpulse-management'),
                'variant' => 'info',
            ];
            $status_value = 'locked';
            $status_label = __('Not yet published', 'artpulse-management');

            if ($status === 'pending') {
                $badge = [
                    'label'   => __('Pending review', 'artpulse-management'),
                    'variant' => 'warning',
                ];
                $status_label = __('Pending review', 'artpulse-management');
            } elseif ($status === 'publish' && $visibility === 'private') {
                $badge = [
                    'label'   => __('Private', 'artpulse-management'),
                    'variant' => 'info',
                ];
                $status_label = __('Private', 'artpulse-management');
            }

            $cta_label   = __('Open builder', 'artpulse-management');
            $cta_url     = $edit_url;
            $description = __('Preview your profile or continue editing before publishing.', 'artpulse-management');
        }

        return [
            'slug'             => 'view_profile',
            'title'            => __('View public profile', 'artpulse-management'),
            'description'      => $description,
            'badge'            => $badge,
            'status'           => $status_value,
            'status_label'     => $status_label,
            'progress_percent' => $is_public && $view_url !== '' ? 100 : 0,
            'cta'              => [
                'label'    => $cta_label,
                'url'      => $cta_url,
                'variant'  => 'secondary',
                'disabled' => $cta_url === '',
            ],
            'links'            => $links,
            'disabled_reason'  => '',
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

    private static function getUpgradeOptions(int $user_id, bool $mergeOrganizationIntoArtist = true): array
    {
        $current_level = strtolower((string) get_user_meta($user_id, 'ap_membership_level', true));

        $options = [
            'artist' => [
                'level'       => 'Pro',
                'title'       => __('Upgrade to Artist', 'artpulse-management'),
                'description' => __('Showcase your portfolio, get discovered, and unlock artist-only publishing tools.', 'artpulse-management'),
                'cta'         => __('Become an Artist', 'artpulse-management'),
            ],
            'organization' => [
                'level'       => 'Org',
                'title'       => __('Upgrade to Organization', 'artpulse-management'),
                'description' => __('Promote your events, highlight members, and grow your creative community.', 'artpulse-management'),
                'cta'         => __('Upgrade to Organization', 'artpulse-management'),
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
                'cta'         => $option['cta'] ?? __('Upgrade now', 'artpulse-management'),
                'url'         => $url,
            ];
        }

        if (!$mergeOrganizationIntoArtist) {
            return array_values($upgrades);
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
            'label' => $organizationUpgrade['cta'] ?? __('Upgrade to Organization', 'artpulse-management'),
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

    private static function getDashboardBaseUrl(): string
    {
        $url = get_page_url('dashboard_page_id');

        if ($url) {
            return $url;
        }

        return get_missing_page_fallback('dashboard_page_id');
    }

    private static function getDashboardUrlForRole(string $role): string
    {
        return esc_url_raw(add_query_args(self::getDashboardBaseUrl(), ['role' => $role]));
    }

    private static function getBuilderBaseUrl(string $key): string
    {
        $url = get_page_url($key);

        if ($url) {
            return $url;
        }

        return get_missing_page_fallback($key);
    }

    private static function getBuilderUrlForJourney(string $journey): string
    {
        if ($journey === 'artist') {
            $base = self::getBuilderBaseUrl('artist_builder_page_id');

            if ($base === '') {
                return '';
            }

            return esc_url_raw(add_query_args($base, ['ap_builder' => 'artist']));
        }

        if ($journey === 'organization') {
            $base = self::getBuilderBaseUrl('org_builder_page_id');

            if ($base === '') {
                return '';
            }

            return esc_url_raw(add_query_args($base, ['ap_builder' => 'organization']));
        }

        return '';
    }

    private static function enrichBuilderUrl(string $journey, string $builder_url, array $snapshot): string
    {
        if ($builder_url === '') {
            return '';
        }

        $args = [];

        if ('artist' === $journey) {
            if (($snapshot['status'] ?? '') === 'none') {
                $args['autocreate'] = '1';
            }
        } elseif ('organization' === $journey) {
            $post_id = isset($snapshot['post_id']) ? (int) $snapshot['post_id'] : 0;

            if ($post_id > 0) {
                $args['org_id'] = (string) $post_id;
            }

            $step = self::determineOrganizationBuilderStep($snapshot);
            if ($step !== null) {
                $args['step'] = $step;
            }
        }

        if (empty($args)) {
            return esc_url_raw($builder_url);
        }

        return esc_url_raw(add_query_args($builder_url, $args));
    }

    private static function determineOrganizationBuilderStep(array $snapshot): ?string
    {
        $post_id = isset($snapshot['post_id']) ? (int) $snapshot['post_id'] : 0;

        if ($post_id <= 0) {
            return null;
        }

        $media      = $snapshot['media'] ?? [];
        $has_images = (bool) ($media['has_images'] ?? false);

        if (!$has_images) {
            return 'images';
        }

        $post_status = $snapshot['post_status'] ?? '';

        if ($post_status !== 'publish') {
            return 'preview';
        }

        return null;
    }

    private static function getRoleLabels(): array
    {
        return [
            'member' => [
                'title'       => __('Member Dashboard', 'artpulse-management'),
                'profile'     => __('Member Profile', 'artpulse-management'),
                'metrics'     => __('Member Metrics', 'artpulse-management'),
                'favorites'   => __('Saved Favorites', 'artpulse-management'),
                'follows'     => __('Following', 'artpulse-management'),
                'submissions' => __('Your Submissions', 'artpulse-management'),
                'upgrades'    => __('Membership Upgrades', 'artpulse-management'),
            ],
            'artist' => [
                'title'       => __('Artist Dashboard', 'artpulse-management'),
                'profile'     => __('Artist Profile', 'artpulse-management'),
                'metrics'     => __('Artist Metrics', 'artpulse-management'),
                'favorites'   => __('Collector Favorites', 'artpulse-management'),
                'follows'     => __('Followers', 'artpulse-management'),
                'submissions' => __('Portfolio Updates', 'artpulse-management'),
            ],
            'organization' => [
                'title'       => __('Organization Dashboard', 'artpulse-management'),
                'profile'     => __('Organization Profile', 'artpulse-management'),
                'metrics'     => __('Organization Metrics', 'artpulse-management'),
                'favorites'   => __('Audience Favorites', 'artpulse-management'),
                'follows'     => __('Supporters', 'artpulse-management'),
                'submissions' => __('Organization Posts', 'artpulse-management'),
            ],
        ];
    }
}
