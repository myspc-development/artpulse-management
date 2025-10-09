<?php

namespace Tests\Integration;

use ArtPulse\Core\Capabilities;
use ArtPulse\Rest\Guards;
use WP_Error;
use WP_REST_Request;
use WP_UnitTestCase;

class PortfolioAccessTest extends WP_UnitTestCase
{
    private $org_id;
    private $owner_id;
    private $admin_id;
    private $subscriber_id;

    protected function set_up(): void
    {
        parent::set_up();

        Capabilities::add_roles_and_capabilities();

        $this->owner_id = $this->factory->user->create([
            'role' => 'organization',
        ]);

        $this->admin_id = $this->factory->user->create([
            'role' => 'administrator',
        ]);

        $this->subscriber_id = $this->factory->user->create([
            'role' => 'subscriber',
        ]);

        $this->org_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_author' => $this->owner_id,
        ]);

        add_post_meta($this->org_id, '_ap_owner_user', $this->owner_id);
    }

    public function test_owner_and_admin_can_write_but_subscriber_cannot(): void
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->org_id);
        $request->set_param('id', $this->org_id);

        wp_set_current_user($this->owner_id);
        $this->assertTrue(Guards::own_portfolio_only($request));

        wp_set_current_user($this->admin_id);
        $this->assertTrue(Guards::own_portfolio_only($request));

        wp_set_current_user($this->subscriber_id);
        $result = Guards::own_portfolio_only($request);
        $this->assertInstanceOf(WP_Error::class, $result);
        $data = $result->get_error_data();
        $this->assertIsArray($data);
        $this->assertSame(403, $data['status']);
    }
}
