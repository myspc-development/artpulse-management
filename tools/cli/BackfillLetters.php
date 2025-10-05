<?php

namespace ArtPulse\Tools\CLI;

use ArtPulse\Core\TitleTools;

/**
 * WP-CLI command for backfilling cached directory letters.
 */
class BackfillLetters
{
    /**
     * Handle the registered WP-CLI command.
     *
     * @param array $args       Positional arguments (unused).
     * @param array $assoc_args Associative arguments (post_type, batch).
     */
    public static function handle(array $args, array $assoc_args): void
    {
        $assoc_args = wp_parse_args($assoc_args, [
            'post_type' => 'artpulse_artist',
            'batch'     => 100,
        ]);

        try {
            $processed = self::run((string) $assoc_args['post_type'], (int) $assoc_args['batch']);
        } catch (\InvalidArgumentException $exception) {
            if (class_exists('\\WP_CLI')) {
                \WP_CLI::error($exception->getMessage());
            }

            return;
        }

        if (!class_exists('\\WP_CLI')) {
            return;
        }

        if (0 === $processed) {
            \WP_CLI::success(sprintf('No posts required updates for "%s".', $assoc_args['post_type']));
            return;
        }

        \WP_CLI::success(sprintf('Updated %d posts for "%s".', $processed, $assoc_args['post_type']));
    }

    /**
     * Execute the backfill routine and return the number of processed posts.
     */
    public static function run(string $post_type, int $batch = 100): int
    {
        $post_type = sanitize_key($post_type);
        if ('' === $post_type) {
            throw new \InvalidArgumentException('A valid post type is required.');
        }

        $batch = max(1, $batch);
        $processed = 0;

        do {
            $ids = TitleTools::get_posts_missing_letter_ids($post_type, $batch);
            if (empty($ids)) {
                break;
            }

            foreach ($ids as $post_id) {
                $post = get_post((int) $post_id);
                if ($post && $post->post_type === $post_type) {
                    TitleTools::update_post_letter((int) $post->ID, $post->post_title);
                    $processed++;
                }
            }
        } while (count($ids) === $batch);

        return $processed;
    }
}

