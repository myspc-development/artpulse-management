<?php

namespace ArtPulse\Core;

use ArtPulse\Community\FavoritesManager;
use ArtPulse\Community\FollowManager;
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
        ],
        'artist' => [
            'shortcode'   => 'ap_artist_dashboard',
            'capability'  => 'edit_artpulse_artist',
            'post_types'  => ['artpulse_artist', 'artpulse_artwork'],
            'profile_post_type' => 'artpulse_artist',
            'title'       => 'Artist Dashboard',
        ],
        'organization' => [
            'shortcode'   => 'ap_organization_dashboard',
            'capability'  => 'edit_artpulse_org',
            'post_types'  => ['artpulse_org', 'artpulse_event'],
            'profile_post_type' => 'artpulse_org',
            'title'       => 'Organization Dashboard',
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

        $should_register_event_widget = false;

        foreach (self::ROLE_CONFIG as $role => $config) {
            $can_manage = user_can($user, 'manage_options') || user_can($user, 'view_artpulse_dashboard');

            if (!$can_manage && !self::userCanViewRole($user, $role)) {
                continue;
            }

            if (in_array($role, ['artist', 'organization'], true)) {
                $should_register_event_widget = true;
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
        }

        if ($should_register_event_widget) {
            wp_add_dashboard_widget(
                'artpulse_event_submission',
                esc_html__('Submit an Event', 'artpulse'),
                [self::class, 'renderEventSubmissionWidget']
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

        wp_enqueue_script(
            'ap-dashboards-js',
            plugins_url('assets/js/ap-dashboards.js', dirname(__DIR__, 2)),
            ['wp-api-fetch', 'ap-social-js'],
            $version,
            true
        );

        wp_localize_script(
            'ap-dashboards-js',
            'ArtPulseDashboards',
            [
                'root'   => $api_root,
                'nonce'  => $api_nonce,
                'labels' => self::getRoleLabels(),
                'strings' => [
                    'loading'           => __('Loading dashboard…', 'artpulse'),
                    'error'             => __('Unable to load dashboard data.', 'artpulse'),
                    'empty'             => __('Nothing to display yet.', 'artpulse'),
                    'profile'           => __('Profile Summary', 'artpulse'),
                    'metrics'           => __('Metrics', 'artpulse'),
                    'favorites'         => __('Favorites', 'artpulse'),
                    'follows'           => __('Follows', 'artpulse'),
                    'submissions'       => __('Submissions', 'artpulse'),
                    'favoritesMetric'   => __('Favorites', 'artpulse'),
                    'followsMetric'     => __('Follows', 'artpulse'),
                    'submissionsMetric' => __('Submissions', 'artpulse'),
                    'pendingMetric'     => __('Pending', 'artpulse'),
                    'publishedMetric'   => __('Published', 'artpulse'),
                    'favorite'          => __('Favorite', 'artpulse'),
                    'unfavorite'        => __('Unfavorite', 'artpulse'),
                    'follow'            => __('Follow', 'artpulse'),
                    'unfollow'          => __('Unfollow', 'artpulse'),
                    'updated'           => __('Updated', 'artpulse'),
                    'viewProfile'       => __('View profile', 'artpulse'),
                    'createProfile'     => __('Create profile', 'artpulse'),
                    'editProfile'       => __('Edit profile', 'artpulse'),
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
                        'favoriteError' => __('Unable to update favorite. Please try again.', 'artpulse'),
                        'followError'   => __('Unable to update follow. Please try again.', 'artpulse'),
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
                        'enum'     => array_keys(self::ROLE_CONFIG),
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

        $favorites   = self::getFavorites($user_id);
        $follows     = self::getFollows($user_id);
        $post_types  = $role_config['post_types'] ?? [];
        $submissions = self::getSubmissions($user_id, $post_types, $role_config);

        return [
            'role'        => $role,
            'favorites'   => $favorites,
            'follows'     => $follows,
            'submissions' => $submissions,
            'metrics'     => self::buildMetrics($favorites, $follows, $submissions),
            'profile'     => self::getProfileSummary($user_id, $role),
        ];
    }

    public static function userCanAccessRole(string $role, ?int $user_id = null): bool
    {
        if (!array_key_exists($role, self::ROLE_CONFIG)) {
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
        if (!is_user_logged_in()) {
            return '<div class="ap-dashboard-message">' . esc_html__('Please log in to view this dashboard.', 'artpulse') . '</div>';
        }

        $user = wp_get_current_user();
        if (!self::userCanViewRole($user, $role)) {
            return '<div class="ap-dashboard-message">' . esc_html__('You do not have permission to view this dashboard.', 'artpulse') . '</div>';
        }

        $classes = sprintf('ap-role-dashboard ap-role-dashboard--%s', esc_attr($role));
        $loading = esc_html__('Loading dashboard…', 'artpulse');

        return sprintf('<div class="%1$s" data-ap-dashboard-role="%2$s"><div class="ap-dashboard-loading">%3$s</div></div>', $classes, esc_attr($role), $loading);
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

        if (in_array($role, (array) $user->roles, true)) {
            return user_can($user, $config['capability']);
        }

        return false;
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
