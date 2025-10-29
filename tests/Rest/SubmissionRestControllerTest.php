<?php

namespace Tests\Rest;

use ArtPulse\Admin\EventApprovals;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use WP_UnitTestCase;
use WP_REST_Request;

class SubmissionRestControllerTest extends \WP_UnitTestCase
{
    protected $user_id;

    public function set_up(): void
    {
        parent::set_up();
        $this->user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($this->user_id);
        $user = get_userdata($this->user_id);
        if ($user instanceof \WP_User) {
            $user->add_cap('create_artpulse_events');
        }
    }

    public function test_can_submit_event()
    {
        $org_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_author' => $this->user_id,
        ]);
        update_post_meta($org_id, '_ap_owner_user', $this->user_id);

        $artist_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'publish',
            'post_author' => $this->user_id,
        ]);
        update_post_meta($artist_id, '_ap_owner_user', $this->user_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'          => 'artpulse_event',
            'title'              => 'Sample Event',
            'content'            => 'Event description',
            'event_date'         => '2025-06-30',
            'event_location'     => 'Virtual',
            'event_organization' => 999,
            'artist_id'          => 123,
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertEquals('Sample Event', $data['title']);
        $this->assertEquals('artpulse_event', $data['type']);

        $post = get_post($data['id']);
        $this->assertStringContainsString('Event description', $post->post_content);

        $meta_date = get_post_meta($data['id'], '_ap_event_date', true);
        $this->assertEquals('2025-06-30', $meta_date);

        $meta_org = (int) get_post_meta($data['id'], '_ap_event_organization', true);
        $this->assertEquals($org_id, $meta_org);

        $this->assertEquals($org_id, (int) get_post_meta($data['id'], '_ap_org_id', true));
        $this->assertEquals($artist_id, (int) get_post_meta($data['id'], '_ap_artist_id', true));
        $this->assertEquals('pending', get_post_meta($data['id'], '_ap_moderation_state', true));

    }

    public function test_artwork_submission_saves_meta()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'           => 'artpulse_artwork',
            'title'               => 'Evening Sculpture',
            'content'             => 'Stone sculpture description',
            'artwork_medium'      => 'Stone',
            'artwork_dimensions'  => '12x12',
            'artwork_materials'   => 'Granite',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertEquals('artpulse_artwork', $data['type']);

        $this->assertEquals('Stone', get_post_meta($data['id'], '_ap_artwork_medium', true));
        $this->assertEquals('12x12', get_post_meta($data['id'], '_ap_artwork_dimensions', true));
        $this->assertEquals('Granite', get_post_meta($data['id'], '_ap_artwork_materials', true));
    }

    public function test_organization_submission_saves_contact_details()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'    => 'artpulse_org',
            'title'        => 'ArtPulse Collective',
            'content'      => 'Community arts organization.',
            'org_website'  => 'https://example.org',
            'org_email'    => 'info@example.org',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertEquals('artpulse_org', $data['type']);

        $this->assertEquals('https://example.org', get_post_meta($data['id'], '_ap_org_website', true));
        $this->assertEquals('info@example.org', get_post_meta($data['id'], '_ap_org_email', true));
    }

    public function test_invalid_post_type_rejected()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type' => 'invalid_type',
            'title'     => 'Ignored'
        ]);

        $response = rest_do_request($request);
        $this->assertSame(400, $response->get_status());
    }

    public function test_duplicate_event_submission_returns_conflict(): void
    {
        $artist_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'publish',
            'post_author' => $this->user_id,
        ]);
        update_post_meta($artist_id, '_ap_owner_user', $this->user_id);

        $payload = [
            'post_type'          => 'artpulse_event',
            'title'              => 'Duplicate Aware Event',
            'content'            => 'First run',
            'event_date'         => '2025-09-01',
            'event_location'     => 'Downtown',
            'artist_id'          => $artist_id,
        ];

        $first = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $first->set_body_params($payload);
        $first_response = rest_do_request($first);

        $this->assertSame(200, $first_response->get_status());

        $second = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $second->set_body_params($payload);
        $second_response = rest_do_request($second);

        $this->assertSame(409, $second_response->get_status());

        $error = $second_response->get_data();
        $this->assertSame('duplicate_event', $error['code']);
        $this->assertSame(409, $error['data']['status']);
        $this->assertSame(MINUTE_IN_SECONDS, $error['data']['details']['retry_after']);
    }

    public function test_event_submission_allowed_with_artist_only(): void
    {
        $artist_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'publish',
            'post_author' => $this->user_id,
        ]);
        update_post_meta($artist_id, '_ap_owner_user', $this->user_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'      => 'artpulse_event',
            'title'          => 'Artist Hosted Event',
            'content'        => 'Showcase evening',
            'event_date'     => '2025-08-15',
            'event_location' => 'Studio Loft',
            'artist_id'      => $artist_id,
        ]);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertSame($artist_id, (int) get_post_meta($data['id'], '_ap_artist_id', true));
        $this->assertSame(0, (int) get_post_meta($data['id'], '_ap_org_id', true));
    }

    public function test_event_submission_blocked_without_profile(): void
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'      => 'artpulse_event',
            'title'          => 'Missing Profile Event',
            'content'        => 'Details go here',
            'event_date'     => '2025-07-20',
            'event_location' => 'Main Hall',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(403, $response->get_status());

        $error = $response->get_data();
        $this->assertSame('rest_forbidden', $error['code']);
        $this->assertStringContainsString('Publish at least one organization or artist profile', $error['message']);
    }
}
