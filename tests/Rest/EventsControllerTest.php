<?php

namespace ArtPulse\Tests\Rest;

use ArtPulse\Community\FavoritesManager;
use ArtPulse\Core\PostTypeRegistrar;
use ArtPulse\Rest\EventsController;
use WP_UnitTestCase;

class EventsControllerTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PostTypeRegistrar::register();
        FavoritesManager::install_favorites_table();
    }

    public function test_fetch_events_respects_date_range(): void
    {
        $event_in_range = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Gallery Opening',
        ]);

        update_post_meta($event_in_range, '_ap_event_start', '2024-09-10T18:00:00+00:00');
        update_post_meta($event_in_range, '_ap_event_end', '2024-09-10T20:00:00+00:00');

        $event_out_of_range = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
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
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Collectors Night',
        ]);

        update_post_meta($fav_event, '_ap_event_start', '2024-10-05T18:00:00+00:00');
        update_post_meta($fav_event, '_ap_event_end', '2024-10-05T20:00:00+00:00');

        FavoritesManager::add_favorite($user_id, $fav_event, 'artpulse_event');

        $results = EventsController::fetch_events([
            'start'     => '2024-10-01T00:00:00+00:00',
            'end'       => '2024-10-31T23:59:59+00:00',
            'favorites' => true,
        ]);

        $ids = wp_list_pluck($results['events'], 'id');
        $this->assertSame([$fav_event], $ids);
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
