<?php

namespace ArtPulse\Tests\Rest;

use ArtPulse\Community\FavoritesManager;
use ArtPulse\Core\PostTypeRegistrar;
use ArtPulse\Rest\EventsController;
use DateTimeInterface;
use WP_REST_Request;
use WP_UnitTestCase;

class EventsControllerTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PostTypeRegistrar::register();
        FavoritesManager::install_favorites_table();
        EventsController::purge_cache();
    }

    public function test_fetch_events_respects_date_range(): void
    {
        $event_in_range = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Gallery Opening',
        ]);

        update_post_meta($event_in_range, '_ap_event_start', '2024-09-10T18:00:00+00:00');
        update_post_meta($event_in_range, '_ap_event_end', '2024-09-10T20:00:00+00:00');

        $event_out_of_range = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Future Expo',
        ]);

        update_post_meta($event_out_of_range, '_ap_event_start', '2024-12-01T12:00:00+00:00');
        update_post_meta($event_out_of_range, '_ap_event_end', '2024-12-01T15:00:00+00:00');

        $results = EventsController::fetch_events([
            'start' => '2024-09-01T00:00:00+00:00',
            'end'   => '2024-09-30T23:59:59+00:00',
        ]);

        $ids = wp_list_pluck($results['events'], 'id');
        $this->assertContains($event_in_range, $ids);
        $this->assertNotContains($event_out_of_range, $ids);
    }

    public function test_favorites_filter_returns_only_favorited_events(): void
    {
        $user_id = self::factory()->user->create();
        wp_set_current_user($user_id);

        $fav_event = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Collectors Night',
        ]);

        update_post_meta($fav_event, '_ap_event_start', '2024-10-05T18:00:00+00:00');
        update_post_meta($fav_event, '_ap_event_end', '2024-10-05T20:00:00+00:00');

        FavoritesManager::add_favorite($user_id, $fav_event, PostTypeRegistrar::EVENT_POST_TYPE);

        $results = EventsController::fetch_events([
            'start'     => '2024-10-01T00:00:00+00:00',
            'end'       => '2024-10-31T23:59:59+00:00',
            'favorites' => true,
        ]);

        $ids = wp_list_pluck($results['events'], 'id');
        $this->assertSame([$fav_event], $ids);
    }

    public function test_unknown_taxonomy_is_ignored(): void
    {
        $term = self::factory()->term->create(['taxonomy' => PostTypeRegistrar::EVENT_TAXONOMY, 'slug' => 'opening']);

        $event = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Art Opening',
        ]);

        wp_set_object_terms($event, [$term], PostTypeRegistrar::EVENT_TAXONOMY);
        update_post_meta($event, '_ap_event_start', '2024-11-10T18:00:00+00:00');
        update_post_meta($event, '_ap_event_end', '2024-11-10T20:00:00+00:00');

        $request = new WP_REST_Request('GET', '/artpulse/v1/events');
        $request->set_param('start', '2024-11-01');
        $request->set_param('end', '2024-11-30');
        $request->set_param('taxonomy', [
            PostTypeRegistrar::EVENT_TAXONOMY => ['opening'],
            'fake_taxonomy'                  => ['ignored'],
        ]);

        $response = EventsController::get_events($request);
        $data     = $response->get_data();

        $this->assertCount(1, $data['events']);
        $this->assertSame($event, $data['events'][0]['id']);
    }

    public function test_per_page_is_clamped_to_maximum(): void
    {
        $start = new \DateTimeImmutable('2024-08-01T12:00:00+00:00');

        for ($i = 0; $i < 120; $i++) {
            $post_id = self::factory()->post->create([
                'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => 'Event ' . $i,
            ]);

            $current = $start->add(new \DateInterval('P' . $i . 'D'));
            update_post_meta($post_id, '_ap_event_start', $current->format(DateTimeInterface::ATOM));
            update_post_meta($post_id, '_ap_event_end', $current->modify('+2 hours')->format(DateTimeInterface::ATOM));
        }

        $results = EventsController::fetch_events([
            'start'    => '2024-08-01',
            'end'      => '2024-12-31',
            'per_page' => 500,
        ]);

        $this->assertSame(100, $results['pagination']['per_page']);
        $this->assertCount(100, $results['events']);
    }

    public function test_orderby_falls_back_to_event_start(): void
    {
        $early = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Early Event',
        ]);
        $late = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Late Event',
        ]);

        update_post_meta($early, '_ap_event_start', '2024-07-01T10:00:00+00:00');
        update_post_meta($early, '_ap_event_end', '2024-07-01T12:00:00+00:00');
        update_post_meta($late, '_ap_event_start', '2024-07-10T10:00:00+00:00');
        update_post_meta($late, '_ap_event_end', '2024-07-10T12:00:00+00:00');

        $results = EventsController::fetch_events([
            'start'   => '2024-07-01',
            'end'     => '2024-07-31',
            'orderby' => 'unsupported',
            'order'   => 'ASC',
        ]);

        $this->assertSame($early, $results['events'][0]['id']);
    }

    public function test_etag_header_is_consistent_across_requests(): void
    {
        $event = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Caching Test',
        ]);

        update_post_meta($event, '_ap_event_start', '2024-06-05T18:00:00+00:00');
        update_post_meta($event, '_ap_event_end', '2024-06-05T20:00:00+00:00');

        $request = new WP_REST_Request('GET', '/artpulse/v1/events');
        $request->set_param('start', '2024-06-01');
        $request->set_param('end', '2024-06-30');

        $responseOne = EventsController::get_events($request);
        $etag        = $responseOne->get_headers()['ETag'] ?? '';
        $this->assertNotEmpty($etag);

        $responseTwo = EventsController::get_events($request);
        $this->assertSame($etag, $responseTwo->get_headers()['ETag'] ?? '');
        $this->assertSame($responseOne->get_data(), $responseTwo->get_data());
    }

    public function test_if_none_match_returns_304(): void
    {
        $event = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Cached Response',
        ]);

        update_post_meta($event, '_ap_event_start', '2024-05-10T18:00:00+00:00');
        update_post_meta($event, '_ap_event_end', '2024-05-10T20:00:00+00:00');

        $request = new WP_REST_Request('GET', '/artpulse/v1/events');
        $request->set_param('start', '2024-05-01');
        $request->set_param('end', '2024-05-31');

        $initial   = EventsController::get_events($request);
        $etag      = $initial->get_headers()['ETag'] ?? '';

        $request->set_header('If-None-Match', $etag);
        $cachedResponse = EventsController::get_events($request);

        $this->assertSame(304, $cachedResponse->get_status());
        $this->assertNull($cachedResponse->get_data());
    }

    public function test_ics_endpoint_honors_etag_headers(): void
    {
        $event = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Calendar Export',
        ]);

        update_post_meta($event, '_ap_event_start', '2024-08-15T18:00:00+00:00');
        update_post_meta($event, '_ap_event_end', '2024-08-15T20:00:00+00:00');

        $request = new WP_REST_Request('GET', '/artpulse/v1/events.ics');
        $request->set_param('start', '2024-08-01');
        $request->set_param('end', '2024-08-31');

        $response = EventsController::get_ics($request);
        $etag     = $response->get_headers()['ETag'] ?? '';
        $this->assertNotEmpty($etag);

        $request->set_header('If-None-Match', $etag);
        $notModified = EventsController::get_ics($request);

        $this->assertSame(304, $notModified->get_status());
        $this->assertNull($notModified->get_data());
    }

    public function test_generate_ics_contains_event_summary(): void
    {
        $event = [
            'id'       => 42,
            'title'    => 'Sunset Session',
            'start'    => '2024-09-15T18:00:00+00:00',
            'end'      => '2024-09-15T20:00:00+00:00',
            'allDay'   => false,
            'location' => 'ArtPulse HQ',
            'url'      => 'https://example.com/events/sunset-session',
        ];

        $ics = EventsController::generate_ics([$event]);
        $this->assertStringContainsString('SUMMARY:Sunset Session', $ics);
        $this->assertStringContainsString('LOCATION:ArtPulse HQ', $ics);
    }
}
