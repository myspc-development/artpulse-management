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
    update_user_meta($user_id, 'membership_level', 'free');
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
                'key' => 'membership_end_date',
                'value' => date('Y-m-d H:i:s', strtotime('+3 days', $now)),
                'compare' => 'LIKE'
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

// 4. Admin settings page for membership fees & config
function artpulse_membership_settings_init() {
    add_options_page('Membership Settings', 'Membership Settings', 'manage_options', 'artpulse-membership-settings', 'artpulse_membership_settings_page');
    add_action('admin_init', function () {
        register_setting('artpulse_membership', 'artpulse_membership_options');
        add_settings_section('artpulse_membership_main', 'Membership Options', null, 'artpulse-membership');

        add_settings_field('basic_fee', 'Basic Member Fee ($)', function () {
            $options = get_option('artpulse_membership_options');
            echo '<input type="number" name="artpulse_membership_options[basic_fee]" value="' . esc_attr($options['basic_fee'] ?? '') . '" />';
        }, 'artpulse-membership', 'artpulse_membership_main');

        add_settings_field('pro_fee', 'Pro Artist Fee ($)', function () {
            $options = get_option('artpulse_membership_options');
            echo '<input type="number" name="artpulse_membership_options[pro_fee]" value="' . esc_attr($options['pro_fee'] ?? '') . '" />';
        }, 'artpulse-membership', 'artpulse_membership_main');

        add_settings_field('currency', 'Currency', function () {
            $options = get_option('artpulse_membership_options');
            echo '<input type="text" name="artpulse_membership_options[currency]" value="' . esc_attr($options['currency'] ?? 'USD') . '" />';
        }, 'artpulse-membership', 'artpulse_membership_main');
    });
}
add_action('admin_menu', 'artpulse_membership_settings_init');

// Extend membership admin settings: Stripe keys
add_action('admin_init', function () {
    add_settings_field('stripe_pk', 'Stripe Publishable Key', function () {
        $options = get_option('artpulse_membership_options');
        echo '<input type="text" name="artpulse_membership_options[stripe_pk]" value="' . esc_attr($options['stripe_pk'] ?? '') . '" style="width: 100%;" />';
    }, 'artpulse-membership', 'artpulse_membership_main');

    add_settings_field('stripe_sk', 'Stripe Secret Key', function () {
        $options = get_option('artpulse_membership_options');
        echo '<input type="text" name="artpulse_membership_options[stripe_sk]" value="' . esc_attr($options['stripe_sk'] ?? '') . '" style="width: 100%;" />';
    }, 'artpulse-membership', 'artpulse_membership_main');
});

function artpulse_membership_settings_page() {
    echo '<div class="wrap"><h1>Membership Settings</h1><form method="post" action="options.php">';
    settings_fields('artpulse_membership');
    do_settings_sections('artpulse-membership');
    submit_button();
    echo '</form></div>';
}

// Simple settings page for Stripe test mode
add_action('admin_menu', function () {
    add_options_page('Membership Settings', 'Membership', 'manage_options', 'membership_settings', function () {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('membership_settings'); ?>
            <?php do_settings_sections('membership_settings'); ?>
            <h2>Stripe Test Mode</h2>
            <label>
                <input type="checkbox" name="stripe_test_mode" value="1" <?php checked(get_option('stripe_test_mode'), 1); ?>>
                Enable Test Mode (use test keys)
            </label>
            <?php submit_button(); ?>
        </form>
        <?php
    });
});

add_action('admin_init', function () {
    register_setting('membership_settings', 'stripe_test_mode');
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
    if (!is_user_logged_in()) return '<p>Please <a href="' . wp_login_url() . '">log in</a> to upgrade your membership.</p>';

    $use_test        = get_option('stripe_test_mode');
    $stripe_price_id = $use_test ? 'price_test_123' : 'price_live_456';
    $stripe_pk       = $use_test ? 'pk_test_123' : 'pk_live_456';

    ob_start();
    echo '<script src="https://js.stripe.com/v3/"></script>';
    echo '<button id="checkout-button">Upgrade to Pro</button>';
    echo '<script>
        var stripe = Stripe("' . esc_js($stripe_pk) . '");
        document.getElementById("checkout-button").addEventListener("click", function () {
            fetch("' . admin_url('admin-ajax.php') . '?action=artpulse_create_checkout_session")
                .then(function (response) { return response.json(); })
                .then(function (session) {
                    return stripe.redirectToCheckout({ sessionId: session.id });
                })
                .then(function (result) {
                    if (result.error) alert(result.error.message);
                });
    });</script>';
    return ob_get_clean();
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
    require_once ABSPATH . 'vendor/autoload.php';
    $use_test        = get_option('stripe_test_mode');
    $stripe_secret   = $use_test ? 'sk_test_123' : 'sk_live_456';
    $stripe_price_id = $use_test ? 'price_test_123' : 'price_live_456';

    \Stripe\Stripe::setApiKey($stripe_secret);

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode'                 => 'payment',
        'line_items'           => [[
            'price'    => $stripe_price_id,
            'quantity' => 1,
        ]],
        'success_url' => home_url('/membership-success/'),
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
    $opts           = get_option('artpulse_membership_options');
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
