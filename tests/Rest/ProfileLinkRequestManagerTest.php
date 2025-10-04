<?php
class ProfileLinkRequestManagerTest extends \WP_UnitTestCase {
    public function test_handle_create_request_invalid_target() {
        wp_set_current_user($this->factory->user->create());
        $request = new WP_REST_Request('POST', '/artpulse/v1/link-requests');
        $request->set_param('target_id', 999999); // assuming this post doesn't exist
        $response = ArtPulse\Community\ProfileLinkRequestManager::handle_create_request($request);
        $this->assertWPError($response);
        $this->assertEquals(404, $response->get_error_code());
    }
}
