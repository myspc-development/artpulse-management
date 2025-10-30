<?php

namespace Tests\Rest;

use ArtPulse\Core\Capabilities;
use WP_REST_Request;
use WP_UnitTestCase;

class ArtistRestControllerTest extends \WP_UnitTestCase
{
    protected $artist_post;

    public function set_up(): void
    {
        parent::set_up();

        Capabilities::add_roles_and_capabilities();

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

        do_action('rest_api_init');
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

    public function test_create_artist_requires_authentication()
    {
        wp_set_current_user(0);

        $request  = new WP_REST_Request('POST', '/artpulse/v1/artist/create');
        $response = rest_do_request($request);

        $this->assertSame(401, $response->get_status());
    }

    public function test_create_artist_requires_capability()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $request  = new WP_REST_Request('POST', '/artpulse/v1/artist/create');
        $response = rest_do_request($request);

        $this->assertSame(403, $response->get_status());
    }

    public function test_create_artist_success()
    {
        $user_id = $this->factory->user->create(['role' => 'artist']);
        wp_set_current_user($user_id);

        $request  = new WP_REST_Request('POST', '/artpulse/v1/artist/create');
        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(201, $response->get_status());
        $this->assertArrayHasKey('postId', $data);

        $post_id = (int) $data['postId'];
        $this->assertSame('draft', get_post_status($post_id));
        $this->assertSame($user_id, (int) get_post_meta($post_id, '_ap_owner_user', true));
    }

    public function test_update_artist_requires_permission(): void
    {
        $owner_id    = $this->factory->user->create(['role' => 'artist']);
        $intruder_id = $this->factory->user->create(['role' => 'artist']);

        $post_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'draft',
            'post_author' => $owner_id,
            'meta_input'  => [
                '_ap_owner_user' => $owner_id,
            ],
        ]);

        wp_set_current_user($intruder_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/artists/' . $post_id);
        $request->set_param('id', $post_id);
        $request->set_body_params([
            'title' => 'Not allowed',
        ]);

        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(403, $response->get_status());
        $this->assertSame('ap_forbidden', $data['code']);
    }

    public function test_update_artist_success(): void
    {
        $user_id = $this->factory->user->create(['role' => 'artist']);
        wp_set_current_user($user_id);

        $post_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'draft',
            'post_author' => $user_id,
            'meta_input'  => [
                '_ap_owner_user' => $user_id,
            ],
        ]);

        $request = new WP_REST_Request('POST', '/artpulse/v1/artists/' . $post_id);
        $request->set_param('id', $post_id);
        $request->set_body_params([
            'title'        => 'Updated Artist',
            'excerpt'      => str_repeat('a', 40),
            'website_url'  => 'https://example.com',
            'socials'      => ['https://twitter.com/example', ''],
            'location'     => 'Seattle, WA',
            'status'       => 'publish',
            'visibility'   => 'private',
        ]);

        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertSame('Updated Artist', $data['title']);
        $this->assertSame('publish', $data['status']);
        $this->assertSame('private', $data['visibility']);
        $this->assertSame('https://example.com', $data['website_url']);
        $this->assertSame(['https://twitter.com/example'], $data['socials']);

        $this->assertSame('https://example.com', get_post_meta($post_id, '_ap_website', true));
        $this->assertSame('publish', get_post_status($post_id));

        $location_meta = get_post_meta($post_id, '_ap_location', true);
        $this->assertIsArray($location_meta);
        $this->assertSame('Seattle, WA', $location_meta['address'] ?? '');
    }

    public function test_update_artist_rejects_invalid_website(): void
    {
        $user_id = $this->factory->user->create(['role' => 'artist']);
        wp_set_current_user($user_id);

        $post_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'draft',
            'post_author' => $user_id,
            'meta_input'  => [
                '_ap_owner_user' => $user_id,
            ],
        ]);

        $request = new WP_REST_Request('POST', '/artpulse/v1/artists/' . $post_id);
        $request->set_param('id', $post_id);
        $request->set_body_params([
            'website_url' => 'not-a-valid-url',
        ]);

        $response = rest_do_request($request);
        $data     = $response->get_data();

        $this->assertSame(422, $response->get_status());
        $this->assertSame('ap_invalid_param', $data['code']);
        $this->assertSame('website_url', $data['data']['field']);
    }
}
