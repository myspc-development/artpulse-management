<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\RsvpEndpoint;

class RsvpEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$user_meta = [];
        Stubs::$logged_in = true;
        Stubs::$posts = [(object)['ID' => 5, 'post_type' => 'ead_event']];
    }

    public function test_add_rsvp_adds_id()
    {
        $endpoint = new RsvpEndpoint();
        $request = new WP_REST_Request();
        $request->set_param('event_id', 5);

        $response = $endpoint->add_rsvp($request);

        $this->assertSame('added', $response->data['status']);
        $this->assertSame([5], Stubs::$user_meta['ead_rsvps']);
    }

    public function test_remove_rsvp_removes_existing()
    {
        Stubs::$user_meta = ['ead_rsvps' => [5]];
        $endpoint = new RsvpEndpoint();
        $request = new WP_REST_Request();
        $request->set_param('event_id', 5);

        $response = $endpoint->remove_rsvp($request);

        $this->assertSame('removed', $response->data['status']);
        $this->assertSame([], Stubs::$user_meta['ead_rsvps']);
    }

    public function test_add_rsvp_invalid_event()
    {
        Stubs::$posts = [];
        $endpoint = new RsvpEndpoint();
        $request = new WP_REST_Request();
        $request->set_param('event_id', 99);

        $response = $endpoint->add_rsvp($request);

        $this->assertSame(400, $response->status);
        $this->assertArrayHasKey('error', $response->data);
    }

    public function test_bulk_rsvp_adds_ids()
    {
        Stubs::$posts[] = (object)['ID' => 6, 'post_type' => 'ead_event'];

        $endpoint = new RsvpEndpoint();
        $request = new WP_REST_Request();
        $request->set_param('event_ids', [5,6]);
        $request->set_param('action', 'POST');

        $response = $endpoint->bulk_rsvp($request);

        $this->assertSame([5,6], $response->data['rsvps']);
        $this->assertSame([5,6], Stubs::$user_meta['ead_rsvps']);
    }

    public function test_bulk_rsvp_removes_ids()
    {
        Stubs::$user_meta = ['ead_rsvps' => [5,6]];

        $endpoint = new RsvpEndpoint();
        $request = new WP_REST_Request();
        $request->set_param('event_ids', [5]);
        $request->set_param('action', 'DELETE');

        $response = $endpoint->bulk_rsvp($request);

        $this->assertSame([6], $response->data['rsvps']);
        $this->assertSame([6], Stubs::$user_meta['ead_rsvps']);
    }
}
