<?php

namespace ArtPulse\Core;

/**
 * Registers and assigns custom capabilities.
 */
class CapabilitiesManager
{
    public static function register(): void
    {
        add_action('init', [self::class, 'add_capabilities']);
    }

    public static function add_capabilities(): void
    {
        $roles = ['administrator', 'editor'];

        $caps = [
            'manage_artpulse_settings',
            'edit_artpulse_content',
            'moderate_link_requests',
            'view_artpulse_dashboard',
        ];

        foreach ($roles as $role_key) {
            $role = get_role($role_key);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }

    public static function remove_capabilities(): void
    {
        $roles = ['administrator', 'editor'];
        $caps = [
            'manage_artpulse_settings',
            'edit_artpulse_content',
            'moderate_link_requests',
            'view_artpulse_dashboard',
        ];

        foreach ($roles as $role_key) {
            $role = get_role($role_key);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}
