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


        $wp_meta_boxes = [];
        do_action('wp_dashboard_setup');

        $this->assertArrayHasKey('dashboard', $wp_meta_boxes);
        $this->assertArrayHasKey('normal', $wp_meta_boxes['dashboard']);
        $this->assertArrayHasKey('core', $wp_meta_boxes['dashboard']['normal']);
        $this->assertArrayHasKey('artpulse_event_submission', $wp_meta_boxes['dashboard']['normal']['core']);
        $widget = $wp_meta_boxes['dashboard']['normal']['core']['artpulse_event_submission'];

        ]);

        $artist_id = $this->factory->user->create([
            'role'       => 'artist',

        ]);

        wp_set_current_user($artist_id);


    }
}
