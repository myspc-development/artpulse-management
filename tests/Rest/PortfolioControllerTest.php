<?php

namespace Tests\Rest;

use ArtPulse\Core\Capabilities;
use WP_REST_Request;
use WP_UnitTestCase;

class PortfolioControllerTest extends WP_UnitTestCase
{
    private int $user_id;
    private int $post_id;

    protected function set_up(): void
    {
        parent::set_up();

        Capabilities::add_roles_and_capabilities();

        $this->user_id = self::factory()->user->create([
            'role' => 'organization',
        ]);

        $this->post_id = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_author' => $this->user_id,
        ]);

        add_post_meta($this->post_id, '_ap_owner_user', $this->user_id);
        update_post_meta($this->post_id, '_ap_visibility', 'public');

        wp_set_current_user($this->user_id);

        do_action('rest_api_init');
    }

    public function test_update_portfolio_valid_payload_updates_meta(): void
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode([
            'tagline'      => 'Updated tagline',
            'bio'          => '<p>Updated bio</p>',
            'website_url'  => 'https://example.com',
            'socials'      => ['https://example.org/profile'],
            'visibility'   => 'private',
            'status'       => 'draft',
        ]));

        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('Updated tagline', get_post_meta($this->post_id, '_ap_tagline', true));
        $this->assertSame('https://example.com', get_post_meta($this->post_id, '_ap_website', true));
        $this->assertSame('https://example.org/profile', trim((string) get_post_meta($this->post_id, '_ap_socials', true)));
        $this->assertSame('<p>Updated bio</p>', get_post_meta($this->post_id, '_ap_about', true));
        $this->assertSame('private', get_post_meta($this->post_id, '_ap_visibility', true));
    }

    public function test_update_portfolio_rejects_invalid_url(): void
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode([
            'website_url' => 'not-a-valid-url',
        ]));

        $response = rest_do_request($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('ap_invalid_param', $data['code']);
        $this->assertSame('website_url', $data['data']['field']);
    }

    public function test_update_portfolio_respects_rate_limit(): void
    {
        set_transient('ap_rate_builder_write_' . $this->user_id, [
            'count' => 30,
            'reset' => time() + 30,
        ], 30);

        $request = new WP_REST_Request('POST', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode([]));

        $response = rest_do_request($request);

        $this->assertSame(429, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('ap_rate_limited', $data['code']);
        $this->assertArrayHasKey('retry_after', $data['data']);

        delete_transient('ap_rate_builder_write_' . $this->user_id);
    }

    public function test_view_context_requires_public_profile(): void
    {
        update_post_meta($this->post_id, '_ap_visibility', 'private');

        $request = new WP_REST_Request('GET', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_param('context', 'view');

        $response = rest_do_request($request);
        $this->assertSame(404, $response->get_status());
    }

    public function test_edit_context_requires_permissions(): void
    {
        $other_user = self::factory()->user->create([
            'role' => 'subscriber',
        ]);
        wp_set_current_user($other_user);

        $request = new WP_REST_Request('GET', '/artpulse/v1/portfolio/org/' . $this->post_id);
        $request->set_param('id', $this->post_id);
        $request->set_param('type', 'org');
        $request->set_param('context', 'edit');

        $response = rest_do_request($request);
        $this->assertSame(403, $response->get_status());
    }
}
