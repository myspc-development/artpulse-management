<?php
// Plugin: ArtPulse Membership Core
// File: membership-core.php

// 1. Register membership-related user meta
function artpulse_register_membership_meta() {
    register_meta('user', 'membership_level', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_meta('user', 'membership_start_date', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_meta('user', 'membership_end_date', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    register_meta('user', 'membership_auto_renew', ['type' => 'boolean', 'single' => true, 'show_in_rest' => true]);
}
add_action('init', 'artpulse_register_membership_meta');

// 2. Assign default membership level on registration
function artpulse_assign_default_membership($user_id) {
    update_user_meta($user_id, 'membership_level', 'basic');
    update_user_meta($user_id, 'membership_start_date', current_time('mysql'));
    update_user_meta($user_id, 'membership_end_date', date('Y-m-d H:i:s', strtotime('+30 days')));
    update_user_meta($user_id, 'membership_auto_renew', true);
}
add_action('user_register', 'artpulse_assign_default_membership');

// 3. Daily cron: expire memberships if auto-renew is off
function artpulse_schedule_membership_check() {
    if (!wp_next_scheduled('artpulse_check_memberships')) {
        wp_schedule_event(time(), 'daily', 'artpulse_check_memberships');
    }
}
add_action('wp', 'artpulse_schedule_membership_check');

// Extend cron: Expiration warnings (3 days before end)
add_action('artpulse_check_memberships', function () {
    $now = current_time('timestamp');
    $expire_users = get_users([
        'meta_query' => [
            [
                'key'     => 'membership_end_date',
                'value'   => [
                    current_time('mysql'),
                    date('Y-m-d H:i:s', strtotime('+3 days', $now))
                ],
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME'
            ]
        ]
    ]);

    foreach ($expire_users as $user) {
        $email = $user->user_email;
        $name = $user->display_name;
        $subject = 'Your ArtPulse Membership is Expiring Soon';
        $message = "Hi $name,\n\nJust a reminder \xE2\x80\x94 your membership will expire in 3 days.\n\nTo avoid interruption, please renew or upgrade your membership.\n\nVisit your account to take action.\n\nThank you,\nArtPulse Team";

        wp_mail($email, $subject, $message);
    }

    // Expiration enforcement
    $users = get_users([
        'meta_key' => 'membership_end_date',
        'meta_compare' => '<=',
        'meta_value' => current_time('mysql'),
    ]);
    foreach ($users as $user) {
        $auto_renew = get_user_meta($user->ID, 'membership_auto_renew', true);
        if (!$auto_renew) {
            update_user_meta($user->ID, 'membership_level', 'expired');
        }
    }
});


// 5. Shortcode: [membership_status]
function artpulse_membership_status_shortcode() {
    if (!is_user_logged_in()) return '<p>Please <a href="' . wp_login_url() . '">log in</a> to view your membership status.</p>';

    $user_id = get_current_user_id();
    $level = get_user_meta($user_id, 'membership_level', true);
    $end = get_user_meta($user_id, 'membership_end_date', true);
    $renew = get_user_meta($user_id, 'membership_auto_renew', true);

    ob_start();
    echo '<div class="membership-status">';
    echo '<p><strong>Membership Level:</strong> ' . esc_html(ucfirst($level)) . '</p>';
    echo '<p><strong>Valid Until:</strong> ' . esc_html(date('F j, Y', strtotime($end))) . '</p>';
    echo '<p><strong>Auto Renew:</strong> ' . ($renew ? 'Enabled' : 'Off') . '</p>';
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('membership_status', 'artpulse_membership_status_shortcode');

// Stripe Test Checkout Button Shortcode
function artpulse_membership_checkout_shortcode() {
    $level = $_GET['level'] ?? '';
    $map   = [
        'pro' => 'prod_ABC123',
        'org' => 'prod_XYZ789',
    ];

    if (!isset($map[$level])) {
        return '<p>Invalid membership level.</p>';
    }

    require_once ABSPATH . 'vendor/autoload.php';

    $settings      = get_option('artpulse_plugin_settings', []);
    $use_test      = ! empty($settings['stripe_test_mode']);
    $stripe_secret = $use_test
        ? ($settings['stripe_test_secret'] ?? '')
        : ($settings['stripe_live_secret'] ?? '');

    \Stripe\Stripe::setApiKey($stripe_secret);

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode'                 => 'payment',
        'line_items'           => [[
            'price'    => $map[$level],
            'quantity' => 1,
        ]],
        'success_url' => home_url('/registration-complete/'),
        'cancel_url'  => home_url('/membership-cancel/'),
    ]);

    wp_redirect($session->url);
    exit;
}
add_shortcode('membership_checkout', 'artpulse_membership_checkout_shortcode');

// Create placeholder pages for success and cancel states if missing
function artpulse_membership_create_placeholder_pages() {
    $pages = [
        'membership-success' => [
            'title'   => 'Membership Upgrade Successful',
            'content' => '<h1>🎉 Welcome to Pro!</h1><p>Your membership upgrade was successful. You now have access to exclusive features.</p><p><a href="' . esc_url(home_url('/dashboard')) . '">View Your Dashboard</a></p>[membership_success_status]'
        ],
        'membership-cancel' => [
            'title'   => 'Checkout Cancelled',
            'content' => '<h1>⚠️ Checkout Cancelled</h1><p>It looks like your payment was not completed. You can try again at any time.</p><p><a href="' . esc_url(home_url('/membership')) . '">Return to Membership Page</a></p>[membership_cancel_status]'
        ],
    ];

    foreach ($pages as $slug => $page) {
        if (!get_page_by_path($slug)) {
            wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $slug,
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);
        }
    }
}
add_action('init', 'artpulse_membership_create_placeholder_pages');

// Shortcode: styled success message
function artpulse_membership_success_message_shortcode() {
    if (!is_user_logged_in()) return '';
    $level = get_user_meta(get_current_user_id(), 'membership_level', true);

    if ($level === 'pro') {
        return '<div class="p-4 bg-green-100 text-green-800 border border-green-300 rounded">✅ <strong>Success:</strong> Your Pro membership is now active!</div>';
    } else {
        return '<div class="p-4 bg-yellow-100 text-yellow-800 border border-yellow-300 rounded">⚠️ <strong>Note:</strong> Your membership is currently: ' . esc_html(ucfirst($level)) . '.</div>';
    }
}
add_shortcode('membership_success_status', 'artpulse_membership_success_message_shortcode');

// Shortcode: styled cancel message
function artpulse_membership_cancel_message_shortcode() {
    return '<div class="p-4 bg-red-100 text-red-800 border border-red-300 rounded">⚠️ <strong>Payment Cancelled:</strong> You can try again or choose a different plan below.</div>';
}
add_shortcode('membership_cancel_status', 'artpulse_membership_cancel_message_shortcode');

add_action('wp_ajax_artpulse_create_checkout_session', 'artpulse_create_checkout_session');
add_action('wp_ajax_nopriv_artpulse_create_checkout_session', 'artpulse_create_checkout_session');

function artpulse_create_checkout_session() {
    $level = sanitize_text_field($_GET['level'] ?? '');

    $map = [
        'pro' => 'prod_ABC123',
        'org' => 'prod_XYZ789',
    ];

    if (!isset($map[$level])) {
        wp_send_json(['error' => 'invalid level'], 400);
    }

    require_once ABSPATH . 'vendor/autoload.php';
    $settings      = get_option('artpulse_plugin_settings', []);
    $use_test      = ! empty($settings['stripe_test_mode']);
    $stripe_secret = $use_test
        ? ($settings['stripe_test_secret'] ?? '')
        : ($settings['stripe_live_secret'] ?? '');

    \Stripe\Stripe::setApiKey($stripe_secret);

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode'                 => 'payment',
        'line_items'           => [[
            'price'    => $map[$level],
            'quantity' => 1,
        ]],
        'success_url' => home_url('/registration-complete/'),
        'cancel_url'  => home_url('/membership-cancel/'),
    ]);

    wp_send_json(['id' => $session->id]);
}

