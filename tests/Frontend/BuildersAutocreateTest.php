<?php

namespace Tests\Frontend;

use ArtPulse\Core\Capabilities;
use ArtPulse\Frontend\ArtistBuilderShortcode;
use ArtPulse\Frontend\OrgBuilderShortcode;
use WP_Post;
use WP_UnitTestCase;
use function add_role;
use function delete_user_meta;
use function get_post;
use function get_post_meta;
use function get_posts;
use function get_user_by;
use function get_user_meta;
use function post_type_exists;
use function register_post_type;
use function remove_role;
use function unregister_post_type;
use function wp_set_current_user;

class BuildersAutocreateTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!post_type_exists('artpulse_artist')) {
            register_post_type('artpulse_artist', [
                'label'  => 'Artists',
                'public' => false,
                'supports' => ['title', 'editor'],
            ]);
        }

        if (!post_type_exists('artpulse_org')) {
            register_post_type('artpulse_org', [
                'label'  => 'Organizations',
                'public' => false,
                'supports' => ['title', 'editor'],
            ]);
        }

        add_role('ap_artist', 'Artist', []);
        add_role('ap_org_manager', 'Org Manager', []);
    }

    protected function tearDown(): void
    {
        unset($_GET['autocreate'], $_GET['redirect']);

        if (post_type_exists('artpulse_artist')) {
            unregister_post_type('artpulse_artist');
        }

        if (post_type_exists('artpulse_org')) {
            unregister_post_type('artpulse_org');
        }

        remove_role('ap_artist');
        remove_role('ap_org_manager');

        parent::tearDown();
    }

    public function test_artist_builder_autocreates_when_missing(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        $user    = get_user_by('id', $user_id);
        $this->assertNotFalse($user);
        $user->add_cap('read');
        $user->add_cap('edit_artpulse_artist');
        $user->add_cap(Capabilities::CAP_MANAGE_PORTFOLIO);

        wp_set_current_user($user_id);
        $_GET['autocreate'] = '1';

        delete_user_meta($user_id, '_ap_artist_post_id');

        $reflector = new \ReflectionClass(ArtistBuilderShortcode::class);
        $method    = $reflector->getMethod('maybe_autocreate_profile');
        $method->setAccessible(true);
        $method->invoke(null);

        $profile_id = (int) get_user_meta($user_id, '_ap_artist_post_id', true);
        $this->assertGreaterThan(0, $profile_id);

        $profile = get_post($profile_id);
        $this->assertInstanceOf(WP_Post::class, $profile);
        $this->assertSame('artpulse_artist', $profile->post_type);
        $this->assertSame($user_id, (int) $profile->post_author);
        $this->assertSame($user_id, (int) get_post_meta($profile_id, '_ap_owner_user', true));

        $method->invoke(null);
        $this->assertSame($profile_id, (int) get_user_meta($user_id, '_ap_artist_post_id', true));

        $posts = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'author'         => $user_id,
        ]);
        $this->assertCount(1, $posts);
    }

    public function test_org_builder_respects_redirect_param(): void
    {
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        $user    = get_user_by('id', $user_id);
        $this->assertNotFalse($user);
        $user->add_cap('read');
        $user->add_cap('edit_artpulse_org');
        $user->add_cap(Capabilities::CAP_MANAGE_PORTFOLIO);

        wp_set_current_user($user_id);

        $reflector = new \ReflectionClass(OrgBuilderShortcode::class);
        $method    = $reflector->getMethod('maybe_append_redirect_cta');
        $method->setAccessible(true);

        $_GET['redirect'] = 'https://example.org/dashboard/';
        $markup           = '<div class="ap-profile-builder__actions"><span>Actions</span></div>';
        $with_redirect    = $method->invoke(null, $markup);

        $this->assertStringContainsString('ap-profile-builder__back', $with_redirect);
        $this->assertStringContainsString('https://example.org/dashboard/', $with_redirect);

        $_GET['redirect'] = 'javascript:alert(1)';
        $sanitised = $method->invoke(null, $markup);
        $this->assertSame($markup, $sanitised);
    }
}
