<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\ManageMembersEndpoint;

class ManageMembersEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$caps = [];
        Stubs::$user_meta = [];
        Stubs::$current_user_roles = [];
    }

    public function test_permissions_check_requires_manage_options()
    {
        $endpoint = new ManageMembersEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('permissions_check');
        $method->setAccessible(true);
        $allowed = $method->invoke($endpoint, new WP_REST_Request());
        $this->assertFalse($allowed);

        Stubs::$caps = ['manage_options'];
        $allowed = $method->invoke($endpoint, new WP_REST_Request());
        $this->assertTrue($allowed);
    }

    public function test_update_member_updates_meta_and_role()
    {
        Stubs::$caps = ['manage_options'];
        $endpoint = new ManageMembersEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('update_member');
        $method->setAccessible(true);

        $request = new WP_REST_Request();
        $request->set_param('id', 10);
        $request->set_param('membership_level', 'pro');
        $request->set_param('membership_end_date', '2024-01-01');
        $request->set_param('membership_auto_renew', true);

        $response = $method->invoke($endpoint, $request);

        $this->assertTrue($response->data['success']);
        $this->assertSame('pro', Stubs::$user_meta['membership_level']);
        $this->assertSame('2024-01-01', Stubs::$user_meta['membership_end_date']);
        $this->assertSame('1', Stubs::$user_meta['membership_auto_renew']);
        $this->assertSame(['member_pro'], Stubs::$current_user_roles);
    }
}
