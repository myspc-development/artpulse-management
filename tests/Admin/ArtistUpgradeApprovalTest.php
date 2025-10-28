<?php

namespace ArtPulse\Tests\Admin;

use ArtPulse\Admin\UpgradeReviewsController;
use ArtPulse\Core\UpgradeReviewRepository;
use WP_Post;
use WP_UnitTestCase;

class ArtistUpgradeApprovalTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!post_type_exists('artist')) {
            register_post_type('artist', [
                'label'  => 'Artist',
                'public' => false,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (post_type_exists('artist')) {
            unregister_post_type('artist');
        }

        parent::tearDown();
    }

    public function test_approval_creates_artist_draft_for_user_without_profile(): void
    {
        $user_id = self::factory()->user->create([
            'role'         => 'subscriber',
            'display_name' => 'Seeded Artist',
        ]);

        $result = UpgradeReviewRepository::upsert_pending($user_id, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE);
        $review_id = $result['request_id'] ?? null;
        $this->assertNotNull($review_id);

        $review = get_post($review_id);
        $this->assertInstanceOf(WP_Post::class, $review);

        $this->assertSame(0, UpgradeReviewRepository::get_post_id($review));

        $mailer = tests_retrieve_phpmailer_instance();
        $mailer->mock_sent = [];

        $reflector = new \ReflectionClass(UpgradeReviewsController::class);
        $approve   = $reflector->getMethod('approve');
        $approve->setAccessible(true);
        $approve->invoke(null, $review);

        $artist_posts = get_posts([
            'post_type'      => 'artist',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => 5,
            'meta_query'     => [
                [
                    'key'   => '_ap_owner_user_id',
                    'value' => $user_id,
                ],
            ],
        ]);

        $this->assertCount(1, $artist_posts);

        $artist_post_id = (int) $artist_posts[0];
        $artist_post    = get_post($artist_post_id);
        $this->assertInstanceOf(WP_Post::class, $artist_post);
        $this->assertSame('draft', $artist_post->post_status);
        $this->assertSame($user_id, (int) $artist_post->post_author);
        $this->assertSame($user_id, (int) get_post_meta($artist_post_id, '_ap_owner_user_id', true));
        $this->assertSame($artist_post_id, (int) get_user_meta($user_id, '_ap_artist_post_id', true));

        $updated_review = get_post($review_id);
        $this->assertSame($artist_post_id, UpgradeReviewRepository::get_post_id($updated_review));

        $builder_fragment = sprintf('ap_builder=artist&post_id=%d', $artist_post_id);
        $matching_emails  = array_filter(
            $mailer->mock_sent,
            static fn($mail) => false !== strpos($mail['body'] ?? '', $builder_fragment)
        );
        $this->assertNotEmpty($matching_emails);
    }
}
