<?php

namespace Tests\Rest;

use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Rest\UpgradeReviewsController;
use WP_Post;
use WP_REST_Request;
use function rest_authorization_required_code;
use function wp_list_pluck;

class UpgradeReviewsControllerTest extends \WP_UnitTestCase
{
    private int $user_id;

    public function set_up(): void
    {
        parent::set_up();

        rest_get_server();
        UpgradeReviewsController::register();

        $this->user_id = $this->factory->user->create(['role' => 'subscriber']);
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
        $nonce = wp_create_nonce('wp_rest');

        $request = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $request->set_header('X-WP-Nonce', $nonce);
        $request->set_body_params([
            'type' => 'artist',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(rest_authorization_required_code(), $response->get_status());
    }

    public function test_create_review_creates_pending_request(): void
    {
        $nonce = wp_create_nonce('wp_rest');

        $request = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $request->set_header('X-WP-Nonce', $nonce);
        $request->set_body_params([
            'type' => 'organization',
            'postId' => 123,
        ]);

        $response = rest_do_request($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();

        $this->assertSame('pending', $data['status']);
        $this->assertSame('organization', $data['type']);
        $this->assertSame(123, $data['postId']);
        $this->assertIsInt($data['id']);

        $review = get_post($data['id']);
        $this->assertInstanceOf(WP_Post::class, $review);
        $this->assertSame(UpgradeReviewRepository::STATUS_PENDING, UpgradeReviewRepository::get_status($review));
    }

    public function test_create_review_returns_existing_pending_request(): void
    {
        $nonce = wp_create_nonce('wp_rest');

        $first = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $first->set_header('X-WP-Nonce', $nonce);
        $first->set_body_params([
            'type' => 'artist',
        ]);
        $first_response = rest_do_request($first);
        $this->assertSame(201, $first_response->get_status());
        $first_id = $first_response->get_data()['id'];

        $second = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $second->set_header('X-WP-Nonce', $nonce);
        $second->set_body_params([
            'type' => 'artist',
        ]);
        $second_response = rest_do_request($second);

        $this->assertSame(200, $second_response->get_status());
        $this->assertSame($first_id, $second_response->get_data()['id']);
    }

    public function test_list_reviews_returns_all_requests(): void
    {
        $nonce = wp_create_nonce('wp_rest');

        $artist_request = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $artist_request->set_header('X-WP-Nonce', $nonce);
        $artist_request->set_body_params([
            'type' => 'artist',
        ]);
        rest_do_request($artist_request);

        $org_request = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $org_request->set_header('X-WP-Nonce', $nonce);
        $org_request->set_body_params([
            'type' => 'organization',
            'postId' => 42,
        ]);
        rest_do_request($org_request);

        $list = new WP_REST_Request('GET', '/artpulse/v1/reviews/me');
        $list->set_header('X-WP-Nonce', $nonce);

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
        $nonce = wp_create_nonce('wp_rest');

        $request = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $request->set_header('X-WP-Nonce', $nonce);
        $request->set_body_params([
            'type' => 'artist',
        ]);
        $create_response = rest_do_request($request);
        $review_id = $create_response->get_data()['id'];

        UpgradeReviewRepository::set_status($review_id, UpgradeReviewRepository::STATUS_DENIED, 'Needs more information');

        $reopen = new WP_REST_Request('POST', sprintf('/artpulse/v1/reviews/%d/reopen', $review_id));
        $reopen->set_header('X-WP-Nonce', $nonce);
        $response = rest_do_request($reopen);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('pending', $data['status']);
        $this->assertArrayNotHasKey('reason', $data);
    }

    public function test_reopen_requires_denied_status(): void
    {
        $nonce = wp_create_nonce('wp_rest');

        $request = new WP_REST_Request('POST', '/artpulse/v1/reviews');
        $request->set_header('X-WP-Nonce', $nonce);
        $request->set_body_params([
            'type' => 'artist',
        ]);
        $create_response = rest_do_request($request);
        $review_id = $create_response->get_data()['id'];

        $reopen = new WP_REST_Request('POST', sprintf('/artpulse/v1/reviews/%d/reopen', $review_id));
        $reopen->set_header('X-WP-Nonce', $nonce);
        $response = rest_do_request($reopen);

        $this->assertSame(400, $response->get_status());
    }
}
