<?php

namespace ArtPulse\Tests\Core;

use ArtPulse\Core\Capabilities;
use ArtPulse\Core\UpgradeReviewHandlers;
use ArtPulse\Core\UpgradeReviewRepository;
use WP_Post;
use WP_UnitTestCase;

class UpgradeReviewHandlersTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!post_type_exists('artpulse_artist')) {
            register_post_type('artpulse_artist', [
                'label'  => 'Artists',
                'public' => false,
            ]);
        }

        if (!post_type_exists('artpulse_org')) {
            register_post_type('artpulse_org', [
                'label'  => 'Organizations',
                'public' => false,
            ]);
        }

        add_role('ap_artist', 'Artist', []);
        add_role('ap_org_manager', 'Organization Manager', []);
    }

    protected function tearDown(): void
    {
        remove_role('ap_artist');
        remove_role('ap_org_manager');

        if (post_type_exists('artpulse_artist')) {
            unregister_post_type('artpulse_artist');
        }

        if (post_type_exists('artpulse_org')) {
            unregister_post_type('artpulse_org');
        }

        parent::tearDown();
    }

    public function test_handle_approved_grants_artist_role_and_creates_profile(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $result = UpgradeReviewRepository::upsert_pending($user_id, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE);
        $request_id = $result['request_id'] ?? null;
        $this->assertNotNull($request_id);

        UpgradeReviewHandlers::handle_approved($request_id, $user_id, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE);

        $user = get_user_by('id', $user_id);
        $this->assertNotFalse($user);
        $this->assertContains('ap_artist', $user->roles);
        $this->assertTrue(user_can($user_id, Capabilities::CAP_MANAGE_OWN_ARTIST));

        $profile_id = (int) get_user_meta($user_id, '_ap_artist_post_id', true);
        $this->assertGreaterThan(0, $profile_id);

        $profile = get_post($profile_id);
        $this->assertInstanceOf(WP_Post::class, $profile);
        $this->assertSame('artpulse_artist', $profile->post_type);
        $this->assertSame($user_id, (int) $profile->post_author);
        $this->assertSame($user_id, (int) get_post_meta($profile_id, '_ap_owner_user', true));
        $this->assertSame($profile_id, UpgradeReviewRepository::get_post_id($request_id));
    }

    public function test_get_or_create_profile_post_reuses_existing_profile(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $existing_id = wp_insert_post([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'draft',
            'post_title'  => 'Existing Artist Profile',
            'post_author' => $user_id,
            'meta_input'  => [
                '_ap_owner_user' => $user_id,
            ],
        ]);
        $this->assertIsInt($existing_id);
        $this->assertGreaterThan(0, $existing_id);

        update_user_meta($user_id, '_ap_artist_post_id', $existing_id);

        $resolved_id = UpgradeReviewHandlers::get_or_create_profile_post($user_id, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE);
        $this->assertSame($existing_id, $resolved_id);

        $posts = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ]);
        $this->assertCount(1, $posts);
    }

    public function test_handle_approved_creates_org_profile_and_role(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $result = UpgradeReviewRepository::upsert_pending($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);
        $request_id = $result['request_id'] ?? null;
        $this->assertNotNull($request_id);

        UpgradeReviewHandlers::handle_approved($request_id, $user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);

        $user = get_user_by('id', $user_id);
        $this->assertNotFalse($user);
        $this->assertContains('ap_org_manager', $user->roles);
        $this->assertTrue(user_can($user_id, Capabilities::CAP_MANAGE_OWN_ORG));

        $profile_id = (int) get_user_meta($user_id, '_ap_org_post_id', true);
        $this->assertGreaterThan(0, $profile_id);

        $profile = get_post($profile_id);
        $this->assertInstanceOf(WP_Post::class, $profile);
        $this->assertSame('artpulse_org', $profile->post_type);
        $this->assertSame($user_id, (int) $profile->post_author);
        $this->assertSame($user_id, (int) get_post_meta($profile_id, '_ap_owner_user', true));
        $this->assertSame($profile_id, UpgradeReviewRepository::get_post_id($request_id));
    }
}
