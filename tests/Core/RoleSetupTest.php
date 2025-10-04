<?php

use ArtPulse\Core\RoleSetup;

class RoleSetupTest extends \WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        RoleSetup::install();
    }

    public function test_artist_role_receives_dashboard_capability(): void
    {
        $role = get_role('artist');
        $this->assertNotFalse($role, 'Artist role should be registered.');

        $role->remove_cap('view_artpulse_dashboard');
        $this->assertFalse($role->has_cap('view_artpulse_dashboard'));

        RoleSetup::install();

        $this->assertTrue(
            get_role('artist')->has_cap('view_artpulse_dashboard'),
            'Artist role should receive the dashboard capability on install.'
        );
    }

    public function test_upgrade_reapplies_dashboard_capability_for_organization(): void
    {
        $role = get_role('organization');
        $this->assertNotFalse($role, 'Organization role should be registered.');

        $role->remove_cap('view_artpulse_dashboard');
        update_option('artpulse_roles_version', '0.0.1');

        RoleSetup::maybe_upgrade();

        $this->assertTrue(
            get_role('organization')->has_cap('view_artpulse_dashboard'),
            'Organization role should regain dashboard capability during upgrade.'
        );

        $this->assertNotSame(
            '0.0.1',
            get_option('artpulse_roles_version'),
            'Role version option should be updated during upgrade.'
        );
    }

    public function test_admin_and_editor_receive_approve_capability_on_install(): void
    {
        foreach (['administrator', 'editor'] as $role_key) {
            $role = get_role($role_key);
            $this->assertNotFalse($role, sprintf('%s role should be registered.', ucfirst($role_key)));

            $this->assertTrue(
                $role->has_cap('artpulse_approve_event'),
                sprintf('%s should have the event approval capability on install.', ucfirst($role_key))
            );
        }
    }

    public function test_upgrade_reapplies_approve_capability_for_admin_and_editor(): void
    {
        foreach (['administrator', 'editor'] as $role_key) {
            $role = get_role($role_key);
            $this->assertNotFalse($role, sprintf('%s role should be registered.', ucfirst($role_key)));
            $role->remove_cap('artpulse_approve_event');
        }

        update_option('artpulse_roles_version', '1.1.5');

        RoleSetup::maybe_upgrade();

        foreach (['administrator', 'editor'] as $role_key) {
            $this->assertTrue(
                get_role($role_key)->has_cap('artpulse_approve_event'),
                sprintf('%s should regain the event approval capability during upgrade.', ucfirst($role_key))
            );
        }
    }
}
