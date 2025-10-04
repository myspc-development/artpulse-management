<?php

namespace ArtPulse\Tests\Admin;

use ArtPulse\Admin\OrgDashboardAdmin;
use WP_UnitTestCase;

class OrgDashboardAdminTest extends WP_UnitTestCase
{
    private int $adminId;
    private int $managerId;
    private int $orgOne;
    private int $orgTwo;

    protected function setUp(): void
    {
        parent::setUp();

        do_action('init');

        if (!get_role('organization')) {
            add_role('organization', 'Organization', ['read' => true]);
        }

        $this->adminId   = self::factory()->user->create(['role' => 'administrator']);
        $this->managerId = self::factory()->user->create(['role' => 'organization']);

        $this->orgOne = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_title'  => 'Org One',
            'post_status' => 'publish',
        ]);

        $this->orgTwo = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_title'  => 'Org Two',
            'post_status' => 'publish',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        wp_set_current_user(0);
        unset($_GET['org_id']);
        putenv('QUERY_STRING');
        delete_user_meta($this->managerId, 'ap_organization_id');
        delete_user_meta($this->managerId, 'ap_org_id');
    }

    public function test_site_administrator_sees_selector_and_can_switch_org_context(): void
    {
        wp_set_current_user($this->adminId);
        $_GET = [];
        putenv('QUERY_STRING');

        ob_start();
        OrgDashboardAdmin::render();
        $output = ob_get_clean();

        $this->assertStringContainsString('id="ap-org-select"', $output);
        $this->assertStringContainsString((string) $this->orgOne, $output);
        $this->assertStringContainsString((string) $this->orgTwo, $output);

        $_GET['org_id'] = (string) $this->orgTwo;
        putenv('QUERY_STRING=page=ap-org-dashboard&org_id=' . $this->orgTwo);

        $this->assertSame($this->orgTwo, $this->callGetCurrentOrgId());
    }

    public function test_org_manager_does_not_see_selector_and_cannot_change_context(): void
    {
        wp_set_current_user($this->managerId);
        update_user_meta($this->managerId, 'ap_organization_id', $this->orgOne);

        ob_start();
        OrgDashboardAdmin::render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('id="ap-org-select"', $output);

        $_GET['org_id'] = (string) $this->orgTwo;
        putenv('QUERY_STRING=page=ap-org-dashboard&org_id=' . $this->orgTwo);

        $this->assertSame($this->orgOne, $this->callGetCurrentOrgId());
    }

    public function test_org_manager_with_legacy_meta_key_is_still_supported(): void
    {
        wp_set_current_user($this->managerId);
        update_user_meta($this->managerId, 'ap_org_id', $this->orgTwo);

        $_GET = [];
        putenv('QUERY_STRING');

        $this->assertSame($this->orgTwo, $this->callGetCurrentOrgId());
    }

    public function test_org_manager_with_org_meta_sees_assigned_data(): void
    {
        wp_set_current_user($this->managerId);
        update_user_meta($this->managerId, 'ap_organization_id', $this->orgOne);

        $linkedArtist = self::factory()->post->create([
            'post_type'   => 'ap_profile_link',
            'post_status' => 'publish',
        ]);
        update_post_meta($linkedArtist, 'org_id', $this->orgOne);
        update_post_meta($linkedArtist, 'status', 'approved');
        update_post_meta($linkedArtist, 'artist_user_id', 123);
        update_post_meta($linkedArtist, 'requested_on', '2024-01-01');

        $artwork = self::factory()->post->create([
            'post_type'   => 'artpulse_artwork',
            'post_title'  => 'Sunset Vista',
            'post_status' => 'publish',
        ]);
        update_post_meta($artwork, 'org_id', $this->orgOne);
        update_post_meta($artwork, 'ap_views', 42);
        update_post_meta($artwork, 'ap_favorites', 7);

        $event = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_title'  => 'Annual Gala',
            'post_status' => 'publish',
        ]);
        update_post_meta($event, 'org_id', $this->orgOne);

        update_post_meta($this->orgOne, 'stripe_payment_ids', ['ch_12345']);

        ob_start();
        OrgDashboardAdmin::render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('No organisation assigned to your user.', $output);
        $this->assertStringContainsString('Linked Artists', $output);
        $this->assertStringContainsString('123', $output);
        $this->assertStringContainsString('Sunset Vista', $output);
        $this->assertStringContainsString('Annual Gala', $output);
        $this->assertStringContainsString('Total Artwork Views', $output);
        $this->assertStringContainsString('Charge ID', $output);
    }

    private function callGetCurrentOrgId(): int
    {
        $method = new \ReflectionMethod(OrgDashboardAdmin::class, 'get_current_org_id');
        $method->setAccessible(true);

        return (int) $method->invoke(null);
    }
}
