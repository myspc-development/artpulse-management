<?php

namespace Tests\Rest\Mobile;

use ArtPulse\Mobile\RefreshTokens;
use ArtPulse\Mobile\RequestMetrics;
use ArtPulse\Tools\CLI\Purge;
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

        /**
         * @param array<string, mixed> $assoc_args
         */
        public static function runcommand(string $command, array $assoc_args = []): void
        {
            if (!isset(self::$commands[$command])) {
                throw new \RuntimeException(sprintf('Command "%s" not registered.', $command));
            }

            call_user_func(self::$commands[$command], [], $assoc_args);
        }

        public static function error(string $message): void
        {
            throw new \RuntimeException($message);
        }

        public static function success(string $message): void
        {
            self::$last_message = $message;
        }
    }
}

require_once dirname(__DIR__, 3) . '/tools/cli/Purge.php';

class MobileDataPurgeTest extends WP_UnitTestCase
{
    private int $user_id;

    protected function set_up(): void
    {
        parent::set_up();

        $this->user_id = self::factory()->user->create();

        delete_user_meta($this->user_id, 'ap_mobile_refresh_tokens');
        delete_option('ap_mobile_metrics_log');
        delete_option('ap_mobile_metrics_summary');

        \WP_CLI::$commands     = [];
        \WP_CLI::$last_message = '';
        \WP_CLI::add_command('artpulse mobile purge', [Purge::class, 'handle']);
    }

    public function test_purge_inactive_sessions_removes_only_stale_records(): void
    {
        $now = time();

        update_user_meta($this->user_id, 'ap_mobile_refresh_tokens', [
            [
                'device_id'    => 'stale-device',
                'created_at'   => $now - (95 * DAY_IN_SECONDS),
                'last_seen_at' => $now - (95 * DAY_IN_SECONDS),
                'last_used_at' => $now - (95 * DAY_IN_SECONDS),
            ],
            [
                'device_id'    => 'active-device',
                'created_at'   => $now - (10 * DAY_IN_SECONDS),
                'last_seen_at' => $now - (5 * DAY_IN_SECONDS),
                'last_used_at' => $now - (5 * DAY_IN_SECONDS),
            ],
            [
                'device_id'  => 'missing-timestamps',
                'created_at' => 0,
            ],
        ]);

        $removed = RefreshTokens::purge_inactive_sessions();

        $this->assertSame(2, $removed);

        $records = get_user_meta($this->user_id, 'ap_mobile_refresh_tokens', true);
        $this->assertIsArray($records);
        $this->assertCount(1, $records);
        $this->assertSame('active-device', $records[0]['device_id']);
    }

    public function test_purge_metrics_removes_outdated_entries(): void
    {
        $now = time();

        update_option('ap_mobile_metrics_log', [
            [
                'timestamp'   => $now - (20 * DAY_IN_SECONDS),
                'route'       => '/artpulse/v1/mobile/old',
                'method'      => 'GET',
                'status'      => 200,
                'duration_ms' => 10.0,
            ],
            [
                'timestamp'   => $now - (2 * DAY_IN_SECONDS),
                'route'       => '/artpulse/v1/mobile/new',
                'method'      => 'POST',
                'status'      => 200,
                'duration_ms' => 15.5,
            ],
        ]);

        update_option('ap_mobile_metrics_summary', [
            '/artpulse/v1/mobile/old' => [
                'latencies'  => [10.0],
                'statuses'   => ['2xx' => 1],
                'updated_at' => $now - (20 * DAY_IN_SECONDS),
            ],
            '/artpulse/v1/mobile/new' => [
                'latencies'  => [12.0],
                'statuses'   => ['2xx' => 1],
                'updated_at' => $now - (2 * DAY_IN_SECONDS),
            ],
        ]);

        $result = RequestMetrics::purge_stale_entries();

        $this->assertSame(1, $result['log_removed']);
        $this->assertSame(1, $result['summary_removed']);

        $log = get_option('ap_mobile_metrics_log', []);
        $this->assertIsArray($log);
        $this->assertCount(1, $log);
        $this->assertSame('/artpulse/v1/mobile/new', $log[0]['route']);

        $summary = get_option('ap_mobile_metrics_summary', []);
        $this->assertIsArray($summary);
        $this->assertCount(1, $summary);
        $this->assertArrayHasKey('/artpulse/v1/mobile/new', $summary);
    }

    public function test_cli_command_runs_selected_purges(): void
    {
        $now = time();

        update_user_meta($this->user_id, 'ap_mobile_refresh_tokens', [
            [
                'device_id'    => 'stale',
                'created_at'   => $now - (100 * DAY_IN_SECONDS),
                'last_seen_at' => $now - (100 * DAY_IN_SECONDS),
                'last_used_at' => $now - (100 * DAY_IN_SECONDS),
            ],
            [
                'device_id'    => 'fresh',
                'created_at'   => $now - (5 * DAY_IN_SECONDS),
                'last_seen_at' => $now - (2 * DAY_IN_SECONDS),
                'last_used_at' => $now - (2 * DAY_IN_SECONDS),
            ],
        ]);

        update_option('ap_mobile_metrics_log', [
            [
                'timestamp'   => $now - (16 * DAY_IN_SECONDS),
                'route'       => '/artpulse/v1/mobile/legacy',
                'method'      => 'GET',
                'status'      => 200,
                'duration_ms' => 8.2,
            ],
            [
                'timestamp'   => $now - DAY_IN_SECONDS,
                'route'       => '/artpulse/v1/mobile/current',
                'method'      => 'GET',
                'status'      => 200,
                'duration_ms' => 6.1,
            ],
        ]);

        update_option('ap_mobile_metrics_summary', [
            '/artpulse/v1/mobile/legacy' => [
                'latencies'  => [8.2],
                'statuses'   => ['2xx' => 1],
                'updated_at' => $now - (16 * DAY_IN_SECONDS),
            ],
            '/artpulse/v1/mobile/current' => [
                'latencies'  => [6.1],
                'statuses'   => ['2xx' => 1],
                'updated_at' => $now - DAY_IN_SECONDS,
            ],
        ]);

        \WP_CLI::runcommand('artpulse mobile purge', [
            'sessions' => true,
            'metrics'  => true,
        ]);

        $records = get_user_meta($this->user_id, 'ap_mobile_refresh_tokens', true);
        $this->assertIsArray($records);
        $this->assertCount(1, $records);
        $this->assertSame('fresh', $records[0]['device_id']);

        $log = get_option('ap_mobile_metrics_log', []);
        $this->assertIsArray($log);
        $this->assertCount(1, $log);
        $this->assertSame('/artpulse/v1/mobile/current', $log[0]['route']);

        $summary = get_option('ap_mobile_metrics_summary', []);
        $this->assertIsArray($summary);
        $this->assertCount(1, $summary);
        $this->assertArrayHasKey('/artpulse/v1/mobile/current', $summary);

        $this->assertStringContainsString('Inactive sessions removed: 1', \WP_CLI::$last_message);
        $this->assertStringContainsString('Metrics log entries removed: 1', \WP_CLI::$last_message);
        $this->assertStringContainsString('Routes trimmed: 1', \WP_CLI::$last_message);
    }

    public function test_cli_requires_flag(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Specify at least one of --sessions or --metrics.');

        \WP_CLI::runcommand('artpulse mobile purge');
    }
}
