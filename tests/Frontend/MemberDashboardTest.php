<?php

use ArtPulse\Core\UpgradeReviewRepository;
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

    public function test_pending_artist_journey_displays_draft_profile_link(): void
    {
        $registered = [];

        if (!post_type_exists('artpulse_artist')) {
            register_post_type('artpulse_artist', [
                'label'  => 'Artist',
                'public' => true,
                'rewrite' => false,
            ]);
            $registered[] = 'artpulse_artist';
        }

        if (!post_type_exists(UpgradeReviewRepository::POST_TYPE)) {
            register_post_type(UpgradeReviewRepository::POST_TYPE, [
                'label'  => 'Review Request',
                'public' => false,
                'rewrite' => false,
            ]);
            $registered[] = UpgradeReviewRepository::POST_TYPE;
        }

        try {
            $user_id = self::factory()->user->create([
                'role' => 'subscriber',
            ]);

            wp_set_current_user($user_id);

            $artist_post_id = self::factory()->post->create([
                'post_author' => $user_id,
                'post_type'   => 'artpulse_artist',
                'post_status' => 'draft',
            ]);

            UpgradeReviewRepository::upsert_pending(
                $user_id,
                UpgradeReviewRepository::TYPE_ARTIST_UPGRADE,
                $artist_post_id
            );

            $artist_journey = [
                'slug'             => 'artist',
                'anchor'           => '#ap-journey-artist',
                'status_label'     => 'Start your artist journey',
                'progress_percent' => 0,
                'links'            => [
                    'upgrade' => home_url('/artist-upgrade/'),
                    'builder' => home_url('/artist-builder/'),
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

            $artist_state = $filtered['org_upgrade']['artist'];

            $this->assertSame('requested', $artist_state['status']);
            $this->assertSame(
                esc_url_raw(get_permalink($artist_post_id)),
                $artist_state['profile_url']
            );

            $org_upgrade = $filtered['org_upgrade'];

            ob_start();
            require dirname(__DIR__, 2) . '/templates/dashboard/partials/member-org-upgrade.php';
            $html = ob_get_clean();

            $this->assertStringContainsString('Preview your draft artist profile', $html);
            $this->assertStringContainsString(esc_url(get_permalink($artist_post_id)), $html);
        } finally {
            wp_set_current_user(0);

            foreach ($registered as $post_type) {
                unregister_post_type($post_type);
            }
        }
    }

    public function test_upgrade_widget_outputs_accessible_status_and_labels(): void
    {
        $section_title    = 'Upgrade options';
        $section_intro    = 'Choose how you would like to upgrade.';
        $section_upgrades = [
            [
                'url'         => home_url('/artist-upgrade/'),
                'cta'         => 'View details',
                'slug'        => 'artist',
                'role_label'  => 'Artist',
                'status'      => 'denied',
                'denial_reason' => 'Please add more samples to your portfolio.',
                'review_id'   => 123,
            ],
        ];

        ob_start();
        require dirname(__DIR__, 2) . '/templates/dashboard/partials/upgrade-section.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('aria-atomic="true"', $html);
        $this->assertStringContainsString('data-ap-upgrade-status="denied"', $html);
        $this->assertStringContainsString('data-ap-upgrade-review="123"', $html);
        $this->assertStringContainsString('data-ap-upgrade-reopen', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertMatchesRegularExpression('/aria-label="[^"]*Artist[^"]*"/', $html);
    }
}
