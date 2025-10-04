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
        remove_all_filters('artpulse_event_submission_url');
        parent::tear_down();
    }

    public function test_event_submission_widget_is_registered_for_artist_dashboard(): void
    {
        global $wp_meta_boxes;

        $submission_url = 'https://example.test/events/new';
        add_filter('artpulse_event_submission_url', static fn () => $submission_url);

        $artist_id = $this->factory->user->create([
            'role'       => 'artist',
            'user_login' => 'artist_user',
        ]);

        wp_set_current_user($artist_id);

        $wp_meta_boxes = [];
        do_action('wp_dashboard_setup');

        $this->assertArrayHasKey('dashboard', $wp_meta_boxes);
        $this->assertArrayHasKey('normal', $wp_meta_boxes['dashboard']);
        $this->assertArrayHasKey('core', $wp_meta_boxes['dashboard']['normal']);
        $this->assertArrayHasKey('artpulse_event_submission', $wp_meta_boxes['dashboard']['normal']['core']);
        $widget = $wp_meta_boxes['dashboard']['normal']['core']['artpulse_event_submission'];
        $this->assertIsArray($widget);
        $this->assertSame([RoleDashboards::class, 'renderEventSubmissionWidget'], $widget['callback']);

        $filtered_url = apply_filters('artpulse_event_submission_url', '');
        $this->assertSame($submission_url, $filtered_url);
    }
}
