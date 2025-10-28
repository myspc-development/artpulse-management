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
        $request = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $request->set_body_params([
            'type' => 'artist',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(403, $response->get_status());
    }

    public function test_create_review_requires_authentication(): void
    {
        wp_set_current_user(0);
        $request  = $this->make_create_request('artist', $this->artist_id);
        $response = $this->dispatch($request);
        $this->assertSame(rest_authorization_required_code(), $response->get_status());
    }

    public function test_create_review_creates_pending_request(): void
    {
        $request  = $this->make_create_request('organization', $this->organization_id);
        $response = $this->dispatch($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();

        $this->assertSame('pending', $data['status']);
        $this->assertSame('organization', $data['type']);
        $this->assertSame($this->organization_id, $data['postId']);
        $this->assertIsInt($data['id']);

        $review = get_post($data['id']);
        $this->assertInstanceOf(WP_Post::class, $review);
        $this->assertSame(UpgradeReviewRepository::STATUS_PENDING, UpgradeReviewRepository::get_status($review));
    }

    public function test_create_review_returns_existing_pending_request(): void
    {
        $first          = $this->make_create_request('organization', $this->organization_id);
        $first_response = $this->dispatch($first);
        $this->assertSame(201, $first_response->get_status());
        $first_id = $first_response->get_data()['id'];

        $second          = $this->make_create_request('organization', $this->organization_id);
        $second_response = $this->dispatch($second);

        $this->assertSame(200, $second_response->get_status());
        $this->assertSame($first_id, $second_response->get_data()['id']);
    }

    public function test_list_reviews_returns_all_requests(): void
    {
        $artist_request = $this->make_create_request('artist', $this->artist_id);
        $this->dispatch($artist_request);

        $org_request = $this->make_create_request('organization', $this->organization_id);
        $this->dispatch($org_request);

        $list = new WP_REST_Request('GET', '/artpulse/v1/reviews/me');
        $list->set_header('X-WP-Nonce', $this->nonce);

        $response = rest_do_request($list);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertCount(2, $data);
        $types = wp_list_pluck($data, 'type');
        $this->assertContains('artist', $types);
        $this->assertContains('organization', $types);
    }

    public function test_reopen_denied_review_resets_status(): void
    {
        $create_response = $this->dispatch($this->make_create_request('artist', $this->artist_id));
        $review_id = $create_response->get_data()['id'];

        UpgradeReviewRepository::set_status($review_id, UpgradeReviewRepository::STATUS_DENIED, 'Needs more information');

        $reopen = new WP_REST_Request('POST', sprintf('/artpulse/v1/reviews/%d/reopen', $review_id));
        $response = $this->dispatch($reopen);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('pending', $data['status']);
        $this->assertArrayNotHasKey('reason', $data);
    }

    public function test_reopen_requires_denied_status(): void
    {
        $create_response = $this->dispatch($this->make_create_request('artist', $this->artist_id));
        $review_id = $create_response->get_data()['id'];

        $reopen = new WP_REST_Request('POST', sprintf('/artpulse/v1/reviews/%d/reopen', $review_id));
        $response = $this->dispatch($reopen);

        $this->assertSame(400, $response->get_status());
    }

    private function dispatch(WP_REST_Request $request): WP_REST_Response
    {
        if ('' === (string) $request->get_header('X-WP-Nonce')) {
            $request->set_header('X-WP-Nonce', $this->nonce);
        }

        return rest_do_request($request);
    }

    private function make_create_request(string $type, int $post_id = 0): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $request->set_body_params([
            'type'   => $type,
            'postId' => $post_id,
        ]);

        return $request;
    }
}
