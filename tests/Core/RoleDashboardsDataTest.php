<?php

namespace Tests\Core;

use ArtPulse\Core\RoleDashboards;
use ArtPulse\Core\RoleSetup;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\ArtistRequestStatusRoute;

class RoleDashboardsDataTest extends \WP_UnitTestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        RoleSetup::install();
        RoleDashboards::register();
        $this->createRelationshipTables();
    }

    protected function tear_down(): void
    {
        parent::tear_down();

        wp_set_current_user(0);
    }

    public function test_member_dashboard_with_engagement_and_locked_quick_actions(): void
    {
        $member_id = $this->factory->user->create([
            'role'       => 'member',
            'user_login' => 'member_user',
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => 'member@example.com',
        ]);

        wp_set_current_user($member_id);
        update_user_meta($member_id, 'ap_membership_level', 'basic');

        $event_id = $this->factory->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Gallery Opening',
        ]);

        $artist_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'publish',
            'post_title'  => 'Featured Artist',
        ]);

        $this->seedFavorite($member_id, $event_id, 'artpulse_event');
        $this->seedFollow($member_id, $artist_id, 'artpulse_artist');

        $data = RoleDashboards::prepareDashboardData('member', $member_id);

        $this->assertSame('member', $data['role']);
        $this->assertArrayHasKey('favorites', $data);
        $this->assertArrayHasKey('follows', $data);

        $this->assertNotEmpty($data['favorites']);
        $favorite = $data['favorites'][0];
        $this->assertSame($event_id, $favorite['id']);
        $this->assertSame('artpulse_event', $favorite['object_type']);
        $this->assertArrayHasKey('favorited_on', $favorite);
        $this->assertNotEmpty($favorite['favorited_on']);

        $this->assertNotEmpty($data['follows']);
        $follow = $data['follows'][0];
        $this->assertSame($artist_id, $follow['id']);
        $this->assertSame('artpulse_artist', $follow['object_type']);
        $this->assertTrue($follow['following']);

        $submit_event = $this->findQuickAction($data['quick_actions'], 'submit_event');
        $this->assertNotNull($submit_event);
        $this->assertSame('locked', $submit_event['status']);
        $this->assertTrue($submit_event['cta']['disabled']);
        $this->assertStringContainsString('Publish your profile', $submit_event['disabled_reason']);

        $journey_artist = $this->findQuickAction($data['quick_actions'], 'journey_artist');
        $this->assertNotNull($journey_artist);
        $this->assertSame('locked', $journey_artist['status']);

        $this->assertArrayHasKey('metrics', $data);
        $this->assertSame(1, $data['metrics']['favorites']);
        $this->assertSame(1, $data['metrics']['follows']);
    }

    public function test_artist_dashboard_with_published_portfolio_unlocks_quick_actions(): void
    {
        $artist_id = $this->factory->user->create([
            'role'       => 'artist',
            'user_login' => 'artist_user',
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => 'artist@example.com',
        ]);

        wp_set_current_user($artist_id);

        $portfolio_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'publish',
            'post_author' => $artist_id,
            'post_title'  => 'Artist Portfolio',
        ]);

        $draft_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artwork',
            'post_status' => 'draft',
            'post_author' => $artist_id,
            'post_title'  => 'Work in Progress',
        ]);

        $data = RoleDashboards::prepareDashboardData('artist', $artist_id);

        $this->assertSame('artist', $data['role']);
        $this->assertArrayHasKey('journeys', $data);
        $this->assertArrayHasKey('artist', $data['journeys']);

        $artist_journey = $data['journeys']['artist'];
        $this->assertSame('published', $artist_journey['status']);
        $this->assertSame('Published', $artist_journey['badge']['label']);
        $this->assertSame($portfolio_id, $artist_journey['portfolio']['post_id']);

        $journey_action = $this->findQuickAction($data['quick_actions'], 'journey_artist');
        $this->assertNotNull($journey_action);
        $this->assertSame('published', $journey_action['status']);
        $this->assertSame(100, $journey_action['progress_percent']);
        $this->assertFalse($journey_action['cta']['disabled']);

        $view_profile = $this->findQuickAction($data['quick_actions'], 'view_profile');
        $this->assertNotNull($view_profile);
        $this->assertSame('ready', $view_profile['status']);
        $this->assertFalse($view_profile['cta']['disabled']);
        $this->assertSame('Live', $view_profile['badge']['label']);

        $submit_event = $this->findQuickAction($data['quick_actions'], 'submit_event');
        $this->assertNotNull($submit_event);
        $this->assertSame('ready', $submit_event['status']);
        $this->assertFalse($submit_event['cta']['disabled']);

        $this->assertArrayHasKey('submissions', $data);
        $this->assertArrayHasKey('artpulse_artwork', $data['submissions']);
        $this->assertArrayHasKey('counts', $data['submissions']['artpulse_artwork']);
        $this->assertSame(1, $data['submissions']['artpulse_artwork']['counts']['draft']);

        $artwork_items = $data['submissions']['artpulse_artwork']['items'];
        $this->assertContains($draft_id, array_column($artwork_items, 'id'));
    }

    public function test_organization_dashboard_with_draft_portfolio_keeps_actions_locked(): void
    {
        $org_user_id = $this->factory->user->create([
            'role'       => 'organization',
            'user_login' => 'org_user',
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => 'org@example.com',
        ]);

        wp_set_current_user($org_user_id);

        $org_post_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'draft',
            'post_author' => $org_user_id,
            'post_title'  => 'Collective Draft',
        ]);

        $data = RoleDashboards::prepareDashboardData('organization', $org_user_id);

        $this->assertSame('organization', $data['role']);
        $this->assertArrayHasKey('journeys', $data);
        $this->assertArrayHasKey('organization', $data['journeys']);

        $journey = $data['journeys']['organization'];
        $this->assertSame('in_progress', $journey['status']);
        $this->assertSame($org_post_id, $journey['portfolio']['post_id']);
        $this->assertSame('Draft', $journey['badge']['label']);

        $journey_action = $this->findQuickAction($data['quick_actions'], 'journey_organization');
        $this->assertNotNull($journey_action);
        $this->assertSame('in_progress', $journey_action['status']);
        $this->assertSame('Continue in builder', $journey_action['cta']['label']);

        $view_profile = $this->findQuickAction($data['quick_actions'], 'view_profile');
        $this->assertNotNull($view_profile);
        $this->assertSame('locked', $view_profile['status']);
        $this->assertTrue($view_profile['cta']['disabled']);

        $submit_event = $this->findQuickAction($data['quick_actions'], 'submit_event');
        $this->assertNotNull($submit_event);
        $this->assertSame('locked', $submit_event['status']);
        $this->assertTrue($submit_event['cta']['disabled']);
    }

    public function test_member_dashboard_surfaces_pending_upgrade_notification(): void
    {
        $member_id = $this->factory->user->create([
            'role'       => 'member',
            'user_login' => 'pending_member',
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => 'pending@example.com',
        ]);

        wp_set_current_user($member_id);
        update_user_meta($member_id, 'ap_membership_level', 'basic');

        $org_post_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'draft',
            'post_author' => $member_id,
            'post_title'  => 'Pending Organization',
        ]);

        $request = UpgradeReviewRepository::upsert_pending($member_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE, $org_post_id);
        $this->assertNotEmpty($request['request_id']);

        $data = RoleDashboards::prepareDashboardData('member', $member_id);

        $this->assertArrayHasKey('notifications', $data);
        $this->assertNotEmpty($data['notifications']);
        $notification = $data['notifications'][0];
        $this->assertSame('info', $notification['type']);
        $this->assertStringContainsString('pending review', $notification['message']);

        $journey = $this->findQuickAction($data['quick_actions'], 'journey_organization');
        $this->assertNotNull($journey);
        $this->assertSame('pending_request', $journey['status']);
        $this->assertSame('Upgrade request pending', $journey['status_label']);
        $this->assertArrayHasKey('cta', $journey);
        $this->assertSame(ArtistRequestStatusRoute::get_status_url('organization'), $journey['cta']['url']);
    }

    public function test_member_dashboard_enables_org_request_form_without_upgrade_link(): void
    {
        $member_id = $this->factory->user->create([
            'role'       => 'member',
            'user_login' => 'org_ready_member',
            'user_pass'  => wp_generate_password(12, false),
            'user_email' => 'org-ready@example.com',
        ]);

        wp_set_current_user($member_id);
        update_user_meta($member_id, 'ap_membership_level', 'org');

        $data = RoleDashboards::prepareDashboardData('member', $member_id);

        $this->assertArrayHasKey('journeys', $data);
        $this->assertArrayHasKey('artist', $data['journeys']);
        $this->assertArrayHasKey('organization', $data['journeys']);

        $artist_journey = $data['journeys']['artist'];
        $this->assertSame('not_started', $artist_journey['status']);
        $this->assertArrayHasKey('cta', $artist_journey);

        $artist_cta = $artist_journey['cta'];
        $this->assertSame('form', $artist_cta['mode']);
        $this->assertFalse($artist_cta['disabled']);
        $this->assertSame('artist', $artist_cta['upgrade_type']);

        $journey = $data['journeys']['organization'];

        $this->assertSame('not_started', $journey['status']);
        $this->assertArrayHasKey('cta', $journey);

        $cta = $journey['cta'];

        $this->assertSame('form', $cta['mode']);
        $this->assertFalse($cta['disabled']);
        $this->assertSame('organization', $cta['upgrade_type']);
    }

    private function createRelationshipTables(): void
    {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $favorites = $wpdb->prefix . 'ap_favorites';
        $follows   = $wpdb->prefix . 'ap_follows';

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$favorites} (
            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) unsigned NOT NULL,
            object_id BIGINT(20) unsigned NOT NULL,
            object_type VARCHAR(64) NOT NULL,
            favorited_on DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charset}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$follows} (
            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) unsigned NOT NULL,
            object_id BIGINT(20) unsigned NOT NULL,
            object_type VARCHAR(64) NOT NULL,
            followed_on DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charset}");

        $wpdb->query("DELETE FROM {$favorites}");
        $wpdb->query("DELETE FROM {$follows}");
    }

    private function seedFavorite(int $user_id, int $object_id, string $object_type): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ap_favorites',
            [
                'user_id'     => $user_id,
                'object_id'   => $object_id,
                'object_type' => $object_type,
                'favorited_on'=> gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    private function seedFollow(int $user_id, int $object_id, string $object_type): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ap_follows',
            [
                'user_id'     => $user_id,
                'object_id'   => $object_id,
                'object_type' => $object_type,
                'followed_on' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    private function findQuickAction(array $actions, string $slug): ?array
    {
        foreach ($actions as $action) {
            if (($action['slug'] ?? '') === $slug) {
                return $action;
            }
        }

        return null;
    }
}
