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

    public function test_create_member_creates_user_and_meta()
    {
        Stubs::$caps = ['manage_options'];
        $endpoint = new ManageMembersEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('create_member');
        $method->setAccessible(true);

        $request = new WP_REST_Request();
        $request->set_param('name', 'New Member');
        $request->set_param('email', 'new@example.com');
        $request->set_param('membership_level', 'basic');
        $request->set_param('membership_end_date', '2024-12-31');
        $request->set_param('membership_auto_renew', true);

        $response = $method->invoke($endpoint, $request);

        $this->assertTrue($response->data['success']);
        $this->assertSame('New Member', Stubs::$users[0]->display_name);
        $this->assertSame('new@example.com', Stubs::$users[0]->user_email);
        $this->assertSame('basic', Stubs::$user_meta['membership_level']);
        $this->assertSame('2024-12-31', Stubs::$user_meta['membership_end_date']);
        $this->assertSame('1', Stubs::$user_meta['membership_auto_renew']);
        $this->assertSame(['member_basic'], Stubs::$current_user_roles);
    }

    public function test_get_member_returns_details()
    {
        Stubs::$caps = ['manage_options'];
        Stubs::$users = [ (object) ['ID'=>5,'user_email'=>'test@example.com','display_name'=>'Test User'] ];
        Stubs::$user_meta = [
            'membership_level' => 'pro',
            'membership_end_date' => '2025-01-01',
            'membership_auto_renew' => '1'
        ];
        $endpoint = new ManageMembersEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('get_member');
        $method->setAccessible(true);

        $request = new WP_REST_Request();
        $request->set_param('id', 5);
        $response = $method->invoke($endpoint, $request);

        $this->assertSame(5, $response->data['id']);
        $this->assertSame('Test User', $response->data['name']);
        $this->assertSame('test@example.com', $response->data['email']);
        $this->assertSame('pro', $response->data['membership_level']);
        $this->assertTrue($response->data['membership_auto_renew']);
    }
}
