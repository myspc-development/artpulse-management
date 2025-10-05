<?php

use ArtPulse\Core\TitleTools;
use ArtPulse\Tools\CLI\BackfillLetters;
use WP_UnitTestCase;

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}

if (!class_exists('\\WP_CLI')) {
    class WP_CLI
    {
        /** @var array<string, callable> */
        public static array $commands = [];

        public static string $last_message = '';

        public static function add_command(string $name, callable $callable): void
        {
            self::$commands[$name] = $callable;
        }

        public static function runcommand(string $command, array $assoc_args = []): void
        {
            if (!isset(self::$commands[$command])) {
                throw new RuntimeException(sprintf('Command "%s" not registered.', $command));
            }

            call_user_func(self::$commands[$command], [], $assoc_args);
        }

        public static function error(string $message): void
        {
            throw new RuntimeException($message);
        }

        public static function success(string $message): void
        {
            self::$last_message = $message;
        }
    }
}

require_once dirname(__DIR__, 2) . '/tools/cli/BackfillLetters.php';

class BackfillLettersCommandTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \WP_CLI::$commands = [];
        \WP_CLI::$last_message = '';
        \WP_CLI::add_command('artpulse backfill-letters', [BackfillLetters::class, 'handle']);
    }

    public function test_command_backfills_missing_letters(): void
    {
        $factory = self::factory();

        $needs_letter = $factory->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Beta',
            'post_status' => 'publish',
        ]);
        delete_post_meta($needs_letter, TitleTools::META_KEY);

        $already_set = $factory->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha',
            'post_status' => 'publish',
        ]);
        update_post_meta($already_set, TitleTools::META_KEY, 'A');

        \WP_CLI::runcommand('artpulse backfill-letters', [
            'post_type' => 'artpulse_artist',
            'batch'     => 1,
        ]);

        $this->assertSame('B', get_post_meta($needs_letter, TitleTools::META_KEY, true));
        $this->assertSame('A', get_post_meta($already_set, TitleTools::META_KEY, true));
        $this->assertStringContainsString('Updated 1 posts', \WP_CLI::$last_message);
    }
}

