<?php

namespace Tests\Integration;

use ArtPulse\Core\Capabilities;
use ArtPulse\Frontend\OrgBuilderShortcode;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use WP_UnitTestCase;

class BuilderNonceAndRateLimitTest extends WP_UnitTestCase
{
    private int $user_id;
    private int $org_id;

    protected function set_up(): void
    {
        parent::set_up();

        Capabilities::add_roles_and_capabilities();

        $this->user_id = self::factory()->user->create([
            'role' => 'organization',
        ]);

        $this->org_id = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_author' => $this->user_id,
        ]);

        add_post_meta($this->org_id, '_ap_owner_user', $this->user_id);
        wp_set_current_user($this->user_id);

        do_action('rest_api_init');
    }

    protected function tear_down(): void
    {
        parent::tear_down();

        unset($_POST, $_FILES, $_REQUEST);
        remove_all_filters('wp_redirect');

        if (function_exists('header_remove')) {
            header_remove();
        }
    }

    public function test_missing_nonce_returns_json_hint(): void
    {
        $_POST = [
            'org_id'       => $this->org_id,
            'builder_step' => 'profile',
        ];

        try {
            OrgBuilderShortcode::handle_save();
            $this->fail('Expected nonce validation failure.');
        } catch (\WPDieException $exception) {
            $payload = json_decode($exception->getMessage(), true);

            $this->assertIsArray($payload);
            $this->assertSame('invalid_nonce', $payload['code'] ?? null);
            $this->assertSame('refresh_nonce_and_retry', $payload['details']['hint'] ?? null);

            global $wp_http_response_code;
            $this->assertSame(403, (int) $wp_http_response_code);
        }

        http_response_code(200);
    }

    public function test_rate_limit_emits_headers_and_returns_429(): void
    {
        $nonce = wp_create_nonce('ap_portfolio_update');

        for ($i = 0; $i < 30; $i++) {
            $this->assertNull(FormRateLimiter::enforce($this->user_id, 'builder_write', 30, 60));
        }

        if (function_exists('header_remove')) {
            header_remove();
        }

        $_POST = [
            'org_id'       => $this->org_id,
            'builder_step' => 'profile',
            '_ap_nonce'    => $nonce,
        ];

        try {
            OrgBuilderShortcode::handle_save();
            $this->fail('Expected rate limit response.');
        } catch (\WPDieException $exception) {
            $payload = json_decode($exception->getMessage(), true);

            $this->assertIsArray($payload);
            $this->assertSame('rate_limited', $payload['code'] ?? null);
            $this->assertSame(30, $payload['details']['limit'] ?? null);
            $this->assertGreaterThanOrEqual(1, $payload['details']['retry_after'] ?? 0);
        }

        $headers = [];
        foreach (headers_list() as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
        }

        $this->assertSame(429, (int) http_response_code());
        $this->assertSame('30', $headers['x-ratelimit-limit'] ?? null);
        $this->assertSame('0', $headers['x-ratelimit-remaining'] ?? null);
        $this->assertArrayHasKey('x-ratelimit-reset', $headers);
        $this->assertArrayHasKey('retry-after', $headers);

        http_response_code(200);
    }
}
