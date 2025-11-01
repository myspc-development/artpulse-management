<?php
namespace ArtPulse\Admin;

use ArtPulse\Mobile\JWT;

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
            __('ArtPulse', 'artpulse-management'),
            __('ArtPulse', 'artpulse-management'),
            'manage_options',
            'artpulse-settings',
            [self::class, 'render'],
            'dashicons-admin-generic',
            56
        );
        add_submenu_page(
            'artpulse-settings',
            __('Settings', 'artpulse-management'),
            __('Settings', 'artpulse-management'),
            'manage_options',
            'artpulse-settings',
            [self::class, 'render']
        );
        add_submenu_page(
            'artpulse-settings',
            __('Members', 'artpulse-management'),
            __('Members', 'artpulse-management'),
            'manage_options',
            'artpulse-members',
            [self::class, 'renderMembersPage']
        );
        add_submenu_page(
            'artpulse-settings',
            __('Engagement Dashboard', 'artpulse-management'),
            __('Engagement', 'artpulse-management'),
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
        $chart_js_path = plugin_dir_path(ARTPULSE_PLUGIN_FILE) . 'assets/vendor/chart.min.js';
        $chart_js_url  = plugins_url('assets/vendor/chart.min.js', ARTPULSE_PLUGIN_FILE);
        if (file_exists($chart_js_path)) {
            wp_enqueue_script('chart-js', $chart_js_url, [], filemtime($chart_js_path), true);
        }
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
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'artpulse-management'));
        }

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
                    count_user_posts($user->ID, 'artpulse_artwork'), // change to match your CPT
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
            <h1><?php esc_html_e('ArtPulse Members', 'artpulse-management'); ?></h1>
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="artpulse-members" />
                <input type="text" name="ap_search" placeholder="<?php esc_attr_e('Search users...', 'artpulse-management'); ?>" value="<?php echo esc_attr($search_query); ?>" />
                <select name="ap_level">
                    <option value=""><?php esc_html_e('All Levels', 'artpulse-management'); ?></option>
                    <option value="free" <?php selected($level_filter, 'free'); ?>><?php esc_html_e('Free', 'artpulse-management'); ?></option>
                    <option value="pro" <?php selected($level_filter, 'pro'); ?>><?php esc_html_e('Pro', 'artpulse-management'); ?></option>
                    <option value="org" <?php selected($level_filter, 'org'); ?>><?php esc_html_e('Org', 'artpulse-management'); ?></option>
                </select>
                <button type="submit" class="button"><?php esc_html_e('Filter', 'artpulse-management'); ?></button>
                <button type="submit" name="ap_export_csv" class="button-secondary"><?php esc_html_e('Export CSV', 'artpulse-management'); ?></button>
            </form>
            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Email', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Level', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Submissions', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Last Login', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Registered', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Expires', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Actions', 'artpulse-management'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user):
                    $level     = get_user_meta($user->ID, 'ap_membership_level', true);
                    $last_login = get_user_meta($user->ID, 'last_login', true);
                    $expires   = get_user_meta($user->ID, 'ap_membership_expires', true);
                    $count     = count_user_posts($user->ID, 'artpulse_artwork'); // change post type if needed
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
                            <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>"><?php esc_html_e('View', 'artpulse-management'); ?></a>
                            |
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url("users.php?action=resetpassword&user={$user->ID}"), 'reset_user_password_' . $user->ID)); ?>">
                                <?php esc_html_e('Reset Password', 'artpulse-management'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8"><?php esc_html_e('No members found.', 'artpulse-management'); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    public static function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'artpulse-management'));
        }

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            self::verify_admin_request();
        }

        if (isset($_POST['ap_rotate_jwt'])) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have sufficient permissions to access this action.', 'artpulse-management'), 403);
            }

            check_admin_referer('ap_jwt_rotate', 'ap_jwt_rotate_nonce');

            $invalidate = !empty($_POST['ap_invalidate_sessions']);
            $result     = JWT::rotate((bool) $invalidate);

            $message = sprintf(
                /* translators: %s: fingerprint */
                __('Signing key rotated. New fingerprint %s.', 'artpulse-management'),
                esc_html($result['new_fingerprint'])
            );

            if ($invalidate) {
                $revoked = (int) ($result['revoked_sessions'] ?? 0);
                if ($revoked > 0) {
                    $message .= ' ' . sprintf(
                        _n(
                            '%d mobile session was invalidated.',
                            '%d mobile sessions were invalidated.',
                            $revoked,
                            'artpulse-management'
                        ),
                        $revoked
                    );
                } else {
                    $message .= ' ' . __('No active mobile sessions required invalidation.', 'artpulse-management');
                }
            }

            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        } elseif (isset($_POST['ap_add_jwt_key']) && check_admin_referer('ap_add_jwt_key_action')) {
            $created = JWT::add_key();
            $message = sprintf(
                /* translators: %s: fingerprint */
                __('New signing key created (fingerprint %s).', 'artpulse-management'),
                esc_html($created['fingerprint'])
            );
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        } elseif (isset($_POST['ap_retire_jwt_key']) && isset($_POST['ap_jwt_kid']) && check_admin_referer('ap_manage_jwt_key_action')) {
            $kid    = sanitize_text_field(wp_unslash($_POST['ap_jwt_kid']));
            $result = JWT::retire_key($kid);
            if ($result) {
                $message = __('Signing key retired.', 'artpulse-management');
                echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            } else {
                $message = __('Signing key could not be retired.', 'artpulse-management');
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            }
        } elseif (isset($_POST['ap_activate_jwt_key']) && isset($_POST['ap_jwt_kid']) && check_admin_referer('ap_manage_jwt_key_action')) {
            $kid    = sanitize_text_field(wp_unslash($_POST['ap_jwt_kid']));
            $result = JWT::set_current_key($kid);
            if ($result) {
                $message = __('Signing key promoted to current.', 'artpulse-management');
                echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            } else {
                $message = __('Unable to promote the selected signing key.', 'artpulse-management');
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            }
        }

        if (isset($_POST['ap_test_webhook']) && check_admin_referer('ap_test_webhook_action')) {
            $log = get_option('artpulse_webhook_log', []);
            $log[] = ['type' => 'invoice.paid', 'time' => current_time('mysql')];
            if (count($log) > 20) {
                $log = array_slice($log, -20);
            }
            update_option('artpulse_webhook_log', $log);
            update_option('artpulse_webhook_status', 'Simulated');
            update_option('artpulse_webhook_last_event', end($log));
            echo '<div class="notice notice-success"><p>' . esc_html__('Webhook simulated successfully.', 'artpulse-management') . '</p></div>';
        }
        if (isset($_POST['ap_clear_webhook_log']) && check_admin_referer('ap_clear_webhook_log_action')) {
            delete_option('artpulse_webhook_log');
            echo '<div class="notice notice-success"><p>' . esc_html__('Webhook log cleared.', 'artpulse-management') . '</p></div>';
        }
        $webhook_status = get_option('artpulse_webhook_status', 'Unknown');
        $last_event     = get_option('artpulse_webhook_last_event', []);
        $log            = get_option('artpulse_webhook_log', []);
        $jwt_keys = JWT::get_keys_for_admin();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ArtPulse Settings', 'artpulse-management'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('artpulse_settings_group');
                do_settings_sections('artpulse-settings');
                submit_button();
                ?>
            </form>
            <hr>
            <h2><?php esc_html_e('Mobile Authentication Keys', 'artpulse-management'); ?></h2>
            <p><?php esc_html_e('Manage the signing keys used for mobile API access tokens. Retired keys remain valid until the grace period expires.', 'artpulse-management'); ?></p>
            <form method="post" style="margin-bottom: 10px;">
                <?php wp_nonce_field('ap_admin_action', 'ap_admin_nonce'); ?>
                <?php wp_nonce_field('ap_jwt_rotate', 'ap_jwt_rotate_nonce'); ?>
                <p>
                    <label for="ap_invalidate_sessions">
                        <input type="checkbox" name="ap_invalidate_sessions" id="ap_invalidate_sessions" value="1" />
                        <?php esc_html_e('Sign out all mobile devices after rotation.', 'artpulse-management'); ?>
                    </label>
                </p>
                <button type="submit" name="ap_rotate_jwt" class="button button-secondary"><?php esc_html_e('Rotate Signing Key', 'artpulse-management'); ?></button>
            </form>
            <form method="post" style="margin-bottom: 10px;">
                <?php wp_nonce_field('ap_admin_action', 'ap_admin_nonce'); ?>
                <?php wp_nonce_field('ap_add_jwt_key_action'); ?>
                <button type="submit" name="ap_add_jwt_key" class="button button-primary"><?php esc_html_e('Add Signing Key', 'artpulse-management'); ?></button>
            </form>
            <table class="widefat fixed striped" style="max-width: 900px;">
                <thead>
                <tr>
                    <th><?php esc_html_e('Key ID', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Fingerprint', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Status', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Created', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Retired', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Actions', 'artpulse-management'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($jwt_keys)) : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No signing keys found.', 'artpulse-management'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($jwt_keys as $key) : ?>
                        <tr>
                            <td><code><?php echo esc_html($key['kid']); ?></code><?php if (!empty($key['is_current'])) : ?> <span class="dashicons dashicons-yes" title="<?php esc_attr_e('Current key', 'artpulse-management'); ?>"></span><?php endif; ?></td>
                            <td><code><?php echo esc_html($key['fingerprint']); ?></code></td>
                            <td><?php echo esc_html(ucfirst($key['status'])); ?></td>
                            <td><?php echo $key['created_at'] ? esc_html(date_i18n(get_option('date_format'), (int) $key['created_at'])) : '—'; ?></td>
                            <td><?php echo $key['retired_at'] ? esc_html(date_i18n(get_option('date_format'), (int) $key['retired_at'])) : '—'; ?></td>
                            <td>
                                <?php if ('active' === $key['status']) : ?>
                                    <?php if (empty($key['is_current'])) : ?>
                                        <form method="post" style="display:inline">
                                            <?php wp_nonce_field('ap_admin_action', 'ap_admin_nonce'); ?>
                                            <?php wp_nonce_field('ap_manage_jwt_key_action'); ?>
                                            <input type="hidden" name="ap_jwt_kid" value="<?php echo esc_attr($key['kid']); ?>" />
                                            <button type="submit" name="ap_activate_jwt_key" class="button button-link"><?php esc_html_e('Make Current', 'artpulse-management'); ?></button>
                                        </form>
                                        |
                                    <?php endif; ?>
                                    <form method="post" style="display:inline">
                                        <?php wp_nonce_field('ap_admin_action', 'ap_admin_nonce'); ?>
                                        <?php wp_nonce_field('ap_manage_jwt_key_action'); ?>
                                        <input type="hidden" name="ap_jwt_kid" value="<?php echo esc_attr($key['kid']); ?>" />
                                        <button type="submit" name="ap_retire_jwt_key" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Retire this signing key?', 'artpulse-management')); ?>');"><?php esc_html_e('Retire', 'artpulse-management'); ?></button>
                                    </form>
                                <?php else : ?>
                                    <em><?php esc_html_e('Retired', 'artpulse-management'); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <p class="description"><?php esc_html_e('Retired keys are automatically purged 14 days after retirement.', 'artpulse-management'); ?></p>
            <hr>
            <h2><?php esc_html_e('System Status', 'artpulse-management'); ?></h2>
            <p>
                <strong><?php esc_html_e('Webhook Status:', 'artpulse-management'); ?></strong>
                <?php echo esc_html($webhook_status); ?><br>
                <strong><?php esc_html_e('Last Webhook Event:', 'artpulse-management'); ?></strong>
                <?php echo esc_html($last_event['type'] ?? 'None'); ?><br>
                <strong><?php esc_html_e('Received At:', 'artpulse-management'); ?></strong>
                <?php echo esc_html($last_event['time'] ?? 'N/A'); ?>
            </p>
            <h2><?php esc_html_e('Webhook Event Log', 'artpulse-management'); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Timestamp', 'artpulse-management'); ?></th>
                    <th><?php esc_html_e('Event Type', 'artpulse-management'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                if (empty($log)) {
                    echo '<tr><td colspan="2">' . esc_html__('No webhook events logged.', 'artpulse-management') . '</td></tr>';
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
                <?php wp_nonce_field('ap_admin_action', 'ap_admin_nonce'); ?>
                <?php wp_nonce_field('ap_test_webhook_action'); ?>
                <input type="submit" name="ap_test_webhook" class="button button-secondary" value="<?php esc_attr_e('Simulate Webhook Event', 'artpulse-management'); ?>">
            </form>
            <form method="post" style="margin-top: 10px;">
                <?php wp_nonce_field('ap_admin_action', 'ap_admin_nonce'); ?>
                <?php wp_nonce_field('ap_clear_webhook_log_action'); ?>
                <input type="submit" name="ap_clear_webhook_log" class="button button-secondary" value="<?php esc_attr_e('Clear Webhook Log', 'artpulse-management'); ?>">
            </form>
        </div>
        <?php
    }
    public static function registerSettings()
    {
        register_setting(
            'artpulse_settings_group',
            'ap_enable_org_builder',
            [
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => [self::class, 'sanitize_boolean'],
                'capability'        => 'manage_options',
            ]
        );

        register_setting(
            'artpulse_settings_group',
            'ap_enable_artist_builder',
            [
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => [self::class, 'sanitize_boolean'],
                'capability'        => 'manage_options',
            ]
        );

        register_setting(
            'artpulse_settings_group',
            'ap_require_event_review',
            [
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => [self::class, 'sanitize_boolean'],
                'capability'        => 'manage_options',
            ]
        );

        register_setting(
            'artpulse_settings_group',
            'ap_widget_whitelist',
            [
                'type'              => 'array',
                'default'           => [],
                'sanitize_callback' => [self::class, 'sanitize_widget_whitelist'],
                'capability'        => 'manage_options',
            ]
        );

        register_setting(
            'artpulse_settings_group',
            'artpulse_settings',
            ['sanitize_callback' => [self::class, 'sanitizeSettings']]
        );
        add_settings_section(
            'ap_general_section',
            __('General Settings', 'artpulse-management'),
            '__return_false',
            'artpulse-settings'
        );
        $fields = [
            'basic_fee' => [
                'label' => __('Basic Member Fee ($)', 'artpulse-management'),
                'desc'  => __('Monthly cost for Basic members. Leave blank to disable.', 'artpulse-management'),
            ],
            'pro_fee' => [
                'label' => __('Pro Artist Fee ($)', 'artpulse-management'),
                'desc'  => __('Subscription price for Pro Artists.', 'artpulse-management'),
            ],
            'org_fee' => [
                'label' => __('Organization Fee ($)', 'artpulse-management'),
                'desc'  => __('Fee charged to organizations.', 'artpulse-management'),
            ],
            'currency' => [
                'label' => __('Currency (ISO)', 'artpulse-management'),
                'desc'  => __('3-letter currency code (e.g., USD, EUR, GBP).', 'artpulse-management'),
            ],
            'stripe_enabled' => [
                'label' => __('Enable Stripe Integration', 'artpulse-management'),
                'desc'  => __('Enable Stripe to manage payments and subscriptions.', 'artpulse-management'),
            ],
            'stripe_pub_key' => [
                'label' => __('Stripe Publishable Key', 'artpulse-management'),
                'desc'  => __('Used for client-side Stripe operations.', 'artpulse-management'),
            ],
            'stripe_secret' => [
                'label' => __('Stripe Secret Key', 'artpulse-management'),
                'desc'  => __('Used for secure server-side API calls to Stripe.', 'artpulse-management'),
            ],
            'stripe_webhook_secret' => [
                'label' => __('Stripe Webhook Secret', 'artpulse-management'),
                'desc'  => __('Secret used to verify webhook calls from Stripe.', 'artpulse-management'),
            ],
            'service_worker_enabled' => [
                'label' => __('Enable Service Worker', 'artpulse-management'),
                'desc'  => __('Adds a service worker for basic offline caching.', 'artpulse-management'),
            ],
            'notification_provider' => [
                'label'   => __('Mobile Notification Provider', 'artpulse-management'),
                'desc'    => __('Choose how mobile push notifications are delivered. The null provider disables sending.', 'artpulse-management'),
                'type'    => 'select',
                'choices' => self::getNotificationProviderChoices(),
            ],
            'approved_mobile_origins' => [
                'label' => __('Approved Mobile Origins', 'artpulse-management'),
                'desc'  => __('Enter one HTTPS origin per line to allow mobile API access.', 'artpulse-management'),
                'type'  => 'textarea',
            ],
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
                    'description' => $config['desc'] ?? '',
                    'type'        => $config['type'] ?? null,
                    'choices'     => $config['choices'] ?? null,
                ]
            );
        }
    }
    private static function verify_admin_request(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'artpulse-management'), 403);
        }

        check_admin_referer('ap_admin_action', 'ap_admin_nonce');
    }

    public static function sanitizeSettings($input)
    {
        $output = [];
        foreach ($input as $key => $value) {
            if (in_array($key, ['stripe_enabled', 'woocommerce_enabled', 'debug_logging', 'service_worker_enabled'], true)) {
                $output[$key] = isset($value) ? 1 : 0;
            } elseif ('notification_provider' === $key) {
                $value    = sanitize_key((string) $value);
                $choices  = array_keys(self::getNotificationProviderChoices());
                $output[$key] = in_array($value, $choices, true) ? $value : 'null';
            } elseif ('approved_mobile_origins' === $key) {
                $output[$key] = sanitize_textarea_field((string) $value);
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
        $type    = $args['type'] ?? (in_array($key, ['stripe_enabled', 'woocommerce_enabled', 'debug_logging', 'service_worker_enabled'], true) ? 'checkbox' : 'text');
        if ('checkbox' === $type) {
            echo '<input type="checkbox" id="' . esc_attr($key) . '" name="artpulse_settings[' . esc_attr($key) . ']" value="1"' . checked(1, $value, false) . ' />';
        } elseif ('textarea' === $type) {
            echo '<textarea id="' . esc_attr($key) . '" name="artpulse_settings[' . esc_attr($key) . ']" rows="5" class="large-text code">' . esc_textarea($value) . '</textarea>';
        } elseif ('select' === $type) {
            $choices = $args['choices'] ?? [];
            echo '<select id="' . esc_attr($key) . '" name="artpulse_settings[' . esc_attr($key) . ']" class="regular-text">';
            foreach ($choices as $choice_value => $choice_label) {
                echo '<option value="' . esc_attr($choice_value) . '"' . selected($value, $choice_value, false) . '>' . esc_html($choice_label) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" id="' . esc_attr($key) . '" name="artpulse_settings[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        }
        if ($desc) {
            echo '<p class="description">' . esc_html($desc) . '</p>';
        }
    }

    /**
     * @return array<string, string>
     */
    private static function getNotificationProviderChoices(): array
    {
        return apply_filters(
            'artpulse_mobile_notification_provider_choices',
            [
                'null' => __('Null provider (disable notifications)', 'artpulse-management'),
            ]
        );
    }

    public static function sanitize_boolean($value): bool
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return null === $filtered ? false : (bool) $filtered;
    }

    public static function sanitize_widget_whitelist($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];

        foreach ($value as $role => $widgets) {
            $role_key = sanitize_key((string) $role);
            if ('' === $role_key) {
                continue;
            }

            $sanitized[$role_key] = array_values(
                array_filter(
                    array_map('sanitize_key', (array) $widgets)
                )
            );
        }

        return $sanitized;
    }
}

