<?php

namespace Tests\Rest;

use WP_UnitTestCase;
use WP_REST_Request;

class ArtistRestControllerTest extends WP_UnitTestCase
{
    protected $artist_post;

    public function set_up(): void
    {
        parent::set_up();

        // Create a test artist post
        $this->artist_post = $this->factory->post->create_and_get([
            'post_type'   => 'artpulse_artist',
            'post_title'  => 'Test Artist',
            'post_status' => 'publish',
            'meta_input'  => [
                '_ap_artist_bio' => 'A brief bio',
                '_ap_artist_org' => 101,
            ]
        ]);
    }

    public function test_can_fetch_all_artists()
    {
        $request  = new WP_REST_Request('GET', '/artpulse/v1/artists');
        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertNotEmpty($data);

        $found = array_filter($data, fn($item) => $item['id'] === $this->artist_post->ID);
        $this->assertNotEmpty($found, 'Created artist should be in the list');
    }

    public function test_can_fetch_single_artist()
    {
        $request  = new WP_REST_Request('GET', '/artpulse/v1/artists/' . $this->artist_post->ID);
        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertEquals('Test Artist', $data['title']);
        $this->assertEquals('A brief bio', $data['meta']['bio']);
        $this->assertEquals(101, $data['meta']['org']);
    }

    public function test_invalid_artist_id_returns_404()
    {
        $request  = new WP_REST_Request('GET', '/artpulse/v1/artists/999999');
        $response = rest_do_request($request);

        $this->assertSame(404, $response->get_status());
    }
}
