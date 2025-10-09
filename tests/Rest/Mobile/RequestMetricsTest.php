<?php

namespace WP_CLI\Utils {
    if (!function_exists(__NAMESPACE__ . '\\format_items')) {
        /**
         * @param array<int, array<string, mixed>> $items
         * @param array<int, string>               $fields
         */
        function format_items(string $format, array $items, array $fields): void
        {
            \Tests\Rest\Mobile\RequestMetricsTest::$formatted_output = [
                'format' => $format,
                'items'  => $items,
                'fields' => $fields,
            ];
        }
    }
}

namespace Tests\Rest\Mobile {

    use ArtPulse\Mobile\RequestMetrics;
    use ArtPulse\Tools\CLI\MetricsDump;
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
            public static string $last_line    = '';

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

            public static function line(string $message): void
            {
                self::$last_line    = $message;
                self::$last_message = $message;
            }
        }
    }

    class RequestMetricsTest extends WP_UnitTestCase
    {
        /** @var array<string, mixed> */
        public static array $formatted_output = [];

        protected function set_up(): void
        {
            parent::set_up();

            delete_option('ap_mobile_metrics_log');
            delete_option('ap_mobile_metrics_summary');

            self::$formatted_output = [];
            \WP_CLI::$last_line     = '';
            \WP_CLI::$last_message  = '';
        }

        public function test_get_summary_groups_by_route_and_method(): void
        {
            $now = time();

            update_option('ap_mobile_metrics_summary', [
                '/artpulse/v1/mobile/items' => [
                    'GET' => [
                        'latencies'  => [100.0, 120.0, 140.0],
                        'statuses'   => ['2xx' => 3],
                        'updated_at' => $now,
                    ],
                    'post' => [
                        'latencies'  => [200.0, 220.0],
                        'statuses'   => ['2xx' => 1, '5xx' => 1],
                        'updated_at' => $now - 10,
                    ],
                ],
            ]);

            $summary = RequestMetrics::get_summary();

            $this->assertArrayHasKey('/artpulse/v1/mobile/items', $summary);
            $route_summary = $summary['/artpulse/v1/mobile/items'];

            $this->assertArrayHasKey('GET', $route_summary);
            $get_summary = $route_summary['GET'];
            $this->assertSame('GET', $get_summary['method']);
            $this->assertSame(3, $get_summary['count']);
            $this->assertSame(120.0, $get_summary['p50']);
            $this->assertSame(138.0, $get_summary['p95']);
            $this->assertSame(['2xx' => 3], $get_summary['statuses']);

            $this->assertArrayHasKey('POST', $route_summary);
            $post_summary = $route_summary['POST'];
            $this->assertSame('POST', $post_summary['method']);
            $this->assertSame(2, $post_summary['count']);
            $this->assertSame(210.0, $post_summary['p50']);
            $this->assertSame(219.0, $post_summary['p95']);
            $this->assertSame(['2xx' => 1, '5xx' => 1], $post_summary['statuses']);
        }

        public function test_get_summary_converts_legacy_entries(): void
        {
            $now = time();

            update_option('ap_mobile_metrics_summary', [
                '/artpulse/v1/mobile/legacy' => [
                    'latencies'  => [50.0, 75.0],
                    'statuses'   => ['2xx' => 2],
                    'updated_at' => $now,
                ],
            ]);

            $summary = RequestMetrics::get_summary();

            $this->assertArrayHasKey('/artpulse/v1/mobile/legacy', $summary);
            $route_summary = $summary['/artpulse/v1/mobile/legacy'];
            $this->assertArrayHasKey('UNKNOWN', $route_summary);
            $legacy_summary = $route_summary['UNKNOWN'];

            $this->assertSame(2, $legacy_summary['count']);
            $this->assertSame(62.5, $legacy_summary['p50']);
            $this->assertSame(73.75, $legacy_summary['p95']);
        }

        public function test_metrics_dump_groups_by_method_and_route(): void
        {
            $now = time();

            update_option('ap_mobile_metrics_log', [
                [
                    'timestamp'   => $now - 30,
                    'route'       => '/artpulse/v1/mobile/items',
                    'method'      => 'GET',
                    'status'      => 200,
                    'duration_ms' => 100.0,
                ],
                [
                    'timestamp'   => $now - 10,
                    'route'       => '/artpulse/v1/mobile/items',
                    'method'      => 'GET',
                    'status'      => 500,
                    'duration_ms' => 200.0,
                ],
                [
                    'timestamp'   => $now - 20,
                    'route'       => '/artpulse/v1/mobile/items',
                    'method'      => 'POST',
                    'status'      => 200,
                    'duration_ms' => 150.0,
                ],
                [
                    'timestamp'   => $now - 5,
                    'route'       => '/artpulse/v1/mobile/other',
                    'method'      => 'GET',
                    'status'      => 200,
                    'duration_ms' => 80.0,
                ],
            ]);

            self::$formatted_output = [];
            MetricsDump::handle([], ['last' => '1h']);

            $this->assertSame('table', self::$formatted_output['format']);
            $this->assertSame(['method', 'route', 'count', 'p50', 'p95', 'statuses'], self::$formatted_output['fields']);

            $items = self::$formatted_output['items'];
            $this->assertCount(3, $items);

            $first = $items[0];
            $this->assertSame('GET', $first['method']);
            $this->assertSame('/artpulse/v1/mobile/items', $first['route']);
            $this->assertSame(2, $first['count']);
            $this->assertSame(150.0, $first['p50']);
            $this->assertSame(195.0, $first['p95']);
            $this->assertSame('2xx:1, 5xx:1', $first['statuses']);

            $second = $items[1];
            $this->assertSame('POST', $second['method']);
            $this->assertSame('/artpulse/v1/mobile/items', $second['route']);
            $this->assertSame(1, $second['count']);
            $this->assertSame(150.0, $second['p50']);
            $this->assertSame(150.0, $second['p95']);

            $third = $items[2];
            $this->assertSame('GET', $third['method']);
            $this->assertSame('/artpulse/v1/mobile/other', $third['route']);
            $this->assertSame(1, $third['count']);
        }

        public function test_metrics_dump_filters_output(): void
        {
            $now = time();

            update_option('ap_mobile_metrics_log', [
                [
                    'timestamp'   => $now - 10,
                    'route'       => '/artpulse/v1/mobile/items',
                    'method'      => 'GET',
                    'status'      => 200,
                    'duration_ms' => 110.0,
                ],
                [
                    'timestamp'   => $now - 8,
                    'route'       => '/artpulse/v1/mobile/items',
                    'method'      => 'POST',
                    'status'      => 200,
                    'duration_ms' => 160.0,
                ],
            ]);

            self::$formatted_output = [];
            MetricsDump::handle([], ['last' => '1h', 'method' => 'post']);

            $items = self::$formatted_output['items'];
            $this->assertCount(1, $items);
            $this->assertSame('POST', $items[0]['method']);

            self::$formatted_output = [];
            MetricsDump::handle([], ['last' => '1h', 'route' => '/artpulse/v1/mobile/items']);
            $this->assertCount(2, self::$formatted_output['items']);

            self::$formatted_output = [];
            MetricsDump::handle([], ['last' => '1h', 'route' => '/missing']);
            $this->assertSame('No metrics recorded in the requested window.', \WP_CLI::$last_line);
        }
    }
}