// Webhook listener endpoint (manual route)
add_action('rest_api_init', function () {
    register_rest_route('artpulse/v1', '/stripe-webhook', [
        'methods'             => 'POST',
        'callback'            => 'artpulse_stripe_webhook_handler',
        'permission_callback' => '__return_true',
    ]);
});

function artpulse_stripe_webhook_handler(WP_REST_Request $request) {
    $opts           = get_option('artpulse_plugin_settings', []);
    $payload        = $request->get_body();
    $sig_header     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $endpoint_secret = $opts['stripe_webhook_secret'] ?? '';

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        if ($event->type === 'checkout.session.completed') {
            $session        = $event->data->object;
            $customer_email = $session->customer_details->email;
            $user           = get_user_by('email', $customer_email);
            if ($user) {
                update_user_meta($user->ID, 'membership_level', 'pro');
                update_user_meta($user->ID, 'membership_start_date', current_time('mysql'));
                update_user_meta($user->ID, 'membership_end_date', date('Y-m-d H:i:s', strtotime('+1 year')));
                update_user_meta($user->ID, 'membership_auto_renew', true);
            }
        }
    } catch (Exception $e) {
        return new WP_REST_Response(['error' => $e->getMessage()], 400);
    }

    return new WP_REST_Response(['status' => 'success'], 200);
}

