<?php

namespace ArtPulse\Tests\Integration;

use ArtPulse\Core\PostTypeRegistrar;
use ArtPulse\Rest\EventsController;
use WP_UnitTestCase;

class RecurrenceTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PostTypeRegistrar::register();
    }

    public function test_weekly_recurrence_generates_multiple_occurrences(): void
    {
        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Weekly Meetup',
        ]);

        update_post_meta($event_id, '_ap_event_start', '2024-09-01T10:00:00+00:00');
        update_post_meta($event_id, '_ap_event_end', '2024-09-01T12:00:00+00:00');
        update_post_meta($event_id, '_ap_event_recurrence', 'RRULE:FREQ=WEEKLY;COUNT=4');

        $results = EventsController::fetch_events([
            'start' => '2024-09-01T00:00:00+00:00',
            'end'   => '2024-09-30T23:59:59+00:00',
        ]);

        $this->assertCount(4, $results['events']);
        $starts = array_map(static fn($event) => $event['start'], $results['events']);
        $this->assertSame('2024-09-01T10:00:00+00:00', $starts[0]);
    }
}
