<?php

namespace ArtPulse\Core;

use ArtPulse\Frontend\Shared\PortfolioAccess;

/**
 * Registers core capabilities used by the front-end builders and REST API.
 */
final class Capabilities
{
    public const CAP_MANAGE_OWN_ORG    = 'ap_manage_own_org';
    public const CAP_MANAGE_OWN_ARTIST = 'ap_manage_own_artist';
    public const CAP_SUBMIT_EVENTS     = 'ap_submit_events';
    public const CAP_MANAGE_PORTFOLIO  = 'ap_manage_portfolio';
    public const CAP_MODERATE_EVENTS   = 'ap_moderate_events';
    public const CAP_IMPERSONATE       = 'ap_impersonate_creator';
    public const CAP_REVIEW_VIEW       = 'ap_review_view';
    public const CAP_REVIEW_MANAGE     = 'ap_review_manage';

    /**
     * Register roles and assign capabilities.
     */
    public static function register(): void
    {
        add_action('init', [self::class, 'add_roles_and_capabilities']);
        add_filter('map_meta_cap', [self::class, 'map_meta_caps'], 10, 4);
    }

    /**
     * Ensure expected roles exist and assign their capabilities.
     */
    public static function add_roles_and_capabilities(): void
    {
        $roles = ['artist', 'organization', 'administrator'];

        foreach ($roles as $role_key) {
            if (!get_role($role_key)) {
                add_role($role_key, ucfirst($role_key), []);
            }
        }

        $artist = get_role('artist');
        if ($artist) {
            $artist->add_cap(self::CAP_SUBMIT_EVENTS);
            $artist->add_cap(self::CAP_MANAGE_PORTFOLIO);
        }

        $organization = get_role('organization');
        if ($organization) {
            $organization->add_cap(self::CAP_SUBMIT_EVENTS);
            $organization->add_cap(self::CAP_MANAGE_PORTFOLIO);
        }

        $admin = get_role('administrator');
        if ($admin) {
            $caps = [
                self::CAP_MODERATE_EVENTS,
                self::CAP_IMPERSONATE,
                self::CAP_MANAGE_OWN_ORG,
                self::CAP_MANAGE_OWN_ARTIST,
                self::CAP_SUBMIT_EVENTS,
                self::CAP_MANAGE_PORTFOLIO,
                self::CAP_REVIEW_VIEW,
                self::CAP_REVIEW_MANAGE,
            ];

            foreach ($caps as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    /**
     * Allow owners to manage their own portfolios.
     *
     * @param string[] $caps    Primitive capabilities that are being checked.
     * @param string   $cap     Capability being checked.
     * @param int      $user_id User identifier.
     * @param array    $args    Additional capability arguments.
     *
     * @return string[]
     */
    public static function map_meta_caps(array $caps, string $cap, int $user_id, array $args): array
    {
        if (in_array($cap, ['create_ap_review_requests', 'edit_ap_review_requests'], true)) {
            return $user_id > 0 ? ['exist'] : ['do_not_allow'];
        }

        $managed_caps = [
            self::CAP_MANAGE_OWN_ORG,
            self::CAP_MANAGE_OWN_ARTIST,
        ];

        if (!in_array($cap, $managed_caps, true)) {
            return $caps;
        }

        $post_id = (int) ($args[0] ?? 0);
        if ($post_id && PortfolioAccess::is_owner($user_id, $post_id)) {
            return ['exist'];
        }

        return ['do_not_allow'];
    }
}
