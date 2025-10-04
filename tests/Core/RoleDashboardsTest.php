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

    public function test_event_submission_url_defaults_to_admin_editor_for_privileged_users(): void
    {
        $admin_id = $this->factory->user->create([
            'role'       => 'administrator',
            'user_login' => 'admin_user',
        ]);

        wp_set_current_user($admin_id);

        $expected = admin_url('post-new.php?post_type=artpulse_event');
        $this->assertSame($expected, RoleDashboards::getEventSubmissionUrl());

        ob_start();
        RoleDashboards::renderEventSubmissionWidget();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('href="' . esc_url($expected) . '"', $output);
    }

    public function test_event_submission_url_falls_back_to_frontend_page_for_artists(): void
    {
        $page_id = $this->factory->post->create([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => '[ap_submit_event]',
            'post_title'   => 'Submit Event',
        ]);

        $artist_id = $this->factory->user->create([
            'role'       => 'artist',
            'user_login' => 'artist_frontend',
        ]);

        wp_set_current_user($artist_id);

        $expected = get_permalink($page_id);

        $this->assertSame($expected, RoleDashboards::getEventSubmissionUrl());

        ob_start();
        RoleDashboards::renderEventSubmissionWidget();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('href="' . esc_url($expected) . '"', $output);
    }
}