// Optional template functions (for theme integration)
function artpulse_render_membership_success_template() {
    get_header();
    echo '<main class="max-w-3xl mx-auto p-6">';
    echo '<h1 class="text-2xl font-bold mb-4">Membership Upgrade Successful</h1>';
    echo do_shortcode('[membership_success_status]');
    echo '<a href="/dashboard" class="inline-block mt-6 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Go to Dashboard</a>';
    echo '</main>';
    get_footer();
}

function artpulse_render_membership_cancel_template() {
    get_header();
    echo '<main class="max-w-3xl mx-auto p-6">';
    echo '<h1 class="text-2xl font-bold mb-4">Checkout Cancelled</h1>';
    echo do_shortcode('[membership_cancel_status]');
    echo '<a href="/membership" class="inline-block mt-6 px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Return to Membership Page</a>';
    echo '</main>';
    get_footer();
}

// Location selector assets
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'artpulse') === false) {
        return;
    }

    wp_enqueue_style('select2-css', plugins_url('assets/select2/css/select2.min.css', __FILE__));
    wp_enqueue_script('select2-js', plugins_url('assets/select2/js/select2.min.js', __FILE__), ['jquery'], null, true);
    wp_enqueue_script('location-cascade', plugins_url('assets/js/location-cascade.js', __FILE__), ['select2-js'], null, true);

    wp_localize_script('location-cascade', 'LocationData', [
        'countries' => json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'data/countries.json')),
        'states'    => json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'data/states.json')),
        'cities'    => json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'data/cities.json')),
    ]);
});

// Optional GeoNames fallback REST endpoint
add_action('rest_api_init', function () {
    register_rest_route('artpulse/v1', '/fetch-location', [
        'methods'             => 'GET',
        'callback'            => 'artpulse_lookup_location',
        'permission_callback' => '__return_true',
    ]);
});

function artpulse_lookup_location($request) {
    $type    = sanitize_text_field($request['type']);
    $country = sanitize_text_field($request['country']);
    $query   = sanitize_text_field($request['q']);

    $url = match ($type) {
        'state' => "http://api.geonames.org/searchJSON?country=$country&featureCode=ADM1&maxRows=10&username=demo",
        'city'  => "http://api.geonames.org/searchJSON?q=$query&country=$country&featureClass=P&maxRows=10&username=demo",
        default => ''
    };

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return [];
    }

    $results = json_decode(wp_remote_retrieve_body($response), true);
    $output  = array_map(fn($r) => $r['name'], $results['geonames'] ?? []);
    return $output;
}

// Load registration form shortcode
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/register-member.php';

