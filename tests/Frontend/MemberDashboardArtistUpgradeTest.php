<?php

namespace ArtPulse\Tests\Frontend;

use ArtPulse\Admin\UpgradeReviewsController;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\MemberDashboard;
use WP_Post;
use WP_UnitTestCase;

class MemberDashboardArtistUpgradeTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!post_type_exists('artpulse_artist')) {
            register_post_type('artpulse_artist', [
                'label'  => 'Artist',
                'public' => false,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (post_type_exists('artpulse_artist')) {
            unregister_post_type('artpulse_artist');
        }

        parent::tearDown();
    }

    public function test_process_artist_upgrade_request_creates_review_and_placeholder(): void
    {
        $user_id = self::factory()->user->create([
            'role'         => 'subscriber',
            'display_name' => 'Member Artist',
        ]);

        $user = get_user_by('id', $user_id);
        $result = $this->invoke_process_artist_upgrade_request($user);

        $this->assertIsArray($result);

        $request_id = (int) ($result['request_id'] ?? 0);
        $artist_id  = (int) ($result['artist_id'] ?? 0);

        $this->assertGreaterThan(0, $request_id);
        $this->assertGreaterThan(0, $artist_id);

        $review = get_post($request_id);
        $this->assertInstanceOf(WP_Post::class, $review);
        $this->assertSame(UpgradeReviewRepository::TYPE_ARTIST_UPGRADE, UpgradeReviewRepository::get_type($review));
        $this->assertSame(UpgradeReviewRepository::STATUS_PENDING, UpgradeReviewRepository::get_status($review));
        $this->assertSame($user_id, UpgradeReviewRepository::get_user_id($review));
        $this->assertSame($artist_id, UpgradeReviewRepository::get_post_id($review));
        $this->assertSame($artist_id, (int) get_post_meta($review->ID, '_ap_placeholder_artist_id', true));

        $artist_post = get_post($artist_id);
        $this->assertInstanceOf(WP_Post::class, $artist_post);
        $this->assertSame('artpulse_artist', $artist_post->post_type);
        $this->assertSame('draft', $artist_post->post_status);
        $this->assertSame($user_id, (int) $artist_post->post_author);
        $this->assertSame($user_id, (int) get_post_meta($artist_id, '_ap_owner_user', true));
    }

    public function test_process_artist_upgrade_request_blocks_pending_duplicates(): void
    {
        $user_id = self::factory()->user->create([
            'role' => 'subscriber',
        ]);

        $user = get_user_by('id', $user_id);
        $first_result = $this->invoke_process_artist_upgrade_request($user);
        $this->assertIsArray($first_result);

        $duplicate = $this->invoke_process_artist_upgrade_request($user);
        $this->assertInstanceOf(\WP_Error::class, $duplicate);
        $this->assertSame('ap_artist_upgrade_pending', $duplicate->get_error_code());
    }

    public function test_deny_cleanup_trashes_artist_placeholder(): void
    {
        $user_id = self::factory()->user->create([
            'role' => 'subscriber',
        ]);

        $user   = get_user_by('id', $user_id);
        $result = $this->invoke_process_artist_upgrade_request($user);
        $request_id = (int) ($result['request_id'] ?? 0);
        $artist_id  = (int) ($result['artist_id'] ?? 0);

        $this->assertGreaterThan(0, $request_id);
        $this->assertGreaterThan(0, $artist_id);

        $review = get_post($request_id);
        $this->assertInstanceOf(WP_Post::class, $review);

        $reflection = new \ReflectionClass(UpgradeReviewsController::class);
        $deny       = $reflection->getMethod('deny');
        $deny->setAccessible(true);
        $deny->invoke(null, $review, 'Not a fit at this time.');

        $updated_review = get_post($request_id);
        $this->assertSame(UpgradeReviewRepository::STATUS_DENIED, UpgradeReviewRepository::get_status($updated_review));
        $this->assertSame('', get_post_meta($request_id, '_ap_placeholder_artist_id', true));

        $trashed_post = get_post($artist_id);
        $this->assertInstanceOf(WP_Post::class, $trashed_post);
        $this->assertSame('trash', $trashed_post->post_status);
        $this->assertSame('', get_post_meta($artist_id, '_ap_owner_user', true));
    }

    private function invoke_process_artist_upgrade_request($user)
    {
        $reflection = new \ReflectionClass(MemberDashboard::class);
        $method     = $reflection->getMethod('process_artist_upgrade_request');
        $method->setAccessible(true);

        return $method->invoke(null, $user);
    }
}
