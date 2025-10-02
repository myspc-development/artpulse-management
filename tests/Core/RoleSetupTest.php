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
}
