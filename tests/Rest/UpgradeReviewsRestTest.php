<?php

namespace Tests\Rest;

use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Rest\UpgradeReviewsController;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use function get_post;
use function rest_authorization_required_code;
use function rest_do_request;
use function rest_get_server;
use function sprintf;
use function wp_list_pluck;
use function wp_create_nonce;
use function wp_set_current_user;
use function delete_transient;

class UpgradeReviewsRestTest extends \WP_UnitTestCase
{
    private int $user_id;

    private int $other_user_id;

    private string $nonce;

    public function set_up(): void
    {
        parent::set_up();

        rest_get_server();
        UpgradeReviewsController::register();

        $this->user_id = self::factory()->user->create(['role' => 'subscriber']);
        $this->other_user_id = self::factory()->user->create(['role' => 'subscriber']);

        $this->nonce = wp_create_nonce('wp_rest');

        wp_set_current_user($this->user_id);
        $this->resetRateLimit($this->user_id);
        $this->resetRateLimit($this->other_user_id);
    }

    public function tear_down(): void
    {
        wp_set_current_user(0);
        parent::tear_down();
    }

    public function test_post_requires_auth_and_nonce(): void
    {
        wp_set_current_user(0);
        $unauthenticated = $this->makeCreateRequest('artist');
        $unauthenticated->set_header('X-WP-Nonce', $this->nonce);
        $response = rest_do_request($unauthenticated);
        $this->assertSame(rest_authorization_required_code(), $response->get_status());

        wp_set_current_user($this->user_id);
        $missing_nonce = $this->makeCreateRequest('artist');
        $missing_nonce_response = rest_do_request($missing_nonce);
        $this->assertSame(403, $missing_nonce_response->get_status());

        $valid = $this->dispatch($this->makeCreateRequest('artist'));
        $this->assertSame(201, $valid->get_status());
    }

    public function test_post_blocks_duplicate_returns_409(): void
    {
        $first = $this->dispatch($this->makeCreateRequest('org'));
        $this->assertSame(201, $first->get_status());

        $duplicate = $this->dispatch($this->makeCreateRequest('org'));
        $this->assertSame(409, $duplicate->get_status());
        $this->assertSame('ap_duplicate_pending', $duplicate->get_error_code());
    }

    public function test_get_mine_returns_only_current_user_items(): void
    {
        $artist_response = $this->dispatch($this->makeCreateRequest('artist'));
        $this->assertSame(201, $artist_response->get_status());

        $org_response = $this->dispatch($this->makeCreateRequest('org'));
        $this->assertSame(201, $org_response->get_status());

        $other_request = UpgradeReviewRepository::create($this->other_user_id, UpgradeReviewRepository::TYPE_ORG);
        $this->assertIsInt($other_request);

        $list_request = new WP_REST_Request('GET', '/artpulse/v1/upgrade-reviews');
        $list_request->set_query_params(['mine' => '1']);
        $list = $this->dispatch($list_request);
        $this->assertSame(200, $list->get_status());

        $data = $list->get_data();
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $types = wp_list_pluck($data, 'type');
        $this->assertEqualsCanonicalizing(['artist', 'org'], $types);

        foreach ($data as $item) {
            $post = get_post($item['id']);
            $this->assertInstanceOf(WP_Post::class, $post);
            $this->assertSame($this->user_id, UpgradeReviewRepository::get_user_id($post));
        }
    }

    private function dispatch(WP_REST_Request $request): WP_REST_Response
    {
        if ('' === (string) $request->get_header('X-WP-Nonce')) {
            $request->set_header('X-WP-Nonce', $this->nonce);
        }

        return rest_do_request($request);
    }

    private function makeCreateRequest(string $type): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/upgrade-reviews');
        $request->set_body_params([
            'type' => $type,
            'note' => 'Please upgrade me',
        ]);

        return $request;
    }

    private function resetRateLimit(int $user_id): void
    {
        delete_transient(sprintf('ap_rate_%s_%d', 'upgrade_reviews', $user_id));
    }
}
