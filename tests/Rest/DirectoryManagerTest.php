<?php
use WP_UnitTestCase;

class DirectoryManagerTest extends WP_UnitTestCase {
    public function test_handleFilter_with_invalid_type_returns_error() {
        $request = new WP_REST_Request('GET', '/artpulse/v1/filter');
        $request->set_param('type', 'invalid_type');
        $response = ArtPulse\Core\DirectoryManager::handleFilter($request);
        $this->assertWPError($response);
        $this->assertEquals(400, $response->get_error_code());
    }
}