// Handle registration form submission
add_action('init', function () {
    if (!isset($_POST['register_member_submit'])) {
        return;
    }
    if (!isset($_POST['register_member_nonce']) ||
        !wp_verify_nonce($_POST['register_member_nonce'], 'register_member_action')) {
        return;
    }

    $username = sanitize_user($_POST['username']);
    $email    = sanitize_email($_POST['email']);
    $password = $_POST['password'];
    $level    = sanitize_text_field($_POST['membership_level']);

    if (in_array($level, ['pro', 'org'], true)) {
        // Store data for post-checkout account creation
        session_start();
        $_SESSION['pending_registration'] = compact('username', 'email', 'password', 'level');
        wp_redirect(home_url("/checkout/?level=$level"));
        exit;
    }

    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        return;
    }

    wp_update_user(['ID' => $user_id, 'display_name' => $username]);
    update_user_meta($user_id, 'membership_level', $level);
    update_user_meta($user_id, 'membership_start_date', current_time('mysql'));
    update_user_meta($user_id, 'membership_end_date', date('Y-m-d H:i:s', strtotime('+1 month')));
    update_user_meta($user_id, 'membership_auto_renew', false);

    artpulse_send_welcome_email($user_id);

    wp_redirect(home_url('/membership-success/'));
    exit;
});

// Finalize registration after successful Stripe checkout
add_shortcode('finalize_registration', function () {
    session_start();
    if (!isset($_SESSION['pending_registration'])) {
        return 'Missing registration session.';
    }

    $data = $_SESSION['pending_registration'];
    unset($_SESSION['pending_registration']);

    $user_id = wp_create_user($data['username'], $data['password'], $data['email']);
    if (is_wp_error($user_id)) {
        return 'User creation failed.';
    }

    wp_update_user(['ID' => $user_id, 'display_name' => $data['username']]);
    update_user_meta($user_id, 'membership_level', $data['level']);
    update_user_meta($user_id, 'membership_start_date', current_time('mysql'));
    update_user_meta($user_id, 'membership_end_date', date('Y-m-d H:i:s', strtotime('+1 month')));
    update_user_meta($user_id, 'membership_auto_renew', true);

    artpulse_send_welcome_email($user_id);

  return '<div class="text-green-600">✅ Registration complete. Welcome to ArtPulse!</div>';
});
add_action('admin_menu', function() {
    add_menu_page(
        'Membership Admin',
        'Membership Admin',
        'manage_options',
        'membership-admin',
        'artpulse_render_membership_admin',
        'dashicons-groups',
        55
    );
});

