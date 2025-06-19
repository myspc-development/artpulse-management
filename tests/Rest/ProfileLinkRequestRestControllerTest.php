<?php
namespace Tests\Rest;

use WP_UnitTestCase;
use WP_REST_Request;

class ProfileLinkRequestRestControllerTest extends WP_UnitTestCase
{
    protected $artist_id;
    protected $admin_id;
    protected $org_id;

    public function set_up(): void
    {
        parent::set_up();
        $this->artist_id = $this->factory->user->create(['role' => 'subscriber']);
        $this->admin_id  = $this->factory->user->create(['role' => 'administrator']);
        $this->org_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_author' => $this->admin_id,
        ]);
    }

    public function test_can_create_request()
    {
        wp_set_current_user($this->artist_id);
        $req = new WP_REST_Request('POST', '/artpulse/v1/link-request');
        $req->set_body_params([
            'org_id'  => $this->org_id,
            'message' => 'please link',
        ]);
        $req->set_header('content-type', 'application/x-www-form-urlencoded');
        $res = rest_do_request($req);
        $data = $res->get_data();

        $this->assertSame(200, $res->get_status());
        $this->assertTrue($data['success']);

        $post = get_post($data['request_id']);
        $this->assertNotEmpty($post);
        $this->assertEquals('ap_profile_link_request', $post->post_type);
        $this->assertEquals('pending', get_post_meta($post->ID, 'status', true));
    }

    public function test_can_approve_and_deny()
    {
        $request_id = \ArtPulse\Community\ProfileLinkRequestManager::create($this->artist_id, $this->org_id, 'approve me');

        wp_set_current_user($this->admin_id);
        $approve = new WP_REST_Request('POST', '/artpulse/v1/link-request/' . $request_id . '/approve');
        $approve->set_header('content-type', 'application/x-www-form-urlencoded');
        $res = rest_do_request($approve);
        $this->assertSame(200, $res->get_status());
        $this->assertEquals('approved', get_post_meta($request_id, 'status', true));

        $request_id2 = \ArtPulse\Community\ProfileLinkRequestManager::create($this->artist_id, $this->org_id, 'deny me');
        $deny = new WP_REST_Request('POST', '/artpulse/v1/link-request/' . $request_id2 . '/deny');
        $deny->set_header('content-type', 'application/x-www-form-urlencoded');
        $res2 = rest_do_request($deny);
        $this->assertSame(200, $res2->get_status());
        $this->assertEquals('denied', get_post_meta($request_id2, 'status', true));
    }
}
