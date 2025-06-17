<?php
namespace ArtPulse\Core;

use WP_REST_Request;
use WP_Error;

class FrontendMembershipPage
{
    public static function register()
    {
        // Shortcode to render the account page
        add_shortcode('ap_membership_account', [ self::class, 'renderAccount' ]);

        // Expose a REST endpoint for the account data
        add_action('rest_api_init', function() {
            register_rest_route('artpulse/v1', '/member/account', [
                'methods'  => 'GET',
                'callback' => [ self::class, 'getAccountData' ],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
            ]);
        });
    }

    /**
     * Shortcode handler: outputs a container div that our JS will hydrate.
     */
    public static function renderAccount()
    {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __('Please log in to view your membership account.','artpulse') . '</p>';
        }

        ob_start(); ?>
        <div class="ap-membership-account">
            <h2><?php _e('My Membership','artpulse'); ?></h2>
            <div id="ap-account-info"></div>
            <div id="ap-account-actions"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * REST callback: returns the current user’s membership data.
     */
    public static function getAccountData(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $level   = get_user_meta($user_id,'ap_membership_level',true) ?: 'Free';
        $expires = get_user_meta($user_id,'ap_membership_expires',true);

        return rest_ensure_response([
            'level'   => $level,
            'expires' => $expires ? date_i18n(get_option('date_format'), intval($expires)) : __('Never','artpulse'),
            // Map the purchase‐shortcode links for upgrade:
            'purchase_links' => [
                'Basic' => do_shortcode('[ap_membership_purchase level="Basic"]'),
                'Pro'   => do_shortcode('[ap_membership_purchase level="Pro"]'),
                'Org'   => do_shortcode('[ap_membership_purchase level="Org"]'),
            ],
        ]);
    }
}
