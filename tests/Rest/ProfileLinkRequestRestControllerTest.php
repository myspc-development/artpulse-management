<?php

namespace Tests\Rest;

use ArtPulse\Community\ProfileLinkRequestManager;
use ArtPulse\Community\ProfileLinkRequestRestController;
use WP_REST_Request;
use WP_UnitTestCase;

class ProfileLinkRequestRestControllerTest extends WP_UnitTestCase
{
    private int $artist_id;
    private int $moderator_id;
    private int $org_id;

    public function set_up(): void
    {
        parent::set_up();

        global $wp_rest_server;
        $wp_rest_server = rest_get_server();

        ProfileLinkRequestRestController::register();
        do_action('rest_api_init');

        $this->artist_id    = $this->factory->user->create(['role' => 'subscriber']);
        $this->moderator_id = $this->factory->user->create(['role' => 'administrator']);
        $this->org_id       = $this->factory->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Test Org',
        ]);
    }

    public function test_create_request_successfully_creates_request(): void
    {
        wp_set_current_user($this->artist_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/link-request');
        $request->set_body_params([
            'org_id'  => $this->org_id,
            'message' => 'Link me!',
        ]);

        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('request_id', $data);

        $request_id = (int) $data['request_id'];
        $this->assertSame($this->artist_id, (int) get_post_meta($request_id, 'artist_user_id', true));
        $this->assertSame($this->org_id, (int) get_post_meta($request_id, 'org_id', true));
        $this->assertSame('Link me!', get_post_meta($request_id, 'message', true));
        $this->assertSame(ProfileLinkRequestManager::STATUS_PENDING, get_post_meta($request_id, 'status', true));
    }

    public function test_create_request_returns_error_for_invalid_org(): void
    {
        wp_set_current_user($this->artist_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/link-request');
        $request->set_body_params([
            'org_id' => 999999,
        ]);

        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(404, $response->get_status());
        $this->assertSame('invalid_org', $data['code']);
    }

    public function test_approve_request_updates_status(): void
    {
        $request_id = ProfileLinkRequestManager::create($this->artist_id, $this->org_id, 'Approve me');
        $this->assertIsInt($request_id);

        wp_set_current_user($this->moderator_id);

        $request = new WP_REST_Request('POST', sprintf('/artpulse/v1/link-request/%d/approve', $request_id));
        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame(ProfileLinkRequestManager::STATUS_APPROVED, get_post_meta($request_id, 'status', true));
        $this->assertSame($this->moderator_id, (int) get_post_meta($request_id, 'moderated_by', true));
    }

    public function test_approve_request_returns_error_if_already_moderated(): void
    {
        $request_id = ProfileLinkRequestManager::create($this->artist_id, $this->org_id, 'Approve me');
        $this->assertIsInt($request_id);
        $result = ProfileLinkRequestManager::approve($request_id, $this->moderator_id);
        $this->assertSame($request_id, $result);

        wp_set_current_user($this->moderator_id);

        $request = new WP_REST_Request('POST', sprintf('/artpulse/v1/link-request/%d/approve', $request_id));
        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(409, $response->get_status());
        $this->assertSame('invalid_status', $data['code']);
    }

    public function test_deny_request_updates_status(): void
    {
        $request_id = ProfileLinkRequestManager::create($this->artist_id, $this->org_id, 'Deny me');
        $this->assertIsInt($request_id);

        wp_set_current_user($this->moderator_id);

        $request = new WP_REST_Request('POST', sprintf('/artpulse/v1/link-request/%d/deny', $request_id));
        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame(ProfileLinkRequestManager::STATUS_DENIED, get_post_meta($request_id, 'status', true));
        $this->assertSame($this->moderator_id, (int) get_post_meta($request_id, 'moderated_by', true));
    }
}
