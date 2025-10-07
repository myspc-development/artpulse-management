<?php

namespace ArtPulse\Tests\Frontend;

use ArtPulse\Core\PostTypeRegistrar;
use ArtPulse\Frontend\EventsCalendar;
use ArtPulse\Rest\EventsController;
use WP_UnitTestCase;

class EventsCalendarTest extends WP_UnitTestCase
{
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jrV8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        PostTypeRegistrar::register();
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
            define('ARTPULSE_PLUGIN_FILE', dirname(__DIR__, 1) . '/../artpulse-management.php');
        }
        add_theme_support('post-thumbnails');
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

    public function test_grid_layout_displays_thumbnail_and_event_meta(): void
    {
        $attachment_id = self::create_attachment_without_large_size();
        $this->assertFalse(wp_get_attachment_image_url($attachment_id, 'large'));
        $expected_image = wp_get_attachment_image_url($attachment_id, 'full');
        $this->assertNotFalse($expected_image);

        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Thumbnail Showcase',
        ]);

        update_post_meta($event_id, '_ap_event_start', '2024-09-15T14:00:00+00:00');
        update_post_meta($event_id, '_ap_event_end', '2024-09-15T16:00:00+00:00');
        set_post_thumbnail($event_id, $attachment_id);

        update_option('date_format', 'F j, Y');
        update_option('time_format', 'g:i a');

        $output = EventsCalendar::render_shortcode([
            'layout'       => 'grid',
            'show_filters' => 'false',
            'per_page'     => 6,
        ]);

        $this->assertStringContainsString('Thumbnail Showcase', $output);
        $this->assertStringContainsString($expected_image, $output);
        $this->assertMatchesRegularExpression('/<img[^>]+alt="Thumbnail Showcase"/i', $output);

        $expected_date = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime('2024-09-15T14:00:00+00:00'));
        $this->assertStringContainsString($expected_date, $output);
    }

    private static function create_attachment_without_large_size(string $filename = 'no-large.png'): int
    {
        $data = base64_decode(self::TINY_PNG_BASE64, true);
        if (false === $data) {
            throw new \RuntimeException('Failed to decode base64 image fixture.');
        }

        $upload = wp_upload_bits($filename, null, $data);
        if (!empty($upload['error'])) {
            throw new \RuntimeException('Failed to write attachment fixture: ' . $upload['error']);
        }

        $file     = $upload['file'];
        $filetype = wp_check_filetype($filename, null);

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $filetype['type'] ?? 'image/png',
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $file);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        add_filter('intermediate_image_sizes_advanced', [self::class, 'filter_remove_large_sizes']);
        $metadata = wp_generate_attachment_metadata($attachment_id, $file);
        remove_filter('intermediate_image_sizes_advanced', [self::class, 'filter_remove_large_sizes']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return $attachment_id;
    }

    public static function filter_remove_large_sizes($sizes)
    {
        unset($sizes['large'], $sizes['medium_large']);

        return $sizes;
    }
}
