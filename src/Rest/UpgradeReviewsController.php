<?php

namespace ArtPulse\Rest;

use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function rest_ensure_response;
use function sanitize_key;
use function sanitize_text_field;
use function wp_verify_nonce;

final class UpgradeReviewsController
{
    private const ROUTE_NAMESPACE = 'artpulse/v1';
    private const RATE_LIMIT_CONTEXT = 'upgrade_reviews';
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = 60;

    public static function register(): void
    {
        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/reviews',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'create_review'],
                'permission_callback' => [self::class, 'permissions_check'],
                'args'                => self::get_create_args_schema(),
            ]
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/reviews/me',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'list_reviews'],
                'permission_callback' => [self::class, 'permissions_check'],
                'args'                => self::get_common_args_schema(),
            ]
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/reviews/(?P<id>\d+)/reopen',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'reopen_review'],
                'permission_callback' => [self::class, 'permissions_check'],
                'args'                => self::get_reopen_args_schema(),
            ]
        );
    }

    public static function permissions_check(WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required to access upgrade reviews.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!self::verify_nonce($request)) {
            return new WP_Error(
                'rest_invalid_nonce',
                __('Security check failed. Please refresh and try again.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        return true;
    }

    public static function create_review(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();

        $rate_error = FormRateLimiter::enforce($user_id, self::RATE_LIMIT_CONTEXT, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW);
        if ($rate_error instanceof WP_Error) {
            return self::format_rate_limit_error($rate_error);
        }

        $type = sanitize_key((string) $request->get_param('type'));
        $post_id = (int) $request->get_param('postId');

        if (!in_array($type, ['artist', 'organization'], true)) {
            return new WP_Error(
                'rest_invalid_review_type',
                __('Invalid review type provided.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        if ($post_id < 0) {
            return new WP_Error(
                'rest_invalid_post_id',
                __('Invalid related post identifier.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $normalized_type = self::map_request_type_to_repository($type);
        $existing = UpgradeReviewRepository::get_latest_for_user($user_id, $normalized_type);
        $existing_is_pending = $existing instanceof WP_Post && UpgradeReviewRepository::STATUS_PENDING === UpgradeReviewRepository::get_status($existing);

        $result = UpgradeReviewRepository::upsert_pending($user_id, $normalized_type, $post_id);
        $request_id = (int) ($result['request_id'] ?? 0);

        if ($request_id <= 0) {
            return new WP_Error(
                'rest_review_create_failed',
                __('Unable to create the upgrade review request.', 'artpulse-management'),
                ['status' => 500]
            );
        }

        $post = get_post($request_id);
        if (!$post instanceof WP_Post) {
            return new WP_Error(
                'rest_review_missing',
                __('The upgrade review request could not be located.', 'artpulse-management'),
                ['status' => 500]
            );
        }

        $status_code = $existing_is_pending ? 200 : 201;

        return new WP_REST_Response(self::prepare_review_data($post), $status_code);
    }

    public static function list_reviews(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $reviews = UpgradeReviewRepository::get_all_for_user($user_id);

        $data = array_map([self::class, 'prepare_review_data'], $reviews);

        return rest_ensure_response($data);
    }

    public static function reopen_review(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $review_id = (int) $request->get_param('id');

        if ($review_id <= 0) {
            return new WP_Error(
                'rest_invalid_review_id',
                __('Invalid review identifier.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $review = get_post($review_id);
        if (!$review instanceof WP_Post || UpgradeReviewRepository::POST_TYPE !== $review->post_type) {
            return new WP_Error(
                'rest_review_not_found',
                __('Review request not found.', 'artpulse-management'),
                ['status' => 404]
            );
        }

        if (UpgradeReviewRepository::get_user_id($review) !== $user_id) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to modify this review request.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (UpgradeReviewRepository::STATUS_DENIED !== UpgradeReviewRepository::get_status($review)) {
            return new WP_Error(
                'rest_invalid_review_status',
                __('Only denied review requests can be reopened.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        UpgradeReviewRepository::set_status($review_id, UpgradeReviewRepository::STATUS_PENDING, '');

        $updated = get_post($review_id);
        if (!$updated instanceof WP_Post) {
            return new WP_Error(
                'rest_review_not_found',
                __('Review request not found.', 'artpulse-management'),
                ['status' => 404]
            );
        }

        return rest_ensure_response(self::prepare_review_data($updated));
    }

    private static function prepare_review_data(WP_Post $post): array
    {
        $status = sanitize_key(UpgradeReviewRepository::get_status($post));
        $type = UpgradeReviewRepository::get_type($post);
        $response_type = 'organization';

        if (UpgradeReviewRepository::TYPE_ARTIST_UPGRADE === $type) {
            $response_type = 'artist';
        }

        $post_id = (int) UpgradeReviewRepository::get_post_id($post);
        $reason = UpgradeReviewRepository::get_reason($post);

        $data = [
            'id'     => (int) $post->ID,
            'status' => $status,
            'type'   => $response_type,
            'postId' => $post_id > 0 ? $post_id : null,
        ];

        if (UpgradeReviewRepository::STATUS_DENIED === $status && '' !== $reason) {
            $data['reason'] = sanitize_text_field($reason);
        }

        return $data;
    }

    private static function verify_nonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (!$nonce) {
            $nonce = (string) $request->get_param('_wpnonce');
        }

        if (!$nonce && $request->get_param('nonce')) {
            $nonce = (string) $request->get_param('nonce');
        }

        return is_string($nonce) && '' !== $nonce && wp_verify_nonce($nonce, 'wp_rest');
    }

    private static function format_rate_limit_error(WP_Error $error): WP_REST_Response
    {
        $data = (array) $error->get_error_data();
        $status = (int) ($data['status'] ?? 429);

        $response = new WP_REST_Response([
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data'    => $data,
        ], $status > 0 ? $status : 429);

        $headers = $data['headers'] ?? [];
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if ('' !== $name) {
                    $response->header((string) $name, (string) $value);
                }
            }
        }

        return $response;
    }

    private static function get_create_args_schema(): array
    {
        return [
            'type' => [
                'type'        => 'string',
                'required'    => true,
                'enum'        => ['artist', 'organization'],
                'description' => __('Type of upgrade review request.', 'artpulse-management'),
            ],
            'postId' => [
                'type'        => 'integer',
                'required'    => false,
                'minimum'     => 1,
                'description' => __('Related post identifier.', 'artpulse-management'),
            ],
        ] + self::get_common_args_schema();
    }

    private static function get_common_args_schema(): array
    {
        return [
            'nonce' => [
                'type'        => 'string',
                'required'    => false,
                'description' => __('Nonce generated via wp_create_nonce("wp_rest").', 'artpulse-management'),
            ],
            '_wpnonce' => [
                'type'        => 'string',
                'required'    => false,
                'description' => __('Nonce generated via wp_create_nonce("wp_rest").', 'artpulse-management'),
            ],
        ];
    }

    private static function get_reopen_args_schema(): array
    {
        return [
            'id' => [
                'type'        => 'integer',
                'required'    => true,
                'minimum'     => 1,
                'description' => __('The review request identifier to reopen.', 'artpulse-management'),
            ],
        ] + self::get_common_args_schema();
    }

    private static function map_request_type_to_repository(string $type): string
    {
        return 'artist' === $type
            ? UpgradeReviewRepository::TYPE_ARTIST_UPGRADE
            : UpgradeReviewRepository::TYPE_ORG_UPGRADE;
    }
}
