<?php

namespace ArtPulse\Core;

/**
 * Determines dashboard destinations for users after login.
 */
class LoginRedirector
{
    private const DASHBOARD_PATH = '/dashboard/';

    private const ROLE_CAPABILITIES = [
        'organization' => 'edit_artpulse_org',
        'artist'       => 'edit_artpulse_artist',
        'member'       => 'read',
    ];

    public static function register(): void
    {
        add_filter('login_redirect', [self::class, 'filterRedirect'], 10, 3);
    }

    /**
     * Choose the most privileged dashboard a user should land on after login.
     *
     * The capabilities are evaluated in order so that organization dashboards take
     * precedence over artist dashboards, which themselves take precedence over the
     * shared dashboard view. When none of the required capabilities are granted
     * the original redirect destination is preserved.
     *
     * @param string      $redirect_to           The default login redirect destination.
     * @param string      $requested_redirect_to The requested destination (unused here).
     * @param mixed       $user                  The authenticated user object when login succeeds.
     *
     * @return string The calculated redirect destination.
     */
    public static function filterRedirect(string $redirect_to, string $requested_redirect_to, $user): string
    {
        if (!is_object($user) || !is_a($user, 'WP_User')) {
            return $redirect_to;
        }

        $roles = (array) $user->roles;

        foreach (self::ROLE_CAPABILITIES as $role => $capability) {
            if (in_array($role, $roles, true) && user_can($user, $capability)) {
                return self::buildDashboardUrl($role);
            }
        }

        if (user_can($user, 'view_artpulse_dashboard')) {
            return home_url(self::DASHBOARD_PATH);
        }

        return $redirect_to;
    }

    private static function buildDashboardUrl(string $role): string
    {
        $base = home_url(self::DASHBOARD_PATH);

        return add_query_arg('role', $role, $base);
    }
}
