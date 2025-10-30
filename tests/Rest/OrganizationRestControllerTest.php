<?php

namespace Tests\Rest;

use ArtPulse\Core\Capabilities;
use WP_REST_Request;
use WP_UnitTestCase;

class OrganizationRestControllerTest extends WP_UnitTestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        Capabilities::add_roles_and_capabilities();

        do_action('rest_api_init');
    }

    public function test_update_organization_requires_permission(): void
    {
        $owner_id    = $this->factory->user->create(['role' => 'organization']);
        $intruder_id = $this->factory->user->create(['role' => 'organization']);

        $post_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'draft',
            'post_author' => $owner_id,
            'meta_input'  => [
                '_ap_owner_user' => $owner_id,
            ],
        ]);

        wp_set_current_user($intruder_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/organizations/' . $post_id);
        $request->set_param('id', $post_id);
        $request->set_body_params([
            'title' => 'Unauthorized update',
        ]);

        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(403, $response->get_status());
        $this->assertSame('ap_forbidden', $data['code']);
    }

    public function test_update_organization_success(): void
    {
        $user_id = $this->factory->user->create(['role' => 'organization']);
        wp_set_current_user($user_id);

        $post_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'draft',
            'post_author' => $user_id,
            'meta_input'  => [
                '_ap_owner_user' => $user_id,
            ],
        ]);

        $request = new WP_REST_Request('POST', '/artpulse/v1/organizations/' . $post_id);
        $request->set_param('id', $post_id);
        $request->set_body_params([
            'title'        => 'Updated Organization',
            'content'      => '<p>Updated content</p>',
            'website_url'  => 'https://example.org',
            'socials'      => ['https://instagram.com/example'],
            'location'     => 'Portland, OR',
            'status'       => 'pending',
            'visibility'   => 'public',
        ]);

        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertSame('Updated Organization', $data['title']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame(['https://instagram.com/example'], $data['socials']);

        $this->assertSame('https://example.org', get_post_meta($post_id, '_ap_website', true));
        $location_meta = get_post_meta($post_id, '_ap_location', true);
        $this->assertIsArray($location_meta);
        $this->assertSame('Portland, OR', $location_meta['address'] ?? '');
    }

    public function test_update_organization_rejects_invalid_social(): void
    {
        $user_id = $this->factory->user->create(['role' => 'organization']);
        wp_set_current_user($user_id);

        $post_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'draft',
            'post_author' => $user_id,
            'meta_input'  => [
                '_ap_owner_user' => $user_id,
            ],
        ]);

        $request = new WP_REST_Request('POST', '/artpulse/v1/organizations/' . $post_id);
        $request->set_param('id', $post_id);
        $request->set_body_params([
            'socials' => ['not-a-url'],
        ]);

        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(422, $response->get_status());
        $this->assertSame('ap_invalid_param', $data['code']);
        $this->assertSame('socials', $data['data']['field']);
    }
}
