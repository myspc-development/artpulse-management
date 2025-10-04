<?php

namespace ArtPulse\Core;

/**
 * Sets up custom roles and capabilities for ArtPulse.
 */
class RoleSetup
{
    private const VERSION_OPTION = 'artpulse_roles_version';
    private const ROLES_VERSION  = '1.1.5';

    /**
     * Run this during plugin activation.
     */
    public static function install(): void
    {
        self::add_roles();
        self::assign_capabilities();
        update_option(self::VERSION_OPTION, self::ROLES_VERSION);
    }

    public static function maybe_upgrade(): void
    {
        $stored_version = get_option(self::VERSION_OPTION);

        if ($stored_version !== self::ROLES_VERSION) {
            self::assign_capabilities();
            update_option(self::VERSION_OPTION, self::ROLES_VERSION);
        }
    }

    private static function add_roles(): void
    {
        if (!get_role('member')) {
            add_role('member', 'Member', ['read' => true]);
        }

        if (!get_role('artist')) {
            add_role('artist', 'Artist', ['read' => true]);
        }

        if (!get_role('organization')) {
            add_role('organization', 'Organization', ['read' => true]);
        }
    }

    private static function assign_capabilities(): void
    {
        $cpt_caps = [
            'artpulse_event',
            'artpulse_artist',
            'artpulse_artwork',
            'artpulse_org',
        ];

        $roles_caps = [
            'member' => [
                'read',
                'create_artpulse_events',
            ],
            'artist' => [
                'read',
                'create_artpulse_artist',
                'edit_artpulse_artist', 'read_artpulse_artist', 'delete_artpulse_artist',
                'edit_artpulse_artists', 'edit_others_artpulse_artists',
                'publish_artpulse_artists', 'read_private_artpulse_artists',
                'delete_artpulse_artists', 'delete_private_artpulse_artists',
                'delete_published_artpulse_artists', 'delete_others_artpulse_artists',
                'edit_private_artpulse_artists', 'edit_published_artpulse_artists',
                'create_artpulse_events',
                'edit_artpulse_event', 'read_artpulse_event', 'delete_artpulse_event',
                'edit_artpulse_events', 'publish_artpulse_events', 'delete_artpulse_events',
                'edit_published_artpulse_events', 'delete_published_artpulse_events',
                'view_artpulse_dashboard',
            ],
            'organization' => [
                'read',
                'create_artpulse_org',
                'edit_artpulse_org', 'read_artpulse_org', 'delete_artpulse_org',
                'edit_artpulse_orgs', 'edit_others_artpulse_orgs',
                'publish_artpulse_orgs', 'read_private_artpulse_orgs',
                'delete_artpulse_orgs', 'delete_private_artpulse_orgs',
                'delete_published_artpulse_orgs', 'delete_others_artpulse_orgs',
                'edit_private_artpulse_orgs', 'edit_published_artpulse_orgs',
                'create_artpulse_events',
                'edit_artpulse_event', 'read_artpulse_event', 'delete_artpulse_event',
                'edit_artpulse_events', 'publish_artpulse_events', 'delete_artpulse_events',
                'edit_published_artpulse_events', 'delete_published_artpulse_events',
                'view_artpulse_dashboard',
            ],
            'administrator' => [],
        ];

        // Generate full admin caps for all CPTs
        foreach ($cpt_caps as $cpt) {
            $plural = $cpt . 's';
            $roles_caps['administrator'] = array_merge(
                $roles_caps['administrator'],
                [
                    "create_{$plural}",
                    "edit_{$cpt}", "read_{$cpt}", "delete_{$cpt}",
                    "edit_{$plural}", "edit_others_{$plural}", "publish_{$plural}",
                    "read_private_{$plural}", "delete_{$plural}", "delete_private_{$plural}",
                    "delete_published_{$plural}", "delete_others_{$plural}",
                    "edit_private_{$plural}", "edit_published_{$plural}",
                ]
            );
        }

        // Shared capabilities across roles
        $shared_caps = [
            'moderate_link_requests',
            'view_artpulse_dashboard',
            'manage_artpulse_settings',
        ];

        foreach (['administrator', 'editor'] as $admin_role) {
            $roles_caps[$admin_role] = array_merge(
                $roles_caps[$admin_role] ?? [],
                $shared_caps
            );
        }

        // Assign to roles
        foreach ($roles_caps as $role_key => $capabilities) {
            $role = get_role($role_key);
            if ($role) {
                foreach (array_unique($capabilities) as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }
}
