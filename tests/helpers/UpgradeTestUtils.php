<?php

namespace ArtPulse\Tests\Helpers;

use ArtPulse\Core\UpgradeReviewRepository;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use function get_posts;
use function rest_get_server;
use function wp_create_nonce;
use function wp_set_current_user;

/**
 * Helper utilities for membership upgrade integration tests.
 */
final class UpgradeTestUtils
{
    private const ROUTE = '/artpulse/v1/upgrade-reviews';

    private function __construct()
    {
    }

    public static function submitUpgradeRequest(int $user_id, string $type, array $params = []): WP_REST_Response
    {
        wp_set_current_user($user_id);

        $request = new WP_REST_Request('POST', self::ROUTE);
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params(array_merge(['type' => $type], $params));

        return rest_get_server()->dispatch($request);
    }

    public static function submitUpgradeRequestWithoutNonce(int $user_id, string $type): WP_REST_Response
    {
        wp_set_current_user($user_id);

        $request = new WP_REST_Request('POST', self::ROUTE);
        $request->set_body_params(['type' => $type]);

        return rest_get_server()->dispatch($request);
    }

    public static function submitUpgradeRequestAsAnonymous(string $type): WP_REST_Response
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', self::ROUTE);
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params(['type' => $type]);

        return rest_get_server()->dispatch($request);
    }

    public static function getLatestRequestForUser(int $user_id, string $type): ?WP_Post
    {
        return UpgradeReviewRepository::get_latest_for_user($user_id, $type);
    }

    public static function countRequestsForUser(int $user_id): int
    {
        $posts = get_posts([
            'post_type'      => UpgradeReviewRepository::POST_TYPE,
            'post_status'    => ['private', 'draft', 'publish'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => UpgradeReviewRepository::META_USER,
                    'value'   => $user_id,
                    'compare' => '=',
                ],
            ],
        ]);

        return is_array($posts) ? count($posts) : 0;
    }
}
