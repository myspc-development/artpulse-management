<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\EventsEndpoint;

// Minimal WP_Query stub for endpoint tests
class WP_Query {
    public array $posts;
    public int $found_posts;
    public int $max_num_pages;
    public function __construct($args = []) {
        $this->posts = Stubs::$posts;
        $this->found_posts = count($this->posts);
        $per_page = $args['posts_per_page'] ?? 10;
        $this->max_num_pages = $per_page > 0 ? (int)ceil($this->found_posts / $per_page) : 0;
    }
    public function have_posts(): bool {
        return !empty($this->posts);
    }
}

class EventsEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$posts = [];
        Stubs::$meta = [];
        Stubs::$transients = [];
        Stubs::$logged_in = true;
    }

    public function test_get_events_returns_empty_array_when_none_exist()
    {
        $endpoint = new EventsEndpoint();
        $request = new WP_REST_Request();

        $response = $endpoint->getEvents($request);

        $this->assertSame(200, $response->status);
        $this->assertSame([], $response->data);
    }
}
