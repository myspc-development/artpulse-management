<?php
namespace ArtPulse\Core;

class AccessControlManager
{
    public static function register()
    {
        add_action('template_redirect', [self::class, 'checkAccess'], 7);
    }

    public static function checkAccess()
    {
        if (self::shouldRedirectGuest()) {
            self::redirectGuest();

            return;
        }

        if (is_singular(['artpulse_event', 'artpulse_artwork'])) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                self::redirectGuest();

                return;
            }

            $level       = get_user_meta($user_id, 'ap_membership_level', true);
            $user        = wp_get_current_user();
            $has_upgrade = $user && array_intersect(['artist', 'organization'], (array) $user->roles);

            if ('Free' === $level && !$has_upgrade) {
                wp_redirect(home_url());
                exit;
            }
        }
    }

    private static function shouldRedirectGuest(): bool
    {
        if (is_user_logged_in()) {
            return false;
        }

        $protected_pages = self::getProtectedPages();

        if (empty($protected_pages)) {
            return false;
        }

        return is_page($protected_pages);
    }

    /**
     * @return array<int|string>
     */
    private static function getProtectedPages(): array
    {
        $pages = [];

        foreach (self::getProtectedPageMap() as $slug => $option) {
            $page_id = (int) get_option($option);

            if ($page_id > 0) {
                $pages[] = $page_id;
            } else {
                $pages[] = $slug;
            }
        }

        return $pages;
    }

    private static function redirectGuest(): void
    {
        $redirect_to = self::getCurrentRequestUrl();

        wp_safe_redirect(wp_login_url($redirect_to));
        exit;
    }

    private static function getCurrentRequestUrl(): string
    {
        $queried_id = get_queried_object_id();
        $url        = $queried_id ? get_permalink($queried_id) : '';

        if (!is_string($url) || $url === '') {
            $slug = self::getCurrentPageSlug();
            $url  = $slug !== '' ? home_url('/' . $slug . '/') : home_url('/');
        }

        $query_args = self::sanitizeQueryArgs($_GET ?? []);

        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        return $url;
    }

    private static function getCurrentPageSlug(): string
    {
        $slug = get_query_var('pagename');
        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        global $wp;

        if (isset($wp->request) && is_string($wp->request) && $wp->request !== '') {
            return trim($wp->request, '/');
        }

        return '';
    }

    private static function sanitizeQueryArgs($args): array
    {
        if (!is_array($args)) {
            return [];
        }

        $sanitized = [];

        foreach ($args as $key => $value) {
            $sanitized_key = sanitize_key((string) $key);

            if ($sanitized_key === '') {
                continue;
            }

            if (is_scalar($value)) {
                $sanitized[$sanitized_key] = sanitize_text_field((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * @return array<string, string>
     */
    private static function getProtectedPageMap(): array
    {
        return [
            'dashboard'      => 'ap_dashboard_page_id',
            'artist-builder' => 'ap_artist_builder_page_id',
            'org-builder'    => 'ap_org_builder_page_id',
        ];
    }
}
