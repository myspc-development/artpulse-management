<?php

namespace ArtPulse\Rest;

use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function get_gmt_from_date;
use function get_post;
use function is_user_logged_in;
use function mysql_to_rfc3339;
use function rest_authorization_required_code;
use function rest_ensure_response;
use function sanitize_key;
use function sanitize_textarea_field;
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
            '/upgrade-reviews',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [self::class, 'create_upgrade_review'],
                    'permission_callback' => [self::class, 'permissions_check'],
                    'args'                => self::get_create_args_schema(),
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [self::class, 'list_upgrade_reviews'],
                    'permission_callback' => [self::class, 'permissions_check'],
                    'args'                => self::get_list_args_schema(),
                ],
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

    public static function create_upgrade_review(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();

        $rate_error = FormRateLimiter::enforce($user_id, self::RATE_LIMIT_CONTEXT, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW);
        if ($rate_error instanceof WP_Error) {
            return self::format_rate_limit_error($rate_error);
        }

        $type = self::normalise_request_type((string) $request->get_param('type'));
        if (null === $type) {
            return new WP_Error(
                'artpulse_upgrade_review_invalid_type',
                __('Invalid upgrade review type provided.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $existing_id = UpgradeReviewRepository::find_pending($user_id, $type);
        if (null !== $existing_id) {
            $existing_post = get_post($existing_id);
            if ($existing_post instanceof WP_Post) {
                $data = self::prepare_review_summary($existing_post);

                return new WP_REST_Response($data, 200);
            }
        }

        $note = $request->get_param('note');
        $args = [];
        if (is_string($note) && '' !== trim($note)) {
            $args['post_content'] = sanitize_textarea_field($note);
        }

        $result = UpgradeReviewRepository::create($user_id, $type, $args);
        if ($result instanceof WP_Error) {
            $status = (int) ($result->get_error_data()['status'] ?? 500);

            return new WP_Error($result->get_error_code(), $result->get_error_message(), ['status' => $status > 0 ? $status : 500]);
        }

        $created_post = get_post((int) $result);
        if (!$created_post instanceof WP_Post) {
            return new WP_Error(
                'artpulse_upgrade_review_missing',
                __('Unable to locate the created upgrade review request.', 'artpulse-management'),
                ['status' => 500]
            );
        }

        $data = self::prepare_review_summary($created_post);

        return new WP_REST_Response($data, 201);
    }

    public static function list_upgrade_reviews(WP_REST_Request $request)
    {
        $mine = (string) $request->get_param('mine');
        if ('1' !== $mine && 'true' !== strtolower($mine)) {
            return new WP_Error(
                'artpulse_upgrade_review_invalid_scope',
                __('Only personal upgrade review listings are supported.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $user_id = get_current_user_id();
        $reviews = UpgradeReviewRepository::get_all_for_user($user_id);

        $data = array_map([self::class, 'prepare_review_details'], $reviews);

        return rest_ensure_response($data);
    }

    private static function prepare_review_summary(WP_Post $post): array
    {
        $data = self::prepare_review_details($post);

        return [
            'id'         => $data['id'],
            'type'       => $data['type'],
            'status'     => $data['status'],
            'created_at' => $data['created_at'],
        ];
    }

    private static function prepare_review_details(WP_Post $post): array
    {
        $status      = UpgradeReviewRepository::get_status($post);
        $repository_type = UpgradeReviewRepository::get_type($post);
        $type        = self::map_repository_type_to_response($repository_type);
        $reason      = UpgradeReviewRepository::get_reason($post);

        return [
            'id'         => (int) $post->ID,
            'type'       => $type,
            'status'     => $status,
            'reason'     => $reason !== '' ? $reason : null,
            'created_at' => self::format_datetime($post->post_date_gmt, $post->post_date),
            'updated_at' => self::format_datetime($post->post_modified_gmt, $post->post_modified),
        ];
    }

    private static function format_datetime(string $gmt, string $local): ?string
    {
        $source = $gmt;
        if ('' === $source) {
            $source = get_gmt_from_date($local);
        }

        if (!$source) {
            return null;
        }

        return mysql_to_rfc3339($source);
    }

    private static function normalise_request_type(string $type): ?string
    {
        return match (sanitize_key($type)) {
            'artist' => UpgradeReviewRepository::TYPE_ARTIST_UPGRADE,
            'org', 'organisation', 'organization' => UpgradeReviewRepository::TYPE_ORG_UPGRADE,
            default => null,
        };
    }

    private static function map_repository_type_to_response(string $type): string
    {
        return match ($type) {
            UpgradeReviewRepository::TYPE_ARTIST_UPGRADE => 'artist',
            default => 'org',
        };
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
                'enum'        => ['artist', 'org'],
                'description' => __('Type of upgrade review request.', 'artpulse-management'),
            ],
            'note' => [
                'type'        => 'string',
                'required'    => false,
                'description' => __('Optional note to accompany the upgrade request.', 'artpulse-management'),
            ],
        ] + self::get_common_args_schema();
    }

    private static function get_list_args_schema(): array
    {
        return [
            'mine' => [
                'type'        => 'string',
                'required'    => false,
                'description' => __('Set to 1 to list your upgrade requests.', 'artpulse-management'),
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
}