function artpulse_render_membership_admin() {
    // --- Handle bulk actions ---
    if (isset($_POST['bulk_action']) && !empty($_POST['bulk_user_ids'])) {
        $bulk_action = sanitize_text_field($_POST['bulk_action']);
        $bulk_ids = array_map('intval', $_POST['bulk_user_ids']);
        foreach ($bulk_ids as $user_id) {
            if ($bulk_action === 'set_expired') {
                update_user_meta($user_id, 'membership_level', 'expired');
            } elseif ($bulk_action === 'set_pro') {
                update_user_meta($user_id, 'membership_level', 'pro');
            } elseif ($bulk_action === 'set_org') {
                update_user_meta($user_id, 'membership_level', 'org');
            } elseif ($bulk_action === 'set_basic') {
                update_user_meta($user_id, 'membership_level', 'basic');
            } elseif ($bulk_action === 'enable_auto_renew') {
                update_user_meta($user_id, 'membership_auto_renew', true);
            } elseif ($bulk_action === 'disable_auto_renew') {
                update_user_meta($user_id, 'membership_auto_renew', false);
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Bulk action applied!</p></div>';
    }

    // --- Handle single user update or quick upgrade/downgrade ---
    if (isset($_POST['update_membership']) || isset($_POST['quick_upgrade'])) {
        $user_id = intval($_POST['user_id']);
        check_admin_referer('artpulse_membership_update_' . $user_id);

        if (isset($_POST['quick_upgrade'])) {
            // Quick upgrade/downgrade button logic
            $new_level = sanitize_text_field($_POST['quick_upgrade']);
            update_user_meta($user_id, 'membership_level', $new_level);
            // Optionally set end date for upgrades, e.g., add 1 year
            if ($new_level == 'pro' || $new_level == 'org') {
                update_user_meta($user_id, 'membership_end_date', date('Y-m-d', strtotime('+1 year')));
            }
            echo '<div class="notice notice-success is-dismissible"><p>Membership set to ' . esc_html(ucfirst($new_level)) . '!</p></div>';
        } else {
            // Standard update
            update_user_meta($user_id, 'membership_level', sanitize_text_field($_POST['membership_level']));
            update_user_meta($user_id, 'membership_end_date', sanitize_text_field($_POST['membership_end_date']));
            update_user_meta($user_id, 'membership_auto_renew', !empty($_POST['membership_auto_renew']));
            echo '<div class="notice notice-success is-dismissible"><p>Membership updated!</p></div>';
        }
    }

    // --- Filters ---
    $search = isset($_GET['membership_search']) ? sanitize_text_field($_GET['membership_search']) : '';
    $filter_level = isset($_GET['membership_level']) ? sanitize_text_field($_GET['membership_level']) : '';
    $filter_auto = isset($_GET['membership_auto']) ? sanitize_text_field($_GET['membership_auto']) : '';

    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($paged - 1) * $per_page;

    // Build meta_query for filters
    $meta_query = [['key' => 'membership_level', 'compare' => 'EXISTS']];
    if ($filter_level && in_array($filter_level, ['basic','pro','org','expired'])) {
        $meta_query[] = ['key' => 'membership_level', 'value' => $filter_level];
    }
    if ($filter_auto === 'on') {
        $meta_query[] = ['key' => 'membership_auto_renew', 'value' => '1'];
    } elseif ($filter_auto === 'off') {
        $meta_query[] = [
            'key' => 'membership_auto_renew',
            'value' => '1',
            'compare' => '!='
        ];
    }

    $user_query_args = [
        'meta_query' => $meta_query,
        'number' => $per_page,
        'offset' => $offset,
        'search_columns' => ['user_login', 'user_email', 'display_name']
    ];
    if (!empty($search)) {
        $user_query_args['search'] = '*' . esc_attr($search) . '*';
    }

    $users = get_users($user_query_args);

    // For pagination, get the total count
    $count_args = $user_query_args;
    $count_args['fields'] = 'ID';
    unset($count_args['number'], $count_args['offset']);
    $total_users = count(get_users($count_args));
    $total_pages = ceil($total_users / $per_page);

    echo '<div class="wrap"><h1>Membership Admin</h1>';

    // --- Filter & Search Form ---
    echo '<form method="get" style="margin-bottom: 1em;">';
    echo '<input type="hidden" name="page" value="membership-admin">';
    echo '<input type="text" name="membership_search" placeholder="Search user..." value="' . esc_attr($search) . '" />';
    echo '&nbsp; Level: <select name="membership_level">';
    echo '<option value="">All</option>';
    foreach (['basic'=>'Basic','pro'=>'Pro','org'=>'Org','expired'=>'Expired'] as $k=>$v) {
        echo '<option value="'.$k.'"'.selected($filter_level, $k, false).'>'.$v.'</option>';
    }
    echo '</select>';
    echo '&nbsp; Auto Renew: <select name="membership_auto">';
    echo '<option value="">All</option>';
    echo '<option value="on"'.selected($filter_auto, 'on', false).'>On</option>';
    echo '<option value="off"'.selected($filter_auto, 'off', false).'>Off</option>';
    echo '</select> ';
    echo '<button class="button">Filter</button>';
    echo '</form>';

    // --- Bulk actions form ---
    echo '<form method="post"><table class="widefat striped"><thead><tr>
            <th style="width:20px;"><input type="checkbox" id="select_all"></th>
            <th>User</th>
            <th>Level</th>
            <th>End Date</th>
            <th>Auto Renew</th>
            <th>Upgrade/Downgrade</th>
            <th>Action</th>
        </tr></thead><tbody>';

    foreach ($users as $user) {
        $level = get_user_meta($user->ID, 'membership_level', true);
        $end   = get_user_meta($user->ID, 'membership_end_date', true);
        $renew = get_user_meta($user->ID, 'membership_auto_renew', true);

        echo '<tr>';
        echo '<td><input type="checkbox" class="user_checkbox" name="bulk_user_ids[]" value="' . esc_attr($user->ID) . '"></td>';
        echo '<td>' . esc_html($user->display_name) . ' <br><small>' . esc_html($user->user_email) . '</small></td>';
        // Inline form for single update
        echo '<form method="post" style="display:inline;">';
        echo '<input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '">';
        wp_nonce_field('artpulse_membership_update_' . $user->ID);
        echo '<td>
                <select name="membership_level">
                    <option value="basic"'   . selected($level, 'basic', false)   . '>Basic</option>
                    <option value="pro"'     . selected($level, 'pro', false)     . '>Pro</option>
                    <option value="org"'     . selected($level, 'org', false)     . '>Org</option>
                    <option value="expired"' . selected($level, 'expired', false) . '>Expired</option>
                </select>
              </td>';
        $end_input = $end ? date('Y-m-d', strtotime($end)) : '';
        echo '<td><input type="date" name="membership_end_date" value="' . esc_attr($end_input) . '"></td>';
        echo '<td style="text-align:center;"><input type="checkbox" name="membership_auto_renew" value="1"' . checked($renew, true, false) . '></td>';
        // Upgrade/downgrade quick buttons
        echo '<td style="white-space:nowrap;">';
        foreach (['pro'=>'Pro','org'=>'Org','basic'=>'Basic','expired'=>'Expired'] as $target=>$label) {
            if ($level != $target) {
                echo '<button class="button" name="quick_upgrade" value="'.esc_attr($target).'">'.esc_html($label).'</button> ';
            }
        }
        echo '</td>';
        echo '<td><button class="button button-primary" name="update_membership" type="submit">Update</button></td>';
        echo '</form></tr>';
    }
    echo '</tbody></table>';

    // --- Bulk Actions Controls ---
    echo '<div style="margin-top:10px;">';
    echo '<select name="bulk_action">
            <option value="">Bulk Actions</option>
            <option value="set_expired">Set to Expired</option>
            <option value="set_basic">Set to Basic</option>
            <option value="set_pro">Set to Pro</option>
            <option value="set_org">Set to Org</option>
            <option value="enable_auto_renew">Enable Auto Renew</option>
            <option value="disable_auto_renew">Disable Auto Renew</option>
        </select> ';
    echo '<button class="button" type="submit">Apply</button>';
    echo '</div></form>';

    // --- Pagination ---
    echo '<div style="margin-top:20px;">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $url = admin_url('admin.php?page=membership-admin&paged=' . $i);
        // Preserve filters in URL
        if ($search) $url .= '&membership_search=' . urlencode($search);
        if ($filter_level) $url .= '&membership_level=' . urlencode($filter_level);
        if ($filter_auto) $url .= '&membership_auto=' . urlencode($filter_auto);
        echo '<a href="' . esc_url($url) . '" class="button' . ($i === $paged ? ' button-primary' : '') . '">' . $i . '</a> ';
    }
    echo '</div>';

    // --- JavaScript for select all checkboxes ---
    echo '<script>
    document.getElementById("select_all").addEventListener("change", function(e) {
        var checkboxes = document.querySelectorAll(".user_checkbox");
        for(var i=0; i<checkboxes.length; i++) {
            checkboxes[i].checked = e.target.checked;
        }
    });
    </script>';

    echo '</div>';
}
// Load the class (adjust the path as needed for your file structure)
require_once __DIR__ . '/src/Admin/ManageMembers.php';

// Register POST handlers and menu
\EAD\Admin\ManageMembers::register();
add_action('admin_menu', ['\\EAD\\Admin\\ManageMembers', 'admin_menu']);


// === Admin: Membership Manager Interface (Disabled, handled in src/Admin) ===
// The legacy admin page registration and rendering logic have been removed
// in favor of the ManageMembers class located under src/Admin. The code
// remains commented out for reference should any functionality need to be
// restored in the future.

/* Legacy admin page code has been removed in favor of the modern
 * ManageMembers interface located under src/Admin. */


