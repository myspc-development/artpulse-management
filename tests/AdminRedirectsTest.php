<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Admin\AdminRedirects;

class AdminRedirectsTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$redirect = '';
    }

    /**
     * @runInSeparateProcess
     */
    public function test_membership_settings_redirect()
    {
        $_SERVER['REQUEST_URI'] = '/wp-admin/ead-membership-settings';
        AdminRedirects::redirect_clean_admin_urls();
        $this->assertSame('admin.php?page=artpulse-settings&tab=membership', Stubs::$redirect);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_membership_overview_redirect()
    {
        $_SERVER['REQUEST_URI'] = '/wp-admin/ead-membership-overview';
        AdminRedirects::redirect_clean_admin_urls();
        $this->assertSame('admin.php?page=artpulse-settings&tab=membership', Stubs::$redirect);
    }
}
