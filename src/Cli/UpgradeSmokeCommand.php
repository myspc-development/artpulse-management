<?php

namespace ArtPulse\Cli;

use ArtPulse\Core\UpgradeReviewRepository;
use WP_CLI; // @phpstan-ignore-line -- WP-CLI only available in CLI context.
use WP_CLI_Command;
use WP_Post;
use WP_User;
use function get_post;
use function get_userdata;
use function get_user_by;
use function is_wp_error;
use function sprintf;
use function wp_json_encode;

/**
 * Simple smoke test for the upgrade review flow via WP-CLI.
 */
class UpgradeSmokeCommand extends WP_CLI_Command
{
    /**
     * Run a smoke check for the upgrade request flow.
     *
     * ## OPTIONS
     *
     * --user=<id>
     * : User identifier to operate on.
     *
     * --target=<artist|org>
     * : Target upgrade type.
     *
     * [--approve]
     * : When set, approve the latest pending request for the user.
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $user_id = isset($assoc_args['user']) ? (int) $assoc_args['user'] : 0;
        $target  = isset($assoc_args['target']) ? (string) $assoc_args['target'] : '';
        $approve = isset($assoc_args['approve']);

        if ($user_id <= 0) {
            WP_CLI::error('A valid --user=<id> value is required.');
        }

        if ('' === $target) {
            WP_CLI::error('A --target value of artist or org is required.');
        }

        $type = $this->normaliseType($target);
        if (null === $type) {
            WP_CLI::error('Unsupported target value. Use artist or org.');
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            WP_CLI::error('The specified user could not be found.');
        }

        if (!$approve) {
            $this->handleSubmission($user_id, $type);

            return;
        }

        $this->handleApproval($user_id, $type);
    }

    private function handleSubmission(int $user_id, string $type): void
    {
        $result = UpgradeReviewRepository::create($user_id, $type);
        if (is_wp_error($result)) {
            $status = (int) (($result->get_error_data()['status'] ?? 0) ?: 1);
            WP_CLI::error($result->get_error_message(), $status);
        }

        $post = get_post((int) $result);
        if (!$post instanceof WP_Post) {
            WP_CLI::error('Upgrade request was created but could not be loaded.');
        }

        $payload = [
            'id'      => $post->ID,
            'user'    => $user_id,
            'type'    => UpgradeReviewRepository::get_type($post),
            'status'  => UpgradeReviewRepository::get_status($post),
            'created' => $post->post_date_gmt,
        ];

        WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT));
        WP_CLI::success('Upgrade request submitted.');
    }

    private function handleApproval(int $user_id, string $type): void
    {
        $request = UpgradeReviewRepository::get_latest_for_user($user_id, $type);
        if (!$request instanceof WP_Post) {
            WP_CLI::error('No upgrade request found for the specified user and target.');
        }

        $status = UpgradeReviewRepository::get_status($request);
        if (UpgradeReviewRepository::STATUS_PENDING !== $status) {
            WP_CLI::warning(sprintf('Latest request is %s; continuing with approval.', $status));
        }

        if (!UpgradeReviewRepository::approve($request->ID)) {
            WP_CLI::error('Failed to approve the upgrade request.');
        }

        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            WP_CLI::error('Upgrade approved but user data could not be loaded.');
        }

        $payload = [
            'request_id' => $request->ID,
            'status'     => UpgradeReviewRepository::get_status($request),
            'roles'      => array_values((array) $user->roles),
            'caps'       => array_keys(array_filter((array) $user->caps)),
        ];

        WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT));
        WP_CLI::success('Upgrade request approved.');
    }

    private function normaliseType(string $target): ?string
    {
        $key = strtolower($target);

        return match ($key) {
            'artist', 'artists' => UpgradeReviewRepository::TYPE_ARTIST,
            'org', 'organisation', 'organization', 'organizations' => UpgradeReviewRepository::TYPE_ORG,
            default => null,
        };
    }
}
