<?php

namespace Tests\Rest;

use WP_UnitTestCase;
use WP_REST_Request;

class MembershipManagerTest extends \WP_UnitTestCase
{
    public function test_webhook_invalid_payload_returns_error()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/stripe-webhook');
        $request->set_body('invalid_payload');

        $response = rest_do_request($request);
        $this->assertInstanceOf(\WP_Error::class, $response);
    }
}