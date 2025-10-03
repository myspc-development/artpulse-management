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
            'title'       => 'Artist Dashboard',
        ],
        'organization' => [
            'shortcode'   => 'ap_organization_dashboard',
            'capability'  => 'edit_artpulse_org',
            'post_types'  => ['artpulse_org', 'artpulse_event'],
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
        add_action('rest_api_init', [self::class, 'registerRoutes']);
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
        $role    = sanitize_key($request->get_param('role'));
        $user_id = get_current_user_id();

        if (!self::currentUserCanAccess($role)) {
            return new WP_REST_Response(['message' => __('Access denied.', 'artpulse')], 403);
        }

        $data = [
            'role'        => $role,
            'favorites'   => self::getFavorites($user_id),
            'follows'     => self::getFollows($user_id),
            'submissions' => self::getSubmissions($user_id, self::ROLE_CONFIG[$role]['post_types'] ?? []),
        ];

        $data['metrics'] = self::buildMetrics($data['favorites'], $data['follows'], $data['submissions']);
        $data['profile'] = self::getProfileSummary($user_id, $role);

        return rest_ensure_response($data);
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

    private static function currentUserCanAccess(string $role): bool
    {
        if (!array_key_exists($role, self::ROLE_CONFIG)) {
            return false;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        if (user_can($user, 'manage_options') || user_can($user, 'view_artpulse_dashboard')) {
            return true;
        }

        return self::userCanViewRole($user, $role);
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

    private static function getSubmissions(int $user_id, array $post_types): array
    {
        $submissions = [];

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
