<?php
namespace ArtPulse\Core;

use WP_REST_Request;

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
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('artpulse/v1', '/user/profile', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'updateProfile' ],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);
    }

    public static function getDashboardData(WP_REST_Request $request)
    {
        $requested_role = sanitize_key($request->get_param('role'));

        if ($requested_role) {
            if (!RoleDashboards::userCanAccessRole($requested_role)) {
                return new \WP_REST_Response([
                    'message' => __('You do not have permission to view this dashboard.', 'artpulse'),
                ], 403);
            }

            $role = $requested_role;
        } else {
            $role = RoleDashboards::getDefaultRoleForUser();
        }

        if (!$role) {
            return new \WP_REST_Response([
                'message' => __('Unable to determine an applicable dashboard.', 'artpulse'),
            ], 404);
        }

        $data = RoleDashboards::prepareDashboardData($role);

        if (empty($data)) {
            return new \WP_REST_Response([
                'message' => __('Unable to load dashboard data.', 'artpulse'),
            ], 404);
        }

        return rest_ensure_response($data);
    }

    public static function updateProfile(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();
        if ( isset($params['display_name']) ) {
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => sanitize_text_field($params['display_name']),
            ]);
        }
        return rest_ensure_response([ 'success' => true ]);
    }

    public static function renderDashboard($atts)
    {
        if ( ! is_user_logged_in() ) {
            auth_redirect();
            exit;
        }
        $role = RoleDashboards::getDefaultRoleForUser();

        if (!$role) {
            return '<div class="ap-dashboard-message">' . esc_html__('No dashboard is available for your account.', 'artpulse') . '</div>';
        }

        $classes = sprintf('ap-user-dashboard ap-role-dashboard ap-role-dashboard--%s', esc_attr($role));
        $loading = esc_html__('Loading dashboard…', 'artpulse');

        return sprintf(
            '<div class="%1$s" data-ap-dashboard-role="%2$s"><div class="ap-dashboard-loading">%3$s</div></div>',
            $classes,
            esc_attr($role),
            $loading
        );
    }

}
