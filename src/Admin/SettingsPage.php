<?php
namespace ArtPulse\Admin;
class SettingsPage
{
    public static function register()
    {
        add_action('admin_menu', [self::class, 'addMenu']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('wp_login', [self::class, 'trackLastLogin'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminAssets']);
    }
    public static function addMenu()
    {
        add_menu_page(
            __('ArtPulse', 'artpulse'),
            __('ArtPulse', 'artpulse'),
            'manage_options',
            'artpulse-settings',
            [self::class, 'render'],
            'dashicons-admin-generic',
            56
        );
        add_submenu_page(
            'artpulse-settings',
            __('Settings', 'artpulse'),
            __('Settings', 'artpulse'),
            'manage_options',
            'artpulse-settings',
            [self::class, 'render']
        );
        add_submenu_page(
            'artpulse-settings',
            __('Members', 'artpulse'),
            __('Members', 'artpulse'),
            'manage_options',
            'artpulse-members',
            [self::class, 'renderMembersPage']
        );
        add_submenu_page(
            'artpulse-settings',
            __('Engagement Dashboard', 'artpulse'),
            __('Engagement', 'artpulse'),
            'manage_options',
            'artpulse-engagement',
            [EngagementDashboard::class, 'render']
        );
    }
    public static function enqueueAdminAssets($hook)
    {
        global $current_screen;
        if (isset($current_screen->id) && $current_screen->id != 'toplevel_page_artpulse-settings') {
            return;
        }
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        wp_enqueue_script('ap-admin-dashboard', plugins_url('/assets/js/ap-admin-dashboard.js', ARTPULSE_PLUGIN_FILE), ['chart-js'], '1.0', true);
        $signup_data = self::getMonthlySignupsByLevel();
        wp_localize_script('ap-admin-dashboard', 'APAdminStats', $signup_data);
    }
    public static function getMonthlySignupsByLevel()
    {
        global $wpdb;
        $levels = ['free', 'pro', 'org'];
        $data = [];
        foreach ($levels as $level) {
            $counts = [];
            for ($i = 0; $i < 6; $i++) {
                $month = date('Y-m-01', strtotime("-{$i} months"));
                $nextMonth = date('Y-m-01', strtotime("-" . ($i - 1) . " months"));
                $users = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $wpdb->usermeta AS um
                         JOIN $wpdb->users AS u ON u.ID = um.user_id
                         WHERE um.meta_key = 'ap_membership_level'
                         AND um.meta_value = %s
                         AND u.user_registered >= %s AND u.user_registered < %s",
                        $level, $month, $nextMonth
                    )
                );
                $counts[] = intval($users);
            }
            $data[$level] = array_reverse($counts); // recent months last
        }
        return $data;
    }
    public static function trackLastLogin($user_login, $user)
    {
        update_user_meta($user->ID, 'last_login', current_time('mysql'));
    }
    public static function renderMembersPage()
    {
        $search_query = sanitize_text_field($_GET['ap_search'] ?? '');
        $level_filter = sanitize_text_field($_GET['ap_level'] ?? '');
        $args = [
            'search'         => "*{$search_query}*",
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'orderby'        => 'registered',
            'order'          => 'DESC',
            'number'         => 100,
        ];
        if (!empty($level_filter)) {
            $args['meta_query'] = [[
                'key'   => 'ap_membership_level',
                'value' => $level_filter,
            ]];
        }
        $users = get_users($args);
        // CSV Export
        if (isset($_GET['ap_export_csv'])) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="artpulse-members.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Email', 'Role', 'Membership Level', 'Submissions', 'Last Login', 'Registered', 'Expiry']);
            foreach ($users as $user) {
                $level = get_user_meta($user->ID, 'ap_membership_level', true);
                $last_login = get_user_meta($user->ID, 'last_login', true);
                $expires = get_user_meta($user->ID, 'ap_membership_expires', true);
                fputcsv($output, [
                    $user->display_name ?: $user->user_login,
                    $user->user_email,
                    implode(', ', $user->roles),
                    $level ?: '—',
                    count_user_posts($user->ID, 'artwork'), // change to match your CPT
                    $last_login ?: '—',
                    $user->user_registered,
                    $expires ?: '—',
                ]);
            }
            fclose($output);
            exit;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ArtPulse Members', 'artpulse'); ?></h1>
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="artpulse-members" />
                <input type="text" name="ap_search" placeholder="<?php esc_attr_e('Search users...', 'artpulse'); ?>" value="<?php echo esc_attr($search_query); ?>" />
                <select name="ap_level">
                    <option value=""><?php esc_html_e('All Levels', 'artpulse'); ?></option>
                    <option value="free" <?php selected($level_filter, 'free'); ?>><?php esc_html_e('Free', 'artpulse'); ?></option>
                    <option value="pro" <?php selected($level_filter, 'pro'); ?>><?php esc_html_e('Pro', 'artpulse'); ?></option>
                    <option value="org" <?php selected($level_filter, 'org'); ?>><?php esc_html_e('Org', 'artpulse'); ?></option>
                </select>
                <button type="submit" class="button"><?php esc_html_e('Filter', 'artpulse'); ?></button>
                <button type="submit" name="ap_export_csv" class="button-secondary"><?php esc_html_e('Export CSV', 'artpulse'); ?></button>
            </form>
            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Email', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Level', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Submissions', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Last Login', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Registered', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Expires', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Actions', 'artpulse'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user):
                    $level     = get_user_meta($user->ID, 'ap_membership_level', true);
                    $last_login = get_user_meta($user->ID, 'last_login', true);
                    $expires   = get_user_meta($user->ID, 'ap_membership_expires', true);
                    $count     = count_user_posts($user->ID, 'artwork'); // change post type if needed
                    ?>
                    <tr>
                        <td><?php echo esc_html($user->display_name ?: $user->user_login); ?></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php echo esc_html($level ?: '—'); ?></td>
                        <td><?php echo esc_html($count); ?></td>
                        <td><?php echo esc_html($last_login ?: '—'); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))); ?></td>
                        <td><?php echo esc_html($expires ?: '—'); ?></td>
                        <td>
                            <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>"><?php esc_html_e('View', 'artpulse'); ?></a>
                            |
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url("users.php?action=resetpassword&user={$user->ID}"), 'reset_user_password_' . $user->ID)); ?>">
                                <?php esc_html_e('Reset Password', 'artpulse'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8"><?php esc_html_e('No members found.', 'artpulse'); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    public static function render()
    {
        if (isset($_POST['ap_test_webhook']) && check_admin_referer('ap_test_webhook_action')) {
            $log = get_option('artpulse_webhook_log', []);
            $log[] = ['type' => 'invoice.paid', 'time' => current_time('mysql')];
            if (count($log) > 20) {
                $log = array_slice($log, -20);
            }
            update_option('artpulse_webhook_log', $log);
            update_option('artpulse_webhook_status', 'Simulated');
            update_option('artpulse_webhook_last_event', end($log));
            echo '<div class="notice notice-success"><p>' . esc_html__('Webhook simulated successfully.', 'artpulse') . '</p></div>';
        }
        if (isset($_POST['ap_clear_webhook_log']) && check_admin_referer('ap_clear_webhook_log_action')) {
            delete_option('artpulse_webhook_log');
            echo '<div class="notice notice-success"><p>' . esc_html__('Webhook log cleared.', 'artpulse') . '</p></div>';
        }
        $webhook_status = get_option('artpulse_webhook_status', 'Unknown');
        $last_event     = get_option('artpulse_webhook_last_event', []);
        $log            = get_option('artpulse_webhook_log', []);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ArtPulse Settings', 'artpulse'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('artpulse_settings_group');
                do_settings_sections('artpulse-settings');
                submit_button();
                ?>
            </form>
            <hr>
            <h2><?php esc_html_e('System Status', 'artpulse'); ?></h2>
            <p>
                <strong><?php esc_html_e('Webhook Status:', 'artpulse'); ?></strong>
                <?php echo esc_html($webhook_status); ?><br>
                <strong><?php esc_html_e('Last Webhook Event:', 'artpulse'); ?></strong>
                <?php echo esc_html($last_event['type'] ?? 'None'); ?><br>
                <strong><?php esc_html_e('Received At:', 'artpulse'); ?></strong>
                <?php echo esc_html($last_event['time'] ?? 'N/A'); ?>
            </p>
            <h2><?php esc_html_e('Webhook Event Log', 'artpulse'); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Timestamp', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Event Type', 'artpulse'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                if (empty($log)) {
                    echo '<tr><td colspan="2">' . esc_html__('No webhook events logged.', 'artpulse') . '</td></tr>';
                } else {
                    foreach (array_reverse($log) as $entry) {
                        echo '<tr>';
                        echo '<td>' . esc_html($entry['time']) . '</td>';
                        echo '<td>' . esc_html($entry['type']) . '</td>';
                        echo '</tr>';
                    }
                }
                ?>
                </tbody>
            </table>
            <form method="post" style="margin-top: 10px;">
                <?php wp_nonce_field('ap_test_webhook_action'); ?>
                <input type="submit" name="ap_test_webhook" class="button button-secondary" value="<?php esc_attr_e('Simulate Webhook Event', 'artpulse'); ?>">
            </form>
            <form method="post" style="margin-top: 10px;">
                <?php wp_nonce_field('ap_clear_webhook_log_action'); ?>
                <input type="submit" name="ap_clear_webhook_log" class="button button-secondary" value="<?php esc_attr_e('Clear Webhook Log', 'artpulse'); ?>">
            </form>
        </div>
        <?php
    }
    public static function registerSettings()
    {
        register_setting(
            'artpulse_settings_group',
            'artpulse_settings',
            ['sanitize_callback' => [self::class, 'sanitizeSettings']]
        );
        add_settings_section(
            'ap_general_section',
            __('General Settings', 'artpulse'),
            '__return_false',
            'artpulse-settings'
        );
        $fields = [
            'basic_fee' => [
                'label' => __('Basic Member Fee ($)', 'artpulse'),
                'desc'  => __('Monthly cost for Basic members. Leave blank to disable.', 'artpulse'),
            ],
            'pro_fee' => [
                'label' => __('Pro Artist Fee ($)', 'artpulse'),
                'desc'  => __('Subscription price for Pro Artists.', 'artpulse'),
            ],
            'org_fee' => [
                'label' => __('Organization Fee ($)', 'artpulse'),
                'desc'  => __('Fee charged to organizations.', 'artpulse'),
            ],
            'currency' => [
                'label' => __('Currency (ISO)', 'artpulse'),
                'desc'  => __('3-letter currency code (e.g., USD, EUR, GBP).', 'artpulse'),
            ],
            'stripe_enabled' => [
                'label' => __('Enable Stripe Integration', 'artpulse'),
                'desc'  => __('Enable Stripe to manage payments and subscriptions.', 'artpulse'),
            ],
            'stripe_pub_key' => [
                'label' => __('Stripe Publishable Key', 'artpulse'),
                'desc'  => __('Used for client-side Stripe operations.', 'artpulse'),
            ],
            'stripe_secret' => [
                'label' => __('Stripe Secret Key', 'artpulse'),
                'desc'  => __('Used for secure server-side API calls to Stripe.', 'artpulse'),
            ],
            'stripe_webhook_secret' => [
                'label' => __('Stripe Webhook Secret', 'artpulse'),
                'desc'  => __('Secret used to verify webhook calls from Stripe.', 'artpulse'),
            ],
            'service_worker_enabled' => [
                'label' => __('Enable Service Worker', 'artpulse'),
                'desc'  => __('Adds a service worker for basic offline caching.', 'artpulse'),
            ]
        ];
        foreach ($fields as $key => $config) {
            add_settings_field(
                $key,
                $config['label'],
                [self::class, 'renderField'],
                'artpulse-settings',
                'ap_general_section',
                [
                    'label_for'   => $key,
                    'description' => $config['desc'] ?? ''
                ]
            );
        }
    }
    public static function sanitizeSettings($input)
    {
        $output = [];
        foreach ($input as $key => $value) {
            if (in_array($key, ['stripe_enabled', 'woocommerce_enabled', 'debug_logging', 'service_worker_enabled'])) {
                $output[$key] = isset($value) ? 1 : 0;
            } else {
                $output[$key] = sanitize_text_field($value);
            }
        }
        return $output;
    }
    public static function renderField($args)
    {
        $options = get_option('artpulse_settings');
        $key     = $args['label_for'];
        $value   = $options[$key] ?? '';
        $desc    = $args['description'] ?? '';
        if (in_array($key, ['stripe_enabled', 'woocommerce_enabled', 'debug_logging', 'service_worker_enabled'])) {
            echo '<input type="checkbox" id="' . esc_attr($key) . '" name="artpulse_settings[' . esc_attr($key) . ']" value="1"' . checked(1, $value, false) . ' />';
        } else {
            echo '<input type="text" id="' . esc_attr($key) . '" name="artpulse_settings[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        }
        if ($desc) {
            echo '<p class="description">' . esc_html($desc) . '</p>';
        }
    }
}