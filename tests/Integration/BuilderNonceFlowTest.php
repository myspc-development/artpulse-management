<?php

namespace Tests\Integration;

use ArtPulse\Core\Capabilities;
use ArtPulse\Frontend\OrgBuilderShortcode;
use WP_REST_Request;
use WP_UnitTestCase;

class BuilderNonceFlowTest extends WP_UnitTestCase
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
    }

    public function test_nonce_refresh_allows_retry(): void
    {
        $_POST = [
            'org_id'       => $this->org_id,
            'builder_step' => 'profile',
        ];

        try {
            OrgBuilderShortcode::handle_save();
            $this->fail('Expected nonce failure.');
        } catch (\WPDieException $exception) {
            $payload = json_decode($exception->getMessage(), true);
            $this->assertIsArray($payload);
            $this->assertSame('invalid_nonce', $payload['code']);
            $this->assertSame('refresh_nonce_and_retry', $payload['details']['hint']);
            $this->assertArrayHasKey('nonce', $payload['details']);
        }

        $nonce_request  = new WP_REST_Request('GET', '/artpulse/v1/nonce');
        $nonce_response = rest_do_request($nonce_request);
        $this->assertSame(200, $nonce_response->get_status());
        $nonce = $nonce_response->get_data()['nonce'];
        $this->assertNotEmpty($nonce);

        $_POST = [
            'org_id'       => $this->org_id,
            'builder_step' => 'profile',
            '_ap_nonce'    => $nonce,
            'ap_tagline'   => 'Updated',
        ];

        add_filter('wp_redirect', static function ($location) {
            throw new \Exception('redirect:' . $location);
        }, 10, 1);

        try {
            OrgBuilderShortcode::handle_save();
            $this->fail('Expected redirect.');
        } catch (\Exception $exception) {
            $this->assertStringStartsWith('redirect:', $exception->getMessage());
        }
    }
}
