<?php
namespace ArtPulse\Admin;

class EnqueueAssets {

    public static function register() {
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_block_editor_assets']);
        add_action('enqueue_block_editor_styles', [self::class, 'enqueue_block_editor_styles']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend']);
    }

    public static function enqueue_block_editor_assets() {
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
            return;
        }

        $plugin_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE);
        $plugin_dir = plugin_dir_path(ARTPULSE_PLUGIN_FILE);

        $editor_scripts = [
            'artpulse-taxonomy-sidebar' => 'sidebar-taxonomies',
            'artpulse-advanced-taxonomy-filter-block' => 'advanced-taxonomy-filter-block',
            'artpulse-filtered-list-shortcode-block' => 'filtered-list-shortcode-block',
            'artpulse-ajax-filter-block' => 'ajax-filter-block',
        ];

        foreach ($editor_scripts as $handle => $filename) {
            $asset_file = $plugin_dir . '/build/' . $filename . '.asset.php';
            $script_file = $plugin_dir . '/build/' . $filename . '.js';

            if (!file_exists($script_file)) {
                continue;
            }

            $asset = [
                'dependencies' => [],
                'version' => filemtime($script_file),
            ];

            if (file_exists($asset_file)) {
                $asset = include $asset_file;
            }

            wp_enqueue_script(
                $handle,
                $plugin_url . '/build/' . $filename . '.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
        }
    }

    public static function enqueue_block_editor_styles() {
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
            return;
        }

        $plugin_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE);
        $style_path = plugin_dir_path(ARTPULSE_PLUGIN_FILE) . '/assets/css/editor-styles.css';
        $style_url = $plugin_url . '/assets/css/editor-styles.css';

        if (file_exists($style_path)) {
            wp_enqueue_style(
                'artpulse-editor-styles',
                $style_url,
                [],
                filemtime($style_path)
            );
        }
    }

    public static function enqueue_admin() {
        $screen = get_current_screen();
        if (!isset($screen->id)) return;

        $plugin_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE);
        $plugin_dir = plugin_dir_path(ARTPULSE_PLUGIN_FILE);

        if ($screen->base === 'artpulse-settings_page_artpulse-engagement') {
            $chart_js_path = $plugin_dir . '/assets/vendor/chart.min.js';
            $chart_js_url  = $plugin_url . '/assets/vendor/chart.min.js';
            if (file_exists($chart_js_path)) {
                wp_enqueue_script(
                    'chart-js',
                    $chart_js_url,
                    [],
                    filemtime($chart_js_path),
                    true
                );
            }

            $custom_js_path = $plugin_dir . '/assets/js/ap-engagement-dashboard.js';
            $custom_js_url = $plugin_url . '/assets/js/ap-engagement-dashboard.js';
            if (file_exists($custom_js_path)) {
                wp_enqueue_script(
                    'ap-engagement-dashboard',
                    $custom_js_url,
                    ['chart-js'],
                    filemtime($custom_js_path),
                    true
                );
            }
        }

        // Enqueue Core-specific admin assets (if not already enqueued on frontend)
        // Check if they are already enqueued in the frontend, if not, enqueue them here
        if (!wp_script_is('ap-user-dashboard', 'enqueued')) {
            wp_enqueue_style(
                'ap-user-dashboard-css',
                $plugin_url . '/assets/css/ap-user-dashboard.css',
                [],
                '1.0.0'
            );
            wp_enqueue_script(
                'ap-user-dashboard-js',
                $plugin_url . '/assets/js/ap-user-dashboard.js',
                [],
                '1.0.0',
                true
            );
        }
         if (!wp_script_is('ap-analytics', 'enqueued')) {
             wp_enqueue_script(
                'ap-analytics-js',
                $plugin_url . '/assets/js/ap-analytics.js',
                [],
                '1.0.0',
                true
            );
         }
        if (!wp_script_is('ap-my-follows', 'enqueued')) {
             wp_enqueue_script(
                'ap-my-follows-js',
                $plugin_url . '/assets/js/ap-my-follows.js',
                [],
                '1.0.0',
                true
            );
         }
    }

    public static function enqueue_frontend() {
        $plugin_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE);

        wp_enqueue_script(
            'ap-membership-account-js',
            $plugin_url . '/assets/js/ap-membership-account.js',
            ['wp-api-fetch'],
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'ap-social-js',
            $plugin_url . '/assets/js/ap-social.js',
            [],
            '1.0.0',
            true
        );
        wp_localize_script('ap-social-js', 'APSocial', [
            'root'     => esc_url_raw(rest_url()),
            'nonce'    => wp_create_nonce('wp_rest'),
            'messages' => [
                'favoriteError' => __('Unable to update favorite. Please try again.', 'artpulse'),
                'followError'   => __('Unable to update follow. Please try again.', 'artpulse'),
            ],
        ]);

        wp_enqueue_script(
            'ap-notifications-js',
            $plugin_url . '/assets/js/ap-notifications.js',
            ['wp-api-fetch'],
            '1.0.0',
            true
        );
        wp_localize_script('ap-notifications-js', 'APNotifications', [
            'apiRoot' => esc_url_raw(rest_url()),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_script(
            'ap-submission-form-js',
            $plugin_url . '/assets/js/ap-submission-form.js',
            ['wp-api-fetch'],
            '1.0.0',
            true
        );
        wp_localize_script('ap-submission-form-js', 'APSubmission', [
            'endpoint'      => esc_url_raw(rest_url('artpulse/v1/submissions')),
            'mediaEndpoint' => esc_url_raw(rest_url('wp/v2/media')),
            'nonce'         => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_style(
            'ap-forms-css',
            $plugin_url . '/assets/css/ap-forms.css',
            [],
            '1.0.0'
        );

        wp_enqueue_style(
            'ap-directory-css',
            $plugin_url . '/assets/css/ap-directory.css',
            [],
            '1.0.0'
        );

        // Enqueue user dashboard styles (Frontend)

        wp_enqueue_script(
            'ap-analytics',
            $plugin_url . '/assets/js/ap-analytics.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'ap-directory',
            $plugin_url . '/assets/js/ap-directory.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'ap-my-follows',
            $plugin_url . '/assets/js/ap-my-follows.js',
            ['jquery'],
            '1.0.0',
            true
        );

        $org_dashboard_path = plugin_dir_path(ARTPULSE_PLUGIN_FILE) . '/assets/js/ap-org-dashboard.js';
        $org_dashboard_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE) . '/assets/js/ap-org-dashboard.js';
        if (file_exists($org_dashboard_path)) {
            wp_enqueue_script(
                'ap-org-dashboard',
                $org_dashboard_url,
                ['jquery'],
                '1.0.0',
                true
            );
            wp_localize_script('ap-org-dashboard', 'APOrgDashboard', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ap_org_dashboard_nonce'),
            ]);
        }

        wp_enqueue_script(
            'ap-user-dashboard',
            $plugin_url . '/assets/js/ap-user-dashboard.js',
            ['jquery'],
            '1.0.0',
            true
        );

        $opts = get_option('artpulse_settings', []);
        if (!empty($opts['service_worker_enabled'])) {
            wp_enqueue_script(
                'ap-sw-loader',
                $plugin_url . '/assets/js/sw-loader.js',
                [],
                '1.0.0',
                true
            );
            wp_localize_script('ap-sw-loader', 'APServiceWorker', [
                'url'     => $plugin_url . '/assets/js/service-worker.js',
                'enabled' => true,
            ]);
        }
    }
}