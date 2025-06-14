<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\MembershipEndpoint;

class MembershipEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$user_meta = [];
        Stubs::$current_user_id = 1;
        Stubs::$current_user_email = 'user@example.com';
        Stubs::$current_user_display_name = 'User';
        Stubs::$current_user_roles = ['subscriber'];
        Stubs::$logged_in = true;
    }

    public function test_get_user_profile_returns_expected_fields()
    {
        Stubs::$user_meta = [
            'membership_level' => 'gold',
            'org_badge_label'  => 'VIP',
        ];

        $endpoint = new MembershipEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('get_user_profile');
        $method->setAccessible(true);

        $response = $method->invoke($endpoint, new WP_REST_Request());

        $this->assertSame(1, $response->data['ID']);
        $this->assertSame('User', $response->data['name']);
        $this->assertSame('user@example.com', $response->data['email']);
        $this->assertSame('subscriber', $response->data['role']);
        $this->assertSame('gold', $response->data['membership_level']);
        $this->assertSame('VIP', $response->data['badge_label']);
    }

    public function test_get_user_badges_builds_badge_list()
    {
        Stubs::$user_meta = [ 'rsvp_count' => 12 ];
        $endpoint = new MembershipEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('get_user_badges');
        $method->setAccessible(true);

        $response = $method->invoke($endpoint, new WP_REST_Request());

        $this->assertSame(12, $response->data['rsvp_count']);
        $this->assertContains('3 RSVPs', $response->data['badges']);
        $this->assertContains('10 RSVPs', $response->data['badges']);
    }

    public function test_get_membership_status_returns_flags()
    {
        Stubs::$user_meta = [
            'is_member' => '1',
            'membership_level' => 'silver',
            'membership_joined' => '2023-01-01 00:00:00',
            'membership_expires' => '2024-01-01 00:00:00',
        ];
        $endpoint = new MembershipEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('get_membership_status');
        $method->setAccessible(true);

        $response = $method->invoke($endpoint, new WP_REST_Request());

        $this->assertTrue($response->data['is_member']);
        $this->assertSame('silver', $response->data['membership_level']);
        $this->assertSame('2023-01-01 00:00:00', $response->data['membership_joined']);
        $this->assertSame('2024-01-01 00:00:00', $response->data['membership_expires']);
        $this->assertSame('subscriber', $response->data['role']);
    }

    public function test_update_user_profile_updates_fields()
    {
        Stubs::$current_user_roles = ['member_org'];
        $endpoint = new MembershipEndpoint();
        $request = new WP_REST_Request();
        $request->set_param('name', 'New User');
        $request->set_param('bio', 'New bio');
        $request->set_param('badge_label', 'Elite');
        $request->set_param('membership_level', 'pro');

        $response = $endpoint->update_user_profile($request);

        $this->assertTrue($response->data['success']);
        $this->assertSame('New User', Stubs::$updated_posts[1]['display_name']);
        $this->assertSame('New bio', Stubs::$user_meta['description']);
        $this->assertSame('Elite', Stubs::$user_meta['org_badge_label']);
        $this->assertSame('pro', Stubs::$user_meta['membership_level']);
        $this->assertSame('1', Stubs::$user_meta['is_member']);
        $this->assertArrayHasKey('membership_joined', Stubs::$user_meta);
        $this->assertArrayHasKey('membership_expires', Stubs::$user_meta);
        $this->assertSame(['member_pro'], Stubs::$current_user_roles);
    }
}
