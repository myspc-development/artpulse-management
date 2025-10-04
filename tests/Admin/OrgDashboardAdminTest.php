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
        update_user_meta($this->managerId, 'ap_org_id', $this->orgOne);

        ob_start();
        OrgDashboardAdmin::render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('id="ap-org-select"', $output);

        $_GET['org_id'] = (string) $this->orgTwo;
        putenv('QUERY_STRING=page=ap-org-dashboard&org_id=' . $this->orgTwo);

        $this->assertSame($this->orgOne, $this->callGetCurrentOrgId());
    }

    private function callGetCurrentOrgId(): int
    {
        $method = new \ReflectionMethod(OrgDashboardAdmin::class, 'get_current_org_id');
        $method->setAccessible(true);

        return (int) $method->invoke(null);
    }
}
