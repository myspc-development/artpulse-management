<?php
namespace ArtPulse\Core;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_User;
use function is_user_logged_in;
use function rest_authorization_required_code;
use function sanitize_key;
use function wp_verify_nonce;

class UserDashboardManager
{
    public static function register()
    {
        add_shortcode('ap_user_dashboard', [ self::class, 'renderDashboard' ]);
        add_action('wp_enqueue_scripts',   [ self::class, 'enqueueAssets' ], 20);
        add_action('rest_api_init',        [ self::class, 'register_routes' ]);
    }

    // Aliased method for compatibility with provided code snippet
    public static function register_routes()
    {
        self::registerRestRoutes();
    }

    public static function enqueueAssets()
    {
        // Core dashboard script
        wp_enqueue_script(
            'ap-user-dashboard-js',
            plugins_url('assets/js/ap-user-dashboard.js', __FILE__),
            ['wp-api-fetch', 'ap-dashboards-js'],
            '1.0.0',
            true
        );

        // Analytics events
        wp_enqueue_script(
            'ap-analytics-js',
            plugins_url('assets/js/ap-analytics.js', __FILE__),
            ['ap-user-dashboard-js'],
            '1.0.0',
            true
        );

        // Dashboard styles
        wp_enqueue_style(
            'ap-user-dashboard-css',
            plugins_url('assets/css/ap-user-dashboard.css', __FILE__),
            [],
            '1.0.0'
        );
    }

