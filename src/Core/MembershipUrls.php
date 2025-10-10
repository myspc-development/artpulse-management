<?php

namespace ArtPulse\Core;

use WP_Post;

/**
 * Resolve public URLs related to membership upgrades and purchases.
 */
class MembershipUrls
{
    /**
     * Cached base URL for membership purchase flows.
     */
    private static ?string $purchaseBaseUrl = null;

    /**
     * Get a full URL that should be used for upgrading a membership level.
     */
    public static function getPurchaseUrl(string $level): string
    {
        $level = trim($level);

        if ($level === '') {
            return '';
        }

        $slug = strtolower($level);

        $baseUrl = self::getPurchaseBaseUrl($slug);

        if ($baseUrl === '') {
            return '';
        }

        $url = add_query_arg('level', $slug, $baseUrl);

        /**
         * Allow the final membership purchase URL to be filtered.
         *
         * @param string $url       Purchase URL with query arguments applied.
         * @param string $level     Normalised membership level slug.
         * @param string $base_url  Resolved base URL prior to query args.
         */
        return apply_filters('artpulse/membership/purchase_url', $url, $slug, $baseUrl);
    }

    private static function getPurchaseBaseUrl(string $levelSlug): string
    {
        /**
         * Allow developers to short-circuit the base URL resolution.
         *
         * @param string $base_url Pre-resolved base URL.
         * @param string $level    Requested membership level slug.
         */
        $filtered = apply_filters('artpulse/membership/purchase_base_url', '', $levelSlug);

        if (is_string($filtered) && $filtered !== '') {
            return $filtered;
        }

        if (function_exists('wc_get_checkout_url')) {
            $checkout = wc_get_checkout_url();

            if (is_string($checkout) && $checkout !== '') {
                return $checkout;
            }
        }

        if (self::$purchaseBaseUrl !== null) {
            return self::$purchaseBaseUrl;
        }

        $url = '';

        $candidates = apply_filters(
            'artpulse/membership/purchase_page_candidates',
            [
                'purchase-membership',
                'membership/purchase',
                'membership',
                'memberships',
                'join',
                'join-us',
            ]
        );

        if (is_array($candidates)) {
            foreach ($candidates as $path) {
                $path = trim((string) $path);

                if ($path === '') {
                    continue;
                }

                $page = get_page_by_path($path);

                if ($page instanceof WP_Post) {
                    $candidateUrl = get_permalink($page);

                    if (is_string($candidateUrl) && $candidateUrl !== '') {
                        $url = $candidateUrl;
                        break;
                    }
                }
            }
        }

        if ($url === '') {
            $checkoutPageId = (int) get_option('woocommerce_checkout_page_id');

            if ($checkoutPageId > 0) {
                $candidateUrl = get_permalink($checkoutPageId);

                if (is_string($candidateUrl) && $candidateUrl !== '') {
                    $url = $candidateUrl;
                }
            }
        }

        if ($url === '') {
            $url = home_url('/membership/');
        }

        if ($url === '') {
            $url = home_url('/');
        }

        self::$purchaseBaseUrl = $url;

        return self::$purchaseBaseUrl;
    }
}

