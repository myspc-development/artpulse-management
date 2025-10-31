<?php

namespace ArtPulse\Tests\Core;

use ArtPulse\Core\UpgradeReviewRepository;
use WP_Error;
use WP_Post;
use WP_UnitTestCase;
use function add_action;
use function get_post;
use function get_post_meta;
use function post_type_exists;
use function register_post_type;
use function remove_all_actions;
use function unregister_post_type;
use function update_post_meta;
use function wp_insert_post;

class UpgradeReviewRepositoryTest extends WP_UnitTestCase
{
    private bool $registered_post_type = false;

    public function set_up(): void
    {
        parent::set_up();

        if (!post_type_exists(UpgradeReviewRepository::POST_TYPE)) {
            register_post_type(
                UpgradeReviewRepository::POST_TYPE,
                [
                    'label'  => 'Upgrade Review',
                    'public' => false,
                    'show_ui' => false,
                ]
            );
            $this->registered_post_type = true;
        }
    }

    public function tear_down(): void
    {
        if ($this->registered_post_type && post_type_exists(UpgradeReviewRepository::POST_TYPE)) {
            unregister_post_type(UpgradeReviewRepository::POST_TYPE);
        }

        remove_all_actions('artpulse/upgrade_review/approved');
        remove_all_actions('artpulse/upgrade_review/denied');

        parent::tear_down();
    }

    public function test_create_creates_pending_request_with_metadata(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $result = UpgradeReviewRepository::create(
            $user_id,
            UpgradeReviewRepository::TYPE_ARTIST_UPGRADE,
            [
                'post_title'   => 'Custom Request Title',
                'post_content' => '<p>Please approve me</p>',
                'post_excerpt' => '<em>Artist bio</em><script>alert(1)</script>',
            ]
        );

        $this->assertIsInt($result);
        $request_id = (int) $result;
        $this->assertGreaterThan(0, $request_id);

        $post = get_post($request_id);
        $this->assertInstanceOf(WP_Post::class, $post);
        $this->assertSame('Custom Request Title', $post->post_title);
        $this->assertStringContainsString('Please approve me', $post->post_content);
        $this->assertStringNotContainsString('<script>', (string) $post->post_excerpt);

        $this->assertSame(
            UpgradeReviewRepository::TYPE_ARTIST_UPGRADE,
            get_post_meta($request_id, UpgradeReviewRepository::META_TYPE, true)
        );
        $this->assertSame(
            UpgradeReviewRepository::STATUS_PENDING,
            get_post_meta($request_id, UpgradeReviewRepository::META_STATUS, true)
        );
        $this->assertSame(
            $user_id,
            (int) get_post_meta($request_id, UpgradeReviewRepository::META_USER, true)
        );
        $this->assertSame(
            0,
            (int) get_post_meta($request_id, UpgradeReviewRepository::META_POST, true)
        );
    }

    public function test_create_returns_error_when_pending_exists(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $first = UpgradeReviewRepository::create($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);
        $this->assertIsInt($first);

        $second = UpgradeReviewRepository::create($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);
        $this->assertInstanceOf(WP_Error::class, $second);
        $this->assertSame('artpulse_upgrade_review_pending', $second->get_error_code());
        $this->assertSame($first, $second->get_error_data()['request_id'] ?? null);
    }

    public function test_find_pending_returns_latest_pending_request(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $first = UpgradeReviewRepository::create($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);
        $this->assertIsInt($first);
        $this->assertSame(
            $first,
            UpgradeReviewRepository::find_pending($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE)
        );

        UpgradeReviewRepository::set_status($first, UpgradeReviewRepository::STATUS_APPROVED);

        $second = UpgradeReviewRepository::create($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE);
        $this->assertIsInt($second);
        $this->assertSame(
            $second,
            UpgradeReviewRepository::find_pending($user_id, UpgradeReviewRepository::TYPE_ORG_UPGRADE)
        );

        $this->assertNull(UpgradeReviewRepository::find_pending(0, UpgradeReviewRepository::TYPE_ORG_UPGRADE));
        $this->assertNull(UpgradeReviewRepository::find_pending($user_id, 'unknown-type'));
    }

    public function test_approve_updates_status_and_triggers_action(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $request_id = wp_insert_post([
            'post_type'   => UpgradeReviewRepository::POST_TYPE,
            'post_status' => 'private',
            'post_title'  => 'Artist upgrade request',
        ]);

        $this->assertIsInt($request_id);
        update_post_meta($request_id, UpgradeReviewRepository::META_STATUS, UpgradeReviewRepository::STATUS_PENDING);
        update_post_meta($request_id, UpgradeReviewRepository::META_TYPE, UpgradeReviewRepository::TYPE_ARTIST_UPGRADE);
        update_post_meta($request_id, UpgradeReviewRepository::META_USER, $user_id);

        $captured = [];
        add_action(
            'artpulse/upgrade_review/approved',
            static function (int $id, int $review_user, string $type) use (&$captured): void {
                $captured[] = compact('id', 'review_user', 'type');
            }
        );

        $this->assertTrue(UpgradeReviewRepository::approve($request_id));
        $this->assertSame(
            UpgradeReviewRepository::STATUS_APPROVED,
            get_post_meta($request_id, UpgradeReviewRepository::META_STATUS, true)
        );

        $this->assertCount(1, $captured);
        $this->assertSame(
            [
                'id'          => $request_id,
                'review_user' => $user_id,
                'type'        => UpgradeReviewRepository::TYPE_ARTIST_UPGRADE,
            ],
            $captured[0]
        );
    }

    public function test_deny_updates_status_and_reason_and_triggers_action(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $request_id = wp_insert_post([
            'post_type'   => UpgradeReviewRepository::POST_TYPE,
            'post_status' => 'private',
            'post_title'  => 'Organization upgrade request',
        ]);

        $this->assertIsInt($request_id);
        update_post_meta($request_id, UpgradeReviewRepository::META_STATUS, UpgradeReviewRepository::STATUS_PENDING);
        update_post_meta($request_id, UpgradeReviewRepository::META_TYPE, UpgradeReviewRepository::TYPE_ORG_UPGRADE);
        update_post_meta($request_id, UpgradeReviewRepository::META_USER, $user_id);

        $captured = [];
        add_action(
            'artpulse/upgrade_review/denied',
            static function (int $id, int $review_user, string $type) use (&$captured): void {
                $captured[] = compact('id', 'review_user', 'type');
            }
        );

        $reason = "The submitted details were incomplete. <script>alert('x');</script>";
        $this->assertTrue(UpgradeReviewRepository::deny($request_id, $reason));

        $this->assertSame(
            UpgradeReviewRepository::STATUS_DENIED,
            get_post_meta($request_id, UpgradeReviewRepository::META_STATUS, true)
        );

        $stored_reason = (string) get_post_meta($request_id, UpgradeReviewRepository::META_REASON, true);
        $this->assertStringNotContainsString('<script>', $stored_reason);
        $this->assertStringContainsString('The submitted details were incomplete.', $stored_reason);

        $this->assertCount(1, $captured);
        $this->assertSame(
            [
                'id'          => $request_id,
                'review_user' => $user_id,
                'type'        => UpgradeReviewRepository::TYPE_ORG_UPGRADE,
            ],
            $captured[0]
        );
    }
}
