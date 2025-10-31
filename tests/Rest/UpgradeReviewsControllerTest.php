<?php

namespace Tests\Rest;

use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Rest\UpgradeReviewsController;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use function rest_authorization_required_code;
use function wp_list_pluck;

class UpgradeReviewsControllerTest extends \WP_UnitTestCase
{
    private int $user_id;
    private int $organization_id;
    private int $artist_id;
    private string $nonce;

    public function set_up(): void
    {
        parent::set_up();

        rest_get_server();
        UpgradeReviewsController::register();

        $this->user_id        = $this->factory->user->create(['role' => 'subscriber']);
        $this->organization_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Community Arts Org',
        ]);
        $this->artist_id = $this->factory->post->create([
            'post_type'   => 'artpulse_artist',
            'post_status' => 'publish',
            'post_title'  => 'Studio Collective',
        ]);
        $this->nonce = wp_create_nonce('wp_rest');

        wp_set_current_user($this->user_id);

        delete_transient(sprintf('ap_rate_%s_%d', 'upgrade_reviews', $this->user_id));
    }

    public function tear_down(): void
    {
        parent::tear_down();
        wp_set_current_user(0);
    }

    public function test_create_review_requires_valid_nonce(): void
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/upgrade-reviews');
        $request->set_body_params([
            'type' => 'artist',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(403, $response->get_status());
    }

    public function test_rest_post_upgrade_requires_nonce_and_auth(): void
    {
        wp_set_current_user(0);
        $no_auth = new WP_REST_Request('POST', '/artpulse/v1/upgrade-reviews');
        $response = rest_do_request($no_auth);
        $this->assertSame(rest_authorization_required_code(), $response->get_status());

        wp_set_current_user($this->user_id);
        $missing_nonce = new WP_REST_Request('POST', '/artpulse/v1/upgrade-reviews');
        $missing_nonce->set_body_params([
            'type' => 'artist',
        ]);

        $second_response = rest_do_request($missing_nonce);
        $this->assertSame(403, $second_response->get_status());
    }

    public function test_create_review_requires_authentication(): void
    {
        wp_set_current_user(0);
        $request  = $this->make_create_request('artist');
        $response = $this->dispatch($request);
        $this->assertSame(rest_authorization_required_code(), $response->get_status());
    }

    public function test_create_review_rejects_invalid_type(): void
    {
        $request = $this->make_create_request('unknown');
        $response = $this->dispatch($request);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('artpulse_upgrade_review_invalid_type', $response->get_error_code());
    }

    public function test_create_review_creates_pending_request(): void
    {
        $request  = $this->make_create_request('org');
        $response = $this->dispatch($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();

        $this->assertSame('pending', $data['status']);
        $this->assertSame('org', $data['type']);
        $this->assertIsInt($data['id']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertNotEmpty($data['created_at']);

        $review = get_post($data['id']);
        $this->assertInstanceOf(WP_Post::class, $review);
        $this->assertSame(UpgradeReviewRepository::STATUS_PENDING, UpgradeReviewRepository::get_status($review));
    }

    public function test_create_review_rejects_duplicate_pending_request(): void
    {
        $first          = $this->make_create_request('org');
        $first_response = $this->dispatch($first);
        $this->assertSame(201, $first_response->get_status());

        $second          = $this->make_create_request('org');
        $second_response = $this->dispatch($second);

        $this->assertSame(409, $second_response->get_status());
        $this->assertSame('ap_duplicate_pending', $second_response->get_error_code());
    }

    public function test_list_reviews_requires_valid_nonce(): void
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/upgrade-reviews');
        $request->set_query_params([
            'mine' => '1',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(403, $response->get_status());
    }

    public function test_list_reviews_requires_authentication(): void
    {
        wp_set_current_user(0);
        $request = new WP_REST_Request('GET', '/artpulse/v1/upgrade-reviews');
        $request->set_query_params([
            'mine' => '1',
        ]);
        $request->set_header('X-WP-Nonce', $this->nonce);

        $response = rest_do_request($request);
        $this->assertSame(rest_authorization_required_code(), $response->get_status());
    }

    public function test_list_reviews_rejects_invalid_scope(): void
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/upgrade-reviews');
        $request->set_query_params([
            'mine' => '0',
        ]);
        $request->set_header('X-WP-Nonce', $this->nonce);

        $response = rest_do_request($request);
        $this->assertSame(400, $response->get_status());
        $this->assertSame('artpulse_upgrade_review_invalid_scope', $response->get_error_code());
    }

    public function test_list_reviews_returns_all_requests(): void
    {
        $artist_request = $this->make_create_request('artist');
        $this->dispatch($artist_request);

        $org_request = $this->make_create_request('org');
        $this->dispatch($org_request);

        $list = new WP_REST_Request('GET', '/artpulse/v1/upgrade-reviews');
        $list->set_header('X-WP-Nonce', $this->nonce);
        $list->set_query_params([
            'mine' => '1',
        ]);

        $response = rest_do_request($list);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertCount(2, $data);
        $types = wp_list_pluck($data, 'type');
        $this->assertContains('artist', $types);
        $this->assertContains('org', $types);

        foreach ($data as $item) {
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('reason', $item);
        }
    }

    private function dispatch(WP_REST_Request $request): WP_REST_Response
    {
        if ('' === (string) $request->get_header('X-WP-Nonce')) {
            $request->set_header('X-WP-Nonce', $this->nonce);
        }

        return rest_do_request($request);
    }

    private function make_create_request(string $type): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/upgrade-reviews');
        $request->set_body_params([
            'type' => $type,
            'note' => 'Please upgrade me',
        ]);

        return $request;
    }
}
