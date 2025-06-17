<?php
namespace ArtPulse\Core;

use WP_REST_Request;

class UserDashboardManager
{
    public static function register()
    {
        add_shortcode('ap_user_dashboard', [ self::class, 'renderDashboard' ]);
        add_action('wp_enqueue_scripts',   [ self::class, 'enqueueAssets' ]);
        add_action('rest_api_init',        [ self::class, 'registerRestRoutes' ]);
    }

    public static function enqueueAssets()
    {
        // Core dashboard script
        wp_enqueue_script(
            'ap-user-dashboard-js',
            plugins_url('assets/js/ap-user-dashboard.js', __FILE__),
            ['wp-api-fetch'],
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

        // Localize dashboard REST endpoint
        wp_localize_script('ap-user-dashboard-js', 'ArtPulseDashboardApi', [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

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
        $user_id = get_current_user_id();
        $data = [
            'membership_level'   => get_user_meta($user_id, 'ap_membership_level', true),
            'membership_expires' => get_user_meta($user_id, 'ap_membership_expires', true),
            'events'             => [],
            'artists'            => [],
            'artworks'           => [],
        ];

        foreach ( ['event','artist','artwork'] as $type ) {
            $posts = get_posts([
                'post_type'      => "artpulse_{$type}",
                'author'         => $user_id,
                'posts_per_page' => -1,
            ]);
            foreach ( $posts as $p ) {
                $data[$type . 's'][] = [
                    'id'    => $p->ID,
                    'title' => $p->post_title,
                    'link'  => get_permalink($p),
                ];
            }
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
            return '<p>' . __('Please log in to view your dashboard.', 'artpulse') . '</p>';
        }
        ob_start(); ?>
        <div class="ap-user-dashboard">
            <h2><?php _e('Your Membership','artpulse'); ?></h2>
            <div id="ap-membership-info"></div>
            <h2><?php _e('Your Content','artpulse'); ?></h2>
            <div id="ap-user-content"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
