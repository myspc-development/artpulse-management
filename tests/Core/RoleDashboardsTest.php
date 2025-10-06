<?php

namespace Tests\Core;

use ArtPulse\Core\RoleDashboards;
use ArtPulse\Core\RoleSetup;

class RoleDashboardsTest extends \WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        RoleSetup::install();
        RoleDashboards::register();
    }

    public function tear_down(): void
    {
        parent::tear_down();

        wp_set_current_user(0);
    }

    public function test_event_submission_widget_registers_for_creators(): void
    {
        global $wp_meta_boxes;

        $wp_meta_boxes = [];

        $submission_page = self::factory()->post->create([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => '[ap_submit_event]',
        ]);

        $artist_id = $this->factory->user->create([
            'role'       => 'artist',
            'user_login' => 'artist_user',
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => 'artist@example.com',
        ]);

        wp_set_current_user($artist_id);

        do_action('wp_dashboard_setup');

        $this->assertArrayHasKey('dashboard', $wp_meta_boxes);
        $this->assertArrayHasKey('normal', $wp_meta_boxes['dashboard']);
        $this->assertArrayHasKey('core', $wp_meta_boxes['dashboard']['normal']);
        $this->assertArrayHasKey('artpulse_event_submission', $wp_meta_boxes['dashboard']['normal']['core']);

        $widget = $wp_meta_boxes['dashboard']['normal']['core']['artpulse_event_submission'];

        $this->assertIsArray($widget);
        $this->assertArrayHasKey('callback', $widget);
        $this->assertIsCallable($widget['callback']);

        ob_start();
        call_user_func($widget['callback']);
        $output = (string) ob_get_clean();

        $expected_url = esc_url(get_permalink($submission_page));

        $this->assertStringContainsString($expected_url, $output);
    }

    public function test_event_submission_widget_shows_fallback_when_no_page(): void
    {
        global $wp_meta_boxes;

        $wp_meta_boxes = [];

        $artist_id = $this->factory->user->create([
            'role'       => 'artist',
            'user_login' => 'artist_no_page',
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => 'artist-no-page@example.com',
        ]);

        wp_set_current_user($artist_id);

        do_action('wp_dashboard_setup');

        $this->assertArrayHasKey('artpulse_event_submission', $wp_meta_boxes['dashboard']['normal']['core']);

        $widget = $wp_meta_boxes['dashboard']['normal']['core']['artpulse_event_submission'];

        ob_start();
        call_user_func($widget['callback']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Event submissions are currently unavailable.', $output);
    }
}
