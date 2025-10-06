<?php

namespace ArtPulse\Tests\Frontend;

use ArtPulse\Core\PostTypeRegistrar;
use ArtPulse\Frontend\EventsCalendar;
use ArtPulse\Rest\EventsController;
use WP_UnitTestCase;

class EventsCalendarTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PostTypeRegistrar::register();
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
            define('ARTPULSE_PLUGIN_FILE', dirname(__DIR__, 1) . '/../artpulse-management.php');
        }
    }

    public function test_grid_layout_renders_salient_classes(): void
    {
        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Launch Party',
        ]);

        update_post_meta($event_id, '_ap_event_start', '2024-09-20T18:00:00+00:00');
        update_post_meta($event_id, '_ap_event_end', '2024-09-20T21:00:00+00:00');

        EventsController::fetch_events(['start' => '2024-09-01T00:00:00+00:00', 'end' => '2024-09-30T23:59:59+00:00']);

        $output = EventsCalendar::render_shortcode([
            'layout'       => 'grid',
            'show_filters' => 'false',
            'per_page'     => 6,
        ]);

        $this->assertStringContainsString('nectar-portfolio', $output);
        $this->assertStringContainsString('ap-events-card', $output);
        $this->assertStringContainsString('Launch Party', $output);
    }

    public function test_calendar_layout_outputs_dataset(): void
    {
        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Workshop',
        ]);

        update_post_meta($event_id, '_ap_event_start', '2024-09-05T09:00:00+00:00');
        update_post_meta($event_id, '_ap_event_end', '2024-09-05T11:00:00+00:00');

        $output = EventsCalendar::render_shortcode([
            'layout'       => 'calendar',
            'show_filters' => 'false',
            'per_page'     => 6,
            'view'         => 'timeGridWeek',
        ]);

        $this->assertStringContainsString('data-ap-events="', $output);
        $this->assertStringContainsString('ap-events--calendar', $output);
    }
}
