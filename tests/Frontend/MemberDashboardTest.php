<?php

use ArtPulse\Frontend\MemberDashboard;
use WP_UnitTestCase;

class MemberDashboardTest extends WP_UnitTestCase
{
    public function test_inject_dashboard_card_includes_artist_and_organization_states(): void
    {
        $user_id = self::factory()->user->create([
            'role' => 'subscriber',
        ]);

        wp_set_current_user($user_id);

        $artist_journey = [
            'slug'             => 'artist',
            'anchor'           => '#ap-journey-artist',
            'status_label'     => 'Start your artist journey',
            'progress_percent' => 0,
            'links'            => [
                'upgrade' => home_url('/artist-upgrade/'),
            ],
            'portfolio'        => [],
        ];

        $organization_journey = [
            'slug'             => 'organization',
            'anchor'           => '#ap-journey-organization',
            'status_label'     => 'Start your organization journey',
            'progress_percent' => 0,
            'links'            => [
                'upgrade' => home_url('/organization-upgrade/'),
            ],
            'portfolio'        => [],
        ];

        $dashboard = [
            'journeys' => [
                'artist'       => $artist_journey,
                'organization' => $organization_journey,
            ],
        ];

        $filtered = MemberDashboard::inject_dashboard_card($dashboard, 'member', $user_id);

        $this->assertArrayHasKey('org_upgrade', $filtered);
        $this->assertArrayHasKey('artist', $filtered['org_upgrade']);
        $this->assertArrayHasKey('organization', $filtered['org_upgrade']);

        $artist_state = $filtered['org_upgrade']['artist'];
        $this->assertSame('artist', $artist_state['journey']['slug']);
        $this->assertSame('form', $artist_state['cta']['mode']);
        $this->assertSame('artist', $artist_state['cta']['upgrade_type']);

        $organization_state = $filtered['org_upgrade']['organization'];
        $this->assertSame('organization', $organization_state['journey']['slug']);
        $this->assertSame('form', $organization_state['cta']['mode']);
        $this->assertSame('organization', $organization_state['cta']['upgrade_type']);

        $this->assertSame($artist_journey, $filtered['journeys']['artist']);
        $this->assertSame($organization_journey, $filtered['journeys']['organization']);
    }
}
