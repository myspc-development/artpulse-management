#!/usr/bin/env bash
set -e

BASE="$(pwd)"
SRC_CORE="$BASE/src/Core"
MAIN_FILE="$BASE/artpulse-management.php"

echo "🚀 Starting Phase 3 scaffolding…"

# 1) Install Stripe PHP SDK
composer require stripe/stripe-php

# 2) Create SettingsPage.php
cat > "$SRC_CORE/SettingsPage.php" << 'EOF'
<?php
namespace ArtPulse\Core;

class SettingsPage
{
    public static function register()
    {
        add_action('admin_menu',   [self::class, 'addMenu']);
        add_action('admin_init',   [self::class, 'registerSettings']);
    }

    public static function addMenu()
    {
        add_options_page(
            __('ArtPulse Settings','artpulse'),
            __('ArtPulse','artpulse'),
            'manage_options',
            'artpulse-settings',
            [self::class, 'render']
        );
    }

    public static function registerSettings()
    {
        register_setting('artpulse_settings_group', 'artpulse_settings');

        add_settings_section(
            'ap_membership_section',
            __('Membership Settings','artpulse'),
            '__return_false',
            'artpulse-settings'
        );

        $fields = [
            'basic_fee'       => 'Basic Member Fee ($)',
            'pro_fee'         => 'Pro Artist Fee ($)',
            'org_fee'         => 'Organization Fee ($)',
            'currency'        => 'Currency (ISO)',
            'stripe_enabled'  => 'Enable Stripe Integration',
            'stripe_pub_key'  => 'Stripe Publishable Key',
            'stripe_secret'   => 'Stripe Secret Key',
            'stripe_test'     => 'Stripe Test Mode',
            'woo_enabled'     => 'Enable WooCommerce Integration',
            'notify_fee'      => 'Email Notification on Fee Change',
        ];

        foreach ($fields as $name => $label) {
            add_settings_field(
                $name,
                __($label,'artpulse'),
                [self::class, 'renderField'],
                'artpulse-settings',
                'ap_membership_section',
                ['label_for' => $name]
            );
        }
    }

    public static function renderField($args)
    {
        $opts = get_option('artpulse_settings', []);
        $val  = $opts[$args['label_for']] ?? '';
        $type = in_array($args['label_for'], ['stripe_enabled','stripe_test','woo_enabled','notify_fee'])
                ? 'checkbox' : 'text';

        if ($type === 'checkbox') {
            printf(
                '<input type="checkbox" id="%1$s" name="artpulse_settings[%1$s]" value="1" %2$s />',
                esc_attr($args['label_for']),
                checked($val, 1, false)
            );
        } else {
            printf(
                '<input type="text" id="%1$s" name="artpulse_settings[%1$s]" value="%2$s" style="width:50%%;" />',
                esc_attr($args['label_for']),
                esc_attr($val)
            );
        }
    }

    public static function render()
    {
        echo '<div class="wrap"><h1>'.__('ArtPulse Settings','artpulse').'</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('artpulse_settings_group');
        do_settings_sections('artpulse-settings');
        submit_button();
        echo '</form></div>';
    }
}
EOF

echo "✅ Created src/Core/SettingsPage.php"

# 3) Create MembershipManager.php
cat > "$SRC_CORE/MembershipManager.php" << 'EOF'
<?php
namespace ArtPulse\Core;

use Stripe\StripeClient;

class MembershipManager
{
    public static function register()
    {
        add_action('user_register',       [self::class,'assignFreeMembership']);
        add_action('ap_stripe_webhook',   [self::class,'handleStripeWebhook'], 10, 1);
        add_action('ap_daily_expiry_check', [self::class,'processExpirations']);
    }

    public static function assignFreeMembership($user_id)
    {
        $role = 'subscriber';
        $user = get_userdata($user_id);
        $user->set_role($role);
        update_user_meta($user_id, 'ap_membership_level', 'Free');
    }

    public static function handleStripeWebhook($payload)
    {
        $stripe = new StripeClient(get_option('artpulse_settings')['stripe_secret']);
        // TODO: parse $payload, update user_meta with level/expiry, send emails
    }

    public static function processExpirations()
    {
        // TODO: query users with expired membership, downgrade role, notify
    }
}
EOF

echo "✅ Created src/Core/MembershipManager.php"

# 4) Create AccessControlManager.php
cat > "$SRC_CORE/AccessControlManager.php" << 'EOF'
<?php
namespace ArtPulse\Core;

class AccessControlManager
{
    public static function register()
    {
        add_action('template_redirect', [self::class,'checkAccess']);
    }

    public static function checkAccess()
    {
        if ( is_singular(['artpulse_event','artpulse_artwork']) ) {
            $level = get_user_meta(get_current_user_id(),'ap_membership_level',true);
            if ( $level === 'Free' ) {
                wp_redirect(home_url());
                exit;
            }
        }
    }
}
EOF

echo "✅ Created src/Core/AccessControlManager.php"

# 5) Inject registrations into artpulse-management.php
if ! grep -q "SettingsPage::register" "$MAIN_FILE"; then
  sed -i "/MetaBoxRegistrar::register();/a \\
    \ArtPulse\\Core\\SettingsPage::register();\\
    \ArtPulse\\Core\\MembershipManager::register();\\
    \ArtPulse\\Core\\AccessControlManager::register();" "$MAIN_FILE"
  echo "✅ Hooked new modules into plugin bootstrap"
fi

echo "🎉 Phase 3 scaffolding complete!"
echo
echo "Next steps:"
echo "  1) Activate Stripe webhooks endpoint and implement handleStripeWebhook() logic."
echo "  2) Schedule 'ap_daily_expiry_check' in activation hook: e.g. wp_schedule_event()."
echo "  3) Fill in processExpirations() to downgrade expired users."
echo "  4) Commit & push:"
echo "       git add src/Core/{SettingsPage.php,MembershipManager.php,AccessControlManager.php} artpulse-management.php"
echo "       git commit -m \"Phase 3: scaffold membership & access control\""
echo "       git push -u origin main"
