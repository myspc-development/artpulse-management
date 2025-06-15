<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\SubmitEventEndpoint;

class SubmitEventEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$logged_in = true;
        Stubs::$caps = ['manage_events'];
    }

    public function test_submit_event_with_honeypot_returns_error()
    {
        $endpoint = new SubmitEventEndpoint();
        $request = new WP_REST_Request();
        $request->set_param('website_url_hp', 'spam');

        $response = $endpoint->submitEvent($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('spam_detected', $response->code);
        $this->assertSame(400, $response->data['status']);
    }

    public function test_permissions_check_requires_manage_events()
    {
        $endpoint = new SubmitEventEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('permissionsCheck');
        $method->setAccessible(true);

        Stubs::$caps = [];
        $allowed = $method->invoke($endpoint, new WP_REST_Request());
        $this->assertFalse($allowed);

        Stubs::$caps = ['manage_events'];
        $allowed = $method->invoke($endpoint, new WP_REST_Request());
        $this->assertTrue($allowed);
    }
}
