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
        $this->assertSame('', get_post_meta($data['id'], '_ap_moderation_reason', true));
        $changed_at = (int) get_post_meta($data['id'], '_ap_moderation_changed_at', true);
        $this->assertGreaterThan(0, $changed_at);
    }

    public function test_duplicate_event_submission_returns_conflict(): void
    {
        $org_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_author' => $this->user_id,
        ]);
        update_post_meta($org_id, '_ap_owner_user', $this->user_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $payload = [
            'post_type'          => 'artpulse_event',
            'title'              => 'Duplicate Check',
            'content'            => 'First submission',
            'event_date'         => '2025-07-01',
            'event_location'     => 'Studio',
            'event_organization' => $org_id,
        ];
        $request->set_body_params($payload);

        $first  = rest_do_request($request);
        $this->assertSame(200, $first->get_status());

        $second_request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $second_request->set_body_params($payload);
        $second = rest_do_request($second_request);

        $this->assertSame(409, $second->get_status());
        $data = $second->get_data();
        $this->assertSame('duplicate_event', $data['code']);
        $this->assertArrayHasKey('details', $data['data']);
        $this->assertArrayHasKey('retry_after', $data['data']['details']);
        $this->assertGreaterThanOrEqual(60, (int) $data['data']['details']['retry_after']);
    }

    public function test_moderation_lifecycle_updates_state_and_reason(): void
    {
        $org_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_author' => $this->user_id,
        ]);
        update_post_meta($org_id, '_ap_owner_user', $this->user_id);

        $base_request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $base_request->set_body_params([
            'post_type'          => 'artpulse_event',
            'title'              => 'Lifecycle Event',
            'content'            => 'Pending event body',
            'event_date'         => '2025-08-01',
            'event_location'     => 'Gallery',
            'event_organization' => $org_id,
        ]);

        $response = rest_do_request(clone $base_request);
        $this->assertSame(200, $response->get_status());
        $event_id = (int) $response->get_data()['id'];

        $initial_changed = (int) get_post_meta($event_id, '_ap_moderation_changed_at', true);
        $this->assertGreaterThan(0, $initial_changed);
        $this->assertSame('pending', get_post_meta($event_id, '_ap_moderation_state', true));

        EventApprovals::register();
        $ref       = new \ReflectionClass(EventApprovals::class);
        $instanceP = $ref->getProperty('instance');
        $instanceP->setAccessible(true);
        $instance  = $instanceP->getValue();

        $_REQUEST['reason'] = 'Looks great';
        $approve = $ref->getMethod('approve_events');
        $approve->setAccessible(true);
        $approve->invoke($instance, [ $event_id ]);

        $this->assertSame('approved', get_post_meta($event_id, '_ap_moderation_state', true));
        $this->assertSame('', get_post_meta($event_id, '_ap_moderation_reason', true));
        $approved_changed = (int) get_post_meta($event_id, '_ap_moderation_changed_at', true);
        $this->assertGreaterThan($initial_changed, $approved_changed);

        $deny_request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $deny_request->set_body_params([
            'post_type'          => 'artpulse_event',
            'title'              => 'Denied Event',
            'content'            => 'Denied body',
            'event_date'         => '2025-09-01',
            'event_location'     => 'Auditorium',
            'event_organization' => $org_id,
        ]);

        $deny_response = rest_do_request($deny_request);
        $this->assertSame(200, $deny_response->get_status());
        $deny_event_id = (int) $deny_response->get_data()['id'];
        $this->assertSame('pending', get_post_meta($deny_event_id, '_ap_moderation_state', true));

        $_REQUEST['reason'] = 'Too short <b>description</b>';
        $reject = $ref->getMethod('reject_events');
        $reject->setAccessible(true);
        $reject->invoke($instance, [ $deny_event_id ]);

        $this->assertSame('denied', get_post_meta($deny_event_id, '_ap_moderation_state', true));
        $this->assertSame('Too short description', get_post_meta($deny_event_id, '_ap_moderation_reason', true));

        $changes_request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $changes_request->set_body_params([
            'post_type'          => 'artpulse_event',
            'title'              => 'Needs Changes',
            'content'            => 'Needs update',
            'event_date'         => '2025-10-01',
            'event_location'     => 'Workshop',
            'event_organization' => $org_id,
        ]);

        $changes_response = rest_do_request($changes_request);
        $this->assertSame(200, $changes_response->get_status());
        $changes_event_id = (int) $changes_response->get_data()['id'];

        $record = $ref->getMethod('record_moderation_meta');
        $record->setAccessible(true);
        $record->invoke($instance, $changes_event_id, 'changes_requested', 'Need more details <script>alert(1)</script>');

        $this->assertSame('changes_requested', get_post_meta($changes_event_id, '_ap_moderation_state', true));
        $this->assertSame('Need more details alert(1)', get_post_meta($changes_event_id, '_ap_moderation_reason', true));
        $this->assertGreaterThan(0, (int) get_post_meta($changes_event_id, '_ap_moderation_changed_at', true));

        unset($_REQUEST['reason']);
    }

    public function test_form_rate_limiter_headers_include_reset(): void
    {
        $this->assertNull(FormRateLimiter::enforce($this->user_id, 'rate_header_check', 1, 60));
        $limited = FormRateLimiter::enforce($this->user_id, 'rate_header_check', 1, 60);

        $this->assertInstanceOf(\WP_Error::class, $limited);
        $data    = (array) $limited->get_error_data();
        $headers = $data['headers'] ?? [];

        $this->assertArrayHasKey('Retry-After', $headers);
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
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
}
