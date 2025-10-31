<?php

namespace ArtPulse\Tests\Core;

use ArtPulse\Community\NotificationManager;
use ArtPulse\Core\Capabilities;
use ArtPulse\Core\UpgradeReviewHandlers;
use ArtPulse\Core\UpgradeReviewRepository;
use WP_Post;
use WP_UnitTestCase;
use function get_posts;
use function wp_strip_all_tags;

class UpgradeReviewHandlersTest extends WP_UnitTestCase
{
    private string $notification_table;

    /**
     * @var array<int,array<string,mixed>>
     */
    private array $sent_mail = [];

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

        NotificationManager::install_notifications_table();

        global $wpdb;
        $this->notification_table = $wpdb->prefix . 'ap_notifications';
        $wpdb->query("TRUNCATE TABLE {$this->notification_table}");

        $this->sent_mail = [];
        add_filter('wp_mail', [$this, 'capture_mail']);
    }

    protected function tearDown(): void
    {
        remove_filter('wp_mail', [$this, 'capture_mail']);
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

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function capture_mail(array $args): array
    {
        $this->sent_mail[] = $args;

        return $args;
    }

    public function test_handle_approved_grants_artist_role_and_creates_profile(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $result = UpgradeReviewRepository::upsert_pending($user_id, UpgradeReviewRepository::TYPE_ARTIST);
        $request_id = $result['request_id'] ?? null;
        $this->assertNotNull($request_id);

        UpgradeReviewHandlers::onApproved($request_id, $user_id, UpgradeReviewRepository::TYPE_ARTIST);

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

    public function test_approve_adds_caps_and_creates_profile_idempotently(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $result = UpgradeReviewRepository::upsert_pending($user_id, UpgradeReviewRepository::TYPE_ARTIST);
        $request_id = $result['request_id'] ?? null;
        $this->assertNotNull($request_id);

        UpgradeReviewHandlers::onApproved($request_id, $user_id, UpgradeReviewRepository::TYPE_ARTIST);

        $profile_id = (int) get_user_meta($user_id, '_ap_artist_post_id', true);
        $this->assertGreaterThan(0, $profile_id);

        $posts_before = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'author'         => $user_id,
        ]);

        UpgradeReviewHandlers::onApproved($request_id, $user_id, UpgradeReviewRepository::TYPE_ARTIST);

        $posts_after = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'author'         => $user_id,
        ]);

        $this->assertCount(count($posts_before), $posts_after);
        $this->assertSame($profile_id, (int) get_user_meta($user_id, '_ap_artist_post_id', true));

        $user = get_user_by('id', $user_id);
        $this->assertNotFalse($user);
        $this->assertContains('ap_artist', $user->roles);
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

        $resolved_id = UpgradeReviewHandlers::get_or_create_profile_post($user_id, UpgradeReviewRepository::TYPE_ARTIST);
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

        $result = UpgradeReviewRepository::upsert_pending($user_id, UpgradeReviewRepository::TYPE_ORG);
        $request_id = $result['request_id'] ?? null;
        $this->assertNotNull($request_id);

        UpgradeReviewHandlers::onApproved($request_id, $user_id, UpgradeReviewRepository::TYPE_ORG);

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

    public function test_handle_approved_creates_notification_and_email(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $result = UpgradeReviewRepository::upsert_pending($user_id, UpgradeReviewRepository::TYPE_ARTIST);
        $request_id = $result['request_id'] ?? 0;

        UpgradeReviewHandlers::onApproved($request_id, $user_id, UpgradeReviewRepository::TYPE_ARTIST);

        global $wpdb;
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->notification_table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        $this->assertIsArray($record);
        $this->assertSame('upgrade_request_approved', $record['type']);
        $this->assertStringContainsString('approved', strtolower($record['content'] ?? ''));
        $this->assertStringContainsString('Builder:', $record['content']);
        $this->assertStringContainsString('Dashboard:', $record['content']);

        $this->assertNotEmpty($this->sent_mail);
        $mail = $this->sent_mail[0];
        $this->assertSame(get_userdata($user_id)->user_email, $mail['to']);
        $this->assertStringContainsString('approved', strtolower((string) $mail['subject']));
        $message = strtolower(wp_strip_all_tags((string) $mail['message']));
        $this->assertStringContainsString('approved', $message);
        $this->assertStringContainsString('view your dashboard', $message);
    }

    public function test_handle_denied_creates_notification_with_reason(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        $result = UpgradeReviewRepository::upsert_pending($user_id, UpgradeReviewRepository::TYPE_ORG);
        $request_id = $result['request_id'] ?? 0;

        $reason = 'Profile incomplete';
        UpgradeReviewRepository::set_status($request_id, UpgradeReviewRepository::STATUS_DENIED, $reason);

        UpgradeReviewHandlers::onDenied($request_id, $user_id, UpgradeReviewRepository::TYPE_ORG, $reason);

        global $wpdb;
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->notification_table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        $this->assertIsArray($record);
        $this->assertSame('upgrade_request_denied', $record['type']);
        $this->assertStringContainsString('not approved', strtolower($record['content'] ?? ''));
        $this->assertStringContainsString('Reason: ' . $reason, $record['content']);
        $this->assertStringContainsString('Dashboard:', $record['content']);

        $this->assertNotEmpty($this->sent_mail);
        $mail = end($this->sent_mail);
        $this->assertStringContainsString('denied', strtolower((string) $mail['subject']));
        $this->assertStringContainsString(strtolower($reason), strtolower(wp_strip_all_tags((string) $mail['message'])));
    }
}