    public static function registerRestRoutes()
    {
        register_rest_route('artpulse/v1', '/user/dashboard', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'getDashboardData' ],
            'permission_callback' => [ self::class, 'permissionsCheckDashboard' ],
            'args'                => [
                'role' => [
                    'type'     => 'string',
                    'required' => false,
                ],
            ] + self::getCommonArgs(),
        ]);

        register_rest_route('artpulse/v1', '/user/profile', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'updateProfile' ],
            'permission_callback' => [ self::class, 'verifyRestRequest' ],
            'args'                => self::getCommonArgs(),
        ]);
    }

    public static function permissionsCheckDashboard(WP_REST_Request $request)
    {
        $verified = self::verifyRestRequest($request);
        if ($verified !== true) {
            return $verified;
        }

        $role = sanitize_key($request->get_param('role'));
        if ('' !== $role && !RoleDashboards::userCanAccessRole($role)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this dashboard.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        return true;
    }

    public static function verifyRestRequest(WP_REST_Request $request)
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

    public static function getDashboardData(WP_REST_Request $request)
    {
        $requested_role = sanitize_key($request->get_param('role'));

        if ($requested_role) {
            if (!RoleDashboards::userCanAccessRole($requested_role)) {
                return new \WP_REST_Response([
                    'message' => __('You do not have permission to view this dashboard.', 'artpulse-management'),
                ], 403);
            }

            $role = $requested_role;
        } else {
            $role = RoleDashboards::getDefaultRoleForUser();
        }

        if (!$role) {
            return new \WP_REST_Response([
                'message' => __('Unable to determine an applicable dashboard.', 'artpulse-management'),
            ], 404);
        }

        $user_id = get_current_user_id();

        $data = RoleDashboards::prepareDashboardData($role, $user_id ?: null);

        if (empty($data)) {
            return new \WP_REST_Response([
                'message' => __('Unable to load dashboard data.', 'artpulse-management'),
            ], 404);
        }

        $profile_summary = $data['profile'] ?? [];

        $data['upgrade'] = [
            'requests'    => self::getUpgradeRequestsForUser($user_id),
            'can_request' => [
                'artist' => self::canRequestArtistUpgrade($user_id),
                'org'    => self::canRequestOrgUpgrade($user_id),
            ],
        ];

        $data['profile_summary'] = $profile_summary;
        $data['profile']          = self::buildProfileBlock($user_id, $role);

        return rest_ensure_response($data);
    }

    public static function updateProfile(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_REST_Response([
                'message' => __('You must be logged in to update your profile.', 'artpulse-management'),
            ], 401);
        }

        $params = $request->get_json_params();

        if (!is_array($params)) {
            return new \WP_REST_Response([
                'message' => __('Invalid profile payload.', 'artpulse-management'),
            ], 400);
        }

        $profile_input    = [];
        $membership_input = [];

        if (isset($params['profile']) && is_array($params['profile'])) {
            $profile_input = $params['profile'];
        } else {
            $profile_input = $params;
        }

        if (isset($params['membership']) && is_array($params['membership'])) {
            $membership_input = $params['membership'];
        } elseif (isset($profile_input['membership']) && is_array($profile_input['membership'])) {
            $membership_input = $profile_input['membership'];
            unset($profile_input['membership']);
        }

        $user_update_args = [ 'ID' => $user_id ];
        $should_update_user = false;
        $updates_applied    = false;

        $sanitized_profile_fields    = [];
        $sanitized_membership_fields = [];

        if (isset($profile_input['display_name'])) {
            if (!is_string($profile_input['display_name'])) {
                return new \WP_REST_Response([
                    'message' => __('The display name must be a string value.', 'artpulse-management'),
                ], 400);
            }

            $display_name = sanitize_text_field($profile_input['display_name']);
            $user_update_args['display_name'] = $display_name;
            $sanitized_profile_fields['display_name'] = $display_name;
            $should_update_user = true;
        }

        if (isset($profile_input['first_name'])) {
            if (!is_string($profile_input['first_name'])) {
                return new \WP_REST_Response([
                    'message' => __('The first name must be a string value.', 'artpulse-management'),
                ], 400);
            }

            $first_name = sanitize_text_field($profile_input['first_name']);
            $user_update_args['first_name'] = $first_name;
            $sanitized_profile_fields['first_name'] = $first_name;
            $should_update_user = true;
        }

        if (isset($profile_input['last_name'])) {
            if (!is_string($profile_input['last_name'])) {
                return new \WP_REST_Response([
                    'message' => __('The last name must be a string value.', 'artpulse-management'),
                ], 400);
            }

            $last_name = sanitize_text_field($profile_input['last_name']);
            $user_update_args['last_name'] = $last_name;
            $sanitized_profile_fields['last_name'] = $last_name;
            $should_update_user = true;
        }

        $bio_value = null;

        if (isset($profile_input['biography'])) {
            $bio_value = $profile_input['biography'];
        } elseif (isset($profile_input['bio'])) {
            $bio_value = $profile_input['bio'];
        } elseif (isset($profile_input['description'])) {
            $bio_value = $profile_input['description'];
        }

        if ($bio_value !== null) {
            if (!is_string($bio_value)) {
                return new \WP_REST_Response([
                    'message' => __('The biography must be a string value.', 'artpulse-management'),
                ], 400);
            }

            $biography = wp_kses_post($bio_value);
            update_user_meta($user_id, 'description', $biography);
            $sanitized_profile_fields['biography'] = $biography;
            $sanitized_profile_fields['bio']        = $biography;
            $sanitized_profile_fields['description'] = $biography;
            $updates_applied = true;
        }

        if (isset($profile_input['website'])) {
            if (!is_string($profile_input['website'])) {
                return new \WP_REST_Response([
                    'message' => __('The website must be a string value.', 'artpulse-management'),
                ], 400);
            }

            $website = esc_url_raw($profile_input['website']);
            $user_update_args['user_url'] = $website;
            $sanitized_profile_fields['website'] = $website;
            $should_update_user = true;

            // Maintain legacy social website meta for consistency across forms.
            update_user_meta($user_id, 'ap_social_website', $website);
        } elseif (isset($profile_input['user_url'])) {
            if (!is_string($profile_input['user_url'])) {
                return new \WP_REST_Response([
                    'message' => __('The website must be a string value.', 'artpulse-management'),
                ], 400);
            }

            $website = esc_url_raw($profile_input['user_url']);
            $user_update_args['user_url'] = $website;
            $sanitized_profile_fields['website'] = $website;
            $should_update_user = true;

            update_user_meta($user_id, 'ap_social_website', $website);
        }

        $legacy_social_fields = [
            'ap_social_twitter'   => 'twitter',
            'ap_social_instagram' => 'instagram',
            'ap_social_website'   => 'website',
        ];

        foreach ($legacy_social_fields as $legacy_key => $social_key) {
            if (!array_key_exists($legacy_key, $profile_input)) {
                continue;
            }

            if (!isset($profile_input['social']) || !is_array($profile_input['social'])) {
                $profile_input['social'] = [];
            }

            $profile_input['social'][$social_key] = $profile_input[$legacy_key];
        }

        if (isset($profile_input['social']) && is_array($profile_input['social'])) {
            $social_map = [
                'twitter'   => 'ap_social_twitter',
                'instagram' => 'ap_social_instagram',
                'website'   => 'ap_social_website',
            ];

            foreach ($social_map as $key => $meta_key) {
                if (!array_key_exists($key, $profile_input['social'])) {
                    continue;
                }

                $value = $profile_input['social'][$key];

                if ($value === null || $value === '') {
                    delete_user_meta($user_id, $meta_key);
                    $sanitized_profile_fields['social'][$key] = '';
                    $updates_applied = true;
                    continue;
                }

                if (!is_string($value)) {
                    return new \WP_REST_Response([
                        'message' => __('Social profile URLs must be strings.', 'artpulse-management'),
                    ], 400);
                }

                $sanitized = esc_url_raw($value);
                update_user_meta($user_id, $meta_key, $sanitized);
                $sanitized_profile_fields['social'][$key] = $sanitized;
                $updates_applied = true;
            }
        }

        if ($should_update_user) {
            $result = wp_update_user($user_update_args);

            if (is_wp_error($result)) {
                return new \WP_REST_Response([
                    'message' => __('Unable to save profile changes.', 'artpulse-management'),
                    'errors'  => $result->get_error_messages(),
                ], 500);
            }

            $updates_applied = true;
        }

        if (!empty($membership_input)) {
            if (!current_user_can('manage_options') && !current_user_can('promote_users')) {
                return new \WP_REST_Response([
                    'message' => __('You are not allowed to modify membership details.', 'artpulse-management'),
                ], 403);
            }

            if (isset($membership_input['level'])) {
                if (!is_string($membership_input['level'])) {
                    return new \WP_REST_Response([
                        'message' => __('The membership level must be a string value.', 'artpulse-management'),
                    ], 400);
                }

                $level = sanitize_text_field($membership_input['level']);
                update_user_meta($user_id, 'ap_membership_level', $level);
                $sanitized_membership_fields['level'] = $level;
                $updates_applied = true;
            }

            $expires_raw = null;

            if (array_key_exists('expires', $membership_input)) {
                $expires_raw = $membership_input['expires'];
            } elseif (array_key_exists('expires_timestamp', $membership_input)) {
                $expires_raw = $membership_input['expires_timestamp'];
            }

            if ($expires_raw !== null) {
                if ($expires_raw === '' || $expires_raw === false) {
                    delete_user_meta($user_id, 'ap_membership_expires');
                    $sanitized_membership_fields['expires'] = null;
                } elseif (is_numeric($expires_raw)) {
                    $timestamp = (int) $expires_raw;
                    update_user_meta($user_id, 'ap_membership_expires', $timestamp);
                    $sanitized_membership_fields['expires'] = $timestamp;
                } elseif (is_string($expires_raw)) {
                    $timestamp = strtotime($expires_raw);

                    if ($timestamp === false) {
                        return new \WP_REST_Response([
                            'message' => __('The membership expiry date is invalid.', 'artpulse-management'),
                        ], 400);
                    }

                    update_user_meta($user_id, 'ap_membership_expires', $timestamp);
                    $sanitized_membership_fields['expires'] = $timestamp;
                } else {
                    return new \WP_REST_Response([
                        'message' => __('The membership expiry date is invalid.', 'artpulse-management'),
                    ], 400);
                }

                $updates_applied = true;
            }
        }

        if (!$updates_applied) {
            return new \WP_REST_Response([
                'message' => __('No profile changes were supplied.', 'artpulse-management'),
            ], 400);
        }

        $requested_role = isset($params['role']) ? sanitize_key((string) $params['role']) : '';
        $role           = '';

        if ($requested_role !== '' && RoleDashboards::userCanAccessRole($requested_role, $user_id)) {
            $role = $requested_role;
        } else {
            $role = RoleDashboards::getDefaultRoleForUser($user_id) ?: '';
        }

        $profile_summary = [];

        if ($role !== '') {
            $dashboard_data  = RoleDashboards::prepareDashboardData($role, $user_id);
            $profile_summary = $dashboard_data['profile'] ?? [];

            if (!empty($profile_summary['membership']['expires_display'])) {
                $profile_summary['membership']['renewal_label'] = sprintf(
                    /* translators: %s: formatted membership renewal date. */
                    esc_html__('Renews %s', 'artpulse-management'),
                    $profile_summary['membership']['expires_display']
                );
            }
        }

        return rest_ensure_response([
            'success'             => true,
            'message'             => __('Profile updated successfully.', 'artpulse-management'),
            'profile'             => $profile_summary,
            'role'                => $role,
            'fields'              => $sanitized_profile_fields,
            'membership_fields'   => $sanitized_membership_fields,
        ]);
    }

    /**
     * Retrieve upgrade requests for the current user.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function getUpgradeRequestsForUser(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $requests = UpgradeReviewRepository::get_all_for_user($user_id);

        return array_map([self::class, 'formatUpgradeRequest'], $requests);
    }

    private static function formatUpgradeRequest(WP_Post $post): array
    {
        $status          = UpgradeReviewRepository::get_status($post);
        $repository_type = UpgradeReviewRepository::get_type($post);
        $type            = self::mapRepositoryTypeToResponse($repository_type);
        $reason          = UpgradeReviewRepository::get_reason($post);

        return [
            'id'         => (int) $post->ID,
            'type'       => $type,
            'status'     => $status,
            'reason'     => $reason !== '' ? $reason : null,
            'created_at' => self::formatDatetime($post->post_date_gmt, $post->post_date),
            'updated_at' => self::formatDatetime($post->post_modified_gmt, $post->post_modified),
        ];
    }

    private static function mapRepositoryTypeToResponse(string $type): string
    {
        return match (sanitize_key($type)) {
            'artist' => 'artist',
            default  => 'org',
        };
    }

    private static function formatDatetime(string $gmt, string $local): ?string
    {
        $source = $gmt;

        if ('' === $source) {
            $source = get_gmt_from_date($local);
        }

        if (!$source) {
            return null;
        }

        return mysql_to_rfc3339($source);
    }

    private static function canRequestArtistUpgrade(int $user_id): bool
    {
        return self::canRequestUpgrade($user_id, 'artist', UpgradeReviewRepository::TYPE_ARTIST);
    }

    private static function canRequestOrgUpgrade(int $user_id): bool
    {
        return self::canRequestUpgrade($user_id, 'organization', UpgradeReviewRepository::TYPE_ORG);
    }

    private static function canRequestUpgrade(int $user_id, string $role, string $review_type): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $user = get_user_by('id', $user_id);

        if (!$user instanceof WP_User) {
            return false;
        }

        if (in_array($role, (array) $user->roles, true)) {
            return false;
        }

        $existing = UpgradeReviewRepository::get_latest_for_user($user_id, $review_type);

        if ($existing instanceof WP_Post && UpgradeReviewRepository::STATUS_PENDING === UpgradeReviewRepository::get_status($existing)) {
            return false;
        }

        return true;
    }

    private static function buildProfileBlock(int $user_id, string $role): array
    {
        $artist = ProfileState::for_user('artist', $user_id);
        $org    = ProfileState::for_user('org', $user_id);

        return [
            'artist' => self::normaliseProfileState('artist', $artist, $role),
            'org'    => self::normaliseProfileState('org', $org, $role),
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function normaliseProfileState(string $type, array $state, string $role): array
    {
        $exists = (bool) ($state['exists'] ?? false);
        $raw_status = isset($state['status']) ? (string) $state['status'] : '';
        if (!$exists) {
            $status = 'none';
        } else {
            $status = match ($raw_status) {
                'publish' => 'publish',
                default   => 'draft',
            };
        }

        $post_id = isset($state['post_id']) ? (int) $state['post_id'] : 0;
        if ($post_id <= 0) {
            $post_id = null;
        }

        $builder_url = self::buildBuilderUrl($type, $state['builder_url'] ?? null, $role);

        return [
            'exists'      => $exists,
            'status'      => $status,
            'post_id'     => $post_id,
            'builder_url' => $builder_url,
        ];
    }

    private static function buildBuilderUrl(string $type, ?string $base_url, string $role): string
    {
        $page_key = 'artist' === $type ? 'artist_builder_page_id' : 'org_builder_page_id';

        $base = is_string($base_url) && $base_url !== '' ? $base_url : get_page_url($page_key);

        if (!$base) {
            $base = get_missing_page_fallback($page_key);
        }

        $query_type = 'artist' === $type ? 'artist' : 'organization';

        $args = [
            'ap_builder' => $query_type,
            'autocreate' => '1',
        ];

        $redirect = self::getDashboardRedirectUrl($role);
        if ($redirect !== '') {
            $args['redirect'] = $redirect;
        }

        $url = add_query_args($base, $args);

        return esc_url_raw($url);
    }

    private static function getDashboardRedirectUrl(string $role): string
    {
        $base = get_page_url('dashboard_page_id');

        if (!$base) {
            $base = get_missing_page_fallback('dashboard_page_id');
        }

        if (!is_string($base) || $base === '') {
            return '';
        }

        if ($role !== '') {
            $base = add_query_args($base, ['role' => $role]);
        }

        return esc_url_raw($base);
    }

    public static function renderDashboard($atts)
    {
        if ( ! is_user_logged_in() ) {
            auth_redirect();
            exit;
        }
        $role = RoleDashboards::getDefaultRoleForUser();

        if (!$role) {
            return '<div class="ap-dashboard-message">' . esc_html__('No dashboard is available for your account.', 'artpulse-management') . '</div>';
        }

        $classes = sprintf('ap-user-dashboard ap-role-dashboard ap-role-dashboard--%s', esc_attr($role));
        $loading = esc_html__('Loading dashboard…', 'artpulse-management');

        return sprintf(
            '<div class="%1$s" data-ap-dashboard-role="%2$s"><div class="ap-dashboard-loading">%3$s</div></div>',
            $classes,
            esc_attr($role),
            $loading
        );
    }

}
