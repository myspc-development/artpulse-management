<?php

namespace ArtPulse\Tests\Integration;

use ArtPulse\Core\PostTypeRegistrar;
use ArtPulse\Integration\Recurrence\RecurrenceExpander;
use ArtPulse\Rest\EventsController;
use WP_UnitTestCase;

class RecurrenceTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PostTypeRegistrar::register();
    }

    public function test_weekly_recurrence_handles_dst_transition(): void
    {
        $event_id = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Weekly Meetup',
        ]);

        update_post_meta($event_id, '_ap_event_start', '2024-03-03T10:00:00-05:00');
        update_post_meta($event_id, '_ap_event_end', '2024-03-03T12:00:00-05:00');
        update_post_meta($event_id, '_ap_event_timezone', 'America/New_York');
        update_post_meta($event_id, '_ap_event_recurrence', 'RRULE:FREQ=WEEKLY;COUNT=3');

        $results = EventsController::fetch_events([
            'start' => '2024-03-01T00:00:00+00:00',
            'end'   => '2024-03-20T23:59:59+00:00',
            'per_page' => 50,
        ]);

        $this->assertCount(3, $results['events']);
        $starts = array_map(static fn($event) => $event['start'], $results['events']);
        $this->assertSame(['2024-03-03T15:00:00+00:00', '2024-03-10T14:00:00+00:00', '2024-03-17T14:00:00+00:00'], $starts);
    }

    public function test_occurrence_includes_events_overlapping_range_start(): void
    {
        $event_id = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Late Night Show',
        ]);

        update_post_meta($event_id, '_ap_event_start', '2024-04-01T23:30:00+00:00');
        update_post_meta($event_id, '_ap_event_end', '2024-04-02T01:00:00+00:00');

        $results = EventsController::fetch_events([
            'start' => '2024-04-02T00:00:00+00:00',
            'end'   => '2024-04-02T23:59:59+00:00',
        ]);

        $ids = wp_list_pluck($results['events'], 'id');
        $this->assertSame([$event_id], $ids);
    }

    public function test_mixed_rrule_and_rdate_without_duplicates(): void
    {
        $event_id = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Hybrid Schedule',
        ]);

        update_post_meta($event_id, '_ap_event_start', '2024-09-01T10:00:00+00:00');
        update_post_meta($event_id, '_ap_event_end', '2024-09-01T11:00:00+00:00');
        update_post_meta($event_id, '_ap_event_recurrence', "RRULE:FREQ=DAILY;COUNT=2\nRDATE:2024-09-02T10:00:00+00:00,2024-09-02T10:00:00+00:00");

        $results = EventsController::fetch_events([
            'start' => '2024-09-01',
            'end'   => '2024-09-05',
            'per_page' => 10,
        ]);

        $this->assertCount(2, $results['events']);
        $starts = array_column($results['events'], 'start');
        $this->assertSame(['2024-09-01T10:00:00+00:00', '2024-09-02T10:00:00+00:00'], $starts);
    }

    public function test_truncation_flag_when_instance_limit_exceeded(): void
    {
        $event_id = self::factory()->post->create([
            'post_type'   => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => 'Daily Marathon',
        ]);

        update_post_meta($event_id, '_ap_event_start', '2024-01-01T08:00:00+00:00');
        update_post_meta($event_id, '_ap_event_end', '2024-01-01T09:00:00+00:00');
        update_post_meta($event_id, '_ap_event_recurrence', 'RRULE:FREQ=DAILY;COUNT=1500');

        $results = EventsController::fetch_events([
            'start'    => '2024-01-01',
            'end'      => '2024-12-31',
            'per_page' => 2000,
        ], false);

        $this->assertTrue($results['truncated']);
        $this->assertCount(RecurrenceExpander::MAX_INSTANCES, $results['events']);
    }
}
