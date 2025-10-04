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

    public function test_event_submission_widget_uses_admin_url_for_privileged_user(): void
    {
        global $wp_meta_boxes;

        $admin_id = $this->factory->user->create([
            'role'       => 'administrator',
            'user_login' => 'admin_user',
        ]);

        wp_set_current_user($admin_id);

        $wp_meta_boxes = [];
        do_action('wp_dashboard_setup');

        $this->assertArrayHasKey('dashboard', $wp_meta_boxes);
        $this->assertArrayHasKey('normal', $wp_meta_boxes['dashboard']);
        $this->assertArrayHasKey('core', $wp_meta_boxes['dashboard']['normal']);
        $this->assertArrayHasKey('artpulse_event_submission', $wp_meta_boxes['dashboard']['normal']['core']);
        $widget = $wp_meta_boxes['dashboard']['normal']['core']['artpulse_event_submission'];
        $this->assertSame([RoleDashboards::class, 'renderEventSubmissionWidget'], $widget['callback']);

        ob_start();
        RoleDashboards::renderEventSubmissionWidget();
        $output = (string) ob_get_clean();

        $expected_url = esc_url(admin_url('post-new.php?post_type=artpulse_event'));
        $this->assertStringContainsString($expected_url, $output);
    }

    public function test_event_submission_widget_uses_frontend_url_when_admin_not_available(): void
    {
        global $wp_meta_boxes;

        $page_id = $this->factory->post->create([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => '[ap_submission_form post_type="artpulse_event"]',
        ]);

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
        $this->assertSame([RoleDashboards::class, 'renderEventSubmissionWidget'], $widget['callback']);

        ob_start();
        RoleDashboards::renderEventSubmissionWidget();
        $output = (string) ob_get_clean();

        $expected_url = esc_url(get_permalink($page_id));
        $this->assertStringContainsString($expected_url, $output);
    }
}
