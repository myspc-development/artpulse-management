<?php

namespace ArtPulse\Tests\Integration;

use ArtPulse\Admin\UpgradeReviewsController;
use ArtPulse\Admin\UpgradeReviewsTable;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Tests\Helpers\UpgradeTestUtils;
use RuntimeException;
use WP_Post;
use WP_User;
use WP_UnitTestCase;
use function add_filter;
use function get_post;
use function get_post_field;
use function get_user_by;
use function remove_all_filters;
use function set_current_screen;
use function wp_create_nonce;
use function wp_set_current_user;

/**
 * Integration tests covering the member upgrade review flow.
 */
class UpgradeFlowTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        remove_all_filters('wp_redirect');
        wp_set_current_user(0);
    }

    public function test_member_can_submit_upgrade_request(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $response = UpgradeTestUtils::submitUpgradeRequest($user_id, 'artist', ['note' => 'Please upgrade me.']);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('artist', $data['type']);
        $this->assertSame('pending', $data['status']);
        $this->assertArrayHasKey('created_at', $data);

        $request = UpgradeTestUtils::getLatestRequestForUser($user_id, UpgradeReviewRepository::TYPE_ARTIST);
        $this->assertInstanceOf(WP_Post::class, $request);
        $this->assertSame(UpgradeReviewRepository::STATUS_PENDING, UpgradeReviewRepository::get_status($request));
        $this->assertSame($user_id, UpgradeReviewRepository::get_user_id($request));
        $this->assertSame(
            UpgradeReviewRepository::TYPE_ARTIST,
            UpgradeReviewRepository::get_type($request)
        );
    }

    public function test_invalid_nonce_or_anon_is_rejected(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $bad_nonce_response = UpgradeTestUtils::submitUpgradeRequestWithoutNonce($user_id, 'artist');
        $this->assertSame(403, $bad_nonce_response->get_status());

        $anon_response = UpgradeTestUtils::submitUpgradeRequestAsAnonymous('artist');
        $this->assertSame(401, $anon_response->get_status());
    }

    public function test_admin_sees_request_in_review_queue(): void
    {
        $member_id = self::factory()->user->create(['role' => 'subscriber']);
        $response = UpgradeTestUtils::submitUpgradeRequest($member_id, 'org');
        $this->assertSame(201, $response->get_status());

        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);
        set_current_screen('dashboard');

        $table = new UpgradeReviewsTable();
        $table->prepare_items();

        $items = $this->getListTableItems($table);
        $this->assertNotEmpty($items);

        $match = array_filter($items, static function (array $item) use ($member_id) {
            return isset($item['user_id']) && (int) $item['user_id'] === $member_id;
        });
        $this->assertNotEmpty($match, 'Upgrade request should appear in the admin table.');

        $row = array_shift($match);
        $this->assertSame('pending', $row['status']);
        $this->assertSame('org', $row['type']);
    }

    public function test_admin_approves_request_and_user_is_promoted(): void
    {
        $member_id = self::factory()->user->create(['role' => 'subscriber']);
        $response = UpgradeTestUtils::submitUpgradeRequest($member_id, 'artist');
        $this->assertSame(201, $response->get_status());

        $request = UpgradeTestUtils::getLatestRequestForUser($member_id, UpgradeReviewRepository::TYPE_ARTIST);
        $this->assertInstanceOf(WP_Post::class, $request);

        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $_REQUEST = [
            'review'         => [$request->ID],
            'operation'      => 'approve',
            'ap_admin_nonce' => wp_create_nonce('ap_admin_action'),
            '_wpnonce'       => wp_create_nonce('ap-upgrade-review-' . $request->ID),
        ];

        $redirect = null;
        add_filter('wp_redirect', static function ($location) use (&$redirect) {
            $redirect = $location;
            throw new RuntimeException('redirect');
        });

        try {
            UpgradeReviewsController::handle_action();
            $this->fail('Approval should trigger a redirect.');
        } catch (RuntimeException $exception) {
            $this->assertSame('redirect', $exception->getMessage());
        }

        $this->assertNotNull($redirect);
        $this->assertStringContainsString('ap_status=approved', $redirect);

        $updated_request = get_post($request->ID);
        $this->assertInstanceOf(WP_Post::class, $updated_request);
        $this->assertSame(UpgradeReviewRepository::STATUS_APPROVED, UpgradeReviewRepository::get_status($updated_request));

        $member = get_user_by('id', $member_id);
        $this->assertInstanceOf(WP_User::class, $member);
        $this->assertContains('artist', $member->roles);

        $profile_id = UpgradeReviewRepository::get_post_id($updated_request);
        $this->assertIsInt($profile_id);
        if ($profile_id > 0) {
            $this->assertSame($member_id, (int) get_post_field('post_author', $profile_id));
        }

        $this->assertNull(
            UpgradeReviewRepository::find_pending($member_id, UpgradeReviewRepository::TYPE_ARTIST)
        );
    }

    public function test_double_submit_is_idempotent(): void
    {
        $member_id = self::factory()->user->create(['role' => 'subscriber']);

        $first = UpgradeTestUtils::submitUpgradeRequest($member_id, 'artist');
        $this->assertSame(201, $first->get_status());

        $second = UpgradeTestUtils::submitUpgradeRequest($member_id, 'artist');
        $this->assertSame(409, $second->get_status());

        $latest = UpgradeTestUtils::getLatestRequestForUser($member_id, UpgradeReviewRepository::TYPE_ARTIST);
        $this->assertInstanceOf(WP_Post::class, $latest);
        $this->assertSame(UpgradeReviewRepository::STATUS_PENDING, UpgradeReviewRepository::get_status($latest));
        $this->assertSame(1, UpgradeTestUtils::countRequestsForUser($member_id));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getListTableItems(UpgradeReviewsTable $table): array
    {
        $reflection = new \ReflectionProperty(UpgradeReviewsTable::class, 'items');
        $reflection->setAccessible(true);

        $items = $reflection->getValue($table);

        return is_array($items) ? $items : [];
    }
}
