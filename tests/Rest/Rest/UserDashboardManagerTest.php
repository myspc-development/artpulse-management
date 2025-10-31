<?php

namespace Tests\Rest;

use ArtPulse\Core\RoleDashboards;
use ArtPulse\Core\UserDashboardManager;
use WP_REST_Request;
use function add_query_arg;
use function get_permalink;
use function update_option;
use function wp_create_nonce;
class UserDashboardManagerTest extends \WP_UnitTestCase
{
    protected $user_id;

    public function set_up(): void
    {
        parent::set_up();
        if (!get_role('member')) {
            add_role('member', 'Member', ['read' => true]);
        }

        $this->user_id = $this->factory->user->create(['role' => 'member']);
        wp_set_current_user($this->user_id);

        RoleDashboards::register();
        UserDashboardManager::register();
        do_action('rest_api_init');
    }

    public function test_get_dashboard_data()
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/dashboard');
        $request->set_param('role', 'member');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('favorites', $data);
        $this->assertArrayHasKey('follows', $data);
        $this->assertArrayHasKey('submissions', $data);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('profile', $data);
        $this->assertArrayHasKey('available_roles', $data);
        $this->assertIsArray($data['available_roles']);
    }

    public function test_get_dashboard_requires_nonce(): void
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/dashboard');
        $request->set_param('role', 'member');
        $response = rest_do_request($request);

        $this->assertSame(403, $response->get_status());
    }

    public function test_user_dashboard_payload_contains_builder_url_with_autocreate(): void
    {
        $dashboard_page = $this->factory->post->create(['post_type' => 'page', 'post_title' => 'Dashboard']);
        $artist_page    = $this->factory->post->create(['post_type' => 'page', 'post_title' => 'Artist Builder']);
        $org_page       = $this->factory->post->create(['post_type' => 'page', 'post_title' => 'Org Builder']);

        update_option('artpulse_pages', [
            'dashboard_page_id'      => $dashboard_page,
            'artist_builder_page_id' => $artist_page,
            'org_builder_page_id'    => $org_page,
        ]);

        $request = new WP_REST_Request('GET', '/artpulse/v1/dashboard');
        $request->set_param('role', 'member');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data['profile']['artist']);
        $builder_url = $data['profile']['artist']['builder_url'] ?? '';
        $this->assertNotEmpty($builder_url);
        $this->assertStringContainsString('autocreate=1', $builder_url);

        $expected_redirect = add_query_arg('role', 'member', get_permalink($dashboard_page));
        $this->assertStringContainsString(rawurlencode($expected_redirect), $builder_url);
    }
}
