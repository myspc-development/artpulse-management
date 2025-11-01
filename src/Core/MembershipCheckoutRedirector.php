<?php

namespace ArtPulse\Core;

use WC_Cart;
use function __;
use function add_action;
use function is_admin;
use function remove_query_arg;
use function sanitize_key;
use function sprintf;
use function wc_add_notice;
use function wc_get_checkout_url;
use function wc_load_cart;
use function wp_doing_ajax;
use function wp_safe_redirect;
use function wp_unslash;

/**
 * Automatically route membership upgrade links to the configured checkout flow.
 */
final class MembershipCheckoutRedirector
{
    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'maybe_redirect_to_checkout']);
    }

    public static function maybe_redirect_to_checkout(): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        if (!isset($_GET['level'])) {
            return;
        }

        $raw_level = (string) wp_unslash($_GET['level']);
        $level_slug = sanitize_key($raw_level);

        if ('' === $level_slug) {
            return;
        }

        $product_id = WooCommerceIntegration::getProductIdForLevel($level_slug);
        if ($product_id <= 0) {
            return;
        }

        if (!function_exists('WC')) {
            return;
        }

        if (!function_exists('wc_get_checkout_url')) {
            return;
        }

        $checkout_url = wc_get_checkout_url();

        if (!$checkout_url) {
            return;
        }

        $woocommerce = WC();

        if (!is_object($woocommerce)) {
            return;
        }

        if (null === $woocommerce->cart && function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        $cart = $woocommerce->cart;

        if (!$cart instanceof WC_Cart) {
            return;
        }

        foreach ($cart->get_cart() as $cart_key => $cart_item) {
            if (isset($cart_item['ap_membership_level'])) {
                $cart->remove_cart_item($cart_key);
            }
        }

        $cart_id = $cart->generate_cart_id($product_id);
        $existing_key = $cart->find_product_in_cart($cart_id);

        if (false === $existing_key) {
            $cart->add_to_cart($product_id, 1, 0, [], [
                'ap_membership_level'       => $level_slug,
                'ap_membership_level_label' => ucfirst($level_slug),
            ]);
        }

        if (function_exists('wc_add_notice')) {
            $notice_level = ucfirst($level_slug);
            wc_add_notice(
                sprintf(
                    /* translators: %s: membership level label. */
                    __('%s membership has been added to your cart. Complete checkout to finish upgrading.', 'artpulse-management'),
                    $notice_level
                ),
                'success'
            );
        }

        $redirect = remove_query_arg('level', $checkout_url);

        wp_safe_redirect($redirect);
        exit;
    }
}
