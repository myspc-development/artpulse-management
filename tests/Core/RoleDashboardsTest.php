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

    public function test_prepare_dashboard_data_includes_available_roles(): void
    {
        $user_id = $this->factory->user->create([
            'role'       => 'member',
            'user_login' => 'multi_role_user',
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => 'multi-role@example.com',
        ]);

        $user = get_user_by('id', $user_id);
        $this->assertInstanceOf(\WP_User::class, $user);
        $user->add_role('artist');

        wp_set_current_user($user_id);

        $data = RoleDashboards::prepareDashboardData('artist', $user_id);

        $this->assertSame('artist', $data['role']);
        $this->assertArrayHasKey('available_roles', $data);
        $this->assertNotEmpty($data['available_roles']);

        $slugs = array_map(
            static fn($entry) => $entry['role'] ?? null,
            $data['available_roles']
        );

        $this->assertContains('member', $slugs);
        $this->assertContains('artist', $slugs);

        $current_roles = array_values(array_filter(
            $data['available_roles'],
            static fn($entry) => !empty($entry['current'])
        ));

        $this->assertNotEmpty($current_roles);
        $this->assertSame('artist', $current_roles[0]['role']);
        $this->assertIsString($current_roles[0]['url']);
        $this->assertStringContainsString('/dashboard/', $current_roles[0]['url']);
    }
}
