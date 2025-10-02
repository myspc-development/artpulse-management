<?php

namespace ArtPulse\Core;

/**
 * Determines dashboard destinations for users after login.
 */
class LoginRedirector
{
    private const DASHBOARD_ROUTES = [
        'edit_artpulse_org'       => '/org-dashboard/',
        'edit_artpulse_artist'    => '/artist-dashboard/',
        'view_artpulse_dashboard' => '/dashboard/',
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

        foreach (self::DASHBOARD_ROUTES as $capability => $path) {
            if (user_can($user, $capability)) {
                return home_url($path);
            }
        }

        return $redirect_to;
    }
}
